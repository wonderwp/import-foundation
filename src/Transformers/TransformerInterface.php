<?php

namespace WonderWp\Component\ImportFoundation\Transformers;

use WP_Post;

interface TransformerInterface
{
    public function transform(mixed $item, bool $isDryRun): mixed;
}
