<?php

namespace WonderWp\Component\ImportFoundation\Transformers;

use WP_Term;

class TermTransformer implements TransformerInterface
{
    /**
     * @param WP_Term $item
     * @param bool $isDryRun
     * @return WP_Term
     */
    public function transform(mixed $item, bool $isDryRun): mixed
    {
        if(!($item instanceof WP_Term)) {
            throw new \InvalidArgumentException('Item must be an instance of WP_Term');
        }

        return $item;
    }

}
