<?php

namespace WonderWp\Component\ImportFoundation\Response;

use WP_Error;

class SyncResponse extends AbstractImportResponse implements SyncResponseInterface
{

    protected float $generationTime = 0;
    protected array $newItems = [];
    protected array $existingItems = [];
    protected array $createdItems = [];
    protected array $skippedItems = [];
    protected array $updatedItems = [];
    protected array $deletedItems = [];
    protected array $erroredItems = [];

    public function getGenerationTime(): float
    {
        return $this->generationTime;
    }

    public function setGenerationTime(float $generationTime): SyncResponse
    {
        $this->generationTime = $generationTime;
        return $this;
    }

    public function getNewItems(): array
    {
        return $this->newItems;
    }

    public function setNewItems(array $apiItems): static
    {
        $this->newItems = $apiItems;
        return $this;
    }

    public function getExistingItems(): array
    {
        return $this->existingItems;
    }

    public function setExistingItems(array $dbItems): static
    {
        $this->existingItems = $dbItems;
        return $this;
    }

    public function getCreatedItems(): array
    {
        return $this->createdItems;
    }

    public function addCreatedItem($id): static
    {
        $this->createdItems[$id] = $id;
        return $this;
    }

    public function getSkippedItems(): array
    {
        return $this->skippedItems;
    }

    public function addSkippedItem($id): static
    {
        $this->skippedItems[$id] = $id;
        return $this;
    }

    public function getUpdatedItems(): array
    {
        return $this->updatedItems;
    }

    public function addUpdatedItem($id, array $updateReasons = []): static
    {
        $this->updatedItems[$id] = $updateReasons;
        return $this;
    }

    public function getDeletedItems(): array
    {
        return $this->deletedItems;
    }

    public function addDeletedItem($pid): static
    {
        $this->deletedItems[$pid] = $pid;
        return $this;
    }

    public function getErroredItems(): array
    {
        return $this->erroredItems;
    }

    public function addErroredItem(string $idToLog, WP_Error $error)
    {
        $this->erroredItems[$idToLog] = $error;
        return $this;
    }

    public function getObjectVars():array
    {
        return get_object_vars($this);
    }

    public function toShortArray(): array
    {
        return [
            'generationTime' => $this->getGenerationTime(),
            'newItems' => count($this->getNewItems()),
            'existingItems' => count($this->getExistingItems()),
            'createdItems' => count($this->getCreatedItems()),
            'skippedItems' => count($this->getSkippedItems()),
            'updatedItems' => count($this->getUpdatedItems()),
            'deletedItems' => count($this->getDeletedItems()),
            'erroredItems' => count($this->getErroredItems()),
            'error' => $this->getError()?->getMessage(),
            'errorFile' => $this->getError() ? $this->getError()->getFile() . '#' . $this->getError()->getLine() : null,
        ];
    }

}
