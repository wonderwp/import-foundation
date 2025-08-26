<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Syncers\Traits\IndexComparisonTrait;
use WonderWp\Component\ImportFoundation\Syncers\Traits\MetaComparisonTrait;
use WP_Post;

use function WP_CLI\Utils\is_json;

class PostsSyncer extends AbstractSyncer
{
    use IndexComparisonTrait, MetaComparisonTrait;

    protected array $itemTermsComparisonIndexes = [];

    public function __construct(PersisterInterface $persister)
    {
        parent::__construct($persister);
        
        // Set default comparison indexes for posts
        $this->setItemComparisonIndexes([
            'post_title',
            'post_excerpt'
        ]);
    }

    protected function idToLog($item): string
    {
        if(!$item instanceof WP_Post){
            throw new \InvalidArgumentException('Item must be an instance of WP_Post');
        }

        /** @var WP_Post $item */
        return $item->item_name . '#' . ($this->findItemId($item));
    }

    protected function findItemId(mixed $item): int|string
    {
        if(!$item instanceof WP_Post){
            throw new \InvalidArgumentException('Item must be an instance of WP_Post');
        }
        /** @var WP_Post $item */

        $metaInputAttribute = PersisterInterface::META_INPUT;
        //Test with item->meta_input['sync_id']
        if (isset($item->$metaInputAttribute[PersisterInterface::SYNC_ID])) {
            return $item->$metaInputAttribute[PersisterInterface::SYNC_ID];
        }

        //If empty, test with item acf meta sync_id
        if (function_exists('\get_field')) {
            $itemId = \get_field(PersisterInterface::SYNC_ID, $item->ID);
            if (!empty($itemId)) {
                return (int)$itemId;
            }
        }

        //If empty, test with item->item_name
        return $item->item_name;
    }

    /**
     * Get existing item meta value for posts
     */
    protected function getExistingItemMetaValue(mixed $destinationItem, string $metaKey): mixed
    {
        $meta = \get_post_meta($destinationItem->ID, $metaKey, true);

        if(is_json($meta)){
            $meta = json_decode($meta, true);
        }

        return $meta;
    }

    protected function checkIfItemNeedsUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];

        // Check if the item needs an update based on the indexes to check
        $updateReasons = array_merge($updateReasons, $this->checkItemIndexesForUpdate($sourceItem, $destinationItem));

        // Check if the item needs an update based on the metas to check
        $updateReasons = array_merge($updateReasons, $this->checkItemMetasForUpdate($sourceItem, $destinationItem));

        // Check if the item needs an update based on the acf fields to check
        $updateReasons = array_merge($updateReasons, $this->checkItemAcfForUpdate($sourceItem, $destinationItem));

        // Check if the item needs an update based on the terms to check
        $updateReasons = array_merge($updateReasons, $this->checkItemTermsForUpdate($sourceItem, $destinationItem));

        return $updateReasons;
    }

    /**
     * Check if item needs update based on ACF fields
     */
    protected function checkItemAcfForUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];
        $newItemData = $sourceItem->to_array();

        if (!function_exists('\get_field')) {
            return $updateReasons;
        }

        $acfToCheck = $this->getItemAcfIndexesToCheck($newItemData);
        foreach ($acfToCheck as $metaKey) {
            $existingItemMetaValue = \get_field($metaKey, $destinationItem->ID);
            // Keep the comparison operator loose here to avoid type comparison issues
            if ($this->itemValueChanged($metaKey, $newItemData[PersisterInterface::ACF_INPUT][$metaKey], $existingItemMetaValue)) {
                $updateReasons[$metaKey] = [
                    $newItemData[PersisterInterface::ACF_INPUT][$metaKey] ?? null,
                    $existingItemMetaValue
                ];
            }
        }

        return $updateReasons;
    }

    /**
     * Check if item needs update based on taxonomy terms
     */
    protected function checkItemTermsForUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];
        $newItemData = $sourceItem->to_array();

        $termsToCheck = $this->getItemTermsIndexesToCheck($newItemData);
        foreach ($termsToCheck as $termKey) {
            $existingItemTermsValue = $existingItemData[PersisterInterface::TAX_INPUT][$termKey] ?? null;
            if (empty($existingItemTermsValue)) {
                $existingItemTermsValue = \wp_get_post_terms($destinationItem->ID, $termKey);
                usort($existingItemTermsValue, fn($a, $b) => $a->term_id <=> $b->term_id);
            }

            if ($this->itemValueChanged($termKey, $newItemData[PersisterInterface::TAX_INPUT][$termKey], $existingItemTermsValue)) {
                $updateReasons[$termKey] = [
                    $newItemData[PersisterInterface::TAX_INPUT][$termKey] ?? null,
                    $existingItemTermsValue
                ];
            }
        }

        return $updateReasons;
    }

    protected function getItemAcfIndexesToCheck(array $newItemData): array
    {
        //Acf fields
        $existingItemDataAcfInputs = $newItemData[PersisterInterface::ACF_INPUT] ?? [];
        return array_keys($existingItemDataAcfInputs);
    }

    protected function getItemTermsIndexesToCheck(array $newItemData): array
    {
        //Terms
        $existingItemDataTerms = $newItemData[PersisterInterface::TAX_INPUT] ?? [];
        return array_keys($existingItemDataTerms);
    }

    public function getItemTermsComparisonIndexes(): array
    {
        return $this->itemTermsComparisonIndexes;
    }
}
