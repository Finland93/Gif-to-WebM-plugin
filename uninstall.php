<?php
/**
 * Remove all stored GIF→WebM shortcode entries (and their meta) on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$entries = get_posts(
	array(
		'post_type'      => 'gif_webm_shortcode',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $entries as $entry_id ) {
	wp_delete_post( $entry_id, true );
}
