<?php
/**
 * Admin interface for adding points to legacy Storymaps.
 *
 * @package CreateBlock
 */

/**
 * Registers the point-entry page under Storymaps.
 */
function wp_storymap_register_point_admin_page() {
	$points_hook = add_submenu_page(
		'edit.php?post_type=wp_storymap',
		__( 'Storymap points', 'wp-storymap' ),
		__( 'All Points', 'wp-storymap' ),
		'manage_options',
		'wp-storymap-points',
		'wp_storymap_render_points_admin_page'
	);
	add_action(
		'admin_enqueue_scripts',
		function ( $hook_suffix ) use ( $points_hook ) {
			if ( $points_hook !== $hook_suffix ) {
				return;
			}

			wp_enqueue_script( 'list-table' );

			$asset_path  = __DIR__ . '/../build/admin/point-list.asset.php';
			$script_path = __DIR__ . '/../build/admin/point-list.js';
			if ( ! file_exists( $asset_path ) || ! file_exists( $script_path ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'The Storymap point list assets are missing. Rebuild the plugin assets before using Quick Edit.', 'wp-storymap' ) . '</p></div>';
					}
				);
				return;
			}

			$asset = require $asset_path;
			wp_enqueue_script(
				'wp-storymap-point-list',
				plugins_url( 'build/admin/point-list.js', WP_STORYMAP_PLUGIN_FILE ),
				$asset['dependencies'],
				filemtime( $script_path ),
				true
			);
		}
	);

	$hook = add_submenu_page(
		'edit.php?post_type=wp_storymap',
		__( 'Add Storymap point', 'wp-storymap' ),
		__( 'Add Point', 'wp-storymap' ),
		'manage_options',
		'wp-storymap-add-point',
		'wp_storymap_render_point_admin_page'
	);

	add_action(
		'admin_enqueue_scripts',
		function ( $hook_suffix ) use ( $hook ) {
			if ( $hook !== $hook_suffix ) {
				return;
			}

			wp_enqueue_media();

			$asset_path = __DIR__ . '/../build/admin/point-editor.asset.php';
			$script_path = __DIR__ . '/../build/admin/point-editor.js';
			$style_path = __DIR__ . '/../build/admin/point-editor.css';

			if ( ! file_exists( $asset_path ) || ! file_exists( $script_path ) || ! file_exists( $style_path ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'The Storymap point editor assets are missing. Rebuild the plugin assets before using this page.', 'wp-storymap' ) . '</p></div>';
					}
				);
				return;
			}

			$asset = require $asset_path;
			wp_enqueue_style(
				'wp-storymap-point-editor',
				plugins_url( 'build/admin/point-editor.css', WP_STORYMAP_PLUGIN_FILE ),
				array(),
				filemtime( $style_path )
			);
			wp_enqueue_script(
				'wp-storymap-point-editor',
				plugins_url( 'build/admin/point-editor.js', WP_STORYMAP_PLUGIN_FILE ),
				$asset['dependencies'],
				filemtime( $script_path ),
				true
			);
		}
	);
}
add_action( 'admin_menu', 'wp_storymap_register_point_admin_page' );

/**
 * Gets the IDs of points currently in the Storymap trash.
 *
 * @return int[]
 */
function wp_storymap_get_trashed_point_ids() {
	$point_ids = get_option( 'wp_storymap_trashed_point_ids', array() );
	if ( ! is_array( $point_ids ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $point_ids ) ) ) );
}

/**
 * Sanitizes a six-digit HTML hex color.
 *
 * @param string $color Color to sanitize.
 * @return string|false
 */
function wp_storymap_sanitize_marker_color( $color ) {
	if ( ! is_string( $color ) ) {
		return false;
	}

	$color = sanitize_hex_color( $color );
	if ( ! $color ) {
		return false;
	}

	if ( 4 === strlen( $color ) ) {
		$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
	}

	return strtoupper( $color );
}

/**
 * Gets a point's selected marker color, defaulting to MapLibre's light blue.
 *
 * @param int $point_id Point ID.
 * @return string
 */
function wp_storymap_get_point_marker_color( $point_id ) {
	$colors = get_option( 'wp_storymap_point_colors', array() );
	if ( ! is_array( $colors ) ) {
		return '#3FB1CE';
	}

	$color = isset( $colors[ $point_id ] ) ? wp_storymap_sanitize_marker_color( $colors[ $point_id ] ) : false;
	return $color ? $color : '#3FB1CE';
}

/**
 * Stores a point's marker color without changing the legacy points table.
 *
 * @param int    $point_id Point ID.
 * @param string $color    Sanitized color.
 * @return bool
 */
function wp_storymap_save_point_marker_color( $point_id, $color ) {
	$colors = get_option( 'wp_storymap_point_colors', array() );
	if ( ! is_array( $colors ) ) {
		$colors = array();
	}

	if ( '#3FB1CE' === $color ) {
		unset( $colors[ $point_id ] );
	} else {
		$colors[ $point_id ] = $color;
	}

	if ( empty( $colors ) ) {
		delete_option( 'wp_storymap_point_colors' );
		return null === get_option( 'wp_storymap_point_colors', null );
	}

	update_option( 'wp_storymap_point_colors', $colors, false );
	$saved_colors = get_option( 'wp_storymap_point_colors', array() );
	if ( ! is_array( $saved_colors ) ) {
		return false;
	}

	if ( '#3FB1CE' === $color ) {
		return ! isset( $saved_colors[ $point_id ] );
	}

	return isset( $saved_colors[ $point_id ] ) &&
		$color === $saved_colors[ $point_id ];
}

/**
 * Redirects to the points list with a status message.
 *
 * @param string $status Status to show.
 * @param string $view   List view to show.
 */
function wp_storymap_redirect_points_list( $status, $view = 'all' ) {
	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'   => 'wp_storymap',
				'page'        => 'wp-storymap-points',
				'point_status'=> $status,
				'point_view'  => $view,
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}

/**
 * Moves a point to the reversible WordPress-side trash without changing the legacy table.
 */
function wp_storymap_trash_point() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage Storymap points.', 'wp-storymap' ) );
	}

	$point_id = isset( $_GET['point_id'] ) ? absint( wp_unslash( $_GET['point_id'] ) ) : 0;
	check_admin_referer( 'wp_storymap_trash_point_' . $point_id );
	global $wpdb;

	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$exists       = $point_id ? $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$points_table} WHERE id = %d", $point_id )
	) : null;
	if ( $wpdb->last_error || ! $exists ) {
		error_log( 'WP Storymap could not move the requested point to trash: ' . $wpdb->last_error );
		wp_storymap_redirect_points_list( 'action_failed' );
	}

	$trashed_ids   = wp_storymap_get_trashed_point_ids();
	$trashed_ids[] = $point_id;
	$updated       = update_option( 'wp_storymap_trashed_point_ids', array_values( array_unique( $trashed_ids ) ), false );
	if ( ! $updated && ! in_array( $point_id, wp_storymap_get_trashed_point_ids(), true ) ) {
		error_log( 'WP Storymap could not update the point trash option.' );
		wp_storymap_redirect_points_list( 'action_failed' );
	}

	wp_storymap_redirect_points_list( 'trashed', 'all' );
}
add_action( 'admin_post_wp_storymap_trash_point', 'wp_storymap_trash_point' );

/**
 * Restores a point from the reversible WordPress-side trash.
 */
function wp_storymap_restore_point() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage Storymap points.', 'wp-storymap' ) );
	}

	$point_id = isset( $_GET['point_id'] ) ? absint( wp_unslash( $_GET['point_id'] ) ) : 0;
	check_admin_referer( 'wp_storymap_restore_point_' . $point_id );
	$trashed_ids = wp_storymap_get_trashed_point_ids();
	if ( ! in_array( $point_id, $trashed_ids, true ) ) {
		wp_storymap_redirect_points_list( 'action_failed', 'trash' );
	}

	$trashed_ids = array_values( array_diff( $trashed_ids, array( $point_id ) ) );
	$updated     = update_option( 'wp_storymap_trashed_point_ids', $trashed_ids, false );
	if ( ! $updated && in_array( $point_id, wp_storymap_get_trashed_point_ids(), true ) ) {
		error_log( 'WP Storymap could not restore the point from trash.' );
		wp_storymap_redirect_points_list( 'action_failed', 'trash' );
	}

	wp_storymap_redirect_points_list( 'restored', 'all' );
}
add_action( 'admin_post_wp_storymap_restore_point', 'wp_storymap_restore_point' );

/**
 * Places a point at its requested position and renumbers the points in its Storymap.
 *
 * @param int $story_number  Storymap number.
 * @param int $point_id      Point ID.
 * @param int $requested_position One-based position; zero appends the point.
 * @return bool
 */
function wp_storymap_set_point_order( $story_number, $point_id, $requested_position ) {
	global $wpdb;

	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$point_ids    = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$points_table} WHERE storyId = %d ORDER BY num ASC, id ASC",
			$story_number
		)
	);
	if ( $wpdb->last_error || ! $point_ids ) {
		return false;
	}

	$point_ids = array_map( 'intval', $point_ids );
	if ( ! in_array( $point_id, $point_ids, true ) ) {
		return false;
	}

	$point_ids = array_values( array_diff( $point_ids, array( $point_id ) ) );
	$position  = $requested_position > 0
		? min( $requested_position, count( $point_ids ) + 1 )
		: count( $point_ids ) + 1;
	array_splice( $point_ids, $position - 1, 0, array( $point_id ) );

	$cases = array();
	$args  = array();
	foreach ( $point_ids as $index => $ordered_point_id ) {
		$cases[] = 'WHEN %d THEN %d';
		$args[]  = $ordered_point_id;
		$args[]  = $index + 1;
	}

	$id_placeholders = implode( ', ', array_fill( 0, count( $point_ids ), '%d' ) );
	$args[]          = $story_number;
	$args            = array_merge( $args, $point_ids );
	$updated         = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$points_table}
			SET num = CASE id " . implode( ' ', $cases ) . ' ELSE num END
			WHERE storyId = %d AND id IN (' . $id_placeholders . ')',
			$args
		)
	);

	return false !== $updated && ! $wpdb->last_error;
}

/**
 * Applies a bulk trash or restore action to selected points.
 */
function wp_storymap_bulk_manage_points() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage Storymap points.', 'wp-storymap' ) );
	}

	check_admin_referer( 'wp_storymap_bulk_manage_points' );

	$action = isset( $_POST['bulk_action_top'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action_top'] ) ) : '';
	if ( '' === $action && isset( $_POST['bulk_action_bottom'] ) ) {
		$action = sanitize_key( wp_unslash( $_POST['bulk_action_bottom'] ) );
	}
	$point_ids = isset( $_POST['point_ids'] ) && is_array( $_POST['point_ids'] )
		? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['point_ids'] ) ) ) ) )
		: array();

	if ( ! in_array( $action, array( 'trash', 'restore' ), true ) || empty( $point_ids ) || count( $point_ids ) > 20 ) {
		wp_storymap_redirect_points_list( 'action_failed' );
	}

	global $wpdb;
	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$placeholders = implode( ',', array_fill( 0, count( $point_ids ), '%d' ) );
	$existing_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$points_table} WHERE id IN ({$placeholders})",
			$point_ids
		)
	);
	if ( $wpdb->last_error ) {
		error_log( 'WP Storymap could not load points for a bulk action: ' . $wpdb->last_error );
		wp_storymap_redirect_points_list( 'action_failed' );
	}
	if ( empty( $existing_ids ) ) {
		wp_storymap_redirect_points_list( 'action_failed' );
	}

	$trashed_ids = wp_storymap_get_trashed_point_ids();
	if ( 'trash' === $action ) {
		$ids_to_trash = array_diff( array_map( 'intval', $existing_ids ), $trashed_ids );
		$trashed_ids  = array_values( array_unique( array_merge( $trashed_ids, $ids_to_trash ) ) );
		$updated      = update_option( 'wp_storymap_trashed_point_ids', $trashed_ids, false );
		if ( ! $updated && array_diff( $ids_to_trash, wp_storymap_get_trashed_point_ids() ) ) {
			error_log( 'WP Storymap could not move the selected points to trash.' );
			wp_storymap_redirect_points_list( 'action_failed' );
		}

		wp_storymap_redirect_points_list( 'trashed', 'all' );
	}

	$ids_to_restore = array_intersect( array_map( 'intval', $existing_ids ), $trashed_ids );
	if ( empty( $ids_to_restore ) ) {
		wp_storymap_redirect_points_list( 'action_failed', 'trash' );
	}

	$remaining_trashed_ids = array_values( array_diff( $trashed_ids, $ids_to_restore ) );
	$updated                = update_option( 'wp_storymap_trashed_point_ids', $remaining_trashed_ids, false );
	if ( ! $updated && array_diff( $ids_to_restore, wp_storymap_get_trashed_point_ids() ) ) {
		error_log( 'WP Storymap could not restore the selected points from trash.' );
		wp_storymap_redirect_points_list( 'action_failed', 'trash' );
	}

	wp_storymap_redirect_points_list( 'restored', 'all' );
}
add_action( 'admin_post_wp_storymap_bulk_manage_points', 'wp_storymap_bulk_manage_points' );

/**
 * Updates quick-editable point fields without leaving the points list.
 */
function wp_storymap_quick_edit_point() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to edit Storymap points.', 'wp-storymap' ) );
	}
	check_admin_referer( 'wp_storymap_quick_edit_point' );

	$point_id = isset( $_POST['point_id'] ) ? absint( wp_unslash( $_POST['point_id'] ) ) : 0;
	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$story_number = isset( $_POST['story_number'] ) ? absint( wp_unslash( $_POST['story_number'] ) ) : 0;
	$point_order = isset( $_POST['point_order'] ) ? absint( wp_unslash( $_POST['point_order'] ) ) : 0;
	$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
	if ( ! $point_id || ! $story_number || ! $point_order || '' === $name || strlen( $name ) > 255 || strlen( $description ) > 10000 ) {
		wp_storymap_redirect_points_list( 'invalid' );
	}
	if ( in_array( $point_id, wp_storymap_get_trashed_point_ids(), true ) ) {
		wp_storymap_redirect_points_list( 'invalid', 'trash' );
	}

	global $wpdb;
	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$existing_point = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, num, storyId FROM {$points_table} WHERE id = %d", $point_id )
	);
	if ( $wpdb->last_error || ! $existing_point ) {
		error_log( 'WP Storymap could not load point ' . $point_id . ' for quick edit: ' . $wpdb->last_error );
		wp_storymap_redirect_points_list( 'action_failed' );
	}

	$stories = wp_storymap_get_point_stories();
	if ( is_wp_error( $stories ) ) {
		error_log( 'WP Storymap could not validate the Storymap for quick edit: ' . $stories->get_error_message() );
		wp_storymap_redirect_points_list( 'action_failed' );
	}
	$story_is_available = false;
	foreach ( $stories as $story ) {
		if ( (int) $story->storyNumber === $story_number ) {
			$story_is_available = true;
			break;
		}
	}
	if ( ! $story_is_available ) {
		wp_storymap_redirect_points_list( 'invalid' );
	}

	$updated      = $wpdb->update(
		$points_table,
		array(
			'name'        => $name,
			'description' => $description,
			'storyId'     => $story_number,
		),
		array( 'id' => $point_id ),
		array( '%s', '%s', '%d' ),
		array( '%d' )
	);
	if ( false === $updated ) {
		error_log( 'WP Storymap could not quick-edit point ' . $point_id . ': ' . $wpdb->last_error );
		wp_storymap_redirect_points_list( 'action_failed' );
	}
	if ( ! wp_storymap_set_point_order( $story_number, $point_id, $point_order ) ) {
		error_log( 'WP Storymap could not set the order for point ' . $point_id . ': ' . $wpdb->last_error );
		wp_storymap_redirect_points_list( 'action_failed' );
	}

	wp_storymap_redirect_points_list( 'quick_updated' );
}
add_action( 'admin_post_wp_storymap_quick_edit_point', 'wp_storymap_quick_edit_point' );

/**
 * Renders a paginated list of points stored in the legacy points table.
 */
function wp_storymap_render_points_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to view Storymap points.', 'wp-storymap' ) );
	}

	global $wpdb;

	$points_table  = $wpdb->prefix . 'storymap_pro_points';
	$stories_table = $wpdb->prefix . 'storymap_pro_my_stories';
	$points_found  = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $points_table ) )
	);
	$stories_found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $stories_table ) )
	);
	$per_page      = 20;
	$current_page  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	$point_view    = isset( $_GET['point_view'] ) && 'trash' === sanitize_key( wp_unslash( $_GET['point_view'] ) ) ? 'trash' : 'all';
	$total_points  = 0;
	$points        = array();
	$query_failed  = false;
	$trashed_ids   = wp_storymap_get_trashed_point_ids();
	$quick_edit_stories = 'all' === $point_view ? wp_storymap_get_point_stories() : array();

	if ( $points_found === $points_table ) {
		$point_ids_placeholders = $trashed_ids ? implode( ',', array_fill( 0, count( $trashed_ids ), '%d' ) ) : '';
		$view_condition = $trashed_ids
			? ( 'trash' === $point_view ? "points.id IN ({$point_ids_placeholders})" : "points.id NOT IN ({$point_ids_placeholders})" )
			: ( 'trash' === $point_view ? '1 = 0' : '1 = 1' );
		$count_query = "SELECT COUNT(*) FROM {$points_table} AS points WHERE {$view_condition}";
		$total_points = $trashed_ids
			? $wpdb->get_var( $wpdb->prepare( $count_query, $trashed_ids ) )
			: $wpdb->get_var( $count_query );
		$query_failed = (bool) $wpdb->last_error;
		if ( ! $query_failed ) {
			$total_points = (int) $total_points;
			$total_pages  = max( 1, (int) ceil( $total_points / $per_page ) );
			$current_page = min( $current_page, $total_pages );
			$story_select = $stories_found === $stories_table ? 'stories.name' : 'NULL';
			$story_join   = $stories_found === $stories_table
				? "LEFT JOIN {$stories_table} AS stories ON stories.storyNumber = points.storyId"
				: '';
			$points_query = "SELECT points.id, points.num, points.name, points.description, points.lat, points.lon, points.image, points.storyId, {$story_select} AS story_name
					FROM {$points_table} AS points
					{$story_join}
					WHERE {$view_condition}
					ORDER BY points.storyId ASC, points.num ASC, points.id ASC
					LIMIT %d OFFSET %d";
			$query_args = array_merge(
				$trashed_ids,
				array(
					$per_page,
					( $current_page - 1 ) * $per_page,
				)
			);
			$points = $trashed_ids
				? $wpdb->get_results( $wpdb->prepare( $points_query, $query_args ) )
				: $wpdb->get_results( $wpdb->prepare( $points_query, $per_page, ( $current_page - 1 ) * $per_page ) );
			$query_failed = (bool) $wpdb->last_error;
		}
	}

	$add_point_url = add_query_arg(
		array(
			'post_type' => 'wp_storymap',
			'page'      => 'wp-storymap-add-point',
		),
		admin_url( 'edit.php' )
	);
	$points_url = add_query_arg(
		array(
			'post_type' => 'wp_storymap',
			'page'      => 'wp-storymap-points',
		),
		admin_url( 'edit.php' )
	);
	$trash_url = add_query_arg(
		array(
			'post_type'  => 'wp_storymap',
			'page'       => 'wp-storymap-points',
			'point_view' => 'trash',
		),
		admin_url( 'edit.php' )
	);
	$all_points_count = $points_found === $points_table
		? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$points_table}" )
		: 0;
	$active_count = max( 0, $all_points_count - count( $trashed_ids ) );
	$trash_count  = min( $all_points_count, count( $trashed_ids ) );
	$status       = isset( $_GET['point_status'] ) ? sanitize_key( wp_unslash( $_GET['point_status'] ) ) : '';
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Storymap points', 'wp-storymap' ); ?></h1>
		<a href="<?php echo esc_url( $add_point_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add Point', 'wp-storymap' ); ?></a>
		<hr class="wp-header-end">

		<?php if ( 'trashed' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The selected point or points were moved to the trash.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'restored' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The selected point or points were restored.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'quick_updated' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The point was updated.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'action_failed' === $status ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The point action could not be completed.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'invalid' === $status ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The point details were invalid. Check the title, Storymap, and description.', 'wp-storymap' ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_wp_error( $quick_edit_stories ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $quick_edit_stories->get_error_message() ); ?></p></div>
		<?php endif; ?>

		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( $points_url ); ?>" <?php echo 'all' === $point_view ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'All', 'wp-storymap' ); ?>
					<span class="count">(<?php echo esc_html( $active_count ); ?>)</span>
				</a>
				|
			</li>
			<li>
				<a href="<?php echo esc_url( $trash_url ); ?>" <?php echo 'trash' === $point_view ? 'class="current" aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Trash', 'wp-storymap' ); ?>
					<span class="count">(<?php echo esc_html( $trash_count ); ?>)</span>
				</a>
			</li>
		</ul>
		<br class="clear">

		<?php if ( $points_found !== $points_table ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The legacy Storymap points table is not available.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( $query_failed ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Storymap points could not be loaded.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( empty( $points ) ) : ?>
			<p><?php echo 'trash' === $point_view ? esc_html__( 'The trash is empty.', 'wp-storymap' ) : esc_html__( 'No points have been added yet.', 'wp-storymap' ); ?></p>
		<?php else : ?>
			<form id="wp-storymap-bulk-point-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wp_storymap_bulk_manage_points">
				<?php wp_nonce_field( 'wp_storymap_bulk_manage_points' ); ?>
			</form>
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="wp-storymap-bulk-action-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp-storymap' ); ?></label>
					<select id="wp-storymap-bulk-action-top" name="bulk_action_top" form="wp-storymap-bulk-point-actions">
						<option value=""><?php esc_html_e( 'Bulk actions', 'wp-storymap' ); ?></option>
						<?php if ( 'trash' === $point_view ) : ?>
							<option value="restore"><?php esc_html_e( 'Restore', 'wp-storymap' ); ?></option>
						<?php else : ?>
							<option value="trash"><?php esc_html_e( 'Move to Trash', 'wp-storymap' ); ?></option>
						<?php endif; ?>
					</select>
					<?php submit_button( __( 'Apply', 'wp-storymap' ), 'action', 'apply_top', false, array( 'form' => 'wp-storymap-bulk-point-actions' ) ); ?>
				</div>
				<br class="clear">
			</div>
			<p><?php echo esc_html( sprintf( _n( '%s point', '%s points', $total_points, 'wp-storymap' ), number_format_i18n( $total_points ) ) ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="check-column">
							<input id="cb-select-all-1" type="checkbox">
						</td>
						<th scope="col"><?php esc_html_e( 'Point', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Story', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Coordinates', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Image', 'wp-storymap' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php foreach ( $points as $point ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="point_ids[]" value="<?php echo esc_attr( $point->id ); ?>" form="wp-storymap-bulk-point-actions" aria-label="<?php echo esc_attr( sprintf( __( 'Select point: %s', 'wp-storymap' ), $point->name ) ); ?>">
							</th>
							<td>
								<strong><?php echo esc_html( $point->name ); ?></strong>
								<div class="description"><?php echo esc_html( sprintf( __( 'Point %d', 'wp-storymap' ), $point->num ) ); ?></div>
								<div class="row-actions">
									<?php if ( 'trash' === $point_view ) : ?>
										<span class="untrash">
											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wp_storymap_restore_point', 'point_id' => $point->id ), admin_url( 'admin-post.php' ) ), 'wp_storymap_restore_point_' . $point->id ) ); ?>">
												<?php esc_html_e( 'Restore', 'wp-storymap' ); ?>
											</a>
										</span>
									<?php else : ?>
										<span class="edit">
											<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'wp_storymap', 'page' => 'wp-storymap-add-point', 'point_id' => $point->id ), admin_url( 'edit.php' ) ) ); ?>">
												<?php esc_html_e( 'Edit', 'wp-storymap' ); ?>
											</a>
										</span>
										|
											<?php if ( ! is_wp_error( $quick_edit_stories ) && ! empty( $quick_edit_stories ) ) : ?>
												<span class="inline">
													<a
														href="#"
														class="editinline wp-storymap-point-quick-edit"
														aria-expanded="false"
														aria-controls="wp-storymap-point-quick-edit-<?php echo esc_attr( $point->id ); ?>"
													><?php esc_html_e( 'Quick Edit', 'wp-storymap' ); ?></a>
												</span>
												|
											<?php endif; ?>
										<span class="trash">
											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wp_storymap_trash_point', 'point_id' => $point->id ), admin_url( 'admin-post.php' ) ), 'wp_storymap_trash_point_' . $point->id ) ); ?>">
												<?php esc_html_e( 'Trash', 'wp-storymap' ); ?>
											</a>
										</span>
									<?php endif; ?>
								</div>
							</td>
							<td><?php echo esc_html( $point->story_name ? $point->story_name : __( 'Story unavailable', 'wp-storymap' ) ); ?></td>
							<td><?php echo esc_html( $point->lat . ', ' . $point->lon ); ?></td>
							<td><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $point->description ), 20 ) ); ?></td>
							<td>
								<?php if ( $point->image ) : ?>
									<a href="<?php echo esc_url( $point->image ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View image', 'wp-storymap' ); ?></a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( 'all' === $point_view && ! is_wp_error( $quick_edit_stories ) && ! empty( $quick_edit_stories ) ) : ?>
							<tr
								id="wp-storymap-point-quick-edit-<?php echo esc_attr( $point->id ); ?>"
								class="inline-edit-row inline-edit-row-post quick-edit-row hidden wp-storymap-point-quick-edit-row"
							>
								<td colspan="6">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="wp_storymap_quick_edit_point">
										<input type="hidden" name="point_id" value="<?php echo esc_attr( $point->id ); ?>">
										<?php wp_nonce_field( 'wp_storymap_quick_edit_point' ); ?>
										<fieldset class="inline-edit-col-left">
											<div class="inline-edit-col">
												<label>
													<span class="title"><?php esc_html_e( 'Title', 'wp-storymap' ); ?></span>
													<span class="input-text-wrap">
														<input type="text" name="name" value="<?php echo esc_attr( $point->name ); ?>" maxlength="255" required>
													</span>
												</label>
												<label>
													<span class="title"><?php esc_html_e( 'Storymap', 'wp-storymap' ); ?></span>
													<select name="story_number" required>
														<?php foreach ( $quick_edit_stories as $story ) : ?>
															<option value="<?php echo esc_attr( $story->storyNumber ); ?>" <?php selected( $point->storyId, $story->storyNumber ); ?>>
																<?php echo esc_html( $story->name ); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</label>
												<label>
													<span class="title"><?php esc_html_e( 'Position', 'wp-storymap' ); ?></span>
													<input type="number" name="point_order" min="1" value="<?php echo esc_attr( max( 1, (int) $point->num ) ); ?>" required>
												</label>
											</div>
										</fieldset>
										<fieldset class="inline-edit-col-right">
											<div class="inline-edit-col">
												<label>
													<span class="title"><?php esc_html_e( 'Description', 'wp-storymap' ); ?></span>
													<textarea name="description" rows="3" maxlength="10000"><?php echo esc_textarea( $point->description ); ?></textarea>
												</label>
											</div>
										</fieldset>
										<div class="submit inline-edit-save">
											<?php submit_button( __( 'Update', 'wp-storymap' ), 'primary', 'submit', false ); ?>
											<button type="button" class="button cancel wp-storymap-point-quick-edit-cancel"><?php esc_html_e( 'Cancel', 'wp-storymap' ); ?></button>
										</div>
									</form>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="check-column">
							<input id="cb-select-all-2" type="checkbox">
						</td>
						<th scope="col"><?php esc_html_e( 'Point', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Story', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Coordinates', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'wp-storymap' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Image', 'wp-storymap' ); ?></th>
					</tr>
				</tfoot>
			</table>
			<?php
			$total_pages = (int) ceil( $total_points / $per_page );
			?>
				<div class="tablenav">
					<div class="alignleft actions bulkactions">
						<label for="wp-storymap-bulk-action-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp-storymap' ); ?></label>
						<select id="wp-storymap-bulk-action-bottom" name="bulk_action_bottom" form="wp-storymap-bulk-point-actions">
							<option value=""><?php esc_html_e( 'Bulk actions', 'wp-storymap' ); ?></option>
							<?php if ( 'trash' === $point_view ) : ?>
								<option value="restore"><?php esc_html_e( 'Restore', 'wp-storymap' ); ?></option>
							<?php else : ?>
								<option value="trash"><?php esc_html_e( 'Move to Trash', 'wp-storymap' ); ?></option>
							<?php endif; ?>
						</select>
						<?php submit_button( __( 'Apply', 'wp-storymap' ), 'action', 'apply_bottom', false, array( 'form' => 'wp-storymap-bulk-point-actions' ) ); ?>
					</div>
					<div class="tablenav-pages">
						<?php if ( $total_pages > 1 ) : ?>
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( array( 'point_view' => $point_view, 'paged' => '%#%' ), $points_url ),
									'format'    => '',
									'current'   => $current_page,
									'total'     => $total_pages,
									'prev_text' => __( '&laquo;', 'wp-storymap' ),
									'next_text' => __( '&raquo;', 'wp-storymap' ),
								)
							)
						);
						?>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Gets Gutenberg Storymaps and ensures each has a legacy story number.
 *
 * @return array|WP_Error
 */
function wp_storymap_get_point_stories() {
	global $wpdb;

	if ( ! wp_storymap_install_legacy_tables() ) {
		return new WP_Error(
			'wp_storymap_legacy_tables_unavailable',
			__( 'The Storymap database tables could not be created. Check that the database user can create tables and review the PHP error log.', 'wp-storymap' )
		);
	}

	$table_name = wp_storymap_legacy_stories_table();
	$table_found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
	);

	if ( $table_found !== $table_name ) {
		return new WP_Error(
			'wp_storymap_missing_legacy_stories',
			__( 'The legacy Storymap stories table is not available.', 'wp-storymap' )
		);
	}

	$registration_errors = wp_storymap_register_existing_stories();
	if ( $registration_errors ) {
		return new WP_Error(
			'wp_storymap_story_registration_failed',
			implode( ' ', array_unique( $registration_errors ) )
		);
	}

	$posts = get_posts(
		array(
			'post_type'              => 'wp_storymap',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
		)
	);

	$stories = array();
	foreach ( $posts as $post ) {
		$story_number = (int) get_post_meta( $post->ID, 'wp_storymap_story_number', true );
		if ( $story_number > 0 ) {
			$stories[] = (object) array(
				'storyNumber' => $story_number,
				'name'        => get_the_title( $post ),
			);
		}
	}

	return $stories;
}

/**
 * Redirects back to the point form with a one-time status code.
 *
 * @param string $status   Status to show in the admin page.
 * @param int    $point_id Point being edited, if any.
 */
function wp_storymap_redirect_point_status( $status, $point_id = 0 ) {
	$args = array(
		'post_type'    => 'wp_storymap',
		'page'         => 'wp-storymap-add-point',
		'point_status' => $status,
	);
	if ( $point_id ) {
		$args['point_id'] = $point_id;
	}

	wp_safe_redirect(
		add_query_arg(
			$args,
			admin_url( 'edit.php' )
		)
	);
	exit;
}

/**
 * Renders the point form and a short list of existing points.
 */
function wp_storymap_render_point_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to add Storymap points.', 'wp-storymap' ) );
	}

	global $wpdb;

	$stories = wp_storymap_get_point_stories();
	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$editing_point_id = isset( $_GET['point_id'] ) ? absint( wp_unslash( $_GET['point_id'] ) ) : 0;
	$editing_point = null;
	$marker_color = '#3FB1CE';
	$point_zoom = 12;
	$editing_point_failed = false;
	$points = array();
	$points_query_failed = false;
	$points_table_found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $points_table ) )
	);

	if ( $editing_point_id && $points_table_found === $points_table ) {
		$editing_point = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, num, name, description, lat, lon, zoom, image, storyId
				FROM {$points_table}
				WHERE id = %d",
				$editing_point_id
			)
		);
		$editing_point_failed = (bool) $wpdb->last_error;
		if ( $editing_point ) {
			$marker_color = wp_storymap_get_point_marker_color( $editing_point_id );
			$point_zoom = max( 0, min( 22, (int) $editing_point->zoom ) );
		}
	}

	if ( $points_table_found === $points_table && ! is_wp_error( $stories ) && ! empty( $stories ) ) {
		$stories_table = $wpdb->prefix . 'storymap_pro_my_stories';
		$points = $wpdb->get_results(
			"SELECT points.id, points.num, points.name, points.lat, points.lon, points.storyId, stories.name AS story_name
			FROM {$points_table} AS points
			LEFT JOIN {$stories_table} AS stories ON stories.storyNumber = points.storyId
			ORDER BY points.id DESC
			LIMIT 20"
		);
		$points_query_failed = (bool) $wpdb->last_error;
	}

	$status = isset( $_GET['point_status'] ) ? sanitize_key( wp_unslash( $_GET['point_status'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php echo $editing_point_id ? esc_html__( 'Edit Storymap point', 'wp-storymap' ) : esc_html__( 'Add Storymap point', 'wp-storymap' ); ?></h1>
		<?php if ( 'saved' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The point was added successfully.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'updated' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The point was updated successfully.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'save_failed' === $status ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The point or its marker color could not be saved. Check that the legacy points table is available and try again.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'invalid' === $status ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The point details were invalid. Check the title, marker color, location, zoom, image, and selected story.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( 'no_stories' === $status ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The selected legacy story is no longer available. Choose an existing story and try again.', 'wp-storymap' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $editing_point_failed ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The selected point could not be loaded.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( $editing_point_id && ! $editing_point ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The selected point does not exist.', 'wp-storymap' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $points_query_failed ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Existing Storymap points could not be loaded.', 'wp-storymap' ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_wp_error( $stories ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $stories->get_error_message() ); ?></p></div>
		<?php elseif ( ! $points_table_found ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The legacy Storymap points table is not available.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( empty( $stories ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'There are no Storymaps available yet. Create and save a Storymap first; each Storymap has its own story number, independent of its WordPress post ID.', 'wp-storymap' ); ?></p></div>
		<?php elseif ( ! $editing_point_id || $editing_point ) : ?>
			<form id="wp-storymap-point-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wp_storymap_save_point">
				<input type="hidden" name="point_id" value="<?php echo esc_attr( $editing_point ? $editing_point->id : 0 ); ?>">
				<?php wp_nonce_field( 'wp_storymap_save_point' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="wp-storymap-point-story"><?php esc_html_e( 'Story', 'wp-storymap' ); ?></label></th>
							<td>
								<select id="wp-storymap-point-story" name="story_number" required>
									<option value=""><?php esc_html_e( 'Select a Storymap', 'wp-storymap' ); ?></option>
									<?php foreach ( $stories as $story ) : ?>
										<option value="<?php echo esc_attr( $story->storyNumber ); ?>" <?php selected( $editing_point ? $editing_point->storyId : 0, $story->storyNumber ); ?>>
											<?php echo esc_html( $story->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'The selected Storymap will be assigned to this point.', 'wp-storymap' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp-storymap-point-order"><?php esc_html_e( 'Position', 'wp-storymap' ); ?></label></th>
							<td>
								<input id="wp-storymap-point-order" name="point_order" type="number" min="1" step="1" value="<?php echo esc_attr( $editing_point ? max( 1, (int) $editing_point->num ) : '' ); ?>">
								<p class="description"><?php esc_html_e( 'Set the point order in this Storymap. Leave empty when adding a point to append it at the end.', 'wp-storymap' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp-storymap-point-title"><?php esc_html_e( 'Point title', 'wp-storymap' ); ?></label></th>
							<td><input id="wp-storymap-point-title" name="name" type="text" class="regular-text" maxlength="255" value="<?php echo esc_attr( $editing_point ? $editing_point->name : '' ); ?>" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="wp-storymap-point-description"><?php esc_html_e( 'Description', 'wp-storymap' ); ?></label></th>
							<td>
								<?php
								wp_editor(
									$editing_point ? $editing_point->description : '',
									'wp-storymap-point-description',
									array(
										'textarea_name' => 'description',
										'textarea_rows' => 5,
										'media_buttons' => false,
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'You can format the description with bold, italics, links, and other rich text options.', 'wp-storymap' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp-storymap-point-marker-color-code"><?php esc_html_e( 'Marker color', 'wp-storymap' ); ?></label></th>
							<td>
								<div class="wp-storymap-marker-color-control">
									<input id="wp-storymap-point-marker-color" type="color" value="<?php echo esc_attr( $marker_color ); ?>" aria-label="<?php esc_attr_e( 'Choose marker color', 'wp-storymap' ); ?>">
									<input id="wp-storymap-point-marker-color-code" name="marker_color" type="text" value="<?php echo esc_attr( $marker_color ); ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" class="regular-text" required aria-describedby="wp-storymap-marker-color-description">
								</div>
								<div class="wp-storymap-marker-color-palette" role="group" aria-label="<?php esc_attr_e( 'Basic marker colors', 'wp-storymap' ); ?>">
									<?php foreach ( array( '#3FB1CE', '#E53935', '#FF9800', '#4CAF50', '#9C27B0', '#FFC107', '#2196F3', '#333333' ) as $palette_color ) : ?>
										<button type="button" class="wp-storymap-marker-color-swatch" data-color="<?php echo esc_attr( $palette_color ); ?>" style="--wp-storymap-swatch-color:<?php echo esc_attr( $palette_color ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select marker color %s', 'wp-storymap' ), $palette_color ) ); ?>"></button>
									<?php endforeach; ?>
								</div>
								<p id="wp-storymap-marker-color-description" class="description"><?php esc_html_e( 'Choose a basic color or enter a six-digit HTML hex color code. The default is blue.', 'wp-storymap' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Location', 'wp-storymap' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Click the map to place the point, or drag its marker to adjust the location.', 'wp-storymap' ); ?></p>
								<div
									id="wp-storymap-point-map"
									class="wp-storymap-point-map"
									data-lat="<?php echo esc_attr( $editing_point ? $editing_point->lat : '' ); ?>"
									data-lon="<?php echo esc_attr( $editing_point ? $editing_point->lon : '' ); ?>"
									data-zoom="<?php echo esc_attr( $editing_point ? $point_zoom : 2 ); ?>"
									style="--wp-storymap-marker-color:<?php echo esc_attr( $marker_color ); ?>"
									role="region"
									aria-label="<?php esc_attr_e( 'Choose point location on map', 'wp-storymap' ); ?>"
								></div>
								<div class="wp-storymap-point-coordinates">
									<label for="wp-storymap-point-lat"><?php esc_html_e( 'Latitude', 'wp-storymap' ); ?></label>
									<input id="wp-storymap-point-lat" name="lat" type="number" min="-90" max="90" step="any" value="<?php echo esc_attr( $editing_point ? $editing_point->lat : '' ); ?>" required>
									<label for="wp-storymap-point-lon"><?php esc_html_e( 'Longitude', 'wp-storymap' ); ?></label>
									<input id="wp-storymap-point-lon" name="lon" type="number" min="-180" max="180" step="any" value="<?php echo esc_attr( $editing_point ? $editing_point->lon : '' ); ?>" required>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp-storymap-point-zoom"><?php esc_html_e( 'Public map zoom', 'wp-storymap' ); ?></label></th>
							<td>
								<input id="wp-storymap-point-zoom" name="zoom" type="number" min="0" max="22" step="1" value="<?php echo esc_attr( $point_zoom ); ?>" required>
								<p class="description"><?php esc_html_e( 'Choose the MapLibre zoom level used when this point is selected. Available levels: 0 to 22.', 'wp-storymap' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Image (optional)', 'wp-storymap' ); ?></th>
							<td>
								<?php $image_attachment_id = $editing_point ? attachment_url_to_postid( $editing_point->image ) : 0; ?>
								<input id="wp-storymap-point-image-id" name="image_id" type="hidden" value="<?php echo esc_attr( $image_attachment_id ); ?>">
								<input id="wp-storymap-point-image-url" name="image_url" type="hidden" value="<?php echo esc_url( $editing_point ? $editing_point->image : '' ); ?>">
								<div id="wp-storymap-point-image-preview">
									<?php if ( $editing_point && $editing_point->image ) : ?>
										<img src="<?php echo esc_url( $editing_point->image ); ?>" alt="<?php echo esc_attr( $editing_point->name ); ?>">
									<?php endif; ?>
								</div>
								<button id="wp-storymap-point-select-image" type="button" class="button"><?php esc_html_e( 'Select image', 'wp-storymap' ); ?></button>
								<button id="wp-storymap-point-remove-image" type="button" class="button" <?php echo $editing_point && $editing_point->image ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove image', 'wp-storymap' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( $editing_point ? __( 'Update point', 'wp-storymap' ) : __( 'Add point', 'wp-storymap' ) ); ?>
			</form>
		<?php endif; ?>

		<?php if ( ! empty( $points ) ) : ?>
			<h2>
				<?php esc_html_e( 'Recently added points', 'wp-storymap' ); ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'wp_storymap', 'page' => 'wp-storymap-points' ), admin_url( 'edit.php' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'View all points', 'wp-storymap' ); ?></a>
			</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Point', 'wp-storymap' ); ?></th>
						<th><?php esc_html_e( 'Story', 'wp-storymap' ); ?></th>
						<th><?php esc_html_e( 'Coordinates', 'wp-storymap' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $points as $point ) : ?>
						<tr>
							<td><?php echo esc_html( $point->name ); ?></td>
							<td><?php echo esc_html( $point->story_name ? $point->story_name : __( 'Story unavailable', 'wp-storymap' ) ); ?></td>
							<td><?php echo esc_html( $point->lat . ', ' . $point->lon ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Saves a point using the unchanged legacy table schema.
 */
function wp_storymap_save_point() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to add Storymap points.', 'wp-storymap' ) );
	}
	check_admin_referer( 'wp_storymap_save_point' );

	global $wpdb;

	$stories_table = $wpdb->prefix . 'storymap_pro_my_stories';
	$points_table = $wpdb->prefix . 'storymap_pro_points';
	$point_id = isset( $_POST['point_id'] ) ? absint( wp_unslash( $_POST['point_id'] ) ) : 0;
	$story_number = isset( $_POST['story_number'] ) ? (int) wp_unslash( $_POST['story_number'] ) : 0;
	$point_order = isset( $_POST['point_order'] ) ? absint( wp_unslash( $_POST['point_order'] ) ) : 0;
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
	$latitude_raw = isset( $_POST['lat'] ) ? wp_unslash( $_POST['lat'] ) : '';
	$longitude_raw = isset( $_POST['lon'] ) ? wp_unslash( $_POST['lon'] ) : '';
	$zoom = isset( $_POST['zoom'] ) ? (int) wp_unslash( $_POST['zoom'] ) : -1;
	$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
	$image_url_input = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
	$marker_color = isset( $_POST['marker_color'] ) ? wp_storymap_sanitize_marker_color( wp_unslash( $_POST['marker_color'] ) ) : false;

	$stories_table_found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $stories_table ) )
	);
	$points_table_found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $points_table ) )
	);
	if ( $stories_table_found !== $stories_table || $points_table_found !== $points_table ) {
		error_log( 'WP Storymap cannot save a point because one or more legacy tables are missing.' );
		wp_storymap_redirect_point_status( 'save_failed', $point_id );
	}

	$existing_point = null;
	if ( $point_id ) {
		$existing_point = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, num, storyId, image FROM {$points_table} WHERE id = %d",
				$point_id
			)
		);
		if ( $wpdb->last_error || ! $existing_point ) {
			error_log( 'WP Storymap could not load the point being edited.' );
			wp_storymap_redirect_point_status( 'save_failed', $point_id );
		}
	}

	$registration_errors = wp_storymap_register_existing_stories();
	if ( $registration_errors ) {
		error_log( 'WP Storymap cannot save a point: ' . implode( ' ', $registration_errors ) );
		wp_storymap_redirect_point_status( 'save_failed', $point_id );
	}

	$story_post_id = $story_number > 0 ? $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
			'wp_storymap_story_number',
			(string) $story_number
		)
	) : null;
	$story_post = $story_post_id ? get_post( $story_post_id ) : null;
	$story_exists = $story_number > 0 ? $wpdb->get_var(
		$wpdb->prepare(
			"SELECT storyNumber FROM {$stories_table} WHERE storyNumber = %d LIMIT 1",
			$story_number
		)
	) : null;
	$latitude = is_numeric( $latitude_raw ) ? (float) $latitude_raw : null;
	$longitude = is_numeric( $longitude_raw ) ? (float) $longitude_raw : null;
	$image_url = false;
	if ( $image_id && wp_attachment_is_image( $image_id ) ) {
		$image_url = wp_get_attachment_url( $image_id );
	} elseif ( $existing_point && $image_url_input === $existing_point->image ) {
		$image_url = $existing_point->image;
	}

	if (
		! $story_post_id ||
		! $story_post ||
		'wp_storymap' !== $story_post->post_type ||
		(int) get_post_meta( $story_post_id, 'wp_storymap_story_number', true ) !== $story_number ||
		! $story_exists ||
		'' === $name ||
		strlen( $name ) > 255 ||
		'' === $description ||
		strlen( $description ) > 10000 ||
		null === $latitude ||
		! is_finite( $latitude ) ||
		$latitude < -90 ||
		$latitude > 90 ||
		null === $longitude ||
		! is_finite( $longitude ) ||
		$longitude < -180 ||
		$longitude > 180 ||
		$zoom < 0 ||
		$zoom > 22 ||
		! $marker_color ||
		( $image_id && ! wp_attachment_is_image( $image_id ) ) ||
		( $image_url && strlen( $image_url ) > 500 )
	) {
		$status = $story_number && ! $story_exists ? 'no_stories' : 'invalid';
		wp_storymap_redirect_point_status( $status, $point_id );
	}
	$image_url = $image_url ? $image_url : '';

	if ( $existing_point && (int) $existing_point->storyId === $story_number ) {
		$point_number = (int) $existing_point->num;
	} else {
		$point_number = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MAX(num), 0) + 1 FROM {$points_table} WHERE storyId = %d",
				$story_number
			)
		);

		if ( $wpdb->last_error ) {
			error_log( 'WP Storymap could not determine the next legacy point number: ' . $wpdb->last_error );
			wp_storymap_redirect_point_status( 'save_failed', $point_id );
		}
	}

	if ( $existing_point ) {
		$saved = $wpdb->update(
			$points_table,
			array(
				'name'        => $name,
				'num'         => max( 1, $point_number ),
				'description' => $description,
				'lat'         => $latitude,
				'lon'         => $longitude,
				'zoom'        => $zoom,
				'image'       => $image_url,
				'storyId'     => $story_number,
			),
			array( 'id' => $point_id ),
			array( '%s', '%d', '%s', '%f', '%f', '%d', '%s', '%d' ),
			array( '%d' )
		);
	} else {
		$saved = $wpdb->insert(
			$points_table,
			array(
				'name'        => $name,
				'num'         => max( 1, $point_number ),
				'description' => $description,
				'lat'         => $latitude,
				'lon'         => $longitude,
				'zoom'        => $zoom,
				'image'       => $image_url,
				'storyId'     => $story_number,
				'color'       => '#FFFFFF',
				'shapeColor'  => '#000000',
				'shape'       => 'fas fa-camera',
			),
			array( '%s', '%d', '%s', '%f', '%f', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	if ( false === $saved ) {
		error_log( 'WP Storymap could not save a point to the legacy points table: ' . $wpdb->last_error );
		$status = 'save_failed';
	} else {
		$saved_point_id = $existing_point ? $point_id : (int) $wpdb->insert_id;
		if ( ! $saved_point_id || ! wp_storymap_set_point_order( $story_number, $saved_point_id, $point_order ) ) {
			error_log( 'WP Storymap could not set the order for point ' . $saved_point_id . ': ' . $wpdb->last_error );
			$status = 'save_failed';
		} elseif ( ! wp_storymap_save_point_marker_color( $saved_point_id, $marker_color ) ) {
			error_log( 'WP Storymap could not save the selected marker color for point ' . $saved_point_id . '.' );
			$status = 'save_failed';
		} else {
			$status = $existing_point ? 'updated' : 'saved';
		}
	}

	wp_storymap_redirect_point_status( $status, $point_id );
}
add_action( 'admin_post_wp_storymap_save_point', 'wp_storymap_save_point' );
