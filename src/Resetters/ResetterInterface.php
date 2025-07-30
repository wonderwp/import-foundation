<?php

namespace WonderWp\Component\ImportFoundation\Resetters;

use WonderWp\Component\ImportFoundation\Response\ResetResponseInterface;

interface ResetterInterface
{
    const ALL = 'all';

    const COMPANIES = 'companies';

    const PRODUCTS = 'products';

    public function reset(): ResetResponseInterface;
}
