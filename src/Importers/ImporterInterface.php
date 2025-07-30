<?php

namespace WonderWp\Component\ImportFoundation\Importers;

use WonderWp\Component\ImportFoundation\Requests\ImportRequestInterface;
use WonderWp\Component\ImportFoundation\Response\ImportResponseInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Task\Progress\ProgressInterface;

interface ImporterInterface
{
    public function forgeRequest(array $args, array $assocArgs): ImportRequestInterface;

    public function migrate(
        ImportRequestInterface $request,
        LoggerInterface $logger,
        ProgressInterface $progress
    ): ImportResponseInterface;
}

