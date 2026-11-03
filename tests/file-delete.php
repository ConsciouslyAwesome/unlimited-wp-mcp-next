<?php
// Focused draft file deletion safety. The caller owns an active disposable draft theme.
$draft_dir = get_theme_root() . '/' . get_option( 'uwnext_draft_theme' );
$ops = new UWNext_File_Ops();
$result_data = static function ( $result ) {
	return is_wp_error( $result ) ? array( 'code' => $result->get_error_code() ) : $result->get_data();
};

file_put_contents( $draft_dir . '/delete-normal.txt', 'normal' );
$normal = $result_data( $ops->delete( 'delete-normal.txt' ) );
check( ! isset( $normal['code'] ) && ! file_exists( $draft_dir . '/delete-normal.txt' ), 'file_delete removes a normal allowed file and verifies absence' );

file_put_contents( $draft_dir . '/delete-forbidden.bin', 'protected by extension policy' );
$forbidden = $result_data( $ops->delete( 'delete-forbidden.bin' ) );
check( ( $forbidden['code'] ?? '' ) === 'forbidden_ext' && file_exists( $draft_dir . '/delete-forbidden.bin' ), 'file_delete preserves files outside the mutation extension allowlist' );

wp_mkdir_p( $draft_dir . '/delete-directory.php' );
$directory = $result_data( $ops->delete( 'delete-directory.php' ) );
check( ( $directory['code'] ?? '' ) === 'is_directory' && is_dir( $draft_dir . '/delete-directory.php' ), 'file_delete still refuses directories' );

file_put_contents( $draft_dir . '/delete-link-target.php', '<?php // link target' );
$symlink_created = @symlink( $draft_dir . '/delete-link-target.php', $draft_dir . '/delete-symlink.php' );
if ( $symlink_created ) {
	$symlink = $result_data( $ops->delete( 'delete-symlink.php' ) );
	check( ( $symlink['code'] ?? '' ) === 'forbidden_path' && is_link( $draft_dir . '/delete-symlink.php' ) && file_exists( $draft_dir . '/delete-link-target.php' ), 'file_delete refuses symbolic links without deleting their target' );
} else {
	check( true, 'file_delete symbolic-link regression unavailable on this host' );
}

$hardlink_created = @link( $draft_dir . '/delete-link-target.php', $draft_dir . '/delete-hardlink.php' );
if ( $hardlink_created ) {
	$hardlink = $result_data( $ops->delete( 'delete-hardlink.php' ) );
	check( ( $hardlink['code'] ?? '' ) === 'forbidden_path' && file_exists( $draft_dir . '/delete-hardlink.php' ) && file_exists( $draft_dir . '/delete-link-target.php' ), 'file_delete refuses hard-linked files' );
} else {
	check( true, 'file_delete hard-link regression unavailable on this host' );
}

if ( is_link( $draft_dir . '/delete-symlink.php' ) ) {
	unlink( $draft_dir . '/delete-symlink.php' );
}
foreach ( array( 'delete-hardlink.php', 'delete-link-target.php', 'delete-forbidden.bin' ) as $file ) {
	if ( file_exists( $draft_dir . '/' . $file ) ) {
		unlink( $draft_dir . '/' . $file );
	}
}
if ( is_dir( $draft_dir . '/delete-directory.php' ) ) {
	rmdir( $draft_dir . '/delete-directory.php' );
}
