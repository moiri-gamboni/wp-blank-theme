<?php
/**
 * WP-Blank-Theme functions and definitions.
 *
 * This file is read by WordPress to setup the theme and his additional
 * features.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package   WP-Blank-Theme
 * @author    Armand Philippot <contact@armandphilippot.com>
 * @copyright 2022 Armand Philippot
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

/**
 * Currently theme version.
 */
define( 'WPBT_VERSION', '1.0.2' );

if ( ! function_exists( 'wpbt_setup' ) ) {
	/**
	 * Setup WP-Blank-Theme theme and registers support for various WordPress
	 * features.
	 *
	 * @since 1.0.0
	 */
	function wpbt_setup() {
		// Add support for full and wide align images.
		add_theme_support( 'align-wide' );

		// Add support for custom logo.
		add_theme_support(
			'custom-logo',
			array(
				'width'       => 150,
				'height'      => 150,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		// Enable support for Post Thumbnails on posts and pages.
		add_theme_support( 'post-thumbnails' );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Let WordPress manage the document title.
		add_theme_support( 'title-tag' );
	}
}
add_action( 'after_setup_theme', 'wpbt_setup' );

/**
 * Redirect to a new URL.
 *
 * @since 1.0.0
 *
 * @param string  $url The new URL.
 * @param integer $status_code The status code.
 * @return void
 */
function wpbt_redirect( $url, $status_code = 303 ) {
	header( 'Location: ' . sanitize_url( $url ), true, $status_code );
	die();
}

/**
 * Load the REDIRECTION_URL environment variable.
 *
 * @since 1.0.2
 *
 * @return boolean Returns true on success or false on failure.
 */
function wpbt_load_redirect_url() {
	if ( defined( 'WPBT_REDIRECTION_URL' ) ) {
		return true;
	}
	$dotenv_path = get_template_directory() . '/.env';
	if ( ! file_exists( $dotenv_path ) ) {
		return false;
	}
	$dotenv = parse_ini_file( $dotenv_path );
	if ( isset( $dotenv['REDIRECTION_URL'] ) ) {
		define( 'WPBT_REDIRECTION_URL', $dotenv['REDIRECTION_URL'] );
		return true;
	}
	return false;
}

if ( ! function_exists( 'wpbt_add_video_post_type' ) ) {
	/**
	 * Add a video post type which has no editor
	 */
	function wpbt_add_video_post_type() {
		$labels   = array(
			'name'               => 'Videos',
			'singular_name'      => 'Video',
			'menu_name'          => 'Videos',
		);
			$args = array(
				'labels'              => $labels,
				'description'         => 'Holds links to videos embedded on the Resources page',
				'public'              => true,
				'menu_position'       => 5,
				'supports'            => array( 'excerpt', 'custom_fields' ),
				'has_archive'         => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'video',
				'graphql_plural_name' => 'videos',
			);
			register_post_type( 'video', $args );
	}
}
add_action( 'init', 'wpbt_add_video_post_type' );

if ( ! function_exists( 'wpbt_add_video_url_meta_box' ) ) {
	/**
	 * Add a meta box (custom field) to videos to save the url
	 */
	function wpbt_add_video_url_meta_box() {
			add_meta_box(
				'video_url',
				'Video URL',
				'wpbt_render_video_url_input',
				'video',
				'normal',
				'default'
			);
	}
}
add_action( 'add_meta_boxes', 'wpbt_add_video_url_meta_box' );

/**
 * Callback to render the meta box
 *
 * @param WP_Post $post Current post object.
 */
function wpbt_render_video_url_input( $post ) {
	$watch_url = get_post_meta( $post->ID, 'watch_url', true );
	?>   
		<style scoped>
			#watch_url{
					width: 100%
			}
	</style>
	<label for="watch_url">
		The video's URL. Do not use the embed code or embed url!
		For example: 
		<a href="https://www.youtube.com/watch?v=NHaLK_NI_c8">https://www.youtube.com/watch?v=NHaLK_NI_c8</a> 
		or 
		<a href="https://youtu.be/NHaLK_NI_c8">https://youtu.be/NHaLK_NI_c8</a> 
		are valid urls for this field.
	</label>
	<input type="text" id="watch_url" name="watch_url" value="<?php echo esc_attr( $watch_url ); ?>">
	<?php
	wp_nonce_field( 'save-video-_' . $post->ID, 'save-video-nonce' );
}

if ( ! function_exists( 'wpbt_save_video' ) ) {
	/**
	 * Save the text input to the post meta
	 *
	 * @param int $post_id Post ID.
	 */
	function wpbt_save_video( $post_id ) {
		if ( ! wp_is_post_revision( $post_id )
			&& isset( $_POST['watch_url'] )
			&& isset( $_POST['save-video-nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-video-nonce'] ) ), 'save-video-_' . $post_id )
		) {
			$watch_url = sanitize_text_field( wp_unslash( $_POST['watch_url'] ) );

			$iframe_html = wp_oembed_get( $watch_url );
			$iframe_doc  = new DOMDocument();
			$iframe_doc->loadHTML( $iframe_html );
			$iframe_xpath = new DOMXPath( $iframe_doc );
			$iframe_src   = $iframe_xpath->query( '//iframe/@src' )[0]->nodeValue;
			$iframe_title = $iframe_xpath->query( '//iframe/@title' )[0]->nodeValue;

			update_post_meta( $post_id, 'watch_url', $watch_url );
			update_post_meta( $post_id, 'embed_url', $iframe_src );

			$post_data = array(
				'ID'         => $post_id,
				'post_title' => $iframe_title,
				'post_name'  => sanitize_title( $iframe_title ),
			);
			// unhook this function so it doesn't loop infinitely.
			remove_action( 'save_post_video', 'wpbt_save_video' );

			// update the post, which calls save_post again.
			wp_update_post( $post_data );

			// re-hook this function.
			add_action( 'save_post_video', 'wpbt_save_video' );
		}
	}
}
add_action( 'save_post_video', 'wpbt_save_video' );

if ( ! function_exists( 'wpbt_register_graphql_video_fields' ) ) {
	/**
	 * Register the custom field with graphql
	 */
	function wpbt_register_graphql_video_fields() {
		register_graphql_field(
			'Video',
			'title',
			array(
				'type'        => 'String',
				'description' => 'Video title',
				'resolve'     => function ( $post ) {
					return get_the_title( $post->ID );
				},
			)
		);
		register_graphql_field(
			'Video',
			'watch_url',
			array(
				'type'        => 'String',
				'description' => 'Video watch url',
				'resolve'     => function ( $post ) {
					return get_post_meta( $post->ID, 'watch_url', true );
				},
			)
		);
		register_graphql_field(
			'Video',
			'embed_url',
			array(
				'type'        => 'String',
				'description' => 'Video embed url',
				'resolve'     => function ( $post ) {
					return get_post_meta( $post->ID, 'embed_url', true );
				},
			)
		);
	}
}
add_action( 'graphql_register_types', 'wpbt_register_graphql_video_fields' );
