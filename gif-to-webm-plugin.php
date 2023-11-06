<?php
/*
Plugin Name: Gif to WebM plugin
Plugin URI: https://github.com/Finland93/Gif-to-WebM-plugin
Description: Convert GIF to WebM and display as a shortcode.
Version: 1.0
Author: Finland93
Author URI: https://github.com/Finland93
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if directly accessed
if (!defined('ABSPATH')) {
    exit; 
}

// Create menu page for managing shortcodes
function gif_webm_menu_page() {
    add_menu_page('GIF to WEBM Shortcodes', 'GIF to WEBM', 'manage_options', 'gif-webm-shortcodes', 'gif_webm_shortcodes_page');
}
add_action('admin_menu', 'gif_webm_menu_page');

function gif_webm_shortcodes_page() {
    // Handle form submission for shortcode creation
    if (isset($_POST['submit'])) {
        $gif_url = esc_url($_POST['gif_url']);
        $webm_url = esc_url($_POST['webm_url']);
        $video_width = intval($_POST['video_width']);
        $video_height = intval($_POST['video_height']);
        $affiliate_link = esc_url($_POST['affiliate_link']);
        $affiliate_title = sanitize_text_field($_POST['affiliate_title']);
        
        // Generate the shortcode
        $shortcode = "[gif-video id='$shortcode_id']";
        
        // Save the shortcode to the database
        $post_data = array(
            'post_title'    => 'GIF to WEBM Shortcode',
            'post_content'  => $shortcode,
            'post_status'   => 'publish',
            'post_type'     => 'gif_webm_shortcode',
        );
        
        $shortcode_id = wp_insert_post($post_data);

        // Save meta data
        update_post_meta($shortcode_id, '_gif_webm_gif_url', $gif_url);
        update_post_meta($shortcode_id, '_gif_webm_webm_url', $webm_url);
        update_post_meta($shortcode_id, '_gif_webm_video_width', $video_width);
        update_post_meta($shortcode_id, '_gif_webm_video_height', $video_height);
        update_post_meta($shortcode_id, '_gif_webm_affiliate_link', $affiliate_link);
        update_post_meta($shortcode_id, '_gif_webm_affiliate_title', $affiliate_title);

        echo "<div class='updated'><p>Shortcode has been generated and saved!</p></div>";
    }

    // Handle shortcode removal
    if (isset($_GET['delete'])) {
        $shortcode_id = intval($_GET['delete']);
        wp_delete_post($shortcode_id, true);
        echo "<div class='updated'><p>Shortcode has been deleted!</p></div>";
    }

    // Display form for adding GIF to WEBM shortcode
    ?>
    <div class="wrap">
    <h2 style="margin-bottom: 20px;">GIF to WEBM Shortcodes</h2>
    <div style="margin-bottom: 20px;">
        <p><strong>Usage:</strong> You need to upload GIF and WebM files to your media library, then use URLs for these 2 files. You can convert GIF to WebM with this <a href="https://ezgif.com/gif-to-webm/ezgif-2-abd622135b.gif" title="EZgif conversion tool">free online tool</a>.</p>
        <p><strong>Styles:</strong> CSS class for video container (DIV): <code>.bannerVideo</code> and for video/GIF output: <code>.bannerGif</code></p>
    </div>
    <form method="post" action="">
        <label for="gif_url">GIF URL:</label>
        <input type="text" id="gif_url" name="gif_url" required class="regular-text"><br><br>

        <label for="webm_url">WEBM URL:</label>
        <input type="text" id="webm_url" name="webm_url" required class="regular-text"><br><br>

        <label for="video_width">Video Width:</label>
        <input type="text" id="video_width" name="video_width" required class="small-text"><br><br>

        <label for="video_height">Video Height:</label>
        <input type="text" id="video_height" name="video_height" required class="small-text"><br><br>

        <label for="affiliate_link">Affiliate Link:</label>
        <input type="text" id="affiliate_link" name="affiliate_link" required class="regular-text"><br><br>

        <label for="affiliate_title">Affiliate Link Title:</label>
        <input type="text" id="affiliate_title" name="affiliate_title" required class="regular-text"><br><br>

        <input type="submit" name="submit" class="button button-primary" value="Generate Shortcode">
    </form>
    <p><strong>Shortcode usage:</strong> Please use this: <code>[gif-video id='1']</code> and change the video ID to the correct one below.</p>

    <?php
    // Display list of existing shortcodes
    $shortcodes = get_posts(array(
        'post_type' => 'gif_webm_shortcode',
        'posts_per_page' => -1,
    ));

    if ($shortcodes) {
        echo '<h2>Existing Shortcodes</h2>';
        echo '<ul>';
        foreach ($shortcodes as $shortcode) {
            $shortcode_id = $shortcode->ID;
            $gif_url = get_post_meta($shortcode_id, '_gif_webm_gif_url', true);
            echo '<li>ID: ' . $shortcode_id . ', GIF URL: ' . esc_url($gif_url) . ' <a href="?page=gif-webm-shortcodes&delete=' . $shortcode_id . '">Delete</a></li>';
        }
        echo '</ul>';
    }
    ?>
</div>

    <?php
}

// Enqueue JavaScript in the footer
function gif_webm_enqueue_scripts() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    var webmVideo = document.getElementById('webmVideo');
    var gifFallback = document.getElementById('gifFallback');

    if (webmVideo && gifFallback) {
        webmVideo.oncanplaythrough = function() {
            webmVideo.style.display = 'block';
            gifFallback.style.display = 'none';
        };

        webmVideo.onerror = function() {
            webmVideo.style.display = 'none';
            gifFallback.style.display = 'block';
        };
    }
});
    </script>
    <?php
}

add_action('wp_footer', 'gif_webm_enqueue_scripts');

// Custom shortcode handling
function gif_webm_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts);

    $shortcode_id = intval($atts['id']);
    $gif_url = get_post_meta($shortcode_id, '_gif_webm_gif_url', true);
    $webm_url = get_post_meta($shortcode_id, '_gif_webm_webm_url', true);
    $video_width = get_post_meta($shortcode_id, '_gif_webm_video_width', true);
    $video_height = get_post_meta($shortcode_id, '_gif_webm_video_height', true);
    $affiliate_link = get_post_meta($shortcode_id, '_gif_webm_affiliate_link', true);
    $affiliate_title = get_post_meta($shortcode_id, '_gif_webm_affiliate_title', true);

    if ($gif_url && $webm_url && $video_width && $video_height) {
        $output = '<div class="bannerVideo">';
        $output .= '<a href="' . esc_url($affiliate_link) . '" rel="sponsored nofollow" title="' . esc_attr($affiliate_title) . '">';
        $output .= '<video autoplay loop muted class="bannerGif" width="' . esc_attr($video_width) . '" height="' . esc_attr($video_height) . '">';
        $output .= '<source src="' . esc_url($webm_url) . '" type="video/webm">';
        $output .= '</video>';
        $output .= '<img class="bannerGif" src="' . esc_url($gif_url) . '" alt="' . esc_attr($affiliate_title) . '" width="' . esc_attr($video_width) . '" height="' . esc_attr($video_height) . '" style="display: none;">';
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    } else {
        return 'Invalid or missing shortcode ID or meta data'; 
    }
}
add_shortcode('gif-video', 'gif_webm_shortcode');

// Register custom post type for managing shortcodes
function gif_webm_register_shortcode_post_type() {
    register_post_type('gif_webm_shortcode', array(
        'labels' => array(
            'name' => 'GIF to WEBM Shortcodes',
            'singular_name' => 'GIF to WEBM Shortcode',
        ),
        'public' => false,
        'show_ui' => false,
    ));
}
add_action('init', 'gif_webm_register_shortcode_post_type');
