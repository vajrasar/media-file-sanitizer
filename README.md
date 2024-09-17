## Media File Sanitizer

**A WordPress plugin to sanitize exsiting and new media file names while also renaming files on filesystem.**

_PS: This plugin is created using ChatGPT and is not battle/production-tested. Use with caution._

### Overview
The plugin is called **"Media File Sanitizer"**, and its primary purpose is to **sanitize file names** for newly uploaded media files and already existing media items in WordPress. Sanitization means cleaning up file names to remove unsafe or unwanted characters, ensuring they follow a safe and consistent naming convention.

### Key Features
1. **Sanitize File Names on Upload**: When a new media file is uploaded, the plugin sanitizes the file name to ensure it only includes safe characters (like removing special characters or spaces).
2. **Sanitize Existing Media Files**: When the plugin is activated, it will sanitize the file names of all existing media items, renaming them on the filesystem.
3. **Preserve Media Attachments**: Ensures that sanitizing the filenames does not break existing links to the media files (such as URLs and metadata).

### Code Breakdown

#### 1. **Sanitize File Names on Upload**
```php
add_filter('wp_handle_upload_prefilter', 'sanitize_uploaded_file_name');

function sanitize_uploaded_file_name($file) {
    $original_file_name = $file['name'];
    $sanitized_file_name = sanitize_file_name($original_file_name);
    $file['name'] = $sanitized_file_name;
    return $file;
}
```
- **What it does**: 
  - This part of the code hooks into the media file upload process using the `wp_handle_upload_prefilter` filter.
  - It captures the file name before it is saved to the server, sanitizes it using the built-in `sanitize_file_name()` function, and then updates the file name in the `$file` array.
  - The sanitization process replaces unsafe characters (e.g., spaces, accents) with more URL-friendly versions (e.g., hyphens).

#### 2. **Ensure Media Attachments Remain Unaffected**
```php
add_filter('wp_get_attachment_url', 'preserve_media_attachment', 10, 2);
add_filter('wp_get_attachment_link', 'preserve_media_attachment', 10, 2);

function preserve_media_attachment($file, $attachment_id) {
    return $file;
}
```
- **What it does**: 
  - These filters (`wp_get_attachment_url` and `wp_get_attachment_link`) ensure that no unintended changes are made to the URLs or links of media attachments after the sanitization process.
  - The `preserve_media_attachment()` function simply returns the file URL or link unchanged. This ensures that media attachments remain functional after sanitization.

#### 3. **Sanitize Existing Media on Activation**
```php
register_activation_hook(__FILE__, 'sanitize_existing_media');

function sanitize_existing_media() {
    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
    );
    $attachments = get_posts($args);

    foreach ($attachments as $attachment) {
        $attachment_id = $attachment->ID;
        $file_path = get_attached_file($attachment_id);

        if ($file_path) {
            $file_name = basename($file_path);
            $sanitized_file_name = sanitize_file_name($file_name);

            if ($sanitized_file_name !== $file_name) {
                $new_file_path = str_replace($file_name, $sanitized_file_name, $file_path);

                if (rename($file_path, $new_file_path)) {
                    update_attached_file($attachment_id, $new_file_path);

                    $metadata = wp_get_attachment_metadata($attachment_id);
                    if (!empty($metadata)) {
                        foreach ($metadata as $key => $value) {
                            if (is_string($value)) {
                                $metadata[$key] = str_replace($file_name, $sanitized_file_name, $value);
                            }
                        }
                        wp_update_attachment_metadata($attachment_id, $metadata);
                    }

                    $dir = dirname($new_file_path);
                    $image_sizes = wp_get_attachment_metadata($attachment_id);
                    if (!empty($image_sizes['sizes'])) {
                        foreach ($image_sizes['sizes'] as $size => $size_info) {
                            $size_file_name = basename($size_info['file']);
                            $sanitized_size_file_name = sanitize_file_name($size_file_name);

                            if ($sanitized_size_file_name !== $size_file_name) {
                                $old_size_path = $dir . '/' . $size_file_name;
                                $new_size_path = $dir . '/' . $sanitized_size_file_name;

                                if (file_exists($old_size_path) && rename($old_size_path, $new_size_path)) {
                                    $image_sizes['sizes'][$size]['file'] = $sanitized_size_file_name;
                                }
                            }
                        }

                        wp_update_attachment_metadata($attachment_id, $image_sizes);
                    }
                }
            }
        }
    }
}
```
- **What it does**:
  - **On Activation**: This function is triggered when the plugin is activated via `register_activation_hook`.
  - It retrieves all media attachments using a `get_posts()` query.
  - For each attachment, the file name is sanitized. If the name changes, the file is renamed on the filesystem using `rename()`.
  - After renaming the file on the server, it updates the WordPress database with the new file path using `update_attached_file()` and updates metadata (like image sizes and thumbnails) using `wp_update_attachment_metadata()`.

### How the Code Works Together:
- **New Uploads**: When a new media file is uploaded, its filename is immediately sanitized, ensuring that it follows a clean naming convention.
- **Existing Files**: When the plugin is activated, it looks through all existing media files, sanitizes their filenames, renames them on the filesystem, and updates the database and metadata accordingly.
- **Preservation of URLs**: While sanitizing file names, the plugin ensures that no media URLs or links are broken, so users and the site remain unaffected.

### Why It's Useful:
- **Security**: Removing unsafe characters from file names can prevent security issues related to file uploads.
- **Consistency**: Ensures that file names follow a consistent format, which is especially useful for organizing and managing media files.
- **SEO and Performance**: Clean file names can be more SEO-friendly and improve overall site performance, especially when handling media files for the web.