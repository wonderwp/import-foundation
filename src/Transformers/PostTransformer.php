<?php

namespace WonderWp\Component\ImportFoundation\Transformers;

use WP_Post;

class PostTransformer implements TransformerInterface
{
    /**
     * @param WP_Post $item
     * @param bool $isDryRun
     * @return WP_Post
     */
    public function transform(mixed $item, bool $isDryRun): mixed
    {
        if(!($item instanceof WP_Post)) {
            throw new \InvalidArgumentException('Item must be an instance of WP_Post');
        }

        return $item;
    }
}
