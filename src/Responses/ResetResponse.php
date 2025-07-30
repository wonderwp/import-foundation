<?php

namespace WonderWp\Component\ImportFoundation\Response;

use WonderWp\Component\Response\AbstractResponse;

class ResetResponse extends AbstractResponse implements ResetResponseInterface
{
    protected int|false $deleted = false;

    public function getDeleted(): false|int
    {
        return $this->deleted;
    }

    public function setDeleted(false|int $deleted): void
    {
        $this->deleted = $deleted;
    }
}
