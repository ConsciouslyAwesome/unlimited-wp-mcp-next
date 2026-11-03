<?php
/** Derived from Awesome Motive / SeedProd WPVibe 1.20.5; modified 2026-10-22 for Unlimited WP MCP Next. GPL-2.0-or-later; see UPSTREAM.md. */
/**
 * Hover-to-edit affordance for editable surfaces declared via the
 * UWNext field API.
 *
 * Theme authors annotate elements whose content comes from a registered
 * field by calling uwnext_edit_attr() (post meta) or
 * uwnext_edit_setting_attr() (settings) from inside the opening tag. The
 * helpers output a data attribute only for registered fields and users with
 * the matching capability — anonymous visitors and read-only roles see clean markup.
 *
 * A small frontend bundle (loaded only for users with edit capability)
 * paints a dashed outline + "Edit" pin on hover and links the pin to the
 * appropriate wp-admin screen with a #uwnext-field-{key} hash anchor. A
 * companion admin bundle reads that hash on the destination page and
 * scrolls/focuses/flashes the matching field for a smooth handoff.
 */

defined( 'ABSPATH' ) || exit;

class UWNext_Edit_Affordance {

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_focus_assets' ) );
	}

	/**
	 * Conditionally enqueue the frontend pin assets. Only authenticated
	 * users with at least edit_posts capability ever download these — the
	 * public site stays untouched.
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$version = defined( 'UWNext_VERSION' ) ? UWNext_VERSION : false;

		wp_enqueue_style(
			'uwnext-edit-affordance',
			UWNext_PLUGIN_URL . 'assets/fields/edit-affordance.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'uwnext-edit-affordance',
			UWNext_PLUGIN_URL . 'assets/fields/edit-affordance.js',
			array(),
			$version,
			true
		);

		wp_localize_script(
			'uwnext-edit-affordance',
			'uwnextEdit',
			array(
				'adminPostUrl'    => admin_url( 'post.php' ),
				'adminSettingUrl' => admin_url( 'options-general.php?page=' . UWNext_Settings::PAGE_SLUG ),
				'labelEdit'       => esc_attr__( 'Edit', 'unlimited-wp-mcp-next' ),
			)
		);
	}

	/**
	 * Enqueue the admin hash-anchor focus script on post edit screens and
	 * on the UWNext settings page. Reads location.hash on load, scrolls
	 * the matching field into view, focuses it, and briefly flashes the
	 * surrounding row.
	 */
	public function enqueue_admin_focus_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ok     = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			|| ( $screen && 'settings_page_' . UWNext_Settings::PAGE_SLUG === $screen->id );
		if ( ! $ok ) {
			return;
		}

		$version = defined( 'UWNext_VERSION' ) ? UWNext_VERSION : false;

		wp_enqueue_script(
			'uwnext-edit-focus',
			UWNext_PLUGIN_URL . 'assets/fields/edit-focus.js',
			array(),
			$version,
			true
		);
	}
}

/**
 * Emit a data attribute marking an element as editable. Outputs nothing
 * unless the field is registered for this post type and the user can edit
 * this specific post.
 *
 * Usage in a template:
 *   <h1 <?php uwnext_edit_attr( get_the_ID(), 'hero_heading' ); ?>>
 *       <?php echo esc_html( get_post_meta( get_the_ID(), 'hero_heading', true ) ); ?>
 *   </h1>
 *
 * @param int    $post_id Post ID whose meta this element is bound to.
 * @param string $key     Meta key.
 */
function uwnext_edit_attr( $post_id, $key ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$post_type = get_post_type( $post_id );
	if ( ! $post_type || ! UWNext_Fields::instance()->get_field( $post_type, $key ) ) {
		return;
	}
	printf( ' data-uwnext-edit="%d:%s"', $post_id, esc_attr( $key ) );
}

/**
 * Emit a data attribute marking an element as bound to an editable
 * global setting. Outputs nothing unless the setting is registered and the
 * user can manage options.
 *
 * Usage in a template:
 *   <div <?php uwnext_edit_setting_attr( 'site_tagline' ); ?>>
 *       <?php echo esc_html( get_option( 'site_tagline' ) ); ?>
 *   </div>
 *
 * @param string $key Setting key.
 */
function uwnext_edit_setting_attr( $key ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! UWNext_Fields::instance()->get_setting( $key ) ) {
		return;
	}
	printf( ' data-uwnext-edit-setting="%s"', esc_attr( $key ) );
}
