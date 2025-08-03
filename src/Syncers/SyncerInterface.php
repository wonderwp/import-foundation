<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Requests\SyncRequestInterface;
use WonderWp\Component\ImportFoundation\Responses\SyncResponseInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Task\Progress\ProgressInterface;

interface SyncerInterface
{
    public function sync(
        SyncRequestInterface $syncRequest,
        LoggerInterface $logger,
    ): SyncResponseInterface;
}
