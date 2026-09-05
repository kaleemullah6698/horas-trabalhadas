<?php
/**
 * Plugin bootstrap: wires every component to WordPress.
 *
 * Responsibilities are deliberately split one-per-class so that the front-end
 * calculator, the admin screens, the licence client and the updater can each be
 * reasoned about (and disabled) in isolation.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin controller.
 */
class Plugin {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'plugins_loaded', array( Migrations::class, 'maybe_upgrade' ) );

		$assets = new Assets();
		$assets->register_hooks();

		$shortcode = new Shortcode( $assets );
		$shortcode->register_hooks();

		// The updater must run on the front end too: WordPress refreshes the
		// update transient from cron, which has no admin context.
		$updater = new Updater( new License() );
		$updater->register_hooks();

		if ( is_admin() ) {
			$admin = new Admin( new License(), $updater );
			$admin->register_hooks();
		}
	}

	/**
	 * Load translations. Runs on `init` because WordPress 6.7+ warns when a text
	 * domain is loaded earlier than that.
	 *
	 * @return void
	 */
	public function on_init() {
		load_plugin_textdomain(
			'horas-trabalhadas',
			false,
			dirname( HORAS_TRABALHADAS_BASENAME ) . '/languages'
		);
	}
}
