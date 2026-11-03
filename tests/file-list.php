<?php
// Focused theme file_list traversal regression coverage. All writes stay in the disposable fixture.
$slug = 'uwnext-file-list-fixture';
$sibling_slug = 'uwnext-file-list-sibling';
$theme_root = get_theme_root();
$fixture = $theme_root . '/' . $slug;
$sibling = $theme_root . '/' . $sibling_slug;
foreach ( array( array( $fixture, $slug ), array( $sibling, $sibling_slug ) ) as $owned ) {
	if ( file_exists( $owned[0] ) ) {
		uwnext_test_remove_theme_tree( $owned[0], $owned[1] );
	}
}
wp_mkdir_p( $fixture . '/nested' );
wp_mkdir_p( $fixture . '/.hidden' );
wp_mkdir_p( $fixture . '/vendor' );
wp_mkdir_p( $fixture . '/bulk' );
wp_mkdir_p( $sibling );
file_put_contents( $fixture . '/visible.css', 'body{color:black}' );
file_put_contents( $fixture . '/nested/inside.css', 'body{color:white}' );
file_put_contents( $fixture . '/.hidden/secret.css', 'hidden' );
file_put_contents( $fixture . '/vendor/dependency.css', 'dependency' );
file_put_contents( $sibling . '/outside.css', 'outside' );
$symlink_created = @symlink( $sibling . '/outside.css', $fixture . '/linked.css' );
$list_max_entries = defined( 'UWNext_File_Ops::LIST_MAX_ENTRIES' ) ? UWNext_File_Ops::LIST_MAX_ENTRIES : 5000;
$list_max_seconds = defined( 'UWNext_File_Ops::LIST_MAX_SECONDS' ) ? UWNext_File_Ops::LIST_MAX_SECONDS : 5;

$old_draft = get_option( 'uwnext_draft_theme', null );
try {
	update_option( 'uwnext_draft_theme', $slug );
	$listed = data( tool( 'file_list', array( 'scope' => 'theme' ) ) );
	$paths = array_column( $listed['files'] ?? array(), 'path' );
	check( in_array( 'visible.css', $paths, true ) && in_array( 'nested/inside.css', $paths, true ), 'file_list still returns normal matching files' );
	check( ! array_filter( $paths, function ( $path ) { return str_starts_with( str_replace( '\\', '/', $path ), '.hidden/' ); } ), 'file_list prunes hidden theme directories' );
	check( ! array_filter( $paths, function ( $path ) { return str_starts_with( str_replace( '\\', '/', $path ), 'vendor/' ); } ), 'file_list prunes dependency directories' );
	if ( $symlink_created ) {
		check( ! in_array( 'linked.css', $paths, true ), 'file_list excludes linked files' );
	} else {
		check( true, 'file_list linked-file regression unavailable on this host' );
	}
	for ( $i = 0; $i <= $list_max_entries; $i++ ) {
		file_put_contents( $fixture . '/bulk/' . $i . '.txt', '' );
	}
	$started = microtime( true );
	$none = data( tool( 'file_list', array( 'scope' => 'theme', 'pattern' => '*.never' ) ) );
	$elapsed = microtime( true ) - $started;
	check( ! isset( $none['code'] ) && $none['truncated'], 'file_list no-match traversal stops at the visited-entry limit' );
	check( $elapsed < $list_max_seconds + 1, 'file_list no-match traversal returns within its time budget' );
} finally {
	if ( null === $old_draft ) {
		delete_option( 'uwnext_draft_theme' );
	} else {
		update_option( 'uwnext_draft_theme', $old_draft );
	}
	uwnext_test_remove_theme_tree( $fixture, $slug );
	uwnext_test_remove_theme_tree( $sibling, $sibling_slug );
}
