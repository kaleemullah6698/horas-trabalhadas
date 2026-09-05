<?php
/**
 * Settings screen template.
 *
 * Variables provided by Admin::render_page():
 *
 * @var array   $notices Queued admin notices.
 * @var License $license Licence client.
 * @var Updater $updater Updater.
 * @var array   $state   Licence state.
 * @var array   $status  Licence status summary.
 * @var array|null $release Latest release data, when available.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$htrb_repo        = $license->get_setting( 'github_repository', 'HORAS_TRABALHADAS_GITHUB_REPOSITORY' );
$htrb_api         = $license->get_setting( 'license_api_url', 'HORAS_TRABALHADAS_LICENSE_API_URL' );
$htrb_repo_locked = defined( 'HORAS_TRABALHADAS_GITHUB_REPOSITORY' );
$htrb_api_locked  = defined( 'HORAS_TRABALHADAS_LICENSE_API_URL' );
$htrb_update_ready = ( '' !== $updater->repository() );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Horas Trabalhadas', 'horas-trabalhadas' ); ?></h1>

	<?php foreach ( $notices as $htrb_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $htrb_notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $htrb_notice['message'] ); ?></p>
		</div>
	<?php endforeach; ?>

	<h2><?php esc_html_e( 'Como publicar a calculadora', 'horas-trabalhadas' ); ?></h2>
	<p>
		<?php esc_html_e( 'Insira o shortcode abaixo em qualquer página, post ou bloco:', 'horas-trabalhadas' ); ?>
		<code>[horas_trabalhadas]</code>
	</p>
	<p class="description">
		<?php esc_html_e( 'Use apenas um shortcode por página. Os shortcodes das versões anteriores continuam funcionando nas páginas já publicadas.', 'horas-trabalhadas' ); ?>
	</p>

	<hr>

	<h2><?php esc_html_e( 'Licença', 'horas-trabalhadas' ); ?></h2>

	<p>
		<strong><?php esc_html_e( 'Status:', 'horas-trabalhadas' ); ?></strong>
		<?php
		$htrb_marks = array(
			'ok'      => "\u{2713}",
			'warn'    => "\u{26A0}",
			'neutral' => "\u{2022}",
		);
		$htrb_mark  = isset( $htrb_marks[ $status['tone'] ] ) ? $htrb_marks[ $status['tone'] ] : $htrb_marks['neutral'];
		?>
		<span aria-hidden="true"><?php echo esc_html( $htrb_mark ); ?></span>
		<?php echo esc_html( $status['label'] ); ?>
	</p>

	<?php if ( 'active' === $state['status'] || '' !== $state['key'] ) : ?>
		<form method="post">
			<?php wp_nonce_field( 'horas_trabalhadas_deactivate_license' ); ?>
			<input type="hidden" name="horas_trabalhadas_action" value="deactivate_license">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Chave de licença', 'horas-trabalhadas' ); ?></th>
					<td>
						<?php
						/*
						 * Only the first and last four characters are shown. A key
						 * too short to mask safely is replaced entirely, so the
						 * screen never reveals a full key to a shoulder-surfer or
						 * to a screenshot pasted into a support ticket.
						 */
						$htrb_key    = (string) $state['key'];
						$htrb_masked = ( strlen( $htrb_key ) > 12 )
							? substr( $htrb_key, 0, 4 ) . str_repeat( '*', strlen( $htrb_key ) - 8 ) . substr( $htrb_key, -4 )
							: str_repeat( '*', max( 8, strlen( $htrb_key ) ) );
						?>
						<code><?php echo esc_html( $htrb_masked ); ?></code>
						<p class="description"><?php esc_html_e( 'A chave é armazenada apenas neste site e nunca é exibida por completo.', 'horas-trabalhadas' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Desativar licença', 'horas-trabalhadas' ), 'secondary' ); ?>
		</form>
	<?php else : ?>
		<form method="post">
			<?php wp_nonce_field( 'horas_trabalhadas_activate_license' ); ?>
			<input type="hidden" name="horas_trabalhadas_action" value="activate_license">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="horas-trabalhadas-license-key"><?php esc_html_e( 'Chave de licença', 'horas-trabalhadas' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text"
							id="horas-trabalhadas-license-key"
							name="license_key"
							value=""
							autocomplete="off"
							spellcheck="false"
						>
						<p class="description">
							<?php esc_html_e( 'A calculadora funciona normalmente mesmo sem licença ativa. A licença controla apenas o recebimento de atualizações.', 'horas-trabalhadas' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Ativar licença', 'horas-trabalhadas' ) ); ?>
		</form>
	<?php endif; ?>

	<hr>

	<h2><?php esc_html_e( 'Atualizações', 'horas-trabalhadas' ); ?></h2>

	<p>
		<strong><?php esc_html_e( 'Versão instalada:', 'horas-trabalhadas' ); ?></strong>
		<?php echo esc_html( HORAS_TRABALHADAS_VERSION ); ?>
	</p>

	<?php if ( ! $htrb_update_ready ) : ?>
		<p><?php esc_html_e( 'Informe o repositório do GitHub abaixo para habilitar as atualizações automáticas pela tela de Plugins.', 'horas-trabalhadas' ); ?></p>
	<?php elseif ( null === $release ) : ?>
		<p><?php esc_html_e( 'Nenhuma informação de versão foi obtida do GitHub ainda.', 'horas-trabalhadas' ); ?></p>
	<?php elseif ( version_compare( $release['version'], HORAS_TRABALHADAS_VERSION, '>' ) ) : ?>
		<p>
			<strong>
				<?php
				printf(
					/* translators: %s: version number. */
					esc_html__( 'Há uma nova versão disponível: %s', 'horas-trabalhadas' ),
					esc_html( $release['version'] )
				);
				?>
			</strong>
			<br>
			<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
				<?php esc_html_e( 'Ir para a tela de Plugins e atualizar', 'horas-trabalhadas' ); ?>
			</a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'Você já está usando a versão mais recente.', 'horas-trabalhadas' ); ?></p>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'horas_trabalhadas_check_updates' ); ?>
		<input type="hidden" name="horas_trabalhadas_action" value="check_updates">
		<?php submit_button( __( 'Verificar atualizações agora', 'horas-trabalhadas' ), 'secondary', 'submit', false ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Configuração', 'horas-trabalhadas' ); ?></h2>

	<form method="post">
		<?php wp_nonce_field( 'horas_trabalhadas_save_settings' ); ?>
		<input type="hidden" name="horas_trabalhadas_action" value="save_settings">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="horas-trabalhadas-repo"><?php esc_html_e( 'Repositório do GitHub', 'horas-trabalhadas' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="regular-text code"
						id="horas-trabalhadas-repo"
						name="github_repository"
						value="<?php echo esc_attr( $htrb_repo ); ?>"
						placeholder="proprietario/repositorio"
						<?php disabled( $htrb_repo_locked ); ?>
					>
					<p class="description">
						<?php esc_html_e( 'Formato proprietario/repositorio. As atualizações são lidas das Releases públicas desse repositório.', 'horas-trabalhadas' ); ?>
						<?php if ( $htrb_repo_locked ) : ?>
							<br><em><?php esc_html_e( 'Definido pela constante HORAS_TRABALHADAS_GITHUB_REPOSITORY no wp-config.php.', 'horas-trabalhadas' ); ?></em>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="horas-trabalhadas-api"><?php esc_html_e( 'URL da API de licenças', 'horas-trabalhadas' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						class="regular-text code"
						id="horas-trabalhadas-api"
						name="license_api_url"
						value="<?php echo esc_attr( $htrb_api ); ?>"
						placeholder="https://"
						<?php disabled( $htrb_api_locked ); ?>
					>
					<p class="description">
						<?php esc_html_e( 'Opcional. Somente endereços HTTPS são aceitos. Deixe em branco para distribuir o plugin sem controle de licença.', 'horas-trabalhadas' ); ?>
						<?php if ( $htrb_api_locked ) : ?>
							<br><em><?php esc_html_e( 'Definido pela constante HORAS_TRABALHADAS_LICENSE_API_URL no wp-config.php.', 'horas-trabalhadas' ); ?></em>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Salvar configurações', 'horas-trabalhadas' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Privacidade dos dados', 'horas-trabalhadas' ); ?></h2>
	<p>
		<?php esc_html_e( 'As folhas de ponto preenchidas pelos visitantes ficam apenas no navegador de cada pessoa (localStorage). O plugin não cria tabelas no banco de dados e não envia dados de jornada para nenhum servidor.', 'horas-trabalhadas' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Os cálculos usam o fuso horário configurado em Configurações › Geral deste site.', 'horas-trabalhadas' ); ?>
		<strong><?php echo esc_html( wp_timezone_string() ); ?></strong>
	</p>
</div>
