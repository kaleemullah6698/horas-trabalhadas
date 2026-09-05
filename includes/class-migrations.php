<?php
/**
 * Versioned, idempotent data migrations.
 *
 * The plugin stores no user timesheet data on the server — every timesheet lives
 * in the visitor's browser localStorage — so migrations only ever touch this
 * plugin's own options. Nothing here deletes user content.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upgrade routines keyed by plugin version.
 */
class Migrations {

	/**
	 * Option holding the schema/plugin version last run.
	 */
	const VERSION_OPTION = 'horas_trabalhadas_version';

	/**
	 * Legacy option written by the pre-2.0 releases of this plugin.
	 *
	 * Retained as a constant (not renamed away) because it is the only handle we
	 * have on data written by installations of the previous version.
	 */
	const LEGACY_VERSION_OPTION = 'whp_version';

	/**
	 * Run on activation. Also covers a fresh install.
	 *
	 * @return void
	 */
	public static function on_activation() {
		self::maybe_upgrade();
		// A newly activated plugin should check for updates promptly rather than
		// waiting for the next scheduled transient refresh.
		delete_site_transient( Updater::RELEASE_TRANSIENT );
	}

	/**
	 * Run on deactivation. Nothing persistent to tear down; the cached release
	 * lookup is dropped so a reactivation re-checks immediately.
	 *
	 * @return void
	 */
	public static function on_deactivation() {
		delete_site_transient( Updater::RELEASE_TRANSIENT );
	}

	/**
	 * Apply any migration the stored version has not seen yet.
	 *
	 * Safe to call on every request: it returns immediately once the stored
	 * version matches the running version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = get_option( self::VERSION_OPTION, '' );

		if ( HORAS_TRABALHADAS_VERSION === $stored ) {
			return;
		}

		// ---- 2.0.0: adopt the new option name, carrying the old value over. ----
		if ( '' === $stored ) {
			$legacy = get_option( self::LEGACY_VERSION_OPTION, false );
			if ( false !== $legacy ) {
				/*
				 * Preserve evidence of where this install came from so support can
				 * tell an upgrade apart from a fresh install, then remove the old
				 * option. Only this plugin ever wrote it, so nothing else breaks.
				 */
				update_option( 'horas_trabalhadas_upgraded_from', (string) $legacy, false );
				delete_option( self::LEGACY_VERSION_OPTION );
			}
		}

		update_option( self::VERSION_OPTION, HORAS_TRABALHADAS_VERSION, false );
	}
}
