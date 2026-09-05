<?php
/**
 * Shortcode handler.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the calculator via shortcode.
 */
class Shortcode {

	/**
	 * Primary shortcode tag for this product.
	 */
	const TAG = 'horas_trabalhadas';

	/**
	 * Shortcode tags kept purely for backward compatibility.
	 *
	 * These two tags were shipped by the pre-2.0 releases and are already saved
	 * inside published post content on live sites. Renaming them away would blank
	 * the calculator on every page that uses them, so they remain registered
	 * permanently as aliases. They are internal WordPress identifiers, never shown
	 * to a visitor, so they carry no user-facing branding.
	 *
	 * @var string[]
	 */
	const LEGACY_TAGS = array( 'work_hours_pro', 'work_hours_calculator' );

	/**
	 * Whether the calculator has already been rendered this request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Asset controller.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Constructor.
	 *
	 * @param Assets $assets Asset controller.
	 */
	public function __construct( Assets $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Every tag this plugin answers to.
	 *
	 * @return string[]
	 */
	public static function tags() {
		return array_merge( array( self::TAG ), self::LEGACY_TAGS );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Register the primary tag and its compatibility aliases.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		foreach ( self::tags() as $tag ) {
			add_shortcode( $tag, array( $this, 'render' ) );
		}
	}

	/**
	 * Render the calculator.
	 *
	 * Outputs only the calculator container (no html/head/body) so it embeds
	 * cleanly inside pages, posts, Gutenberg, Elementor, Classic Editor and block
	 * themes. It deliberately contains no h1: the surrounding WordPress page
	 * supplies the page heading, and a second h1 would compete with it.
	 *
	 * The calculator is a single-instance tool: its markup uses fixed element IDs
	 * and its script binds to the first container it finds. A second shortcode on
	 * the same page therefore never worked — it only produced a dead copy plus
	 * duplicate IDs, which is invalid HTML and corrupts the accessibility tree.
	 * Later occurrences render nothing, and say why when WP_DEBUG is on.
	 *
	 * @param array|string $atts    Shortcode attributes (reserved for future use).
	 * @param string|null  $content Enclosed content (unused).
	 * @param string       $tag     The tag that invoked this callback.
	 * @return string HTML markup for the calculator.
	 */
	public function render( $atts = array(), $content = null, $tag = '' ) {
		// Reserved for future options; parsed now so custom attributes never error.
		shortcode_atts( array(), (array) $atts, self::TAG );

		if ( $this->rendered ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- ' . esc_html__( 'Horas Trabalhadas: a calculadora já foi exibida nesta página. Use apenas um shortcode por página.', 'horas-trabalhadas' ) . ' -->';
			}
			return '';
		}
		$this->rendered = true;

		// Load CSS/JS only because this shortcode is present on the page.
		$this->assets->enqueue();

		$template = HORAS_TRABALHADAS_PATH . 'templates/calculator.php';
		if ( ! file_exists( $template ) ) {
			return '';
		}

		// Buffer the template output and return it (shortcodes must return, not echo).
		ob_start();
		include $template;
		return (string) ob_get_clean();
	}
}
