<?php
// Focused draft file mutation bounds. The caller owns an active disposable draft theme.
$mutation_limit = defined( 'UWNext_File_Ops::MUTATION_MAX_BYTES' ) ? UWNext_File_Ops::MUTATION_MAX_BYTES : 1048576;
$definitions = UWNext_Tools::definitions();
$write_properties = (array) $definitions['file_write']['inputSchema']['properties'];
$edit_properties = (array) $definitions['file_edit']['inputSchema']['properties'];
$write_content_schema = (array) $write_properties['content'];
$old_content_schema = (array) $edit_properties['old_content'];
$new_content_schema = (array) $edit_properties['new_content'];
check( ( $write_content_schema['maxLength'] ?? null ) === $mutation_limit, 'file_write schema advertises the mutation byte ceiling' );
check( ( $old_content_schema['minLength'] ?? null ) === 1, 'file_edit schema rejects an empty match string' );
check( ( $old_content_schema['maxLength'] ?? null ) === $mutation_limit, 'file_edit schema advertises bounded match content' );
check( ( $new_content_schema['maxLength'] ?? null ) === $mutation_limit, 'file_edit schema advertises bounded replacement content' );

$draft_dir = get_theme_root() . '/' . get_option( 'uwnext_draft_theme' );
$ops = new UWNext_File_Ops();
$result_data = static function ( $result ) {
	return is_wp_error( $result ) ? array( 'code' => $result->get_error_code() ) : $result->get_data();
};

$too_large = str_repeat( 'x', $mutation_limit + 1 );
$large_write = $result_data( $ops->write( 'oversized-write.txt', $too_large ) );
check( ( $large_write['code'] ?? '' ) === 'file_too_large', 'file_write rejects content above its byte ceiling' );
if ( file_exists( $draft_dir . '/oversized-write.txt' ) ) {
	unlink( $draft_dir . '/oversized-write.txt' );
}

file_put_contents( $draft_dir . '/oversized-edit.txt', 'needle' . str_repeat( 'x', $mutation_limit ) );
$large_edit = $result_data( $ops->edit( 'oversized-edit.txt', 'needle', 'changed' ) );
check( ( $large_edit['code'] ?? '' ) === 'file_too_large', 'file_edit rejects an existing file above its byte ceiling' );
unlink( $draft_dir . '/oversized-edit.txt' );

file_put_contents( $draft_dir . '/empty-match.txt', 'normal content' );
try {
	$empty_edit = $result_data( $ops->edit( 'empty-match.txt', '', 'replacement' ) );
} catch ( Throwable $e ) {
	$empty_edit = array( 'code' => 'uncaught_throwable' );
}
check( ( $empty_edit['code'] ?? '' ) === 'invalid_content', 'file_edit rejects an empty old_content without throwing' );

$normal_write = $result_data( $ops->write( 'bounded-mutation.txt', 'before' ) );
$normal_edit = $result_data( $ops->edit( 'bounded-mutation.txt', 'before', 'after' ) );
check( ! isset( $normal_write['code'] ) && ! isset( $normal_edit['code'] ) && 'after' === file_get_contents( $draft_dir . '/bounded-mutation.txt' ), 'bounded file_write and file_edit preserve normal behavior' );
unlink( $draft_dir . '/bounded-mutation.txt' );
unlink( $draft_dir . '/empty-match.txt' );
