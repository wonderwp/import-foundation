<?php

namespace WonderWp\Component\ImportFoundation\Transformers;

use WP_Post;

abstract class AbstractTransformer implements TransformerInterface
{
    public function transform(WP_Post $post, bool $isDryRun): WP_Post
    {
        return $post;
    }
}
