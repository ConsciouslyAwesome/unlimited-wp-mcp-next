<?php
// Focused stable-file coverage for draft_theme_compile_sources.
$slug = 'uwnext-compile-source-fixture';
$theme_root = get_theme_root();
$fixture = $theme_root . '/' . $slug;
if ( file_exists( $fixture ) ) {
	uwnext_test_remove_theme_tree( $fixture, $slug );
}
wp_mkdir_p( $fixture );
file_put_contents( $fixture . '/theme.css', '@tailwind utilities;' );
file_put_contents( $fixture . '/template.php', '<?php echo "compile fixture";' );

$old_draft = get_option( 'uwnext_draft_theme', null );
$ops = new UWNext_File_Ops();
try {
	update_option( 'uwnext_draft_theme', $slug );
	$normal = $ops->compile_sources();
	check( ! is_wp_error( $normal ) && isset( $normal['source_hash'] ) && isset( $normal['files']->{'template.php'} ), 'draft compile source collection preserves normal bounded reads' );

	$hardlink_created = @link( $fixture . '/template.php', $fixture . '/hardlinked.php' );
	if ( $hardlink_created ) {
		$hardlink = $ops->compile_sources();
		check( is_wp_error( $hardlink ) && 'compile_sources_failed' === $hardlink->get_error_code(), 'draft compile source collection refuses hard-linked files' );
		unlink( $fixture . '/hardlinked.php' );
	} else {
		check( true, 'draft compile source hard-link regression unavailable on this host' );
	}

	$symlink_created = @symlink( $fixture . '/template.php', $fixture . '/symlinked.php' );
	if ( $symlink_created ) {
		$symlink = $ops->compile_sources();
		check( is_wp_error( $symlink ) && 'compile_sources_failed' === $symlink->get_error_code(), 'draft compile source collection refuses symbolic links' );
		unlink( $fixture . '/symlinked.php' );
	} else {
		check( true, 'draft compile source symbolic-link regression unavailable on this host' );
	}
} finally {
	if ( null === $old_draft ) {
		delete_option( 'uwnext_draft_theme' );
	} else {
		update_option( 'uwnext_draft_theme', $old_draft );
	}
	uwnext_test_remove_theme_tree( $fixture, $slug );
}
