<?php

namespace WonderWp\Component\ImportFoundation\Resetters;

use WonderWp\Component\ImportFoundation\Responses\ResetResponseInterface;

interface ResetterInterface
{
    const ALL = 'all';

    public function reset(): ResetResponseInterface;
}
