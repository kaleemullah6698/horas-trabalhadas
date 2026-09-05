<?php
/**
 * Asset registration & on-demand enqueueing.
 *
 * Assets are registered globally but only enqueued on pages that actually render
 * the calculator, so CSS/JS never load on the rest of the site.
 *
 * Two enqueue paths exist, in this order:
 *
 * 1. `wp_enqueue_scripts` scans the queried post for the shortcode. When it is
 *    found the stylesheet is printed in <head>, which is where it belongs — a
 *    stylesheet that arrives in the footer restyles content that is already
 *    painted, which is exactly the kind of layout shift CLS measures.
 * 2. The shortcode callback itself, as a fallback for page builders, widgets and
 *    template calls whose content the scan cannot see. There the assets go to the
 *    footer, which still works.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end asset controller.
 */
class Assets {

	const SCRIPT_HANDLE = 'horas-trabalhadas';
	const STYLE_HANDLE  = 'horas-trabalhadas';
	const FONT_HANDLE   = 'horas-trabalhadas-fonts';

	/**
	 * Guards against enqueueing twice when several shortcodes are on one page.
	 *
	 * @var bool
	 */
	private $enqueued = false;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
	}

	/**
	 * Whether to serve the unminified sources (for debugging).
	 *
	 * @return bool
	 */
	private function use_debug_assets() {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
	}

	/**
	 * Register all styles and scripts, then enqueue them if the queried post
	 * visibly contains the shortcode.
	 *
	 * @return void
	 */
	public function register() {
		$this->register_handles();

		if ( $this->post_has_shortcode() ) {
			$this->enqueue();
		}
	}

	/**
	 * Declare the handles without enqueueing them.
	 *
	 * Kept separate from register() so the shortcode fallback path can make sure
	 * the handles exist and then go straight on to enqueueing. Folding the two
	 * together would mean that on a non-singular page — a widget, an archive, a
	 * page builder — the content scan returns false and the assets are never
	 * enqueued at all.
	 *
	 * @return void
	 */
	private function register_handles() {
		$suffix = $this->use_debug_assets() ? '' : '.min';

		// Google Fonts (Inter). Loaded non-blocking below; degrades gracefully to
		// the system font stack if it is slow or blocked.
		wp_register_style(
			self::FONT_HANDLE,
			'https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&display=swap',
			array(),
			null
		);

		// Main scoped stylesheet. Deliberately has no dependency on the font
		// stylesheet: the fonts must never sit on the critical path for these rules.
		wp_register_style(
			self::STYLE_HANDLE,
			HORAS_TRABALHADAS_URL . 'assets/css/styles' . $suffix . '.css',
			array(),
			HORAS_TRABALHADAS_VERSION
		);

		// Calculator application logic. No library dependencies.
		wp_register_script(
			self::SCRIPT_HANDLE,
			HORAS_TRABALHADAS_URL . 'assets/js/app' . $suffix . '.js',
			array(),
			HORAS_TRABALHADAS_VERSION,
			true
		);

		// WordPress 6.3+ prints `defer` natively; older versions fall back to the
		// filter in defer_script() below.
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );
		}
	}

	/**
	 * Whether the post being viewed contains the calculator shortcode.
	 *
	 * Only inspects the already-loaded post object, so this costs no extra query.
	 *
	 * @return bool
	 */
	private function post_has_shortcode() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		foreach ( Shortcode::tags() as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue the registered assets. Also called from the shortcode so it covers
	 * page builders the content scan cannot see. Runs once per request even if the
	 * shortcode appears multiple times.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		// Ensure the handles exist even if `wp_enqueue_scripts` order differs, e.g.
		// the shortcode is rendered very early or from a REST/preview context.
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			$this->register_handles();
		}

		wp_enqueue_style( self::FONT_HANDLE );
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.horasTrabalhadasCfg = ' . wp_json_encode( $this->script_config() ) . ';',
			'before'
		);

		// These filters only matter on a page that renders the calculator, so they
		// are attached here rather than site-wide.
		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
		add_filter( 'style_loader_tag', array( $this, 'non_blocking_font' ), 10, 2 );
		add_filter( 'script_loader_tag', array( $this, 'defer_script' ), 10, 2 );
	}

	/**
	 * Runtime configuration handed to the browser.
	 *
	 * The calculator runs entirely client-side, so "hoje" would otherwise be the
	 * visitor's own device date. Passing the site's configured timezone lets the
	 * script resolve the current date in the site's timezone instead, which is
	 * what an employer in Brazil expects from presets such as "Esta semana" when
	 * an employee opens the page from another country or with a wrong device clock.
	 *
	 * Only non-sensitive site configuration is exposed: timezone, UTC offset,
	 * week-start day and locale. No credentials, licence keys or user data are
	 * ever sent to the front end.
	 *
	 * @return array<string,mixed>
	 */
	private function script_config() {
		/*
		 * wp_timezone_string() returns either an IANA identifier ("America/Sao_Paulo")
		 * or a fixed "+HH:MM" offset when the administrator configured an offset
		 * rather than a city. The IANA name is preferred because the browser's Intl
		 * database then applies the correct DST/historical rules for that zone, which
		 * matters for Brazilian zones whose DST rules have changed over the years.
		 * The numeric offset is sent as a fallback for offset-configured sites and
		 * for the rare browser without Intl.
		 *
		 * The site's own timezone is used — never a hard-coded one — so an
		 * administrator who deliberately configured America/Manaus, America/Belem or
		 * America/Rio_Branco gets that zone rather than Sao Paulo.
		 */
		$timezone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';
		$is_iana  = ( false !== strpos( $timezone, '/' ) );

		$config = array(
			'timezone'   => $is_iana ? $timezone : '',
			'utcOffset'  => (float) get_option( 'gmt_offset', 0 ),
			'weekStart'  => (int) get_option( 'start_of_week', 1 ),
			'locale'     => determine_locale(),
			'serverDate' => current_time( 'Y-m-d' ),
			'version'    => HORAS_TRABALHADAS_VERSION,
		);

		/**
		 * Filter the configuration handed to the calculator script.
		 *
		 * @param array $config Configuration array.
		 */
		return (array) apply_filters( 'horas_trabalhadas_script_config', $config );
	}

	/**
	 * Warm up the font connection while the HTML is still parsing.
	 *
	 * Effective only on the early enqueue path: `wp_resource_hints` runs at
	 * priority 2 of `wp_head`, just after `wp_enqueue_scripts`.
	 *
	 * @param array  $urls Existing hints for this relation type.
	 * @param string $type Relation type.
	 * @return array
	 */
	public function resource_hints( $urls, $type ) {
		if ( 'preconnect' === $type ) {
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
			$urls[] = array( 'href' => 'https://fonts.googleapis.com' );
		}
		return $urls;
	}

	/**
	 * Load the font stylesheet without blocking the first paint.
	 *
	 * The media="print" / onload swap is the standard non-blocking CSS pattern; a
	 * noscript copy keeps the fonts working with JavaScript disabled.
	 *
	 * @param string $tag    The full link tag.
	 * @param string $handle Style handle.
	 * @return string
	 */
	public function non_blocking_font( $tag, $handle ) {
		if ( self::FONT_HANDLE !== $handle ) {
			return $tag;
		}

		// WordPress prints media='all' (single quotes) by default; handle both styles.
		$single = "media='all'";
		$double = 'media="all"';
		$async  = $tag;

		foreach ( array( $single, $double ) as $needle ) {
			if ( false === strpos( $tag, $needle ) ) {
				continue;
			}
			// The onload body contains single quotes, so it is always double quoted,
			// whatever quote style WordPress used for the media attribute.
			$quote       = ( $single === $needle ) ? chr( 39 ) : chr( 34 );
			$replacement = 'media=' . $quote . 'print' . $quote
				. ' onload=' . chr( 34 ) . 'this.media=' . chr( 39 ) . 'all' . chr( 39 )
				. ';this.onload=null;' . chr( 34 );
			$async       = str_replace( $needle, $replacement, $tag );
			break;
		}

		// Bail out unchanged if WordPress ever prints a different media attribute.
		if ( $async === $tag ) {
			return $tag;
		}

		// The noscript copy must not repeat the id attribute: two elements sharing
		// one id is invalid HTML and corrupts the accessibility tree.
		$fallback = preg_replace( '/\s+id=([\'"])[^\'"]*\1/', '', $tag, 1 );
		if ( null === $fallback ) {
			$fallback = $tag;
		}

		return $async . '<noscript>' . $fallback . '</noscript>' . "\n";
	}

	/**
	 * Add `defer` on WordPress versions without the native loading strategy API.
	 *
	 * @param string $tag    The full script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function defer_script( $tag, $handle ) {
		if ( self::SCRIPT_HANDLE !== $handle || false !== strpos( $tag, ' defer' ) ) {
			return $tag;
		}
		return str_replace( ' src=', ' defer src=', $tag );
	}
}
