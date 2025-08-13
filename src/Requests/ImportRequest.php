<?php

namespace WonderWp\Component\ImportFoundation\Requests;

use WonderWp\Component\Task\Traits\HasDryRun;
use WonderWp\Component\Task\Traits\HasDryRunInterface;

class ImportRequest implements ImportRequestInterface
{
    use HasDryRun;
    use HasDeletionEnabled;
}
