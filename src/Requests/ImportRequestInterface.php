<?php

namespace WonderWp\Component\ImportFoundation\Requests;

interface ImportRequestInterface
{
    public function isDryRun(): bool;

    public function setDryRun(bool $dryRun): static;
}
