<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WonderWp\Component\Task\Traits\HasDryRunInterface;
use WP_Post;

interface SyncRequestInterface extends HasDryRunInterface, HasDeletionEnabledInterface
{
    /**
     * @return WP_Post[]
     */
    public function getExistingPosts(): array;

    /**
     * @param WP_Post[] $existingPosts
     */
    public function setExistingPosts(array $existingPosts): static;

    /**
     * @return WP_Post[]
     */
    public function getNewPosts(): array;

    /**
     * @param WP_Post[] $newPosts
     */
    public function setNewPosts(array $newPosts): static;
}
