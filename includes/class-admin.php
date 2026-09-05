<?php
/**
 * Admin screens.
 *
 * Every state-changing action on this screen goes through the same three gates,
 * in order: capability check, nonce check, then sanitisation of each field. All
 * output is escaped at the point of printing.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and licence administration.
 */
class Admin {

	/**
	 * Settings page slug.
	 */
	const PAGE = 'horas-trabalhadas';

	/**
	 * Capability required to manage the plugin.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Licence client.
	 *
	 * @var License
	 */
	private $license;

	/**
	 * Updater.
	 *
	 * @var Updater
	 */
	private $updater;

	/**
	 * Notices queued for display.
	 *
	 * @var array<int,array{type:string,message:string}>
	 */
	private $notices = array();

	/**
	 * Constructor.
	 *
	 * @param License $license Licence client.
	 * @param Updater $updater Updater.
	 */
	public function __construct( License $license, Updater $updater ) {
		$this->license = $license;
		$this->updater = $updater;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this->license, 'maybe_refresh' ) );
		add_filter( 'plugin_action_links_' . HORAS_TRABALHADAS_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Horas Trabalhadas', 'horas-trabalhadas' ),
			__( 'Horas Trabalhadas', 'horas-trabalhadas' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a "Configurações" link on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		$url = add_query_arg( 'page', self::PAGE, admin_url( 'options-general.php' ) );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Configurações', 'horas-trabalhadas' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Handle form submissions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_POST['horas_trabalhadas_action'] ) ) {
			return;
		}

		// Gate 1 — capability. Checked before anything else is read.
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para alterar estas configurações.', 'horas-trabalhadas' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['horas_trabalhadas_action'] ) );

		// Gate 2 — nonce, scoped to the specific action (CSRF protection).
		check_admin_referer( 'horas_trabalhadas_' . $action );

		// Gate 3 — per-field sanitisation inside each handler.
		switch ( $action ) {
			case 'save_settings':
				$this->save_settings();
				break;

			case 'activate_license':
				$key    = isset( $_POST['license_key'] ) ? wp_unslash( $_POST['license_key'] ) : '';
				$result = $this->license->activate( $key );
				$this->notice( $result['success'] ? 'success' : 'error', $result['message'] );
				break;

			case 'deactivate_license':
				$result = $this->license->deactivate();
				$this->notice( 'success', $result['message'] );
				break;

			case 'check_updates':
				delete_site_transient( Updater::RELEASE_TRANSIENT );
				$release = $this->updater->get_release( true );
				$this->notice_for_release( $release );
				break;
		}

		// Redirect after POST so a refresh cannot resubmit the form.
		$this->stash_notices();
		wp_safe_redirect( add_query_arg( 'page', self::PAGE, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Persist the settings form.
	 *
	 * @return void
	 */
	private function save_settings() {
		$settings = get_option( License::SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Repository: stored as typed, then validated by Updater::repository(),
		// which is the single place that decides whether a value is usable.
		$repo = isset( $_POST['github_repository'] )
			? sanitize_text_field( wp_unslash( $_POST['github_repository'] ) )
			: '';

		$settings['github_repository'] = substr( $repo, 0, 220 );

		// Licence endpoint: only a well-formed absolute URL is ever stored.
		$api = isset( $_POST['license_api_url'] )
			? esc_url_raw( trim( wp_unslash( $_POST['license_api_url'] ) ), array( 'https' ) )
			: '';

		$settings['license_api_url'] = substr( (string) $api, 0, 500 );

		update_option( License::SETTINGS_OPTION, $settings, false );

		// Configuration changes invalidate any cached release lookup.
		delete_site_transient( Updater::RELEASE_TRANSIENT );

		$this->notice( 'success', __( 'Configurações salvas.', 'horas-trabalhadas' ) );

		if ( '' !== $settings['github_repository'] && '' === $this->updater->repository() ) {
			$this->notice(
				'error',
				__( 'O repositório informado é inválido. Use o formato proprietario/repositorio, por exemplo: minhaempresa/horas-trabalhadas.', 'horas-trabalhadas' )
			);
		}
	}

	/**
	 * Build the notice describing a manual update check.
	 *
	 * @param array<string,mixed>|null $release Release data.
	 * @return void
	 */
	private function notice_for_release( $release ) {
		if ( '' === $this->updater->repository() ) {
			$this->notice( 'error', __( 'Configure o repositório do GitHub antes de verificar atualizações.', 'horas-trabalhadas' ) );
			return;
		}

		if ( null === $release ) {
			$this->notice( 'error', __( 'Não foi possível obter informações de versão do GitHub. Verifique o repositório informado e tente novamente.', 'horas-trabalhadas' ) );
			return;
		}

		if ( version_compare( $release['version'], HORAS_TRABALHADAS_VERSION, '>' ) ) {
			$this->notice(
				'success',
				sprintf(
					/* translators: %s: version number. */
					__( 'Nova versão disponível: %s. Acesse a tela de Plugins para atualizar.', 'horas-trabalhadas' ),
					$release['version']
				)
			);
			return;
		}

		$this->notice( 'success', __( 'Você já está usando a versão mais recente.', 'horas-trabalhadas' ) );
	}

	/**
	 * Queue a notice.
	 *
	 * @param string $type    success|error.
	 * @param string $message Message text.
	 * @return void
	 */
	private function notice( $type, $message ) {
		$this->notices[] = array(
			'type'    => ( 'success' === $type ) ? 'success' : 'error',
			'message' => (string) $message,
		);
	}

	/**
	 * Carry notices across the post/redirect/get boundary.
	 *
	 * @return void
	 */
	private function stash_notices() {
		if ( empty( $this->notices ) ) {
			return;
		}

		set_transient( $this->notice_key(), $this->notices, MINUTE_IN_SECONDS );
	}

	/**
	 * Notices are stored per user so one administrator never sees another's.
	 *
	 * @return string
	 */
	private function notice_key() {
		return 'horas_trabalhadas_notices_' . get_current_user_id();
	}

	/**
	 * Read and clear stashed notices.
	 *
	 * @return array<int,array{type:string,message:string}>
	 */
	private function take_notices() {
		$notices = get_transient( $this->notice_key() );
		delete_transient( $this->notice_key() );

		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'horas-trabalhadas' ) );
		}

		$notices = $this->take_notices();
		$license = $this->license;
		$updater = $this->updater;
		$state   = $license->get_state();
		$status  = $license->status_summary();
		$release = $updater->get_release();

		include HORAS_TRABALHADAS_PATH . 'templates/admin-settings.php';
	}
}
