<?php

namespace WonderWp\Component\ImportFoundation\Requests;

trait HasDeletionEnabled
{
    protected bool $deletionEnabled = false;

    public function isDeletionEnabled(): bool
    {
        return $this->deletionEnabled;
    }

    public function setDeletionEnabled(bool $deletionEnabled): static
    {
        $this->deletionEnabled = $deletionEnabled;

        return $this;
    }
}
