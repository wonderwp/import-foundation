<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WonderWp\Component\Task\Traits\HasDryRunInterface;
use WP_Post;

interface SyncRequestInterface extends HasDryRunInterface, HasDeletionEnabledInterface
{

    public function getExistingItems(): array;

    public function setExistingItems(array $existingItems): static;

    public function getNewItems(): array;

    public function setNewItems(array $newItems): static;
}
