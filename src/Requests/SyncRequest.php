<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WonderWp\Component\Task\Traits\HasDryRun;
use WonderWp\Component\Task\Traits\HasDryRunInterface;
use WP_Post;

class SyncRequest implements SyncRequestInterface
{
    use HasDryRun;
    use HasDeletionEnabled;

    protected array $existingItems = [];
    protected array $newItems = [];

    /**
     * @param WP_Post[] $existingItems
     * @param WP_Post[] $newItems
     * @param bool $dryRun
     * @param bool $deletionEnabled
     */
    public function __construct(
        array $newItems,
        array $existingItems,
        bool $dryRun = false,
        bool $deletionEnabled = false
    )
    {
        $this->newItems = $newItems;
        $this->existingItems = $existingItems;
        $this->dryRun = $dryRun;
        $this->deletionEnabled = $deletionEnabled;
    }

    public function getExistingItems(): array
    {
        return $this->existingItems;
    }

    public function setExistingItems(array $existingItems): static
    {
        $this->existingItems = $existingItems;
        return $this;
    }

    public function getNewItems(): array
    {
        return $this->newItems;
    }

    public function setNewItems(array $newItems): static
    {
        $this->newItems = $newItems;
        return $this;
    }


}
