<?php

namespace WonderWp\Component\ImportFoundation\Responses;

use WonderWp\Component\Response\ResponseInterface;

interface ResetResponseInterface extends ResponseInterface
{
    const SUCCESS = 'reset.success';
    const ERROR = 'reset.error';
}
