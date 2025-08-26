<?php

namespace WonderWp\Component\ImportFoundation\Persisters;

use WP_Error;
use WP_Term;

class WpTermPersister implements PersisterInterface
{
    public function create(mixed $toCreate, bool $isDryRun = false): mixed
    {
        return $this->createTerm($toCreate, $isDryRun);
    }

    public function update(mixed $toUpdate, mixed $toUpdateId, array $updateReasons, bool $isDryRun = false): mixed
    {
        return $this->updateTerm($toUpdate, $toUpdateId, $updateReasons, $isDryRun);
    }

    public function delete(mixed $toDelete, bool $isDryRun = false): mixed
    {
        return $this->deleteTerm($toDelete, $isDryRun);
    }

    /**
     * Create a new term
     */
    protected function createTerm(WP_Term $term, bool $isDryRun = false): int|WP_Error
    {
        $termData = [
            'description' => $term->description,
            'slug' => $term->slug,
            'parent' => $term->parent,
        ];

        if ($isDryRun) {
            // Return a fake ID for dry run
            return rand(1000, 2000);
        }

        $result = wp_insert_term($term->name, $term->taxonomy, $termData);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Save additional term meta if present
        $this->saveTermMeta($result['term_id'], $term, $isDryRun);

        return $result['term_id'];
    }

    /**
     * Update an existing term
     */
    protected function updateTerm(WP_Term $newTerm, mixed $existingTermId, array $updateReasons, bool $isDryRun = false): int|WP_Error
    {
        $termData = [
            'description' => $newTerm->description,
            'slug' => $newTerm->slug,
            'parent' => $newTerm->parent,
        ];

        if ($isDryRun) {
            return (int) $existingTermId;
        }

        $result = wp_update_term($existingTermId, $newTerm->taxonomy, $termData);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Save additional term meta if present
        $this->saveTermMeta($result['term_id'], $newTerm, $isDryRun);

        return $result['term_id'];
    }

    /**
     * Delete a term
     */
    protected function deleteTerm(WP_Term $term, bool $isDryRun = false): WP_Term|array|null|false
    {
        if ($isDryRun) {
            // Return term data for dry run
            return $term->to_array();
        }

        return wp_delete_term($term->term_id, $term->taxonomy);
    }

    /**
     * Save additional term meta data
     */
    protected function saveTermMeta(int $termId, WP_Term $term, bool $isDryRun = false): array
    {
        $savedMeta = [];

        if ($isDryRun) {
            return $savedMeta;
        }

        // Check if the term has meta_input data (following framework pattern)
        if (!isset($term->meta_input) || !is_array($term->meta_input)) {
            return $savedMeta;
        }

        // Save meta data from the meta_input array
        foreach ($term->meta_input as $metaKey => $metaValue) {
            if (!is_null($metaValue)) {
                update_term_meta($termId, $metaKey, $metaValue);
                $savedMeta[$metaKey] = $metaValue;
            }
        }

        return $savedMeta;
    }
}
