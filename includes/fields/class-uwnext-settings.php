<?php
/** Derived from Awesome Motive / SeedProd WPVibe 1.20.5; modified 2026-10-22 for Unlimited WP MCP Next. GPL-2.0-or-later; see UPSTREAM.md. */
/**
 * Settings API wrapper.
 *
 * Bridges UWNext_Fields::register_setting() to the WordPress Settings API:
 * registers each setting under the 'uwnext_settings' group, adds a Settings
 * → UWNext admin page when at least one setting is registered, and renders
 * fields through the same UWNext_Field_Renderers used by post meta boxes.
 */

defined( 'ABSPATH' ) || exit;

class UWNext_Settings {

	private static $instance;
	const OPTION_GROUP = 'uwnext_settings';
	const PAGE_SLUG    = 'uwnext';
	const SECTION_ID   = 'uwnext_main';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'maybe_register_page' ) );
	}

	public function register_settings() {
		$settings = UWNext_Fields::instance()->get_settings();
		if ( empty( $settings ) ) {
			return;
		}

		add_settings_section( self::SECTION_ID, '', '__return_null', self::PAGE_SLUG );

		foreach ( $settings as $key => $config ) {
			register_setting( self::OPTION_GROUP, $key, array(
				'type'              => $this->wp_setting_type( $config['type'] ),
				'default'           => $config['default'],
				'sanitize_callback' => function ( $value ) use ( $config ) {
					return UWNext_Fields::instance()->sanitize_field( $config, $value );
				},
				'show_in_rest'      => true,
			) );

			add_settings_field(
				$key,
				$config['label'],
				function () use ( $key, $config ) {
					$value = get_option( $key, $config['default'] );
					UWNext_Field_Renderers::render( $key, $value, $config, 'setting' );
				},
				self::PAGE_SLUG,
				self::SECTION_ID
			);
		}
	}

	public function maybe_register_page() {
		$settings = UWNext_Fields::instance()->get_settings();
		if ( empty( $settings ) ) {
			return;
		}

		// White label keeps this page (clients edit real site content here) but
		// drops the branding from the menu label and title.
		$hidden = UWNext_White_Label::is_hidden();
		add_options_page(
			$hidden ? __( 'Site Settings', 'unlimited-wp-mcp-next' ) : __( 'UWNext Settings', 'unlimited-wp-mcp-next' ),
			$hidden ? __( 'Site Settings', 'unlimited-wp-mcp-next' ) : __( 'UWNext', 'unlimited-wp-mcp-next' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php UWNext_White_Label::is_hidden() ? esc_html_e( 'Site Settings', 'unlimited-wp-mcp-next' ) : esc_html_e( 'UWNext Settings', 'unlimited-wp-mcp-next' ); ?></h1>
			<?php // WP core auto-renders settings_errors() above the heading for pages under Settings; calling it here too caused a duplicate "Settings saved" notice. ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	private function wp_setting_type( $field_type ) {
		switch ( $field_type ) {
			case 'number':
				return 'number';
			case 'checkbox':
			case 'image':
				return 'integer';
			default:
				return 'string';
		}
	}
}
