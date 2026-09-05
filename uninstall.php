<?php
/**
 * Uninstall routine.
 *
 * Fires only when an administrator deletes the plugin from the WordPress admin.
 * It removes the options this plugin created and nothing else.
 *
 * No timesheet data is touched, because none exists server-side: every timesheet
 * lives in the visitor's own browser localStorage and is never transmitted. The
 * plugin creates no database tables, no post types and no user meta.
 *
 * @package HorasTrabalhadas
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Options and transients created by this plugin, current and legacy.
 *
 * The pre-2.0 option name is included so that deleting the plugin leaves nothing
 * behind even on a site that was upgraded from the previous release.
 */
$horas_trabalhadas_options = array(
	'horas_trabalhadas_version',
	'horas_trabalhadas_settings',
	'horas_trabalhadas_license',
	'horas_trabalhadas_upgraded_from',
	'whp_version',
);

/**
 * Remove every option and transient belonging to this plugin on one site.
 *
 * @return void
 */
function horas_trabalhadas_delete_site_data() {
	global $horas_trabalhadas_options;

	foreach ( $horas_trabalhadas_options as $option ) {
		delete_option( $option );
	}

	delete_site_transient( 'horas_trabalhadas_release' );
}

horas_trabalhadas_delete_site_data();

// For multisite installs, clean every site in the network. get_sites() is used
// rather than a direct query so WordPress handles pagination and caching.
if ( is_multisite() ) {
	$horas_trabalhadas_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $horas_trabalhadas_sites as $horas_trabalhadas_site_id ) {
		switch_to_blog( (int) $horas_trabalhadas_site_id );
		horas_trabalhadas_delete_site_data();
		restore_current_blog();
	}
}
