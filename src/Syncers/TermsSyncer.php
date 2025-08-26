<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Syncers\Traits\IndexComparisonTrait;
use WonderWp\Component\ImportFoundation\Syncers\Traits\MetaComparisonTrait;

class TermsSyncer extends AbstractSyncer
{
    use IndexComparisonTrait, MetaComparisonTrait;

    public function __construct(PersisterInterface $persister)
    {
        parent::__construct($persister);
        
        // Set default comparison indexes for terms
        $this->setItemComparisonIndexes([
            'name',
            'description'
        ]);
    }

    protected function idToLog($item): string
    {
        if(!$item instanceof \WP_Term){
            throw new \InvalidArgumentException('Item must be an instance of WP_Term');
        }

        /** @var \WP_Term $item */
        return $item->name . '#' . ($this->findItemId($item));
    }

    protected function findItemId(mixed $item): int|string
    {
        if(!$item instanceof \WP_Term){
            throw new \InvalidArgumentException('Item must be an instance of WP_Term');
        }

        /** @var \WP_Term $item */
        $metaInputAttribute = PersisterInterface::META_INPUT;
        if (isset($item->$metaInputAttribute[PersisterInterface::SYNC_ID])) {
            return $item->$metaInputAttribute[PersisterInterface::SYNC_ID];
        }

        //If empty, return term slug
        return $item->slug;
    }

    /**
     * Get existing item meta value for terms
     */
    protected function getExistingItemMetaValue(mixed $destinationItem, string $metaKey): mixed
    {
        return \get_term_meta($destinationItem->term_id, $metaKey, true);
    }

    protected function checkIfItemNeedsUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];

        // Check if the item needs an update based on the indexes to check
        $updateReasons = array_merge($updateReasons, $this->checkItemIndexesForUpdate($sourceItem, $destinationItem));

        // Check if the item needs an update based on the metas to check
        $updateReasons = array_merge($updateReasons, $this->checkItemMetasForUpdate($sourceItem, $destinationItem));

        return $updateReasons;
    }
}
