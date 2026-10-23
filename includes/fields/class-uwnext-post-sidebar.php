<?php
/** Derived from Awesome Motive / SeedProd WPVibe 1.20.5; modified 2026-10-23 for Unlimited WP MCP Next. GPL-2.0-or-later; see UPSTREAM.md. */
/**
 * Post-edit sidebar for UWNext-built themes.
 *
 * Renders a meta box in the right rail of every post edit screen when
 * the active theme declares itself with a "UWNext: yes" header in
 * style.css. Content adapts to two checks:
 *
 *   1. Are any fields registered (via uwnext_field_register) for the
 *      post type being edited?
 *   2. Does any admin have an Application Password whose name contains
 *      "UWNext" (i.e., an MCP client is paired with the site)?
 *
 * The four combinations produce four sidebar states. Anchor links to
 * registered fields reuse the #uwnext-field-{key} convention; the
 * edit-focus.js hashchange listener handles the scroll + focus + flash.
 */

defined( 'ABSPATH' ) || exit;

class UWNext_Post_Sidebar {

	const META_BOX_ID = 'uwnext-sidebar';


	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'maybe_register_meta_box' ) );
	}

	/**
	 * Add the sidebar meta box on all public post types when the active
	 * theme has opted in via the UWNext header.
	 */
	public function maybe_register_meta_box() {
		if ( ! $this->is_uwnext_theme() || UWNext_White_Label::is_hidden() ) {
			return;
		}
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				self::META_BOX_ID,
				__( 'Unlimited WP MCP Next', 'unlimited-wp-mcp-next' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public function render( $post ) {
		$fields    = UWNext_Fields::instance()->get_fields( $post->post_type );
		$connected = self::is_mcp_connected();

		echo '<div class="uwnext-sidebar">';

		// Top badge.
		echo '<div class="uwnext-sidebar-badge">';
		echo '<span class="uwnext-sidebar-icon" aria-hidden="true">';
		echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2 4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4zm0 2.2 6 3v4.8c0 4-2.6 7.4-6 8-3.4-.6-6-4-6-8V7.2l6-3z"/></svg>';
		echo '</span>';
		echo '<span>' . esc_html__( 'Editable theme fields', 'unlimited-wp-mcp-next' ) . '</span>';
		echo '</div>';

		// Field list or empty state.
		if ( ! empty( $fields ) ) {
			echo '<div class="uwnext-sidebar-fields">';
			echo '<p class="uwnext-sidebar-fields-title">' . esc_html__( 'Editable on this page', 'unlimited-wp-mcp-next' ) . '</p>';
			echo '<ul>';
			foreach ( $fields as $key => $config ) {
				$label = $config['label'] ? $config['label'] : $key;
				printf(
					'<li><a href="#uwnext-field-%1$s">%2$s</a></li>',
					esc_attr( $key ),
					esc_html( $label )
				);
			}
			echo '</ul>';
			echo '</div>';
		} else {
			echo '<div class="uwnext-sidebar-empty">';
			echo '<p>' . esc_html__( 'No editable fields on this page yet.', 'unlimited-wp-mcp-next' ) . '</p>';
			if ( $connected ) {
				echo '<p>' . esc_html__( 'Ask your AI assistant to register editable fields for this post type.', 'unlimited-wp-mcp-next' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Connect an AI client below, then ask it to register editable fields.', 'unlimited-wp-mcp-next' ) . '</p>';
			}
			echo '</div>';
		}

		// MCP status / Connect CTA.
		if ( $connected ) {
			echo '<div class="uwnext-sidebar-mcp uwnext-sidebar-mcp-connected">';
			echo '<span class="uwnext-sidebar-dot" aria-hidden="true"></span>';
			echo '<span>' . esc_html__( 'Connected to UWNext MCP', 'unlimited-wp-mcp-next' ) . '</span>';
			echo '</div>';
		} else {
			$connect_url = admin_url('admin.php?page=unlimited-wp-mcp-next');
			echo '<div class="uwnext-sidebar-mcp">';
			printf(
				'<a class="button button-primary" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( $connect_url ),
				esc_html__( 'Connect Claude / ChatGPT', 'unlimited-wp-mcp-next' )
			);
			echo '<p class="uwnext-sidebar-mcp-help">' . esc_html__( 'Pair an AI client to edit this site by chat.', 'unlimited-wp-mcp-next' ) . '</p>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Detect a UWNext-built theme via the "UWNext" style.css header.
	 *
	 * @return bool
	 */
	public static function is_uwnext_theme() {
		$theme=wp_get_theme(); $value=$theme->get('UWNext') ?: ($theme->get('UWMCP') ?: $theme->get('WPVibe'));
		return ! empty( $value );
	}

	/**
	 * Check whether any admin has an Application Password whose name
	 * contains "UWNext" — our proxy for "an MCP client is paired."
	 *
	 * Cached per-request because the render callback may indirectly
	 * trigger it more than once.
	 *
	 * @return bool
	 */
    public static function is_mcp_connected() {
        foreach(UWNext_Store::records('connection') as $connection) {
            if(!empty($connection['active']) && (int)$connection['user']===get_current_user_id()) {return true;}
        }
        return false;
    }
}
