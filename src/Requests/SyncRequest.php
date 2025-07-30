<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WonderWp\Component\Task\Traits\HasDryRun;
use WonderWp\Component\Task\Traits\HasDryRunInterface;
use WP_Post;

class SyncRequest implements SyncRequestInterface, HasDryRunInterface
{
    use HasDryRun;
    protected array $existingPosts = [];
    protected array $newPosts = [];

    /**
     * @param WP_Post[] $existingPosts
     * @param WP_Post[] $newPosts
     */
    public function __construct(
        array $newPosts,
        array $existingPosts,
        bool $dryRun = false
    )
    {
        $this->newPosts = $newPosts;
        $this->existingPosts = $existingPosts;
        $this->dryRun = $dryRun;
    }

    public function getExistingPosts(): array
    {
        return $this->existingPosts;
    }

    public function setExistingPosts(array $existingPosts): static
    {
        $this->existingPosts = $existingPosts;
        return $this;
    }

    public function getNewPosts(): array
    {
        return $this->newPosts;
    }

    public function setNewPosts(array $newPosts): static
    {
        $this->newPosts = $newPosts;
        return $this;
    }


}
