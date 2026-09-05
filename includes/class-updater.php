<?php
/**
 * GitHub release updater.
 *
 * This does NOT replace the WordPress update system — it feeds it. The class
 * injects release metadata into the standard `update_plugins` site transient, so
 * every native WordPress feature keeps working unchanged:
 *
 *   - the "Há uma nova versão disponível / Atualizar agora" row on Plugins,
 *   - the update count badge and the Updates screen,
 *   - the plugin details modal (via the `plugins_api` filter),
 *   - and WordPress's own automatic background updates, including the
 *     "Ativar atualizações automáticas" link, which core only offers for plugins
 *     present in the transient's `response` or `no_update` lists.
 *
 * SECURITY MODEL. Everything returned by GitHub is untrusted input:
 *   - the response must be HTTP 200 and decode to a JSON object;
 *   - the tag must parse as a plain semantic version;
 *   - the download URL must be absolute HTTPS on a host from a fixed allow-list,
 *     so a compromised or spoofed response can never point the WordPress
 *     upgrader at an arbitrary server;
 *   - the repository slug itself is validated as `owner/name` before it is ever
 *     interpolated into an API URL, which keeps a hostile setting value from
 *     redirecting the request elsewhere.
 *
 * No credential is stored or sent. Only public release metadata is read, so the
 * repository must be public — see class-license.php for why a token embedded in
 * a plugin would be worthless anyway, and docs/atualizacoes.md for the private
 * distribution topology.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves plugin updates from GitHub Releases.
 */
class Updater {

	/**
	 * Site transient caching the latest release lookup.
	 */
	const RELEASE_TRANSIENT = 'horas_trabalhadas_release';

	/**
	 * How long a successful lookup is cached.
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * How long a failed lookup is cached, so an outage cannot hammer the API.
	 */
	const FAILURE_TTL = 1 * HOUR_IN_SECONDS;

	/**
	 * Hosts permitted to serve an update package.
	 *
	 * GitHub serves release assets from several hostnames depending on how the
	 * asset was uploaded and whether a redirect is followed, so all of them are
	 * listed. Nothing outside this list is ever handed to the upgrader.
	 *
	 * @var string[]
	 */
	const ALLOWED_PACKAGE_HOSTS = array(
		'github.com',
		'api.github.com',
		'codeload.github.com',
		'objects.githubusercontent.com',
		'release-assets.githubusercontent.com',
	);

	/**
	 * Licence client.
	 *
	 * @var License
	 */
	private $license;

	/**
	 * Constructor.
	 *
	 * @param License $license Licence client.
	 */
	public function __construct( License $license ) {
		$this->license = $license;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'normalise_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache' ), 10, 2 );
	}

	/**
	 * The configured repository in `owner/name` form, or '' when not configured.
	 *
	 * @return string
	 */
	public function repository() {
		$repo = trim( $this->license->get_setting( 'github_repository', 'HORAS_TRABALHADAS_GITHUB_REPOSITORY' ) );

		// Accept a full GitHub URL and reduce it to owner/name for convenience.
		if ( 0 === strpos( $repo, 'http' ) ) {
			$path = (string) wp_parse_url( $repo, PHP_URL_PATH );
			$repo = trim( $path, '/' );
		}

		$repo = preg_replace( '#\.git$#', '', $repo );

		// Strict shape check: exactly two path segments of GitHub-legal characters.
		// This is what stops a hostile setting from steering the API request.
		if ( ! preg_match( '#^[A-Za-z0-9_.\-]{1,100}/[A-Za-z0-9_.\-]{1,100}$#', (string) $repo ) ) {
			return '';
		}

		return (string) $repo;
	}

	/**
	 * Whether update checking is possible and permitted right now.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		if ( '' === $this->repository() ) {
			return false;
		}

		// When a licence server is configured, an invalid licence stops updates
		// being offered. It never stops the calculator working.
		return $this->license->is_active();
	}

	/**
	 * Fetch (and cache) the latest release from GitHub.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array<string,mixed>|null Normalised release data, or null.
	 */
	public function get_release( $force = false ) {
		if ( ! $this->is_enabled() ) {
			return null;
		}

		if ( ! $force ) {
			$cached = get_site_transient( self::RELEASE_TRANSIENT );
			if ( is_array( $cached ) ) {
				return isset( $cached['version'] ) ? $cached : null;
			}
		}

		$repo = $this->repository();
		$url  = sprintf( 'https://api.github.com/repos/%s/releases/latest', $repo );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'HorasTrabalhadas/' . HORAS_TRABALHADAS_VERSION . '; ' . home_url(),
				'headers'    => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		$release = $this->parse_release( $response );

		if ( null === $release ) {
			// Cache the failure briefly so a rate limit or outage does not retry on
			// every single page load.
			set_site_transient( self::RELEASE_TRANSIENT, array( 'failed' => true ), self::FAILURE_TTL );
			return null;
		}

		set_site_transient( self::RELEASE_TRANSIENT, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Validate and normalise a GitHub API response.
	 *
	 * @param array|\WP_Error $response Raw HTTP response.
	 * @return array<string,mixed>|null
	 */
	private function parse_release( $response ) {
		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		// Never offer a draft or a pre-release as a production update.
		if ( ! empty( $body['draft'] ) || ! empty( $body['prerelease'] ) ) {
			return null;
		}

		$version = $this->normalise_version( $body['tag_name'] );
		if ( '' === $version ) {
			return null;
		}

		$package = $this->select_package( $body );
		if ( '' === $package ) {
			return null;
		}

		return array(
			'version'   => $version,
			'package'   => $package,
			'url'       => $this->safe_release_url( $body ),
			'changelog' => isset( $body['body'] ) ? (string) $body['body'] : '',
			'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);
	}

	/**
	 * Reduce a release tag to a bare semantic version.
	 *
	 * Accepts `v1.2.3` and `1.2.3`; rejects anything that is not a plain dotted
	 * version so a crafted tag cannot smuggle characters into version_compare or
	 * into the admin screen.
	 *
	 * @param string $tag Release tag.
	 * @return string Version, or '' when the tag is not a valid version.
	 */
	private function normalise_version( $tag ) {
		$tag = ltrim( trim( (string) $tag ), 'vV' );

		if ( ! preg_match( '/^\d{1,5}(\.\d{1,5}){1,3}$/', $tag ) ) {
			return '';
		}

		return $tag;
	}

	/**
	 * Choose the update package for a release.
	 *
	 * A purpose-built asset named `horas-trabalhadas.zip` is strongly preferred:
	 * it is produced by the release workflow with the exact directory layout
	 * WordPress expects. GitHub's auto-generated source zipball is accepted as a
	 * fallback, because normalise_source_dir() repairs its folder name.
	 *
	 * @param array<string,mixed> $body Decoded release payload.
	 * @return string Validated URL, or '' when nothing usable was found.
	 */
	private function select_package( array $body ) {
		$wanted = HORAS_TRABALHADAS_SLUG . '.zip';

		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! is_array( $asset ) || empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
					continue;
				}
				if ( strtolower( (string) $asset['name'] ) !== $wanted ) {
					continue;
				}
				$url = $this->validate_package_url( (string) $asset['browser_download_url'] );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		if ( ! empty( $body['zipball_url'] ) ) {
			return $this->validate_package_url( (string) $body['zipball_url'] );
		}

		return '';
	}

	/**
	 * Ensure a download URL is safe to hand to the WordPress upgrader.
	 *
	 * @param string $url Candidate URL.
	 * @return string The URL, or '' when it is not acceptable.
	 */
	private function validate_package_url( $url ) {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return '';
		}

		if ( empty( $parts['host'] ) ) {
			return '';
		}

		if ( ! in_array( strtolower( $parts['host'] ), self::ALLOWED_PACKAGE_HOSTS, true ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * The human-facing release page URL, validated the same way.
	 *
	 * @param array<string,mixed> $body Decoded release payload.
	 * @return string
	 */
	private function safe_release_url( array $body ) {
		if ( empty( $body['html_url'] ) ) {
			return '';
		}

		$url   = esc_url_raw( (string) $body['html_url'] );
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$https = ( 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) );

		return ( $https && 'github.com' === $host ) ? $url : '';
	}

	/**
	 * Inject this plugin's update state into the WordPress update transient.
	 *
	 * Placing the plugin in `no_update` when it is current is what makes the
	 * native "Ativar atualizações automáticas" control appear on the Plugins
	 * screen, so both branches matter.
	 *
	 * @param mixed $transient The update_plugins site transient.
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( ! $this->is_enabled() ) {
			return $transient;
		}

		$release = $this->get_release();
		if ( null === $release ) {
			return $transient;
		}

		$item = array(
			'id'            => HORAS_TRABALHADAS_SLUG,
			'slug'          => HORAS_TRABALHADAS_SLUG,
			'plugin'        => HORAS_TRABALHADAS_BASENAME,
			'new_version'   => $release['version'],
			'url'           => $release['url'],
			'package'       => $release['package'],
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'requires'      => '5.6',
			'requires_php'  => '7.2',
			'tested'        => '',
			'compatibility' => new \stdClass(),
		);

		if ( version_compare( $release['version'], HORAS_TRABALHADAS_VERSION, '>' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ HORAS_TRABALHADAS_BASENAME ] = (object) $item;
			unset( $transient->no_update[ HORAS_TRABALHADAS_BASENAME ] );
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$item['new_version'] = HORAS_TRABALHADAS_VERSION;
			$item['package']     = '';
			$transient->no_update[ HORAS_TRABALHADAS_BASENAME ] = (object) $item;
		}

		return $transient;
	}

	/**
	 * Supply the "View details" modal, which would otherwise query WordPress.org
	 * and find nothing (or worse, an unrelated plugin with the same slug).
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The API action being performed.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || HORAS_TRABALHADAS_SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_release();
		if ( null === $release ) {
			return $result;
		}

		$information = array(
			'name'          => 'Horas Trabalhadas',
			'slug'          => HORAS_TRABALHADAS_SLUG,
			'version'       => $release['version'],
			'requires'      => '5.6',
			'requires_php'  => '7.2',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'trunk'         => $release['package'],
			'last_updated'  => $release['published'],
			'sections'      => array(
				// The release body is author-written Markdown from the repository.
				// It is escaped rather than rendered so a release note can never
				// inject markup into the admin modal.
				'changelog' => '<pre>' . esc_html( $release['changelog'] ) . '</pre>',
			),
		);

		return (object) $information;
	}

	/**
	 * Rename the unpacked folder to the plugin slug.
	 *
	 * GitHub's auto-generated source zipball unpacks to `owner-repo-a1b2c3d/`.
	 * Installing that as-is would create a second, differently named copy of the
	 * plugin instead of upgrading the existing one. The release workflow's
	 * purpose-built asset already has the right name, so this is a safety net for
	 * the zipball fallback and for manual installs.
	 *
	 * @param string       $source        Path to the unpacked package.
	 * @param string       $remote_source Path to the download directory.
	 * @param \WP_Upgrader $upgrader      Upgrader instance.
	 * @param array        $extra         Extra arguments.
	 * @return string|\WP_Error
	 */
	public function normalise_source_dir( $source, $remote_source, $upgrader = null, $extra = array() ) {
		global $wp_filesystem;

		if ( ! is_array( $extra ) || ! isset( $extra['plugin'] ) ) {
			return $source;
		}

		if ( HORAS_TRABALHADAS_BASENAME !== $extra['plugin'] ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . HORAS_TRABALHADAS_SLUG;

		if ( untrailingslashit( $source ) === $desired ) {
			return $source;
		}

		// Refuse to clobber an unrelated directory that is already sitting there.
		if ( $wp_filesystem->exists( $desired ) ) {
			$wp_filesystem->delete( $desired, true );
		}

		if ( ! $wp_filesystem->move( untrailingslashit( $source ), $desired ) ) {
			return new \WP_Error(
				'horas_trabalhadas_rename_failed',
				__( 'Não foi possível preparar o pacote de atualização.', 'horas-trabalhadas' )
			);
		}

		return trailingslashit( $desired );
	}

	/**
	 * Drop the cached release after any plugin update, so the Plugins screen does
	 * not keep advertising an update that has just been installed.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Update options.
	 * @return void
	 */
	public function flush_cache( $upgrader, $options ) {
		if ( ! is_array( $options ) ) {
			return;
		}

		if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_site_transient( self::RELEASE_TRANSIENT );
		}
	}
}
