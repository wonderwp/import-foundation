<?php

namespace WonderWp\Component\ImportFoundation\Responses;

use WonderWp\Component\Response\AbstractResponse;

abstract class AbstractImportResponse extends AbstractResponse
{
    public function __construct(int $code = 0, string $msgKey = '', string $textDomain = '')
    {
        if(empty($textDomain)){
            $textDomain = 'feocc-catalogue';
        }
        parent::__construct($code, $msgKey, $textDomain);
    }

}
