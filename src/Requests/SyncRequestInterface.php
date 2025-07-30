<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WP_Post;

interface SyncRequestInterface
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

    public function isDryRun(): bool;
    public function setDryRun(bool $dryRun): static;
}
