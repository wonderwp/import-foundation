<?php

namespace WonderWp\Component\ImportFoundation\Persisters;

use WonderWp\Component\Logging\HasLoggerInterface;
use WonderWp\Component\Logging\HasLoggerTrait;
use WonderWp\Component\Logging\LoggerInterface;
use WP_Error;
use WP_Post;

class WpPostPersister implements PersisterInterface, HasLoggerInterface
{
    use HasLoggerTrait;

    private WpMediaPersister $mediaPersister;

    public function __construct(WpMediaPersister $mediaPersister)
    {
        $this->mediaPersister = $mediaPersister;
    }

    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function create(mixed $toCreate, bool $isDryRun = false): int|WP_Error
    {
        return $this->createPost($toCreate, $isDryRun);
    }

    protected function createPost(WP_Post $post, bool $isDryRun = false): int|WP_Error
    {
        $newPostData = $post->to_array();

        if ($isDryRun) {
            //Return a fake id
            $insertedId = rand(1000, 2000);
        } else {
            $insertedId = wp_insert_post($newPostData, true);
        }

        $this->savePostData($insertedId, $newPostData, null, $isDryRun);

        return $insertedId;
    }

    public function update(mixed $toUpdate, mixed $toUpdateId, array $updateReasons, bool $isDryRun = false): int|WP_Error
    {
        return $this->updatePost($toUpdate, $toUpdateId, $updateReasons, $isDryRun);
    }

    protected function updatePost(WP_Post $newPost, mixed $existingPostId, array $updateReasons, bool $isDryRun = false): int|WP_Error
    {
        $newPostData = $newPost->to_array();
        $newPostData['ID'] = $existingPostId;

        if ($isDryRun) {
            $postId = $newPostData['ID'];
        } else {
            $postId = wp_update_post($newPostData, true);
        }

        $this->savePostData($postId, $newPostData, $updateReasons, $isDryRun);

        return $postId;
    }

    public function delete(mixed $toDelete, bool $isDryRun = false): WP_Post|array|null|false
    {
        return $this->deletePost($toDelete, $isDryRun);
    }

    protected function deletePost(WP_Post $p, bool $isDryRun = false): WP_Post|array|null|false
    {
        if ($isDryRun) {
            //Return a fake id
            return $p->to_array();
        }

        return wp_delete_post($p->ID, true);
    }

    protected function savePostData(WP_Error|int $postId, array $postData, ?array $updateReasons, bool $isDryRun): array
    {
        if (!$postId || is_wp_error($postId)) {
            return [
                'taxonomies' => [],
                'media' => [],
                'acf' => [],
            ];
        }

        $savedPostTaxonomies = $this->savePostTaxonomies($postId, $postData, $updateReasons, $isDryRun);
        $savedPostMedia = $this->savePostMedia($postId, $postData, $updateReasons, $isDryRun);
        $savedAcfFields = $this->saveAcfFields($postId, $postData, $updateReasons, $isDryRun);

        return [
            'taxonomies' => $savedPostTaxonomies,
            'media' => $savedPostMedia,
            'acf' => $savedAcfFields,
        ];
    }

    protected function savePostTaxonomies(WP_Error|int $postId, array $postData, ?array $updateReasons, bool $isDryRun): array
    {
        $associatedTaxonomies = [];
        if (!$postId || is_wp_error($postId) || empty($postData[PersisterInterface::TAX_INPUT])) {
            return apply_filters('WpPostPersister/savePostData/savePostTaxonomies/associatedTaxonomies', $associatedTaxonomies, $postId, $postData, $updateReasons, $isDryRun, $this);
        }

        foreach ($postData[PersisterInterface::TAX_INPUT] as $taxonomyName => $taxonomyValues) {
            //make sure we only apply taxonomy ids in the values we pass to wp_set_object_terms.
            //Here, $taxonomyValues can hold some WP_Term objects, we need to extract the ids
            $taxonomyValues = array_map(function ($term) {
                if ($term instanceof \WP_Term) {
                    return $term->term_id;
                }
                return $term;
            }, $taxonomyValues);
            $associatedTaxonomies[$taxonomyName] = $isDryRun ? [] : wp_set_object_terms($postId, $taxonomyValues, $taxonomyName);
        }

        return apply_filters('WpPostPersister/savePostData/savePostTaxonomies/associatedTaxonomies', $associatedTaxonomies, $postId, $postData, $updateReasons, $isDryRun, $this);
    }

    protected function savePostMedia(WP_Error|int $postId, array $postData, ?array $updateReasons, bool $isDryRun): array
    {
        $associatedMedia = [];

        if (!empty($postData[PersisterInterface::MEDIA_INPUT])) {
            var_dump($postData[PersisterInterface::MEDIA_INPUT]);
            foreach ($postData[PersisterInterface::MEDIA_INPUT] as $mediaKey => $mediaValue) {
                $associatedMedia[$mediaKey] = $isDryRun ? false : $this->mediaPersister->downloadAndCreateAttachment($mediaValue, $postId, $mediaKey, $isDryRun);
            }
        }

        $associatedMedia = apply_filters('WpPostPersister/savePostData/savePostMedia/associatedMedia', $associatedMedia, $postId, $postData, $updateReasons, $isDryRun, $this);
        
        //Update the post with the associated media
        foreach ($associatedMedia as $mediaKey => $attachmentId) {
            if ($attachmentId && !is_wp_error($attachmentId)) {
                //If it's a featured image, set it as the featured image
                if ($mediaKey === PersisterInterface::FEATURED_IMAGE_URL) {
                    set_post_thumbnail($postId, $attachmentId);
                } else {
                    update_post_meta($postId, $mediaKey, apply_filters('WpPostPersister/savePostData/savePostMedia/associatedMedia/metaValue', $attachmentId, $mediaKey, $postId));
                }
            }
        }

        return $associatedMedia;
    }

    protected function saveAcfFields(WP_Error|int $postId, array $postData, ?array $updateReasons, bool $isDryRun): array
    {
        $associatedFields = [];

        //make sur meta inputs are saved as acf fields too
        if (!function_exists('update_field') || !$postId || is_wp_error($postId) || empty($postData[PersisterInterface::ACF_INPUT])) {
            return apply_filters('WpPostPersister/savePostData/saveAcfFields/associatedFields', $associatedFields, $postId, $postData, $updateReasons, $isDryRun, $this);
        }

        foreach ($postData[PersisterInterface::ACF_INPUT] as $metaKey => $metaValue) {
            $associatedFields[$metaKey] = $isDryRun ? false : update_field($metaKey, $metaValue, $postId);
        }


        return apply_filters('WpPostPersister/savePostData/saveAcfFields/associatedFields', $associatedFields, $postId, $postData, $updateReasons, $isDryRun, $this);
    }

    /**
     * Check if an attachment already exists in the media library
     *
     * @param string $fileName The file name to check
     * @param string $content The file content to compare with
     * @return int|false The attachment ID if found, false otherwise
     */
    public function getExistingAttachment(string $fileName): int|false
    {
        global $wpdb;

        // First try to find by filename in wp_posts
        $attachment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_title LIKE '%%%s%%'",
                sanitize_file_name($fileName)
            )
        );

        if (empty($attachment)) {
            return false;
        }

        return (int) $attachment->ID;
    }
}
