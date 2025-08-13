<?php

namespace WonderWp\Component\ImportFoundation\Requests;

interface HasDeletionEnabledInterface
{
    public function isDeletionEnabled(): bool;
    public function setDeletionEnabled(bool $deletionEnabled): static;
}
