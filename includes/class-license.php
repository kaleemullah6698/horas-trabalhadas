<?php
/**
 * Licence client.
 *
 * ARCHITECTURE — read this before changing anything here.
 *
 * This class is a *client*. It holds no secret of its own, and it must never be
 * given one: every file in this plugin is downloadable by anyone who can install
 * it, so any credential placed here is public. In particular a GitHub personal
 * access token embedded in a plugin provides no licence protection whatsoever —
 * it only leaks the token.
 *
 * The intended production topology is:
 *
 *     WordPress site (this plugin)
 *         |  POST licence key + site URL over HTTPS
 *         v
 *     Licence server you control  (LICENSE_API_URL)
 *         |  verifies key, seat count, expiry; holds every secret
 *         v
 *     Private release storage / signed download URL
 *
 * The licence server is the only component that may hold a GitHub token, a
 * signing key, or a customer database. It answers a small JSON contract
 * (documented in docs/licenca-api.md) and, for private distribution, returns a
 * short-lived download URL that this plugin hands to the WordPress upgrader.
 *
 * Failure policy: the calculator NEVER stops working because the licence server
 * is unreachable. An unreachable server keeps the last known status for a grace
 * period; only update delivery is gated on licence state, and only when a
 * licence server is actually configured.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and validates the site licence.
 */
class License {

	/**
	 * Option holding licence state.
	 */
	const OPTION = 'horas_trabalhadas_license';

	/**
	 * Option holding plugin settings (repository, licence endpoint).
	 */
	const SETTINGS_OPTION = 'horas_trabalhadas_settings';

	/**
	 * How long a previously valid licence survives an unreachable licence server.
	 */
	const GRACE_PERIOD = 14 * DAY_IN_SECONDS;

	/**
	 * How often the licence is silently re-validated.
	 */
	const CHECK_INTERVAL = DAY_IN_SECONDS;

	/**
	 * Default licence state.
	 *
	 * @return array<string,mixed>
	 */
	private function defaults() {
		return array(
			'key'         => '',
			'status'      => 'inactive',
			'expires'     => '',
			'site'        => '',
			'last_check'  => 0,
			'last_error'  => '',
			'grace_until' => 0,
		);
	}

	/**
	 * Read the stored licence state.
	 *
	 * @return array<string,mixed>
	 */
	public function get_state() {
		$state = get_option( self::OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		return array_merge( $this->defaults(), $state );
	}

	/**
	 * Persist licence state.
	 *
	 * @param array<string,mixed> $state Licence state.
	 * @return void
	 */
	private function save_state( array $state ) {
		update_option( self::OPTION, array_merge( $this->defaults(), $state ), false );
	}

	/**
	 * Read a plugin setting, letting a wp-config.php constant win.
	 *
	 * Constants are supported so an agency can pin the repository or licence
	 * endpoint per environment without exposing an editable field to site admins.
	 *
	 * @param string $key      Setting key.
	 * @param string $constant Overriding constant name.
	 * @return string
	 */
	public function get_setting( $key, $constant = '' ) {
		if ( '' !== $constant && defined( $constant ) ) {
			return (string) constant( $constant );
		}

		$settings = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) || ! isset( $settings[ $key ] ) ) {
			return '';
		}

		return (string) $settings[ $key ];
	}

	/**
	 * The configured licence API endpoint, or an empty string when licensing has
	 * not been set up.
	 *
	 * @return string
	 */
	public function api_url() {
		$url = trim( $this->get_setting( 'license_api_url', 'HORAS_TRABALHADAS_LICENSE_API_URL' ) );
		if ( '' === $url ) {
			return '';
		}

		// Only absolute HTTPS endpoints are ever contacted. Refusing plain HTTP
		// prevents the licence key being readable on the wire, and refusing
		// anything wp_http_validate_url() rejects blocks requests aimed at the
		// site's own private network (SSRF).
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return '';
		}
		if ( ! wp_http_validate_url( $url ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Whether a licence server has been configured at all.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->api_url();
	}

	/**
	 * Whether the licence currently permits updates.
	 *
	 * When no licence server is configured the plugin is treated as unlicensed-but-
	 * permitted: this is the open/self-hosted distribution mode, and gating updates
	 * would simply break the update system for no benefit.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( ! $this->is_configured() ) {
			return true;
		}

		$state = $this->get_state();

		if ( 'active' === $state['status'] ) {
			return true;
		}

		// A previously active licence survives an unreachable server for the grace
		// period, so a licence-server outage never blocks a security update.
		if ( 'unreachable' === $state['status'] && $state['grace_until'] > time() ) {
			return true;
		}

		return false;
	}

	/**
	 * Normalise a licence key from user input.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	public function sanitize_key( $key ) {
		$key = sanitize_text_field( wp_unslash( (string) $key ) );
		// Licence keys are opaque tokens; restricting the alphabet keeps anything
		// that could be interpreted as markup or a control character out of the
		// option, the admin screen and the outbound request.
		$key = preg_replace( '/[^A-Za-z0-9\-_]/', '', $key );

		return substr( (string) $key, 0, 128 );
	}

	/**
	 * Activate a licence key for this site.
	 *
	 * @param string $key Licence key.
	 * @return array{success:bool,message:string}
	 */
	public function activate( $key ) {
		$key = $this->sanitize_key( $key );

		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Informe uma chave de licença válida.', 'horas-trabalhadas' ),
			);
		}

		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Nenhum servidor de licenças foi configurado. Informe a URL da API de licenças nas configurações.', 'horas-trabalhadas' ),
			);
		}

		$response = $this->request( 'activate', $key );

		if ( is_wp_error( $response ) ) {
			$state               = $this->get_state();
			$state['key']        = $key;
			$state['last_error'] = $response->get_error_message();
			$state['last_check'] = time();
			$this->save_state( $state );

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message returned by the licence server. */
					__( 'Não foi possível contatar o servidor de licenças: %s', 'horas-trabalhadas' ),
					$response->get_error_message()
				),
			);
		}

		$active = ! empty( $response['active'] );

		$this->save_state(
			array(
				'key'         => $key,
				'status'      => $active ? 'active' : 'invalid',
				'expires'     => isset( $response['expires'] ) ? sanitize_text_field( (string) $response['expires'] ) : '',
				'site'        => home_url(),
				'last_check'  => time(),
				'last_error'  => $active ? '' : ( isset( $response['message'] ) ? sanitize_text_field( (string) $response['message'] ) : '' ),
				'grace_until' => $active ? time() + self::GRACE_PERIOD : 0,
			)
		);

		// A licence change can change what the update endpoint is willing to serve.
		delete_site_transient( Updater::RELEASE_TRANSIENT );

		if ( $active ) {
			return array(
				'success' => true,
				'message' => __( 'Licença ativada com sucesso.', 'horas-trabalhadas' ),
			);
		}

		return array(
			'success' => false,
			'message' => isset( $response['message'] ) && '' !== $response['message']
				? sanitize_text_field( (string) $response['message'] )
				: __( 'A chave de licença não foi aceita pelo servidor.', 'horas-trabalhadas' ),
		);
	}

	/**
	 * Release this site's licence seat.
	 *
	 * The local state is always cleared, even when the server cannot be reached,
	 * so an administrator is never stuck with a licence they cannot remove.
	 *
	 * @return array{success:bool,message:string}
	 */
	public function deactivate() {
		$state = $this->get_state();

		if ( '' !== $state['key'] && $this->is_configured() ) {
			$this->request( 'deactivate', $state['key'] );
		}

		$this->save_state( $this->defaults() );
		delete_site_transient( Updater::RELEASE_TRANSIENT );

		return array(
			'success' => true,
			'message' => __( 'Licença desativada neste site.', 'horas-trabalhadas' ),
		);
	}

	/**
	 * Re-validate the stored licence at most once per CHECK_INTERVAL.
	 *
	 * @return void
	 */
	public function maybe_refresh() {
		if ( ! $this->is_configured() ) {
			return;
		}

		$state = $this->get_state();

		if ( '' === $state['key'] ) {
			return;
		}

		if ( ( time() - (int) $state['last_check'] ) < self::CHECK_INTERVAL ) {
			return;
		}

		$response = $this->request( 'check', $state['key'] );

		if ( is_wp_error( $response ) ) {
			// Keep the licence usable until the grace period runs out rather than
			// punishing the customer for our server being down.
			$state['status']      = 'active' === $state['status'] ? 'unreachable' : $state['status'];
			$state['last_error']  = $response->get_error_message();
			$state['last_check']  = time();
			$state['grace_until'] = $state['grace_until'] > 0 ? $state['grace_until'] : time() + self::GRACE_PERIOD;
			$this->save_state( $state );
			return;
		}

		$active = ! empty( $response['active'] );

		$state['status']      = $active ? 'active' : 'invalid';
		$state['expires']     = isset( $response['expires'] ) ? sanitize_text_field( (string) $response['expires'] ) : '';
		$state['last_check']  = time();
		$state['last_error']  = $active ? '' : ( isset( $response['message'] ) ? sanitize_text_field( (string) $response['message'] ) : '' );
		$state['grace_until'] = $active ? time() + self::GRACE_PERIOD : 0;

		$this->save_state( $state );
	}

	/**
	 * Perform a licence API request.
	 *
	 * The response is treated as untrusted input: it must be HTTP 200, valid JSON,
	 * and a JSON object. Nothing from the response is ever executed, written to
	 * disk, or used as a URL without separate validation by the caller.
	 *
	 * @param string $action One of activate|deactivate|check.
	 * @param string $key    Licence key.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function request( $action, $key ) {
		$url = $this->api_url();

		if ( '' === $url ) {
			return new \WP_Error( 'horas_trabalhadas_no_endpoint', __( 'Servidor de licenças não configurado.', 'horas-trabalhadas' ) );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'HorasTrabalhadas/' . HORAS_TRABALHADAS_VERSION . '; ' . home_url(),
				'headers'    => array( 'Accept' => 'application/json' ),
				'body'       => array(
					'action'      => $action,
					'license_key' => $key,
					'site_url'    => home_url(),
					'plugin'      => HORAS_TRABALHADAS_SLUG,
					'version'     => HORAS_TRABALHADAS_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'horas_trabalhadas_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'O servidor de licenças respondeu com o código HTTP %d.', 'horas-trabalhadas' ),
					$code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'horas_trabalhadas_bad_response',
				__( 'O servidor de licenças retornou uma resposta inválida.', 'horas-trabalhadas' )
			);
		}

		return $body;
	}

	/**
	 * Human-readable status for the admin screen.
	 *
	 * @return array{code:string,label:string,tone:string}
	 */
	public function status_summary() {
		if ( ! $this->is_configured() ) {
			return array(
				'code'  => 'unconfigured',
				'label' => __( 'Licenciamento não configurado — o plugin funciona normalmente.', 'horas-trabalhadas' ),
				'tone'  => 'neutral',
			);
		}

		$state = $this->get_state();

		switch ( $state['status'] ) {
			case 'active':
				return array(
					'code'  => 'active',
					'label' => '' !== $state['expires']
						? sprintf(
							/* translators: %s: expiry date. */
							__( 'Licença ativa — válida até %s.', 'horas-trabalhadas' ),
							$this->format_date( $state['expires'] )
						)
						: __( 'Licença ativa.', 'horas-trabalhadas' ),
					'tone'  => 'ok',
				);

			case 'unreachable':
				return array(
					'code'  => 'unreachable',
					'label' => sprintf(
						/* translators: %s: date on which the grace period ends. */
						__( 'Servidor de licenças indisponível. A licença continua válida até %s.', 'horas-trabalhadas' ),
						$this->format_date( gmdate( 'Y-m-d', (int) $state['grace_until'] ) )
					),
					'tone'  => 'warn',
				);

			case 'invalid':
				return array(
					'code'  => 'invalid',
					'label' => '' !== $state['last_error']
						? $state['last_error']
						: __( 'Licença inválida ou expirada.', 'horas-trabalhadas' ),
					'tone'  => 'warn',
				);
		}

		return array(
			'code'  => 'inactive',
			'label' => __( 'Licença não ativada.', 'horas-trabalhadas' ),
			'tone'  => 'warn',
		);
	}

	/**
	 * Format a Y-m-d date using the site's configured date format.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private function format_date( $date ) {
		$timestamp = strtotime( (string) $date );
		if ( ! $timestamp ) {
			return (string) $date;
		}

		return wp_date( (string) get_option( 'date_format', 'd/m/Y' ), $timestamp );
	}
}
