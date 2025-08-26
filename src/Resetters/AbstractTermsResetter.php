<?php

namespace WonderWp\Component\ImportFoundation\Resetters;

use WonderWp\Component\ImportFoundation\Response\ResetResponseInterface;
use WonderWp\Component\ImportFoundation\Responses\ResetResponse;

abstract class AbstractTermsResetter implements ResetterInterface
{
    public function reset(): ResetResponseInterface
    {
        //TODO : implement terms resetter
        return new ResetResponse(501);
    }

}
