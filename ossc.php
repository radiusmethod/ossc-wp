<?php
/**
 * Plugin Name:       Open Source Software Contributions
 * Plugin URI:        https://github.com/radiusmethod/ossc-wp/
 * Description:       Displays Pull Request links from GitHub for Open Source Software Contributions. To use this, specify [ossc] in your text code.
 * Install:           Drop this directory in the "wp-content/plugins/" directory and activate it. You need to specify "[ossc]" in the code section of a page or a post.
 * Contributors:      pjaudiomv, radius314
 * Authors:           pjaudiomv, radius314
 * Version:           1.1.2
 * Requires PHP:      8.1
 * Requires at least: 6.2
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace OsscPlugin;

if ( basename( $_SERVER['PHP_SELF'] ) == basename( __FILE__ ) ) {
	die( 'Sorry, but you cannot access this page directly.' );
}

/**
 * Class OSSC
 * @package OsscPlugin
 */
class OSSC {

	private const SETTINGS_GROUP   = 'ossc-group';
	private const PLUG_SLUG = 'ossc';

	/**
	 * Singleton instance of the class.
	 *
	 * @var null|self
	 */
	private static ?self $instance = null;

	/**
	 * Constructor method for initializing the plugin.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'plugin_setup' ] );
		add_action( 'ossc_daily_event', [ $this, 'fetch_and_save_github_data' ] );
		register_activation_hook( __FILE__, [ static::class, 'ossc_activate' ] );
		register_deactivation_hook( __FILE__, [ static::class, 'ossc_deactivate' ] );
	}

	/**
	 * Setup method for initializing the plugin.
	 *
	 * This method checks if the current context is in the admin dashboard or not.
	 * If in the admin dashboard, it registers admin-related actions and settings.
	 * If not in the admin dashboard, it sets up a shortcode and associated actions.
	 *
	 * @return void
	 */
	public function plugin_setup(): void {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ static::class, 'create_menu' ] );
			add_action( 'admin_init', [ static::class, 'register_settings' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_files' ] );
			add_action( 'admin_post_osscManualUpdate', [ $this, 'manual_update_handler' ] );
		} else {
			add_shortcode( self::PLUG_SLUG, [ $this, 'render_ossc' ] );
		}
	}

	public static function ossc_activate(): void {
		self::ossc_create_table();
		self::ossc_schedule_event();
	}

	public static function ossc_deactivate(): void {
		self::ossc_drop_table();
		self::ossc_unschedule_event();
	}

	private static function ossc_schedule_event(): void {
		if ( ! wp_next_scheduled( 'ossc_daily_event' ) ) {
			wp_schedule_event( time(), 'daily', 'ossc_daily_event', [] );
		}
	}

	private static function ossc_unschedule_event(): void {
		wp_clear_scheduled_hook( 'ossc_daily_event' );
	}

	public function enqueue_backend_files(): void {
		wp_enqueue_style( self::PLUG_SLUG, plugin_dir_url( __FILE__ ) . 'css/admin-ossc.css', false, '1.0.0', 'all' );
	}

	public function manual_update_handler(): void {
		$result = $this->fetch_and_save_github_data();
		if ( is_array( $result ) && ! empty( $result ) ) {
			$error_message = implode( '<br>', $result );
			wp_redirect( admin_url( 'options-general.php?page=' . self::PLUG_SLUG . '&status=error&message=' . urlencode( $error_message ) ) );
		} else {
			wp_redirect( admin_url( 'options-general.php?page=' . self::PLUG_SLUG . '&status=updated' ) );
		}
		exit();
	}


	private static function ossc_create_table(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ossc_github_data';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            repo varchar(255) NOT NULL,
            url varchar(255) NOT NULL,
            closed_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY repo_url (repo, url)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function ossc_drop_table(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ossc_github_data';
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );  // phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			'github_api_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'github_repos',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'github_users',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
	}

	public static function create_menu(): void {
		// Create the plugin's settings page in the WordPress admin menu
		add_options_page(
			esc_html__( 'OSSC Settings', 'ossc' ), // Page Title
			esc_html__( 'OSSC', 'ossc' ),          // Menu Title
			'manage_options',                        // Capability
			self::PLUG_SLUG,                         // Menu Slug
			[ static::class, 'draw_settings' ]         // Callback function to display the page content
		);
		// Add a settings link in the plugins list
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ static::class, 'settings_link' ] );
	}

	public static function settings_link( array $links ): array {
		// Add a "Settings" link for the plugin in the WordPress admin
		$settings_url = admin_url( 'options-general.php?page=' . self::PLUG_SLUG );
		$links[]      = "<a href='{$settings_url}'>Settings</a>";
		return $links;
	}

	private static function determine_option( string|array $attrs, string $option ): string {
		if ( isset( $_POST['ossc_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ossc_nonce'] ) ), 'ossc_action' ) ) {
			if ( isset( $_POST[ $option ] ) ) {
				// Form data option
				return sanitize_text_field( strtolower( $_POST[ $option ] ) );
			}
		}
		if ( isset( $_GET[ $option ] ) ) {
			// Query String Option
			return wp_kses( strtolower( $_GET[ $option ] ), [ 'br' => [] ] );
		} elseif ( ! empty( $attrs[ $option ] ) ) {
			// Shortcode Option
			return sanitize_text_field( strtolower( $attrs[ $option ] ) );
		} else {
			// Settings Option or Default
			return sanitize_text_field( strtolower( get_option( $option ) ?? '' ) );
		}
	}

	public static function draw_settings(): void {
		// Display the plugin's settings page
		$github_api_key     = esc_attr( get_option( 'github_api_key' ) );
		$github_repos   = esc_attr( get_option( 'github_repos' ) );
		$github_users = esc_attr( get_option( 'github_users' ) );
		$status = self::determine_option( [], 'status' );
		$message = self::determine_option( [], 'message' );
		?>
		<div class="ossc_admin_div">
			<h2>Open Source Software Contributions</h2>
			<p>You must create a GitHub personal access token to use this plugin. Instructions can be found here <a href="https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token">https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token</a>.</p>
			<form method="post" action="options.php">
				<?php wp_nonce_field( 'ossc_action', 'ossc_nonce' ); ?>
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<?php do_settings_sections( self::SETTINGS_GROUP ); ?>
				<table class="ossc_table">
					<tr class="ossc_tr">
						<th scope="row" class="ossc_th"><label for="github_api_key">GitHub API Token</label></th>
						<td class="ossc_td"><input type="text" id="github_api_key" class="ossc_input" name="github_api_key" value="<?php echo wp_kses( $github_api_key, [] ); ?>" /></td>
					</tr>
					<tr class="ossc_tr">
						<th scope="row" class="ossc_th"><label for="github_repos">Github Repos (Comma Separated String)</label></th>
						<td class="ossc_td"><input type="text" id="github_repos" class="ossc_input" name="github_repos" value="<?php echo wp_kses( $github_repos, [] ); ?>" /></td>
					</tr>
					<tr class="ossc_tr">
						<th scope="row" class="ossc_th"><label for="github_users">Github Users (Comma Separated String)</label></th>
						<td class="ossc_td"><input type="text" id="github_users" class="ossc_input" name="github_users" value="<?php echo wp_kses( $github_users, [] ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
				<p>
					<a href="<?php echo esc_attr( admin_url( 'admin-post.php?action=osscManualUpdate' ) ); ?>" class="button button-primary">Manual Update</a>
				</p>
			</form>
			<?php if ( 'updated' == $status ) { ?>
				<div class="notice notice-success is-dismissible">
					<p>GitHub data updated successfully.</p>
				</div>
			<?php } elseif ( 'error' == $status ) { ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo wp_kses( urldecode( $message ), [ 'br' => [] ] ); ?></p>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	public function render_ossc( string|array $attrs = [] ): string {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ossc_github_data';
		$content = '<div class="ossc_div">';

		$github_repos_option = sanitize_textarea_field( get_option( 'github_repos' ) );

		if ( ! empty( $github_repos_option ) ) {
			$github_repos = array_map( 'trim', explode( ',', $github_repos_option ) );
		} else {
			$github_repos = [];
		}

		foreach ( $github_repos as $repo ) {
			$repo_name = explode( '/', $repo )[1] ?? '';
			$content .= '<p><strong><a href="https://github.com/' . esc_url( $repo ) . '" target="_blank" data-type="URL" rel="noreferrer noopener">' . $repo_name . '</a></strong></p>';
			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE repo = %s ORDER BY closed_at DESC', $table_name, $repo ), ARRAY_A ); // phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$content .= '<ul class="ossc_ul">';
			foreach ( $results as $item ) {
				$content .= '<li class="ossc_li">' . '<a target="_blank" rel="noopener noreferrer" href="' . $item['url'] . '">' . $item['url'] . '</a></li>';
			}
			$content .= '</ul>';
		}

		$content .= '</div>';
		return $content;
	}

	public function fetch_and_save_github_data(): array|bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ossc_github_data';
		$github_repos_option = sanitize_textarea_field( get_option( 'github_repos' ) );
		$github_users_option = sanitize_textarea_field( get_option( 'github_users' ) );
		$github_repos = array_values( array_unique( array_map( 'trim', explode( ',', $github_repos_option ) ) ) );
		$github_users = array_values( array_unique( array_map( 'trim', explode( ',', $github_users_option ) ) ) );
		$errors = [];

		foreach ( $github_repos as $repo ) {
			$items = $this->github_pull_requests( $repo, $github_users );
			if ( is_string( $items ) ) {
				$errors[] = $items;
				continue;
			}

			foreach ( $items as $item ) {
				$exists = $wpdb->get_var(   // phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE repo = %s AND url = %s',
						$table_name,
						$repo,
						$item['html_url']
					)
				);

				if ( 0 == $exists ) {
					$data = [
						'repo' => $repo,
						'url' => $item['html_url'],
						'closed_at' => $item['closed_at'],
					];
					$wpdb->insert( $table_name, $data );   // phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return $errors;
		}

		return true;
	}

	private function github_pull_requests( string $repo, ?array $users = null ): array|string {
		$user_string = '';
		if ( $users ) {
			foreach ( $users as $user ) {
				$connector = '+author:';
				$user_string .= $connector . $user;
			}
		}

		$per_page = 75;
		$page = 1;
		$all_results = [];

		while ( true ) {
			$url = "https://api.github.com/search/issues?q=is:pr+is:merged+repo:$repo$user_string&per_page=$per_page&page=$page";
			$results = $this->get( $url );

			if ( is_wp_error( $results ) ) {
				return $results;
			}

			$httpcode = wp_remote_retrieve_response_code( $results );
			$response_message = wp_remote_retrieve_response_message( $results );
			if ( 200 != $httpcode && 302 != $httpcode && 304 != $httpcode && ! empty( $response_message ) ) {
				return 'Problem Connecting to Server! : ' . $response_message . ' URL: ' . $url;
			}
			$body = wp_remote_retrieve_body( $results );
			$data = json_decode( $body, true );

			if ( empty( $data['items'] ) ) {
				break;
			}

			$all_results = array_merge( $all_results, $data['items'] );
			$link_header = wp_remote_retrieve_header( $results, 'Link' );
			$next_page_url = $this->get_next_page_url_from_link_header( $link_header );

			if ( ! $next_page_url ) {
				break;
			}

			$page++;
		}

		return $all_results;
	}

	private function get_next_page_url_from_link_header( string $link_header ): string {
		preg_match( '/<(.*?(?:(?:\?|\&)page=(\d+).*)?)>.*rel="(.*)"/', $link_header, $matches, PREG_UNMATCHED_AS_NULL );
		return $matches[1] ?? '';
	}

	private function get( string $url ): array|WP_Error {
		$github_api_key = sanitize_text_field( get_option( 'github_api_key' ) );

		$args = [
			'timeout' => '120',
			'headers' => [
				'Accept' => 'application/vnd.github+json',
				'Authorization' => "Bearer $github_api_key",
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:105.0) Gecko/20100101 Firefox/105.0',
			],
		];

		return wp_remote_get( $url, $args );
	}

	public static function get_instance(): self {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

OSSC::get_instance();

?>
