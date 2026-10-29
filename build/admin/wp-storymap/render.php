<?php
/**
 * Server-side rendering for the StoryMap block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved block content.
 * @var WP_Block $block      Block instance.
 */

$story_number = isset( $attributes['storyNumber'] ) ? absint( $attributes['storyNumber'] ) : 0;
$story_post_id = 0;
$current_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();

if ( ! $story_number && 'wp_storymap' === get_post_type( $current_post_id ) ) {
	$story_number = (int) get_post_meta( $current_post_id, 'wp_storymap_story_number', true );
}

if ( $story_number ) {
	$story_post_ids = get_posts(
		array(
			'post_type'              => 'wp_storymap',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'meta_key'               => 'wp_storymap_story_number',
			'meta_value'             => $story_number,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		)
	);
	$story_post_id = $story_post_ids ? (int) $story_post_ids[0] : 0;
}

$post_id = $story_post_id ? $story_post_id : $current_post_id;
$height  = (int) get_post_meta( $post_id, 'wp_storymap_height', true );
$width   = (int) get_post_meta( $post_id, 'wp_storymap_width', true );
$is_full_bleed = (bool) get_post_meta( $post_id, 'wp_storymap_full_bleed', true );
$arrow_color = sanitize_hex_color( get_post_meta( $post_id, 'wp_storymap_arrow_color', true ) );
if ( ! $arrow_color ) {
	$arrow_color = '#000000';
}
$basemap = get_post_meta( $post_id, 'wp_storymap_basemap', true );
if ( ! is_string( $basemap ) || '' === $basemap ) {
	$basemap = 'osm';
	if ( $story_number > 0 ) {
		global $wpdb;

		$stories_table = wp_storymap_legacy_stories_table();
		$table_found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $stories_table ) )
		);
		if ( $wpdb->last_error ) {
			error_log( 'WP Storymap could not check the legacy stories table for a basemap: ' . $wpdb->last_error );
		} elseif ( $table_found === $stories_table ) {
			$legacy_basemap = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT basemap FROM {$stories_table} WHERE storyNumber = %d LIMIT 1",
					$story_number
				)
			);
			if ( $wpdb->last_error ) {
				error_log( 'WP Storymap could not load the legacy story basemap: ' . $wpdb->last_error );
			} elseif ( is_string( $legacy_basemap ) && '' !== $legacy_basemap ) {
				$basemap = $legacy_basemap;
			}
		}
	}
}
$supported_basemaps = array( 'osm', 'satellite', 'hybrid', 'relief', 'cyclemap', 'worldstreetmap' );
if ( ! in_array( $basemap, $supported_basemaps, true ) ) {
	$basemap = 'osm';
}
$story_image_id = absint( get_post_meta( $post_id, 'wp_storymap_image', true ) );
$story_image_url = $story_image_id ? wp_get_attachment_image_url( $story_image_id, 'large' ) : '';
$story_intro = array(
	'title'       => get_the_title( $post_id ),
	'description' => wp_kses_post( get_post_meta( $post_id, 'wp_storymap_description', true ) ),
	'image'       => $story_image_url ? esc_url_raw( $story_image_url ) : '',
);
$height  = $height > 0 ? $height : 500;
$is_full_width = $width <= 0;
$class         = 'storymap-map-container';
$style         = sprintf( 'height:%dpx;--storymap-arrow-color:%s', $height, $arrow_color );
$points        = array();

if ( ! $is_full_width ) {
	$class .= ' storymap-map-container--custom-width';
}
if ( $is_full_bleed ) {
	$class .= ' storymap-map-container--full-bleed';
}

if ( $story_number > 0 ) {
	global $wpdb;

	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$points        = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, num, name, description, lat, lon, zoom, image
			FROM {$points_table}
			WHERE storyId = %d
			ORDER BY num ASC, id ASC",
			$story_number
		),
		ARRAY_A
	);

	if ( $wpdb->last_error ) {
		error_log( 'WP Storymap could not load points for the public map: ' . $wpdb->last_error );
		$points = array();
	}

	$trashed_point_ids = wp_storymap_get_trashed_point_ids();
	if ( $trashed_point_ids ) {
		$points = array_filter(
			$points,
			static function ( $point ) use ( $trashed_point_ids ) {
				return ! in_array( (int) $point['id'], $trashed_point_ids, true );
			}
		);
	}

	$points = array_values(
		array_filter(
			array_map(
				static function ( $point ) {
					$latitude  = is_numeric( $point['lat'] ) ? (float) $point['lat'] : null;
					$longitude = is_numeric( $point['lon'] ) ? (float) $point['lon'] : null;

					if (
						null === $latitude ||
						null === $longitude ||
						! is_finite( $latitude ) ||
						! is_finite( $longitude ) ||
						$latitude < -90 ||
						$latitude > 90 ||
						$longitude < -180 ||
						$longitude > 180
					) {
						return null;
					}

					return array(
						'latitude'   => $latitude,
						'longitude'  => $longitude,
						'zoom'       => max( 0, min( 22, (int) $point['zoom'] ) ),
						'title'      => sanitize_text_field( $point['name'] ),
						'description'=> wp_kses_post( $point['description'] ),
						'image'      => esc_url_raw( $point['image'] ),
						'color'      => wp_storymap_get_point_marker_color( (int) $point['id'] ),
					);
				},
				$points
			)
		)
	);
}

if ( $is_full_width && empty( $attributes['align'] ) ) {
	$class .= ' alignfull';
}

if ( ! $is_full_width && ! $is_full_bleed ) {
	$style .= sprintf( ';width:%dpx;max-width:none', $width );
}

echo '<div ' . get_block_wrapper_attributes(
	array(
		'class'             => $class,
		'style'             => $style,
		'data-storymap-map' => 'true',
		'data-storymap-basemap' => $basemap,
		'data-storymap-points' => wp_json_encode( $points ),
		'data-storymap-intro' => wp_json_encode( $story_intro ),
		'role'             => 'region',
		'aria-label'       => __( 'OpenStreetMap map', 'wp-storymap' ),
	)
) . ' data-story-number="' . esc_attr( $story_number ) . '"></div>';
