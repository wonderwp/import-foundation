<?php

namespace WonderWp\Component\ImportFoundation\Transformers;

use WP_Post;

interface TransformerInterface
{
    public function transform(WP_Post $post, bool $isDryRun): WP_Post;
}
