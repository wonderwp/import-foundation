<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WonderWp\Component\Task\Traits\HasDryRunInterface;

interface ImportRequestInterface extends HasDeletionEnabledInterface, HasDryRunInterface
{
    const DELETION_ENABLED_ARG = 'deletion_enabled';
    const DRY_RUN_ARG = 'dry_run';
}
