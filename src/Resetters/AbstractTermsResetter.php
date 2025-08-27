<?php

namespace WonderWp\Component\ImportFoundation\Resetters;

use WonderWp\Component\ImportFoundation\Responses\ResetResponseInterface;
use WonderWp\Component\ImportFoundation\Responses\ResetResponse;
use Throwable;

abstract class AbstractTermsResetter implements ResetterInterface
{
    const TAXONOMY_NAME = 'category';
    
    public function reset(): ResetResponseInterface
    {
        try {
            global $wpdb;
            
            $taxonomyName = $this->getTaxonomyNameToReset();
            
            // Get all term IDs for the specified taxonomy
            $termIds = $wpdb->get_col($wpdb->prepare(
                "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                $taxonomyName
            ));
            
            if (empty($termIds)) {
                // No terms to delete
                $response = new ResetResponse(200, ResetResponseInterface::SUCCESS);
                $response->setDeleted(0);
                return $response;
            }
            
            // Get term taxonomy IDs for the specified taxonomy
            $termTaxonomyIds = $wpdb->get_col($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                $taxonomyName
            ));
            
            // Delete in the correct order to maintain referential integrity
            // 1. Delete term relationships
            $deletedRelationships = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode(',', array_fill(0, count($termTaxonomyIds), '%d')) . ")",
                ...$termTaxonomyIds
            ));
            
            // 2. Delete term meta
            $deletedMeta = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->termmeta} WHERE term_id IN (" . implode(',', array_fill(0, count($termIds), '%d')) . ")",
                ...$termIds
            ));
            
            // 3. Delete term taxonomy entries
            $deletedTaxonomy = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                $taxonomyName
            ));
            
            // 4. Delete terms
            $deletedTerms = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->terms} WHERE term_id IN (" . implode(',', array_fill(0, count($termIds), '%d')) . ")",
                ...$termIds
            ));
            
            $response = new ResetResponse(200, ResetResponseInterface::SUCCESS);
            $response->setDeleted($deletedTerms);
            
        } catch (Throwable $e) {
            $responseCode = is_int($e->getCode()) ? $e->getCode() : 500;
            $response = new ResetResponse($responseCode, ResetResponseInterface::ERROR);
            $response->setError($e);
        }

        return $response;
    }
    
    /**
     * Get the taxonomy name to reset
     * Override this method in child classes if needed
     */
    protected function getTaxonomyNameToReset(): string
    {
        return static::TAXONOMY_NAME;
    }
}
