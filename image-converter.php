<?php
/*
Plugin Name: MiniImgMix
Description: A WordPress plugin to convert and compress images.
Version: 1.7
Author: BrianCodeDev
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add Admin Menu and Page
add_action('admin_menu', 'image_converter_menu');

function image_converter_menu() {
    add_menu_page(
        'Image Converter',
        'Image Converter',
        'manage_options',
        'image-converter',
        'image_converter_page',
        'dashicons-format-image',
        20
    );
}

function image_converter_page() {
    ?>
    <div class="wrap">
        <h1>Image Converter</h1>
        <form id="image-converter-form" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="image_file">Select image to convert:</label>
                <input type="file" name="image_file" id="image_file" required>
            </div>
            <div class="form-group">
                <label for="source">Image source (optional):</label>
                <input type="text" name="source" id="source" placeholder="Enter source URL or name">
            </div>
            <div class="form-group">
                <label for="convert_to">Convert to:</label>
                <select name="convert_to" id="convert_to">
                    <option value="jpeg">JPEG</option>
                    <option value="png">PNG</option>
                    <option value="gif">GIF</option>
                    <option value="webp">WebP</option>
                    <option value="avif">AVIF</option>
                    <option value="svg">SVG</option>
                </select>
            </div>
            <div class="form-group">
                <label for="size">Max size (MB):</label>
                <select name="size" id="size">
                    <option value="1">1 MB</option>
                    <option value="2">2 MB</option>
                    <option value="5">5 MB</option>
                    <option value="10">10 MB</option>
                </select>
            </div>
            <input type="submit" name="convert_image" value="Convert Image" class="button button-primary">
        </form>
        <div id="conversion-result"></div>
        <h2>Conversion History</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Original Image</th>
                    <th>Converted Format</th>
                    <th>Source</th>
                    <th>Download Link</th>
                </tr>
            </thead>
            <tbody id="conversion-history">
                <?php display_conversion_history(); ?>
            </tbody>
        </table>
        <h2>Statistics</h2>
        <div class="charts-container">
            <canvas id="uploadChart" width="200" height="100"></canvas>
            <canvas id="seoChart" width="200" height="100"></canvas>
        </div>
    </div>
    <?php
    enqueue_chart_js();
}


// Handle Image Conversion
if (isset($_POST['convert_image'])) {
    function handle_image_conversion() {
        if (!empty($_FILES['image_file']['tmp_name'])) {
            $image_file = $_FILES['image_file'];
            $convert_to = sanitize_text_field($_POST['convert_to']);
            $source = sanitize_text_field($_POST['source']);
            $size_mb = intval($_POST['size']);
            $image_info = getimagesize($image_file['tmp_name']);
            $image_type = $image_info[2];
            $upload_dir = wp_upload_dir();
            $output_file = $upload_dir['path'] . '/converted-image.' . $convert_to;
    
            // Calculate compression quality based on desired size
            $compression_quality = calculate_compression_quality($size_mb, $image_file['tmp_name'], $convert_to);
    
            if ($convert_to === 'svg') {
                if (move_uploaded_file($image_file['tmp_name'], $output_file)) {
                    $attachment = array(
                        'guid' => $upload_dir['url'] . '/converted-image.' . $convert_to,
                        'post_mime_type' => 'image/svg+xml',
                        'post_title' => 'Converted SVG Image',
                        'post_content' => 'This is a converted SVG image.',
                        'post_excerpt' => 'Converted SVG image from ' . $image_file['name'],
                        'post_status' => 'inherit'
                    );
    
                    $attachment_id = wp_insert_attachment($attachment, $output_file);
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attachment_id, $output_file);
                    wp_update_attachment_metadata($attachment_id, $attach_data);
    
                    save_conversion_history($image_file['name'], 'SVG', $source, $attachment_id);
                    update_upload_statistics();
    
                    echo '<div id="conversion-result">';
                    echo '<p>SVG uploaded successfully!</p>';
                    echo '<p><a href="' . wp_get_attachment_url($attachment_id) . '">Download SVG image</a></p>';
                    echo '<p><strong>SEO Feedback:</strong> SVG images are not generally recommended for SEO purposes.</p>';
                    echo '</div>';
                } else {
                    echo 'Failed to upload SVG file.';
                }
                return;
            }
    
            switch ($image_type) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($image_file['tmp_name']);
                    imagejpeg($image, $output_file, $compression_quality);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($image_file['tmp_name']);
                    imagepng($image, $output_file, $compression_quality);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($image_file['tmp_name']);
                    imagegif($image, $output_file);
                    break;
                case IMAGETYPE_WEBP:
                    $image = imagecreatefromwebp($image_file['tmp_name']);
                    imagewebp($image, $output_file, $compression_quality);
                    break;
                case IMAGETYPE_AVIF:
                    $image = imagecreatefromavif($image_file['tmp_name']);
                    imageavif($image, $output_file);
                    break;
                default:
                    echo 'Unsupported image type!';
                    return;
            }
    
            imagedestroy($image);
    
            $image_name = pathinfo($image_file['name'], PATHINFO_FILENAME);
            $generated_title = 'Converted ' . strtoupper($convert_to) . ' Image';
            $generated_alt_text = 'Converted image of ' . $image_name;
            $generated_content = 'This image was converted from ' . $image_file['name'] . ' to ' . strtoupper($convert_to) . ' format.';
            $generated_excerpt = 'Converted image of ' . $image_name;
    
            $attachment = array(
                'guid' => $upload_dir['url'] . '/converted-image.' . $convert_to,
                'post_mime_type' => 'image/' . $convert_to,
                'post_title' => $generated_title,
                'post_content' => $generated_content,
                'post_excerpt' => $generated_excerpt,
                'post_status' => 'inherit'
            );
    
            $attachment_id = wp_insert_attachment($attachment, $output_file);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $output_file);
            wp_update_attachment_metadata($attachment_id, $attach_data);
    
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $generated_alt_text);
    
            save_conversion_history($image_file['name'], strtoupper($convert_to), $source, $attachment_id);
            update_upload_statistics();
    
            echo '<div id="conversion-result">';
            echo '<p>Image converted successfully!</p>';
            echo '<p><a href="' . wp_get_attachment_url($attachment_id) . '">Download converted image</a></p>';
            echo '<p><strong>SEO Feedback:</strong> ' . (in_array($convert_to, ['jpeg', 'png', 'gif']) ? 'The format you selected is SEO-friendly.' : 'The format you selected is less optimal for SEO.') . '</p>';
            echo '</div>';
        } else {
            echo 'No image file uploaded!';
        }
    }
    
    // Calculate Compression Quality
    function calculate_compression_quality($max_size_mb, $image_path, $format) {
        $image_size = filesize($image_path) / 1024 / 1024; // Size in MB
        $desired_size = $max_size_mb * 1024 * 1024; // Desired size in bytes
    
        $quality = 75; // Default quality
    
        if ($image_size > $max_size_mb) {
            $quality = max(10, min(100, intval((100 * $desired_size) / filesize($image_path))));
        }
    
        if ($format === 'png') {
            $quality = max(0, min(9, round($quality / 10)));
        }
    
        return $quality;
    }
    

    add_action('admin_notices', 'handle_image_conversion');
}

// Save Conversion History
function save_conversion_history($original_image, $converted_format, $source, $attachment_id) {
    $history = get_option('image_converter_history', array());

    $history[] = array(
        'original_image' => $original_image,
        'converted_format' => $converted_format,
        'source' => $source,
        'attachment_id' => $attachment_id,
        'download_link' => wp_get_attachment_url($attachment_id)
    );

    update_option('image_converter_history', $history);
}

// Display Conversion History
function display_conversion_history() {
    $history = get_option('image_converter_history', array());

    foreach ($history as $item) {
        echo '<tr>';
        echo '<td>' . esc_html($item['original_image']) . '</td>';
        echo '<td>' . esc_html($item['converted_format']) . '</td>';
        echo '<td>' . esc_html($item['source']) . '</td>';
        echo '<td><a href="' . esc_url($item['download_link']) . '">Download</a></td>';
        echo '</tr>';
    }
}

// Update Upload Statistics
function update_upload_statistics() {
    $statistics = get_option('image_converter_statistics', array());
    $today = date('Y-m-d');

    if (!isset($statistics[$today])) {
        $statistics[$today] = 0;
    }

    $statistics[$today]++;

    update_option('image_converter_statistics', $statistics);
}

// Enqueue Styles and Scripts
add_action('admin_enqueue_scripts', 'image_converter_styles');

function image_converter_styles() {
    wp_enqueue_style('image-converter-styles', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery'), null, true);
    wp_enqueue_script('image-converter-scripts', plugin_dir_url(__FILE__) . 'scripts.js', array('chart-js'), null, true);
}

function enqueue_chart_js() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var ctxUpload = document.getElementById('uploadChart').getContext('2d');
            var ctxSEO = document.getElementById('seoChart').getContext('2d');
            
            var chartDataUpload = <?php echo json_encode(get_upload_statistics()); ?>;
            var labelsUpload = Object.keys(chartDataUpload);
            var dataUpload = Object.values(chartDataUpload);

            var uploadChart = new Chart(ctxUpload, {
                type: 'line',
                data: {
                    labels: labelsUpload,
                    datasets: [{
                        label: 'Uploads Per Day',
                        data: dataUpload,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        x: {
                            beginAtZero: true
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            var chartDataSEO = <?php echo json_encode(get_seo_statistics()); ?>;
            var labelsSEO = Object.keys(chartDataSEO);
            var dataSEO = Object.values(chartDataSEO);

            // Determine color based on SEO friendliness
            var backgroundColors = dataSEO.map(function(value, index) {
                var format = labelsSEO[index];
                if (format === 'JPEG' || format === 'PNG' || format === 'GIF') {
                    return 'rgba(255, 99, 132, 0.2)'; // Green for SEO-friendly formats
                } else {
                    return 'rgba(75, 192, 192, 0.2)'; // Red for non-SEO-friendly formats
                }
            });

            var seoChart = new Chart(ctxSEO, {
                type: 'bar',
                data: {
                    labels: labelsSEO,
                    datasets: [{
                        label: 'SEO-Friendly Formats',
                        data: dataSEO,
                        backgroundColor: backgroundColors,
                        borderColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        x: {
                            beginAtZero: true
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
    <?php
}

function get_upload_statistics() {
    return get_option('image_converter_statistics', array());
}

function get_seo_statistics() {
    $history = get_option('image_converter_history', array());
    $seo_counts = array(
        'JPEG' => 0,
        'PNG' => 0,
        'GIF' => 0,
        'WebP' => 0,
        'AVIF' => 0,
        'SVG' => 0
    );

    foreach ($history as $item) {
        $format = $item['converted_format'];
        if (isset($seo_counts[$format])) {
            $seo_counts[$format]++;
        }
    }

    return $seo_counts;
}
