<?php

namespace WonderWp\Component\ImportFoundation\Responses;

use WP_Error;

interface SyncResponseInterface
{
    const SUCCESS = 'SyncResponse.success';

    const ERROR = 'SyncResponse.error';

    const NOOP = 'SyncResponse.noop';

    public function getGenerationTime(): float;

    public function setGenerationTime(float $generationTime): SyncResponseInterface;

    public function getNewItems(): array;

    public function setNewItems(array $apiItems): static;

    public function getExistingItems(): array;

    public function setExistingItems(array $dbItems): static;

    public function getCreatedItems(): array;

    public function addCreatedItem($id): static;

    public function getSkippedItems(): array;

    public function addSkippedItem($id): static;

    public function getUpdatedItems(): array;

    public function addUpdatedItem($id, array $updateReasons = []): static;

    public function getDeletedItems(): array;

    public function addDeletedItem($pid): static;

    public function getErroredItems(): array;

    public function addErroredItem(string $idToLog, WP_Error $error);

    public function toShortArray(): array;

}
