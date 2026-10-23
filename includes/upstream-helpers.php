<?php
// Derived from SeedProd WPVibe 1.17.2; GPL-2.0-or-later.
defined('ABSPATH') || exit;
function uwnext_fs() {
	global $wp_filesystem;
	if ( ! empty( $wp_filesystem ) ) {
		return $wp_filesystem;
	}
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! WP_Filesystem() ) {
		return false;
	}
	return $wp_filesystem;
}

/**
 * Validate PHP source syntax without executing it.
 *
 * Uses the tokenizer with TOKEN_PARSE so PHP throws ParseError/CompileError
 * on invalid syntax. Runs in-process — no shell exec or temp binaries.
 *
 * @param string $source PHP source code.
 * @param string $label  Label for the error message (e.g. basename).
 * @return true|WP_Error
 */
function uwnext_check_php_syntax( $source, $label = '' ) {
	try {
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- Suppress E_COMPILE_WARNING; we rely on the thrown ParseError below.
		@token_get_all( $source, TOKEN_PARSE );
	} catch ( \Error $e ) {
		return new WP_Error(
			'php_syntax',
			sprintf(
				/* translators: 1: file label, 2: error details */
				__( 'Syntax error in %1$s: %2$s', 'unlimited-wp-mcp-next' ),
				'' !== $label ? $label : __( 'file', 'unlimited-wp-mcp-next' ),
				'Host extension failed; inspect the local server log.'
			),
			UWNext_Error_Contract::data( 'invalid_input', false, array( 'status' => 422 ) )
		);
	}
	return true;
}

function uwnext_field_register( $post_type, $key, $config ) {
	return UWNext_Fields::instance()->register_field( $post_type, $key, $config );
}
function uwnext_setting_register( $key, $config ) {
	return UWNext_Fields::instance()->register_setting( $key, $config );
}
function uwnext_field_group_register( $post_type, $group_id, $config ) {
	return UWNext_Fields::instance()->register_group( $post_type, $group_id, $config );
}