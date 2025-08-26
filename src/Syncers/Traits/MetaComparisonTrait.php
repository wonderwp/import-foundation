<?php

namespace WonderWp\Component\ImportFoundation\Syncers\Traits;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;

trait MetaComparisonTrait
{
    protected array $itemMetaComparisonIndexes = [];

    /**
     * Check if item needs update based on meta fields
     */
    protected function checkItemMetasForUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];
        $newItemData = $sourceItem->to_array();
        $existingItemData = $destinationItem->to_array();

        $metasToCheck = $this->getItemMetasIndexesToCheck($newItemData);
        foreach ($metasToCheck as $metaKey) {
            $existingItemMetaValue = $existingItemData[PersisterInterface::META_INPUT][$metaKey] ?? null;
            if (empty($existingItemMetaValue)) {
                $existingItemMetaValue = $this->getExistingItemMetaValue($destinationItem, $metaKey);
            }
            // Keep the comparison operator loose here to avoid type comparison issues
            if ($this->itemValueChanged($metaKey, $newItemData[PersisterInterface::META_INPUT][$metaKey], $existingItemMetaValue)) {
                $updateReasons[$metaKey] = [
                    $newItemData[PersisterInterface::META_INPUT][$metaKey] ?? null,
                    $existingItemMetaValue
                ];
            }
        }

        return $updateReasons;
    }

    /**
     * Get existing item meta value - to be implemented by consuming classes
     */
    abstract protected function getExistingItemMetaValue(mixed $destinationItem, string $metaKey): mixed;

    protected function getItemMetasIndexesToCheck(array $newItemData): array
    {
        // Metas
        $existingItemDataMetaInputs = $newItemData[PersisterInterface::META_INPUT] ?? [];

        return array_keys($existingItemDataMetaInputs);
    }

    public function getItemMetaComparisonIndexes(): array
    {
        return $this->itemMetaComparisonIndexes;
    }

    public function setItemMetaComparisonIndexes(array $itemMetaComparisonIndexes): static
    {
        $this->itemMetaComparisonIndexes = $itemMetaComparisonIndexes;
        return $this;
    }
}
