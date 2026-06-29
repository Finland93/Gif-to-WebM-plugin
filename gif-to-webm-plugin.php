<?php
/*
Plugin Name: Gif to WebM plugin
Plugin URI: https://github.com/Finland93/Gif-to-WebM-plugin
Description: Store GIF + WebM pairs and output them as a lightweight, autoplaying WebM video with an automatic GIF fallback, via a simple shortcode.
Version: 2.0.0
Author: Finland93
Author URI: https://github.com/Finland93
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gif-to-webm-plugin
*/

// Exit if directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIF_WEBM_VERSION', '2.0.0' );
define( 'GIF_WEBM_FILE', __FILE__ );
define( 'GIF_WEBM_URL', plugin_dir_url( __FILE__ ) );

final class Gif_To_WebM {

	const CPT       = 'gif_webm_shortcode';
	const MENU_SLUG = 'gif-webm-shortcodes';

	private static $instance = null;
	private $page_hook       = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		// Register (but don't enqueue) the front-end fallback script; the
		// shortcode enqueues it on demand so it only loads where it's needed.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );

		add_shortcode( 'gif-video', array( $this, 'render_shortcode' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'gif-to-webm-plugin', false, dirname( plugin_basename( GIF_WEBM_FILE ) ) . '/languages' );
	}

	/* ---------------------------------------------------------------------
	 * Storage (custom post type, hidden from the UI)
	 * ------------------------------------------------------------------- */

	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'GIF to WebM Shortcodes', 'gif-to-webm-plugin' ),
					'singular_name' => __( 'GIF to WebM Shortcode', 'gif-to-webm-plugin' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	public function admin_menu() {
		$this->page_hook = add_menu_page(
			__( 'GIF to WEBM Shortcodes', 'gif-to-webm-plugin' ),
			__( 'GIF to WEBM', 'gif-to-webm-plugin' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-format-video'
		);

		// Process create/edit/delete BEFORE any HTML is sent, so PRG redirects
		// work cleanly (no "headers already sent").
		add_action( 'load-' . $this->page_hook, array( $this, 'handle_actions' ) );
	}

	public function admin_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}
		wp_enqueue_style( 'gif-webm-admin', GIF_WEBM_URL . 'assets/admin.css', array(), GIF_WEBM_VERSION );
	}

	private function page_url() {
		return menu_page_url( self::MENU_SLUG, false );
	}

	/** All mutating actions happen here, before output. */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ---- Create or update ----
		if ( isset( $_POST['gif_webm_submit'] ) ) {
			check_admin_referer( 'gif_webm_save', 'gif_webm_nonce' );

			$entry_id = absint( $_POST['entry_id'] ?? 0 );
			$fields   = array(
				'_gif_webm_gif_url'         => esc_url_raw( wp_unslash( $_POST['gif_url'] ?? '' ) ),
				'_gif_webm_webm_url'        => esc_url_raw( wp_unslash( $_POST['webm_url'] ?? '' ) ),
				'_gif_webm_video_width'     => absint( $_POST['video_width'] ?? 0 ),
				'_gif_webm_video_height'    => absint( $_POST['video_height'] ?? 0 ),
				'_gif_webm_affiliate_link'  => esc_url_raw( wp_unslash( $_POST['affiliate_link'] ?? '' ) ),
				'_gif_webm_affiliate_title' => sanitize_text_field( wp_unslash( $_POST['affiliate_title'] ?? '' ) ),
			);

			// Need at least one media URL.
			if ( '' === $fields['_gif_webm_gif_url'] && '' === $fields['_gif_webm_webm_url'] ) {
				wp_safe_redirect( add_query_arg( 'msg', 'nomedia', $this->page_url() ) );
				exit;
			}

			// Update an existing entry, or create a new one.
			if ( $entry_id ) {
				$post = get_post( $entry_id );
				if ( ! $post || self::CPT !== $post->post_type ) {
					$entry_id = 0;
				}
			}

			if ( ! $entry_id ) {
				$entry_id = wp_insert_post(
					array(
						'post_title'  => 'GIF to WebM',
						'post_status' => 'publish',
						'post_type'   => self::CPT,
					),
					true
				);
			}

			if ( is_wp_error( $entry_id ) || ! $entry_id ) {
				wp_safe_redirect( add_query_arg( 'msg', 'error', $this->page_url() ) );
				exit;
			}

			foreach ( $fields as $key => $value ) {
				update_post_meta( $entry_id, $key, $value );
			}

			// Now that we have the real ID, store a correct title + shortcode.
			// (The 1.0 bug saved an empty id here because $shortcode_id was used
			// before wp_insert_post() had returned it.)
			wp_update_post(
				array(
					'ID'           => $entry_id,
					'post_title'   => 'GIF to WebM #' . $entry_id,
					'post_content' => "[gif-video id='" . $entry_id . "']",
				)
			);

			wp_safe_redirect( add_query_arg( 'msg', 'saved', $this->page_url() ) );
			exit;
		}

		// ---- Delete (nonce-protected; 1.0 deleted via an unprotected GET link) ----
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = absint( $_GET['id'] );
			check_admin_referer( 'gif_webm_delete_' . $id );
			if ( $id ) {
				$post = get_post( $id );
				if ( $post && self::CPT === $post->post_type ) {
					wp_delete_post( $id, true );
				}
			}
			wp_safe_redirect( add_query_arg( 'msg', 'deleted', $this->page_url() ) );
			exit;
		}
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gif-to-webm-plugin' ) );
		}

		// Notices.
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';
		$map = array(
			'saved'   => array( 'success', __( 'Shortcode saved.', 'gif-to-webm-plugin' ) ),
			'deleted' => array( 'success', __( 'Shortcode deleted.', 'gif-to-webm-plugin' ) ),
			'nomedia' => array( 'error', __( 'Please provide at least a WebM or a GIF URL.', 'gif-to-webm-plugin' ) ),
			'error'   => array( 'error', __( 'Could not save the shortcode.', 'gif-to-webm-plugin' ) ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}

		// Are we editing an existing entry?
		$edit_id = 0;
		$edit    = array(
			'gif_url'         => '',
			'webm_url'        => '',
			'video_width'     => '',
			'video_height'    => '',
			'affiliate_link'  => '',
			'affiliate_title' => '',
		);
		if ( isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'] ) {
			$edit_id = absint( $_GET['id'] );
			$post    = get_post( $edit_id );
			if ( $post && self::CPT === $post->post_type ) {
				$edit['gif_url']         = get_post_meta( $edit_id, '_gif_webm_gif_url', true );
				$edit['webm_url']        = get_post_meta( $edit_id, '_gif_webm_webm_url', true );
				$edit['video_width']     = get_post_meta( $edit_id, '_gif_webm_video_width', true );
				$edit['video_height']    = get_post_meta( $edit_id, '_gif_webm_video_height', true );
				$edit['affiliate_link']  = get_post_meta( $edit_id, '_gif_webm_affiliate_link', true );
				$edit['affiliate_title'] = get_post_meta( $edit_id, '_gif_webm_affiliate_title', true );
			} else {
				$edit_id = 0;
			}
		}
		?>
		<div class="wrap gif-webm-admin">
			<h1><?php esc_html_e( 'GIF to WebM Shortcodes', 'gif-to-webm-plugin' ); ?></h1>

			<div class="gif-webm-help">
				<p><strong><?php esc_html_e( 'How it works:', 'gif-to-webm-plugin' ); ?></strong>
					<?php esc_html_e( 'Upload your GIF and a converted WebM to the Media Library, paste both URLs below, and place the generated shortcode in any post or page. Visitors get the small WebM video, falling back to the GIF automatically if WebM can\'t play.', 'gif-to-webm-plugin' ); ?>
					<a href="https://ezgif.com/gif-to-webm" target="_blank" rel="noopener"><?php esc_html_e( 'Free GIF→WebM converter', 'gif-to-webm-plugin' ); ?></a>.
				</p>
				<p><strong><?php esc_html_e( 'Styling:', 'gif-to-webm-plugin' ); ?></strong>
					<?php
					printf(
						/* translators: 1: container CSS class, 2: media CSS class */
						esc_html__( 'Container: %1$s — video/image: %2$s', 'gif-to-webm-plugin' ),
						'<code>.bannerVideo</code>',
						'<code>.bannerGif</code>'
					);
					?>
				</p>
			</div>

			<h2><?php echo $edit_id ? esc_html__( 'Edit shortcode', 'gif-to-webm-plugin' ) : esc_html__( 'Add new shortcode', 'gif-to-webm-plugin' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'gif_webm_save', 'gif_webm_nonce' ); ?>
				<input type="hidden" name="entry_id" value="<?php echo esc_attr( $edit_id ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="webm_url"><?php esc_html_e( 'WebM URL', 'gif-to-webm-plugin' ); ?></label></th>
						<td><input type="url" id="webm_url" name="webm_url" value="<?php echo esc_attr( $edit['webm_url'] ); ?>" class="regular-text" placeholder="https://…/animation.webm"></td>
					</tr>
					<tr>
						<th scope="row"><label for="gif_url"><?php esc_html_e( 'GIF URL (fallback)', 'gif-to-webm-plugin' ); ?></label></th>
						<td><input type="url" id="gif_url" name="gif_url" value="<?php echo esc_attr( $edit['gif_url'] ); ?>" class="regular-text" placeholder="https://…/animation.gif"></td>
					</tr>
					<tr>
						<th scope="row"><label for="video_width"><?php esc_html_e( 'Width (px)', 'gif-to-webm-plugin' ); ?></label></th>
						<td><input type="number" min="0" step="1" id="video_width" name="video_width" value="<?php echo esc_attr( $edit['video_width'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="video_height"><?php esc_html_e( 'Height (px)', 'gif-to-webm-plugin' ); ?></label></th>
						<td><input type="number" min="0" step="1" id="video_height" name="video_height" value="<?php echo esc_attr( $edit['video_height'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="affiliate_link"><?php esc_html_e( 'Link URL (optional)', 'gif-to-webm-plugin' ); ?></label></th>
						<td><input type="url" id="affiliate_link" name="affiliate_link" value="<?php echo esc_attr( $edit['affiliate_link'] ); ?>" class="regular-text" placeholder="https://…"></td>
					</tr>
					<tr>
						<th scope="row"><label for="affiliate_title"><?php esc_html_e( 'Link / alt text (optional)', 'gif-to-webm-plugin' ); ?></label></th>
						<td><input type="text" id="affiliate_title" name="affiliate_title" value="<?php echo esc_attr( $edit['affiliate_title'] ); ?>" class="regular-text"></td>
					</tr>
				</table>
				<?php submit_button( $edit_id ? __( 'Update Shortcode', 'gif-to-webm-plugin' ) : __( 'Add Shortcode', 'gif-to-webm-plugin' ), 'primary', 'gif_webm_submit' ); ?>
				<?php if ( $edit_id ) : ?>
					<a href="<?php echo esc_url( $this->page_url() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'gif-to-webm-plugin' ); ?></a>
				<?php endif; ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Existing shortcodes', 'gif-to-webm-plugin' ); ?></h2>
			<?php
			$entries = get_posts(
				array(
					'post_type'      => self::CPT,
					'posts_per_page' => 100,
					'orderby'        => 'ID',
					'order'          => 'DESC',
				)
			);

			if ( ! $entries ) {
				echo '<p>' . esc_html__( 'No shortcodes yet.', 'gif-to-webm-plugin' ) . '</p>';
			} else {
				echo '<table class="wp-list-table widefat fixed striped">';
				echo '<thead><tr>';
				echo '<th>' . esc_html__( 'Preview', 'gif-to-webm-plugin' ) . '</th>';
				echo '<th>' . esc_html__( 'Shortcode', 'gif-to-webm-plugin' ) . '</th>';
				echo '<th>' . esc_html__( 'Actions', 'gif-to-webm-plugin' ) . '</th>';
				echo '</tr></thead><tbody>';

				foreach ( $entries as $entry ) {
					$id       = $entry->ID;
					$gif      = get_post_meta( $id, '_gif_webm_gif_url', true );
					$webm     = get_post_meta( $id, '_gif_webm_webm_url', true );
					$preview  = $gif ? $gif : '';
					$edit_url = add_query_arg(
						array(
							'page'   => self::MENU_SLUG,
							'action' => 'edit',
							'id'     => $id,
						),
						admin_url( 'admin.php' )
					);
					$del_url  = wp_nonce_url(
						add_query_arg(
							array(
								'page'   => self::MENU_SLUG,
								'action' => 'delete',
								'id'     => $id,
							),
							admin_url( 'admin.php' )
						),
						'gif_webm_delete_' . $id
					);

					echo '<tr>';
					echo '<td>';
					if ( $preview ) {
						echo '<img src="' . esc_url( $preview ) . '" alt="" style="max-width:80px;max-height:60px;height:auto;">';
					} elseif ( $webm ) {
						echo '<span class="dashicons dashicons-format-video"></span>';
					}
					echo '</td>';
					echo '<td><input type="text" readonly class="code" style="width:100%;max-width:220px;" value="' . esc_attr( "[gif-video id='" . $id . "']" ) . '" onclick="this.select();"></td>';
					echo '<td>';
					echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Edit', 'gif-to-webm-plugin' ) . '</a> ';
					echo '<a href="' . esc_url( $del_url ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Delete this shortcode?', 'gif-to-webm-plugin' ) ) . '\');">' . esc_html__( 'Delete', 'gif-to-webm-plugin' ) . '</a>';
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
			?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Front-end
	 * ------------------------------------------------------------------- */

	public function register_frontend_assets() {
		wp_register_script(
			'gif-webm-fallback',
			GIF_WEBM_URL . 'assets/gif-webm-fallback.js',
			array(),
			GIF_WEBM_VERSION,
			true
		);
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'gif-video' );
		$id   = absint( $atts['id'] );
		if ( ! $id ) {
			return '';
		}

		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return '';
		}

		$gif   = get_post_meta( $id, '_gif_webm_gif_url', true );
		$webm  = get_post_meta( $id, '_gif_webm_webm_url', true );
		$w     = absint( get_post_meta( $id, '_gif_webm_video_width', true ) );
		$h     = absint( get_post_meta( $id, '_gif_webm_video_height', true ) );
		$link  = get_post_meta( $id, '_gif_webm_affiliate_link', true );
		$title = get_post_meta( $id, '_gif_webm_affiliate_title', true );

		if ( ! $webm && ! $gif ) {
			return '';
		}

		// Only load the fallback script when WebM is present (it's pointless for
		// a GIF-only entry).
		if ( $webm ) {
			wp_enqueue_script( 'gif-webm-fallback' );
		}

		$dim = '';
		if ( $w ) {
			$dim .= ' width="' . esc_attr( $w ) . '"';
		}
		if ( $h ) {
			$dim .= ' height="' . esc_attr( $h ) . '"';
		}

		$media = '';
		if ( $webm ) {
			$media .= '<video class="bannerGif" autoplay loop muted playsinline preload="metadata"' . $dim . '>';
			$media .= '<source src="' . esc_url( $webm ) . '" type="video/webm">';
			$media .= '</video>';
		}
		if ( $gif ) {
			$style  = $webm ? ' style="display:none"' : '';
			$media .= '<img class="bannerGif" src="' . esc_url( $gif ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async"' . $dim . $style . '>';
		}

		if ( $link ) {
			$title_attr = $title ? ' title="' . esc_attr( $title ) . '"' : '';
			$media      = '<a href="' . esc_url( $link ) . '" rel="sponsored nofollow noopener" target="_blank"' . $title_attr . '>' . $media . '</a>';
		}

		return '<div class="bannerVideo gif-webm">' . $media . '</div>';
	}
}

Gif_To_WebM::instance();
