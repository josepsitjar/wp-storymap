<?php
/**
 * Registers Gutenberg Storymaps in the unchanged legacy story table.
 *
 * @package CreateBlock
 */

/**
 * Gets the legacy stories table name.
 *
 * @return string
 */
function wp_storymap_legacy_stories_table() {
	global $wpdb;
	return $wpdb->prefix . 'storymap_pro_my_stories';
}

/**
 * Creates the legacy-compatible tables required to store Storymap stories and points.
 *
 * Existing tables are left untouched so legacy installations keep their schema and data.
 *
 * @return bool Whether both tables are available.
 */
function wp_storymap_install_legacy_tables() {
	global $wpdb;

	$tables = array(
		'stories' => wp_storymap_legacy_stories_table(),
		'points'  => $wpdb->prefix . 'storymap_pro_points',
	);
	$missing_tables = array();

	foreach ( $tables as $table_name ) {
		$table_found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);
		if ( $wpdb->last_error ) {
			error_log( 'WP Storymap could not check its required tables: ' . $wpdb->last_error );
			return false;
		}
		if ( $table_found !== $table_name ) {
			$missing_tables[] = $table_name;
		}
	}

	if ( empty( $missing_tables ) ) {
		return true;
	}

	$charset_collate = $wpdb->get_charset_collate();
	$schemas         = array(
		$tables['stories'] => "CREATE TABLE {$tables['stories']} (
			storyNumber int(10) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			basemap varchar(50) NOT NULL DEFAULT 'osm',
			height int(11) NOT NULL DEFAULT 500,
			image varchar(500) NOT NULL DEFAULT '',
			description longtext NOT NULL,
			show_line varchar(3) NOT NULL DEFAULT 'no',
			PRIMARY KEY  (storyNumber)
		) {$charset_collate};",
		$tables['points']  => "CREATE TABLE {$tables['points']} (
			id int(11) NOT NULL AUTO_INCREMENT,
			num int(11) NOT NULL DEFAULT 1,
			name varchar(255) NOT NULL DEFAULT '',
			description longtext NOT NULL,
			lat double NOT NULL,
			lon double NOT NULL,
			zoom int(11) NOT NULL DEFAULT 0,
			image varchar(500) NOT NULL DEFAULT '',
			storyId int(11) NOT NULL,
			color varchar(20) NOT NULL DEFAULT '#FFFFFF',
			shapeColor varchar(20) NOT NULL DEFAULT '#000000',
			shape varchar(100) NOT NULL DEFAULT 'fas fa-camera',
			PRIMARY KEY  (id),
			KEY storyId (storyId)
		) {$charset_collate};",
	);

	foreach ( $missing_tables as $table_name ) {
		if ( false === $wpdb->query( $schemas[ $table_name ] ) ) {
			error_log(
				sprintf(
					'WP Storymap could not create the required table %s: %s',
					$table_name,
					$wpdb->last_error ? $wpdb->last_error : 'database query failed'
				)
			);
			return false;
		}
	}

	foreach ( $tables as $table_name ) {
		$table_found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);
		if ( $wpdb->last_error || $table_found !== $table_name ) {
			error_log(
				sprintf(
					'WP Storymap could not create the required table %s: %s',
					$table_name,
					$wpdb->last_error ? $wpdb->last_error : 'table not found after creation'
				)
			);
			return false;
		}
	}

	return true;
}
register_activation_hook( WP_STORYMAP_PLUGIN_FILE, 'wp_storymap_install_legacy_tables' );
add_action( 'admin_init', 'wp_storymap_install_legacy_tables', 0 );

/**
 * Registers or synchronizes a Gutenberg Storymap's independent story number.
 *
 * @param int $post_id Storymap post ID.
 * @return int|WP_Error The story number, or an error if registration failed.
 */
function wp_storymap_sync_legacy_story_record( $post_id ) {
	static $syncing = false;
	global $wpdb;

	$post = get_post( $post_id );
	if (
		$syncing ||
		! $post ||
		'wp_storymap' !== $post->post_type ||
		'auto-draft' === $post->post_status ||
		'trash' === $post->post_status ||
		wp_is_post_revision( $post_id )
	) {
		return new WP_Error(
			'wp_storymap_story_not_ready',
			__( 'This Storymap is not ready to be registered.', 'wp-storymap' )
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

	$story_number = (int) get_post_meta( $post_id, 'wp_storymap_story_number', true );
	if ( $story_number < 1 ) {
		$story_number = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT GREATEST(
					COALESCE((SELECT MAX(storyNumber) FROM {$table_name}), 0),
					COALESCE((
						SELECT MAX(CAST(meta_value AS UNSIGNED))
						FROM {$wpdb->postmeta}
						WHERE meta_key = %s
					), 0)
				) + 1",
				'wp_storymap_story_number'
			)
		);
		if ( $wpdb->last_error || $story_number < 1 ) {
			return new WP_Error(
				'wp_storymap_story_number_failed',
				__( 'A story number could not be assigned to this Storymap.', 'wp-storymap' )
			);
		}
	}

	$title = sanitize_text_field( $post->post_title );
	if ( '' === $title ) {
		$title = sprintf(
			/* translators: %d is the Storymap number. */
			__( 'Storymap %d', 'wp-storymap' ),
			$story_number
		);
	}
	$title = wp_html_excerpt( $title, 255, '' );
	$basemap = get_post_meta( $post_id, 'wp_storymap_basemap', true );
	$basemap = is_string( $basemap ) && '' !== $basemap ? sanitize_text_field( $basemap ) : 'osm';
	$height = (int) get_post_meta( $post_id, 'wp_storymap_height', true );
	$height = $height > 0 ? $height : 500;
	$existing_record = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT storyNumber FROM {$table_name} WHERE storyNumber = %d LIMIT 1",
			$story_number
		)
	);

	$syncing = true;
	if ( ! metadata_exists( 'post', $post_id, 'wp_storymap_story_number' ) || (int) get_post_meta( $post_id, 'wp_storymap_story_number', true ) !== $story_number ) {
		update_post_meta( $post_id, 'wp_storymap_story_number', $story_number );
	}

	if ( $existing_record ) {
		$result = $wpdb->update(
			$table_name,
			array(
				'name'    => $title,
				'basemap' => $basemap,
				'height'  => $height,
			),
			array( 'storyNumber' => $story_number ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	} else {
		$result = $wpdb->insert(
			$table_name,
			array(
				'storyNumber' => $story_number,
				'name'        => $title,
				'basemap'     => $basemap,
				'height'      => $height,
				'image'       => '',
				'description' => '',
				'show_line'   => 'no',
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}
	$syncing = false;

	if ( false === $result || $wpdb->last_error ) {
		return new WP_Error(
			'wp_storymap_story_record_sync_failed',
			__( 'The Storymap could not be registered in the legacy stories table.', 'wp-storymap' )
		);
	}

	return $story_number;
}

/**
 * Keeps the legacy story row current when its Gutenberg Storymap is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function wp_storymap_save_legacy_story_record( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$result = wp_storymap_sync_legacy_story_record( $post_id );
	if ( is_wp_error( $result ) && 'wp_storymap_story_not_ready' !== $result->get_error_code() ) {
		error_log( 'WP Storymap story registration failed: ' . $result->get_error_message() );
	}
}
add_action( 'save_post_wp_storymap', 'wp_storymap_save_legacy_story_record', 20, 2 );

/**
 * Synchronizes story settings after WordPress has saved the post metadata.
 *
 * @param int    $meta_id  Metadata ID.
 * @param int    $post_id  Post ID.
 * @param string $meta_key Metadata key.
 */
function wp_storymap_sync_legacy_story_meta( $meta_id, $post_id, $meta_key ) {
	unset( $meta_id );
	if ( ! in_array( $meta_key, array( 'wp_storymap_basemap', 'wp_storymap_height' ), true ) ) {
		return;
	}

	$result = wp_storymap_sync_legacy_story_record( $post_id );
	if ( is_wp_error( $result ) && 'wp_storymap_story_not_ready' !== $result->get_error_code() ) {
		error_log( 'WP Storymap story settings synchronization failed: ' . $result->get_error_message() );
	}
}
add_action( 'added_post_meta', 'wp_storymap_sync_legacy_story_meta', 10, 3 );
add_action( 'updated_post_meta', 'wp_storymap_sync_legacy_story_meta', 10, 3 );

/**
 * Assigns story numbers to existing Gutenberg Storymaps when opening wp-admin.
 */
function wp_storymap_register_existing_stories() {
	$post_ids = get_posts(
		array(
			'post_type'              => 'wp_storymap',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
		)
	);

	$errors = array();
	foreach ( $post_ids as $post_id ) {
		$result = wp_storymap_sync_legacy_story_record( $post_id );
		if ( is_wp_error( $result ) && 'wp_storymap_story_not_ready' !== $result->get_error_code() ) {
			error_log( 'WP Storymap existing story registration failed: ' . $result->get_error_message() );
			$errors[] = $result->get_error_message();
		}
	}

	return $errors;
}
add_action( 'admin_init', 'wp_storymap_register_existing_stories', 20 );
