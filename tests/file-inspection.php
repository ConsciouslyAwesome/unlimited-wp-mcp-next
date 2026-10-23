<?php
// Focused theme file_read/file_outline bounds and file-type regression coverage.
$slug = 'uwnext-file-inspection-fixture';
$theme_root = get_theme_root();
$fixture = $theme_root . '/' . $slug;
if ( file_exists( $fixture ) ) {
	uwnext_test_remove_theme_tree( $fixture, $slug );
}
wp_mkdir_p( $fixture );
$source = "<?php\nfunction uwnext_inspection_fixture() {}\nadd_action( 'init', 'uwnext_inspection_fixture' );\n";
file_put_contents( $fixture . '/normal.php', $source );
$read_max_bytes = defined( 'UWNext_File_Ops::READ_MAX_BYTES' ) ? UWNext_File_Ops::READ_MAX_BYTES : 1048576;
file_put_contents( $fixture . '/oversized.php', '<?php /* ' . str_repeat( 'x', $read_max_bytes ) . ' */' );
$symlink_created = @symlink( $fixture . '/normal.php', $fixture . '/linked.php' );
file_put_contents( $fixture . '/hardlink-target.php', '<?php // hard-link target' );
$hardlink_created = @link( $fixture . '/hardlink-target.php', $fixture . '/hardlinked.php' );

$old_draft = get_option( 'uwnext_draft_theme', null );
try {
	update_option( 'uwnext_draft_theme', $slug );
	$read = data( tool( 'file_read', array( 'scope' => 'theme', 'path' => 'normal.php', 'start_line' => 2, 'end_line' => 3 ) ) );
	check( ! isset( $read['code'] ) && str_contains( $read['content'] ?? '', 'uwnext_inspection_fixture' ) && 2 === $read['start_line'] && 3 === $read['end_line'], 'bounded theme file_read preserves normal line ranges' );
	$outline = data( tool( 'file_outline', array( 'path' => 'normal.php' ) ) );
	$outline_names = array_column( $outline['outline'] ?? array(), 'name' );
	check( ! isset( $outline['code'] ) && in_array( 'uwnext_inspection_fixture', $outline_names, true ), 'bounded theme file_outline preserves normal results' );

	$large_read = data( tool( 'file_read', array( 'scope' => 'theme', 'path' => 'oversized.php' ) ) );
	check( ( $large_read['code'] ?? '' ) === 'file_too_large', 'theme file_read rejects files above its byte ceiling' );
	$large_outline = data( tool( 'file_outline', array( 'path' => 'oversized.php' ) ) );
	check( ( $large_outline['code'] ?? '' ) === 'file_too_large', 'theme file_outline rejects files above its byte ceiling' );

	if ( $symlink_created ) {
		$link_read = data( tool( 'file_read', array( 'scope' => 'theme', 'path' => 'linked.php' ) ) );
		check( ( $link_read['code'] ?? '' ) === 'forbidden_path', 'theme file_read refuses symbolic links inside the selected theme' );
	} else {
		check( true, 'theme file_read symbolic-link regression unavailable on this host' );
	}
	if ( $hardlink_created ) {
		$link_outline = data( tool( 'file_outline', array( 'path' => 'hardlinked.php' ) ) );
		check( ( $link_outline['code'] ?? '' ) === 'forbidden_path', 'theme file_outline refuses hard-linked files' );
	} else {
		check( true, 'theme file_outline hard-link regression unavailable on this host' );
	}
} finally {
	if ( null === $old_draft ) {
		delete_option( 'uwnext_draft_theme' );
	} else {
		update_option( 'uwnext_draft_theme', $old_draft );
	}
	uwnext_test_remove_theme_tree( $fixture, $slug );
}
