<?php
/**
 * Plugin Name:       StoryMap
 * Description:       Create storymaps with an interactive OpenStreetMap map.
 * Version:           3.2
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            Keepyourphoto
 * Author URI: 	      https://keepyourphoto.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-storymap
 *
 * @package CreateBlock
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

define('WP_STORYMAP_PLUGIN_FILE', __FILE__);
require_once __DIR__ . '/includes/story-records.php';
require_once __DIR__ . '/includes/point-admin.php';
require_once __DIR__ . '/includes/upgrade-notice.php';

/**
 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function create_block_wp_storymap_block_init()
{
	wp_register_block_types_from_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
}
add_action('init', 'create_block_wp_storymap_block_init');

/**
 * Loads the Storymap editor script on every Storymap, even when its map block is absent.
 */
function wp_storymap_enqueue_story_editor_script()
{
	$screen = get_current_screen();
	if (!$screen || 'wp_storymap' !== $screen->post_type) {
		return;
	}

	$script_handle = generate_block_asset_handle('wp-storymap/map', 'editorScript');
	if (wp_script_is($script_handle, 'registered')) {
		wp_enqueue_script($script_handle);
	}
}
add_action('enqueue_block_editor_assets', 'wp_storymap_enqueue_story_editor_script');

/**
 * Registers storymaps as Gutenberg-editable content.
 */
function wp_storymap_register_story_post_type()
{
	$labels = array(
		'name' => __('Storymaps', 'wp-storymap'),
		'singular_name' => __('Storymap', 'wp-storymap'),
		'menu_name' => __('Storymaps', 'wp-storymap'),
		'add_new' => __('Add New', 'wp-storymap'),
		'add_new_item' => __('Add New Storymap', 'wp-storymap'),
		'edit_item' => __('Edit Storymap', 'wp-storymap'),
		'new_item' => __('New Storymap', 'wp-storymap'),
		'view_item' => __('View Storymap', 'wp-storymap'),
		'all_items' => __('All Storymaps', 'wp-storymap'),
		'search_items' => __('Search Storymaps', 'wp-storymap'),
		'not_found' => __('No storymaps found.', 'wp-storymap'),
		'not_found_in_trash' => __('No storymaps found in Trash.', 'wp-storymap'),
	);

	register_post_type(
		'wp_storymap',
		array(
			'labels' => $labels,
			'public' => true,
			'show_in_rest' => true,
			'has_archive' => false,
			'rewrite' => array(
				'slug' => 'storymap',
				'with_front' => false,
			),
			'menu_icon' => 'dashicons-location-alt',
			'supports' => array('title', 'editor', 'custom-fields'),
			'template' => array(
				array('wp-storymap/map'),
			),
		)
	);
}
add_action('init', 'wp_storymap_register_story_post_type');

/**
 * Displays the Storymap block when a story has no map block in its content.
 *
 * @param string $content The post content.
 * @return string
 */
function wp_storymap_add_map_to_story_content($content)
{
	if (
		!is_singular('wp_storymap') ||
		!in_the_loop() ||
		!is_main_query() ||
		has_block('wp-storymap/map', $content)
	) {
		return $content;
	}

	return $content . "\n<!-- wp:wp-storymap/map /-->";
}
add_filter('the_content', 'wp_storymap_add_map_to_story_content', 8);

/**
 * Registers Storymap settings as REST API-enabled post meta.
 */
function wp_storymap_register_story_meta()
{
	$meta_args = array(
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => 'wp_storymap_can_edit_meta',
	);

	register_post_meta(
		'wp_storymap',
		'wp_storymap_basemap',
		array_merge(
			$meta_args,
			array(
				'type' => 'string',
				'default' => 'osm',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array('osm', 'satellite', 'hybrid', 'relief', 'cyclemap', 'worldstreetmap'),
					),
				),
			)
		)
	);

	register_post_meta(
		'wp_storymap',
		'wp_storymap_arrow_color',
		array_merge(
			$meta_args,
			array(
				'type' => 'string',
				'default' => '#000000',
				'sanitize_callback' => 'sanitize_hex_color',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'string',
						'pattern' => '^#(?:[A-Fa-f0-9]{3}){1,2}$',
					),
				),
			)
		)
	);

	register_post_meta(
		'wp_storymap',
		'wp_storymap_story_number',
		array_merge(
			$meta_args,
			array(
				'type' => 'integer',
				'default' => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'integer',
						'minimum' => 0,
						'readonly' => true,
					),
				),
			)
		)
	);

	register_post_meta(
		'wp_storymap',
		'wp_storymap_description',
		array_merge(
			$meta_args,
			array(
				'type' => 'string',
				'default' => '',
				'sanitize_callback' => 'wp_kses_post',
			)
		)
	);

	register_post_meta(
		'wp_storymap',
		'wp_storymap_image',
		array_merge(
			$meta_args,
			array(
				'type' => 'integer',
				'default' => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'integer',
						'minimum' => 0,
					),
				),
			)
		)
	);

	foreach (array('height', 'width') as $dimension) {
		$is_width = 'width' === $dimension;
		register_post_meta(
			'wp_storymap',
			'wp_storymap_' . $dimension,
			array_merge(
				$meta_args,
				array(
					'type' => 'integer',
					'default' => $is_width ? 0 : 500,
					'sanitize_callback' => 'absint',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'integer',
							'minimum' => $is_width ? 0 : 1,
						),
					),
				)
			)
		);
	}

	register_post_meta(
		'wp_storymap',
		'wp_storymap_full_bleed',
		array_merge(
			$meta_args,
			array(
				'type' => 'boolean',
				'default' => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'boolean',
					),
				),
			)
		)
	);
}
add_action('init', 'wp_storymap_register_story_meta');

/**
 * Adds the rich-text description editor to the Storymap editor.
 */
function wp_storymap_add_description_meta_box()
{
	add_meta_box(
		'wp-storymap-description',
		__('Story description', 'wp-storymap'),
		'wp_storymap_render_description_meta_box',
		'wp_storymap',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes_wp_storymap', 'wp_storymap_add_description_meta_box');

/**
 * Renders the Storymap description using the WordPress rich-text editor.
 *
 * @param WP_Post $post Current Storymap post.
 */
function wp_storymap_render_description_meta_box($post)
{
	wp_nonce_field('wp_storymap_save_description', 'wp_storymap_description_nonce');
	wp_editor(
		get_post_meta($post->ID, 'wp_storymap_description', true),
		'wp_storymap_description',
		array(
			'textarea_name' => 'wp_storymap_description',
			'textarea_rows' => 6,
			'media_buttons' => false,
			'teeny' => true,
		)
	);
}

/**
 * Saves the rich-text Storymap description.
 *
 * @param int $post_id Storymap post ID.
 */
function wp_storymap_save_description($post_id)
{
	if (
		!isset($_POST['wp_storymap_description_nonce']) ||
		!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_POST['wp_storymap_description_nonce'])),
			'wp_storymap_save_description'
		) ||
		(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
		wp_is_post_revision($post_id) ||
		!current_user_can('edit_post', $post_id) ||
		!isset($_POST['wp_storymap_description']) ||
		!is_string($_POST['wp_storymap_description'])
	) {
		return;
	}

	update_post_meta(
		$post_id,
		'wp_storymap_description',
		wp_kses_post(wp_unslash($_POST['wp_storymap_description']))
	);
}
add_action('save_post_wp_storymap', 'wp_storymap_save_description', 30);

/**
 * Restricts Storymap meta editing to users who can edit the post.
 *
 * @param bool   $allowed  Whether the user can edit the meta value.
 * @param string $meta_key The meta key being checked.
 * @param int    $post_id  The post ID being edited.
 * @return bool
 */
function wp_storymap_can_edit_meta($allowed, $meta_key, $post_id)
{
	unset($allowed, $meta_key);
	return current_user_can('edit_post', $post_id);
}
