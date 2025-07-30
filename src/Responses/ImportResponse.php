<?php

namespace WonderWp\Component\ImportFoundation\Response;

class ImportResponse extends AbstractImportResponse implements ImportResponseInterface
{
    protected ?SyncResponseInterface $syncResponse;

    public function getSyncResponse(): ?SyncResponseInterface
    {
        return $this->syncResponse;
    }

    public function setSyncResponse(?SyncResponseInterface $syncResponse): static
    {
        $this->syncResponse = $syncResponse;

        return $this;
    }
}
