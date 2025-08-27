<?php

namespace WonderWp\Component\ImportFoundation\Persisters;

use WP_Error;

class WpMediaPersister
{
    public function uploadImageFromContent(string $fileName, string $content, ?int $postId = null): int|\WP_Error
    {
        // Check if an identical attachment already exists
        $existingId = $this->getExistingAttachment($fileName);
        if ($existingId) {
            return $existingId;
        }

        // Upload the new photo to the media library
        $upload = wp_upload_bits($fileName, null, $content);
        if ($upload['error']) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        // Create a new attachment post
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($upload['file']),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        // Insert the attachment into the media library
        $attachmentId = wp_insert_attachment($attachment, $upload['file'], $postId);
        if (is_wp_error($attachmentId)) {
            return $attachmentId;
        }

        // Generate the attachment metadata
        $attachmentData = wp_generate_attachment_metadata($attachmentId, $upload['file']);

        // Update the attachment metadata in the database
        wp_update_attachment_metadata($attachmentId, $attachmentData);

        return $attachmentId;
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

    /**
     * Check if a file already exists on the server filesystem and retrieve its content
     * 
     * @param string $baseFileName The base file name without extension
     * @return array|false Array with 'path', 'content', and 'extension' if found, false otherwise
     */
    public function getExistingFileOnServer(string $baseFileName): array|false
    {
        // Get upload directory info
        $uploadDir = wp_upload_dir();
        $baseDir = $uploadDir['basedir'];
        $subDir = $uploadDir['subdir'];
        
        // Common image extensions to check
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        
        // Check in current upload directory (respects year/month organization)
        $currentDir = $baseDir . $subDir;
        
        foreach ($extensions as $ext) {
            $fileName = $baseFileName . '.' . $ext;
            $filePath = $currentDir . '/' . $fileName;
            
            // Check if file exists on filesystem
            if (file_exists($filePath)) {
                // Read the file content
                $fileContent = file_get_contents($filePath);
                if ($fileContent !== false) {
                    return [
                        'path' => $filePath,
                        'content' => $fileContent,
                        'extension' => $ext
                    ];
                }
            }
        }
        
        // Also check in base directory for files that might not be organized by date
        foreach ($extensions as $ext) {
            $fileName = $baseFileName . '.' . $ext;
            $filePath = $baseDir . '/' . $fileName;
            
            // Check if file exists on filesystem
            if (file_exists($filePath)) {
                // Read the file content
                $fileContent = file_get_contents($filePath);
                if ($fileContent !== false) {
                    return [
                        'path' => $filePath,
                        'content' => $fileContent,
                        'extension' => $ext
                    ];
                }
            }
        }
        
        return false;
    }

    /**
     * Downloads an image from a URL and creates a WordPress attachment
     *
     * @param string $imageUrl The URL of the image to download
     * @param int $postId The post ID to attach the image to
     * @param string $baseFileName The base name for the file (without extension)
     * @param bool $isDryRun Whether this is a dry run
     * @return int|WP_Error The attachment ID or WP_Error on failure
     */
    public function downloadAndCreateAttachment(string $imageUrl, int $postId, string $baseFileName, bool $isDryRun): int|WP_Error
    {
        //check if the image url is a valid url
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid image URL');
        }

        //Check if the attachment already exists in database
        $existingAttachmentId = $this->getExistingAttachment($baseFileName);
        if ($existingAttachmentId) {
            $this->log('existingAttachmentId found : ' . $existingAttachmentId);
            return $existingAttachmentId;
        }

        // Check if the file content already exists on the server filesystem
        $existingFile = $this->getExistingFileOnServer($baseFileName);
        if ($existingFile) {
            $this->log('existingFile found on server: ' . $existingFile['path'] . ' - using existing content instead of remote download');
            
            // Use the existing file content to create the attachment
            $fileName = $baseFileName . '.' . $existingFile['extension'];
            return $this->uploadImageFromContent($fileName, $existingFile['content'], $postId);
        }

        //Fetch the image content response from the API URL
        $newPhotoContentResponse = wp_remote_get($imageUrl, [
            'timeout' => 10,
        ]);

        if (is_wp_error($newPhotoContentResponse)) {
            $this->log('image_download_failed: ' . $newPhotoContentResponse->get_error_message());
            return new WP_Error('image_download_failed', $newPhotoContentResponse->get_error_message());
        }

        // Get the content of the new photo from the API response
        $newPhotoContent = wp_remote_retrieve_body($newPhotoContentResponse);
        if (empty($newPhotoContent)) {
            $this->log('newPhotoContent is empty');
            return new WP_Error('empty_content', 'Image content is empty');
        } else {
            $this->log('newPhotoContent found');
        }

        //Compute the file name from the image and product info
        $contentType = wp_remote_retrieve_header($newPhotoContentResponse, 'content-type');
        $extensionFromContentType = explode('/', $contentType)[1] ?? '.jpg';
        $fileName = $baseFileName . '.' . $extensionFromContentType;

        // Use the persister to upload the image (only if not a dry run)
        if ($isDryRun) {
            $attachmentId = rand(5000, 6000);
            $this->log(sprintf('newPhotoContent %s dry run (no effective upload)', $fileName));
            return $attachmentId;
        } else {
            $this->log(sprintf('uploading new photo %s', $fileName));
        }

        return $this->uploadImageFromContent($fileName, $newPhotoContent, $postId);
    }

    private function log(string $message)
    {
        //echo $message . PHP_EOL;
    }
}