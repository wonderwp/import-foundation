<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Requests\SyncRequestInterface;
use WonderWp\Component\ImportFoundation\Responses\SyncResponse;
use WonderWp\Component\ImportFoundation\Responses\SyncResponseInterface;
use Throwable;
use WonderWp\Component\Logging\HasLoggerInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Task\Progress\ProgressInterface;
use WP_Error;
use WP_Item;

class PostsSyncer extends AbstractSyncer
{
    protected array $itemComparisonIndexes = [
        'item_title',
        'item_excerpt'
    ];
    protected array $itemMetaComparisonIndexes = [];
    protected array $itemTermsComparisonIndexes = [];

    protected function idToLog($item): string
    {
        if(!$item instanceof WP_Item){
            throw new \InvalidArgumentException('Item must be an instance of WP_Item');
        }

        /** @var WP_Item $item */
        return $item->item_name . '#' . ($this->findItemId($item));
    }

    protected function findItemId(mixed $item): int|string
    {
        if(!$item instanceof WP_Item){
            throw new \InvalidArgumentException('Item must be an instance of WP_Item');
        }
        /** @var WP_Item $item */

        $metaInputAttribute = PersisterInterface::META_INPUT;
        //Test with item->meta_input['item_id']
        if (isset($item->$metaInputAttribute[PersisterInterface::SYNC_ID])) {
            return $item->$metaInputAttribute[PersisterInterface::SYNC_ID];
        }

        //If empty, test with item acf meta item_id
        if (function_exists('get_field')) {
            $itemId = get_field(PersisterInterface::SYNC_ID, $item->ID);
            if (!empty($itemId)) {
                return (int)$itemId;
            }
        }

        //If empty, test with item->item_name
        return $item->item_name;
    }



    protected function checkIfItemNeedsUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];

        //First, check if an update is needed by comparing the new item with the existing one
        $newItemData = $sourceItem->to_array();
        $existingItemData = $existingItem->to_array();

        $indexesToCheck = $this->getItemIndexesToCheck();
        if (empty($indexesToCheck)) {
            return $updateReasons;
        }


        //Check if the item needs an update based on the indexes to check

        foreach ($indexesToCheck as $index) {
            //Keep the comparison operator loose here to avoid type comparison issues
            if ($this->itemValueChanged($index, $newItemData[$index], $existingItemData[$index])) {
                $updateReasons[$index] = [
                    $newItemData[$index] ?? null,
                    $existingItemData[$index] ?? null
                ];
            }
        }

        $metasToCheck = $this->getItemMetasIndexesToCheck($newItemData);
        //Check if the item needs an update based on the metas to check
        foreach ($metasToCheck as $metaKey) {
            $existingItemMetaValue = $existingItemData[PersisterInterface::META_INPUT][$metaKey] ?? null;
            if (empty($existingItemMetaValue)) {
                $existingItemMetaValue = get_item_meta($existingItem->ID, $metaKey, true);
            }
            //Keep the comparison operator loose here to avoid type comparison issues
            if ($this->itemValueChanged($metaKey, $newItemData[PersisterInterface::META_INPUT][$metaKey], $existingItemMetaValue)) {
                $updateReasons[$metaKey] = [
                    $newItemData[PersisterInterface::META_INPUT][$metaKey] ?? null,
                    $existingItemMetaValue
                ];
            }
        }

        if (function_exists('get_field')) {
            $acfToCheck = $this->getItemAcfIndexesToCheck($newItemData);
            //Check if the item needs an update based on the metas to check
            foreach ($acfToCheck as $metaKey) {
                $existingItemMetaValue = get_field($metaKey, $existingItem->ID);
                //Keep the comparison operator loose here to avoid type comparison issues
                if ($this->itemValueChanged($metaKey, $newItemData[PersisterInterface::ACF_INPUT][$metaKey], $existingItemMetaValue)) {
                    $updateReasons[$metaKey] = [
                        $newItemData[PersisterInterface::ACF_INPUT][$metaKey] ?? null,
                        $existingItemMetaValue
                    ];
                }
            }
        }

        $termsToCheck = $this->getItemTermsIndexesToCheck($newItemData);
        //Check if the item needs an update based on the metas to check
        foreach ($termsToCheck as $termKey) {
            $existingItemTermsValue = $existingItemData[PersisterInterface::TAX_INPUT][$termKey] ?? null;
            if (empty($existingItemMetaValue)) {
                $existingItemTermsValue = wp_get_item_terms($existingItem->ID, $termKey);
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

    protected function getItemIndexesToCheck(): array
    {
        return $this->getItemComparisonIndexes();
    }

    protected function getItemMetasIndexesToCheck(array $newItemData): array
    {
        //Metas
        $existingItemDataMetaInputs = $newItemData[PersisterInterface::META_INPUT] ?? [];

        return array_keys($existingItemDataMetaInputs);
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



    public function getItemComparisonIndexes(): array
    {
        return $this->itemComparisonIndexes;
    }

    public function setItemComparisonIndexes(array $itemComparisonIndexes): static
    {
        $this->itemComparisonIndexes = $itemComparisonIndexes;
        return $this;
    }

    public function getItemMetaComparisonIndexes(): array
    {
        return $this->itemMetaComparisonIndexes;
    }

    public function getItemTermsComparisonIndexes(): array
    {
        return $this->itemTermsComparisonIndexes;
    }

    public function setItemMetaComparisonIndexes(array $itemMetaComparisonIndexes): static
    {
        $this->itemMetaComparisonIndexes = $itemMetaComparisonIndexes;
        return $this;
    }
}
