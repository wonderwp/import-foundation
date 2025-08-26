<?php

namespace WonderWp\Component\ImportFoundation\Transformers;

class NonTransformer implements TransformerInterface
{
    public function transform(mixed $item, bool $isDryRun): mixed
    {
        return $item;
    }

}
