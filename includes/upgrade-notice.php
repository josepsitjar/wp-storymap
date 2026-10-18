<?php
/**
 * Warns administrators when upgrading an installation with legacy Storymap data.
 *
 * @package CreateBlock
 */

/**
 * Checks whether the legacy Storymap tables contain records.
 *
 * @return bool
 */
function wp_storymap_has_legacy_data() {
	global $wpdb;

	$table_names = array(
		$wpdb->prefix . 'storymap_pro_my_stories',
		$wpdb->prefix . 'storymap_pro_points',
	);

	foreach ( $table_names as $table_name ) {
		$table_found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);
		if ( $wpdb->last_error ) {
			error_log( 'WP Storymap could not check for legacy tables: ' . $wpdb->last_error );
			continue;
		}

		if ( $table_found !== $table_name ) {
			continue;
		}

		$has_records = $wpdb->get_var( "SELECT 1 FROM {$table_name} LIMIT 1" );
		if ( $wpdb->last_error ) {
			error_log( 'WP Storymap could not check legacy Storymap data: ' . $wpdb->last_error );
			continue;
		}

		if ( $has_records ) {
			return true;
		}
	}

	return false;
}

/**
 * Records whether this installation already had legacy data when first initialized.
 */
function wp_storymap_record_upgrade_state() {
	if ( false !== get_option( 'wp_storymap_upgrade_state', false ) ) {
		return;
	}

	$state = wp_storymap_has_legacy_data() ? 'legacy_data' : 'current';
	add_option( 'wp_storymap_upgrade_state', $state, '', false );
}
add_action( 'admin_init', 'wp_storymap_record_upgrade_state', 1 );

/**
 * Dismisses the upgrade warning for the current administrator.
 */
function wp_storymap_dismiss_upgrade_notice() {
	if ( ! isset( $_GET['wp_storymap_dismiss_upgrade_notice'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'wp-storymap' ) );
	}

	check_admin_referer( 'wp_storymap_dismiss_upgrade_notice' );
	update_user_meta( get_current_user_id(), 'wp_storymap_upgrade_notice_dismissed', 1 );

	$redirect_url = wp_get_referer();
	if ( ! $redirect_url ) {
		$redirect_url = admin_url();
	}
	$redirect_url = remove_query_arg(
		array( 'wp_storymap_dismiss_upgrade_notice', '_wpnonce' ),
		$redirect_url
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_init', 'wp_storymap_dismiss_upgrade_notice', 2 );

/**
 * Displays a backup warning for installations that already had legacy data.
 */
function wp_storymap_upgrade_backup_notice() {
	if (
		! current_user_can( 'manage_options' ) ||
		'legacy_data' !== get_option( 'wp_storymap_upgrade_state' ) ||
		get_user_meta( get_current_user_id(), 'wp_storymap_upgrade_notice_dismissed', true )
	) {
		return;
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg( 'wp_storymap_dismiss_upgrade_notice', '1' ),
		'wp_storymap_dismiss_upgrade_notice'
	);
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'WP Storymap update: back up your site before continuing.', 'wp-storymap' ); ?></strong>
			<?php esc_html_e( 'This installation contains stories or points from an older version. The new version may not be fully compatible with all legacy stories. Back up your database and files, and test the update on a staging site before editing or republishing existing stories.', 'wp-storymap' ); ?>
			<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss this notice', 'wp-storymap' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'wp_storymap_upgrade_backup_notice' );
