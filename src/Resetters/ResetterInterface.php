<?php

namespace WonderWp\Component\ImportFoundation\Resetters;

use WonderWp\Component\ImportFoundation\Response\ResetResponseInterface;

interface ResetterInterface
{
    const ALL = 'all';

    public function reset(): ResetResponseInterface;
}
