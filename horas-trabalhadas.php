<?php
/**
 * Plugin Name:       Horas Trabalhadas
 * Description:       Calculadora de horas trabalhadas e folha de ponto: intervalos, horas extras, dobro de horas, gorjetas, pagamento bruto e líquido, exportação CSV, impressão e link compartilhável. Publique em qualquer página com o shortcode [horas_trabalhadas].
 * Version:           2.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Horas Trabalhadas
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       horas-trabalhadas
 * Domain Path:       /languages
 * Update URI:        false
 *
 * `Update URI: false` tells WordPress that this plugin is not hosted on
 * WordPress.org, so the .org API can never hijack it with an unrelated plugin
 * that happens to share the slug. Updates are supplied by the GitHub release
 * updater in includes/class-updater.php, which injects into the standard
 * update transient and therefore keeps every native WordPress update feature
 * (the "Atualizar agora" link and automatic updates) working normally.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Constants — the plugin version is defined exactly once, here. Every other
 * component reads HORAS_TRABALHADAS_VERSION, and tools/check-version.js fails
 * the build if this value ever disagrees with readme.txt or the release tag.
 * ---------------------------------------------------------------------------
 */
define( 'HORAS_TRABALHADAS_VERSION', '2.0.0' );
define( 'HORAS_TRABALHADAS_FILE', __FILE__ );
define( 'HORAS_TRABALHADAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'HORAS_TRABALHADAS_URL', plugin_dir_url( __FILE__ ) );
define( 'HORAS_TRABALHADAS_BASENAME', plugin_basename( __FILE__ ) );
define( 'HORAS_TRABALHADAS_SLUG', 'horas-trabalhadas' );

/**
 * Minimal PSR-4 style autoloader.
 *
 * Maps HorasTrabalhadas\Foo_Bar to includes/class-foo-bar.php. Only classes in
 * this plugin's namespace are handled, so it never interferes with other
 * autoloaders on the site.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function autoload( $class_name ) {
	$prefix = __NAMESPACE__ . '\\';
	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$file     = 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
	$path     = HORAS_TRABALHADAS_PATH . 'includes/' . $file;

	if ( is_readable( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( __NAMESPACE__ . '\\autoload' );

/**
 * Boot the plugin.
 *
 * @return Plugin
 */
function plugin() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new Plugin();
	}
	return $instance;
}

plugin()->register();

register_activation_hook( __FILE__, array( Migrations::class, 'on_activation' ) );
register_deactivation_hook( __FILE__, array( Migrations::class, 'on_deactivation' ) );
