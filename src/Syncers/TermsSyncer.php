<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Requests\SyncRequestInterface;
use WonderWp\Component\ImportFoundation\Responses\SyncResponse;
use WonderWp\Component\ImportFoundation\Responses\SyncResponseInterface;
use WonderWp\Component\Logging\LoggerInterface;

class TermsSyncer extends AbstractSyncer
{
    protected function idToLog($item): string
    {
        if(!$item instanceof \WP_Term){
            throw new \InvalidArgumentException('Item must be an instance of WP_Term');
        }
    }

    protected function findItemId(mixed $item): int|string
    {
        // TODO: Implement findItemId() method.
    }

}
