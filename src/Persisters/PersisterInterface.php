<?php

namespace WonderWp\Component\ImportFoundation\Persisters;

interface PersisterInterface
{
    const SYNC_ID = 'sync_id';
    const META_INPUT = 'meta_input';
    const ACF_INPUT = 'acf_input';
    const TAX_INPUT = 'tax_input';
    const MEDIA_INPUT = 'media_input';
    const RELATIONSHIP_INPUT = 'relationship_input';
    const FEATURED_IMAGE = '_thumbnail_id';
    const FEATURED_IMAGE_URL = 'featured_image_url';

    public function create(mixed $toCreate, bool $isDryRun = false): mixed;
    public function update(mixed $toUpdate, mixed $toUpdateId, array $updateReasons, bool $isDryRun = false): mixed;
    public function delete(mixed $toDelete, bool $isDryRun = false): mixed;
}

