<?php

namespace WonderWp\Component\ImportFoundation\Syncers\Traits;

trait IndexComparisonTrait
{
    protected array $itemComparisonIndexes = [];

    /**
     * Check if item needs update based on core indexes
     */
    protected function checkItemIndexesForUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        $updateReasons = [];
        $newItemData = $sourceItem->to_array();
        $existingItemData = $destinationItem->to_array();

        $indexesToCheck = $this->getItemIndexesToCheck();
        if (empty($indexesToCheck)) {
            return $updateReasons;
        }

        foreach ($indexesToCheck as $index) {
            // Keep the comparison operator loose here to avoid type comparison issues
            if ($this->itemValueChanged($index, $newItemData[$index], $existingItemData[$index])) {
                $updateReasons[$index] = [
                    $newItemData[$index] ?? null,
                    $existingItemData[$index] ?? null
                ];
            }
        }

        return $updateReasons;
    }

    protected function getItemIndexesToCheck(): array
    {
        return $this->getItemComparisonIndexes();
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
}
