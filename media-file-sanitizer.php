<?php
/**
 * Plugin Name: Media File Sanitizer
 * Description: A plugin to sanitize media file names for new uploads and existing media items while ensuring media attachments are not affected.
 * Version: 1.2
 * Author: ChatGPT (prompted by Vajrasar Goswami)
 */

// Hook into the file upload process to sanitize file names before saving
add_filter('wp_handle_upload_prefilter', 'sanitize_uploaded_file_name');

/**
 * Sanitize the file name of uploaded media.
 *
 * @param array $file The file array.
 * @return array The sanitized file array.
 */
function sanitize_uploaded_file_name($file) {
    // Extract the original file name
    $original_file_name = $file['name'];

    // Sanitize the file name using the WordPress built-in function
    $sanitized_file_name = sanitize_file_name($original_file_name);

    // Update the file array with the sanitized file name
    $file['name'] = $sanitized_file_name;

    return $file;
}

/**
 * Ensure the media attachment remains unaffected after sanitization.
 *
 * @param string $file The file path or URL.
 * @param int $attachment_id The attachment post ID.
 * @return string The unchanged file URL or path.
 */
function preserve_media_attachment($file, $attachment_id) {
    // Return the file without making any changes
    return $file;
}

// Hook into the media upload actions to preserve the media attachment functionality
add_filter('wp_get_attachment_url', 'preserve_media_attachment', 10, 2);
add_filter('wp_get_attachment_link', 'preserve_media_attachment', 10, 2);

// Run the sanitization for existing media items on plugin activation
register_activation_hook(__FILE__, 'sanitize_existing_media');

/**
 * Sanitize file names of all existing media items and rename them in the filesystem.
 */
function sanitize_existing_media() {
    // Get all existing media attachments
    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,  // Retrieve all attachments
    );

    $attachments = get_posts($args);

    foreach ($attachments as $attachment) {
        $attachment_id = $attachment->ID;
        $file_path = get_attached_file($attachment_id);

        if ($file_path) {
            // Extract the file name from the path
            $file_name = basename($file_path);
            $sanitized_file_name = sanitize_file_name($file_name);

            // If the file name was changed, rename the file and update the media metadata
            if ($sanitized_file_name !== $file_name) {
                $new_file_path = str_replace($file_name, $sanitized_file_name, $file_path);

                // Rename the file on the filesystem
                if (rename($file_path, $new_file_path)) {
                    // Update the database with the new file path
                    update_attached_file($attachment_id, $new_file_path);

                    // Update metadata that refers to the old file name
                    $metadata = wp_get_attachment_metadata($attachment_id);
                    if (!empty($metadata)) {
                        foreach ($metadata as $key => $value) {
                            if (is_string($value)) {
                                $metadata[$key] = str_replace($file_name, $sanitized_file_name, $value);
                            }
                        }
                        wp_update_attachment_metadata($attachment_id, $metadata);
                    }

                    // If there are any intermediate image sizes, rename those as well
                    $dir = dirname($new_file_path);
                    $image_sizes = wp_get_attachment_metadata($attachment_id);
                    if (!empty($image_sizes['sizes'])) {
                        foreach ($image_sizes['sizes'] as $size => $size_info) {
                            $size_file_name = basename($size_info['file']);
                            $sanitized_size_file_name = sanitize_file_name($size_file_name);

                            if ($sanitized_size_file_name !== $size_file_name) {
                                $old_size_path = $dir . '/' . $size_file_name;
                                $new_size_path = $dir . '/' . $sanitized_size_file_name;

                                // Rename the intermediate size file
                                if (file_exists($old_size_path) && rename($old_size_path, $new_size_path)) {
                                    // Update the metadata with the new file name
                                    $image_sizes['sizes'][$size]['file'] = $sanitized_size_file_name;
                                }
                            }
                        }

                        // Update attachment metadata with renamed image sizes
                        wp_update_attachment_metadata($attachment_id, $image_sizes);
                    }
                }
            }
        }
    }
}