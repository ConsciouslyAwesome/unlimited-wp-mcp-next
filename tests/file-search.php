<?php
// Focused 0.1.5+ file_search regression coverage. All writes stay in the disposable fixture.
final class UWNext_Test_Counting_FS {
	public $reads = array();
	public function get_contents( $path ) {
		$this->reads[] = wp_normalize_path( $path );
		return file_get_contents( $path );
	}
}

function uwnext_test_remove_theme_tree( $path, $expected_slug ) {
	$root = realpath( get_theme_root() );
	$real = realpath( $path );
	if ( ! $root || ! $real || dirname( $real ) !== $root || basename( $real ) !== $expected_slug ) {
		throw new RuntimeException( 'Refusing unsafe file_search fixture cleanup.' );
	}
	$walk = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $walk as $item ) {
		$item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $real );
}

$definition = UWNext_Tools::definitions()['file_search'];
$properties = (array) $definition['inputSchema']['properties'];
check( ( $properties['extensions']['items']['type'] ?? null ) === 'string', 'file_search extensions schema declares string items' );
check( ( $properties['max_results']['maximum'] ?? null ) === UWNext_File_Ops::SEARCH_MAX_RESULTS, 'file_search schema exposes the bounded result limit' );

$slug = 'uwnext-file-search-fixture';
$sibling_slug = 'uwnext-file-search-sibling';
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
wp_mkdir_p( $fixture . '/node_modules' );
wp_mkdir_p( $fixture . '/bulk' );
wp_mkdir_p( $sibling );
file_put_contents( $fixture . '/00-match.css', "/* file-search-valid-match */\n" );
file_put_contents( $fixture . '/01-late.css', "/* file-search-valid-match */\n" );
file_put_contents( $fixture . '/binary.css', "\x00\x01\xff" );
file_put_contents( $fixture . '/ignored.php', str_repeat( 'file-search-valid-match', 1000 ) );
file_put_contents( $fixture . '/oversized.css', str_repeat( 'x', UWNext_File_Ops::SEARCH_MAX_FILE_BYTES + 1 ) );
file_put_contents( $fixture . '/nested/no-match.css', 'body{color:black}' );
file_put_contents( $fixture . '/.hidden/hidden.css', 'body{color:black}' );
file_put_contents( $fixture . '/node_modules/dependency.css', 'body{color:black}' );
file_put_contents( $sibling . '/outside.css', 'file-search-scope-escape' );
$symlink_created = @symlink( $sibling . '/outside.css', $fixture . '/linked.css' );
for ( $i = 0; $i < 5; $i++ ) {
	file_put_contents( $fixture . '/bulk/' . $i . '.json', str_repeat( 'x', UWNext_File_Ops::SEARCH_MAX_FILE_BYTES ) );
}

$old_draft = get_option( 'uwnext_draft_theme', null );
global $wp_filesystem;
$old_fs = $wp_filesystem;
try {
	update_option( 'uwnext_draft_theme', $slug );
	$wp_filesystem = new UWNext_Test_Counting_FS();
	$started = microtime( true );
	$none = data( tool( 'file_search', array(
		'pattern' => '__phase4a_acceptance_probe_9f7c2d__',
		'extensions' => array( 'css' ),
		'max_results' => 1,
		'case_sensitive' => true,
	) ) );
	$elapsed = microtime( true ) - $started;
	check( ! isset( $none['code'] ) && 0 === $none['total_matches'], 'exact no-match CSS tool call completes normally' );
	check( $elapsed < 5, 'small no-match CSS search returns promptly' );
	check( ! array_filter( $wp_filesystem->reads, function ( $path ) { return str_ends_with( $path, '/ignored.php' ); } ), 'extension filtering occurs before file reads' );
	check( ! array_filter( $wp_filesystem->reads, function ( $path ) { return str_contains( $path, '/uwnext-file-search-sibling/' ); } ), 'file_search remains inside the selected theme scope' );
	check( (bool) array_filter( $wp_filesystem->reads, function ( $path ) { return str_ends_with( $path, '/binary.css' ); } ), 'CSS extension controls reads without hidden binary detection' );
	check( ! array_filter( $wp_filesystem->reads, function ( $path ) { return str_ends_with( $path, '/oversized.css' ); } ), 'oversized files are rejected before file reads' );
	check( ! array_filter( $wp_filesystem->reads, function ( $path ) { return str_contains( $path, '/.hidden/' ); } ), 'hidden theme directories are pruned from search scope' );
	check( ! array_filter( $wp_filesystem->reads, function ( $path ) { return str_contains( $path, '/node_modules/' ); } ), 'dependency directories are pruned from search scope' );
	check( $none['truncated'], 'skipping an oversized eligible file reports an incomplete search' );

	$wp_filesystem = new UWNext_Test_Counting_FS();
	$bulk = data( tool( 'file_search', array(
		'pattern' => '__file_search_aggregate_budget_probe__',
		'extensions' => array( 'json' ),
		'max_results' => 1,
		'case_sensitive' => true,
	) ) );
	check( $bulk['truncated'] && $bulk['files_searched'] <= 4 && count( $wp_filesystem->reads ) <= 4, 'aggregate search byte budget stops production-scale reads' );

	if ( $symlink_created ) {
		$wp_filesystem = new UWNext_Test_Counting_FS();
		$linked = data( tool( 'file_search', array(
			'pattern' => 'file-search-scope-escape',
			'extensions' => array( 'css' ),
			'max_results' => 10,
			'case_sensitive' => true,
		) ) );
		check( 0 === $linked['total_matches'] && ! array_filter( $wp_filesystem->reads, function ( $path ) { return str_contains( $path, '/uwnext-file-search-sibling/' ); } ), 'linked files cannot escape the selected theme scope' );
	} else {
		check( true, 'linked-file scope regression unavailable on this host' );
	}

	$wp_filesystem = new UWNext_Test_Counting_FS();
	$one = data( tool( 'file_search', array(
		'pattern' => 'file-search-valid-match',
		'extensions' => array( 'css' ),
		'max_results' => 1,
		'case_sensitive' => true,
	) ) );
	check( 1 === $one['total_matches'] && '00-match.css' === $one['matches'][0]['file'], 'file_search still returns valid matches' );
	check( $one['truncated'] && count( $wp_filesystem->reads ) < 6, 'max_results=1 short-circuits traversal after the first match' );
} finally {
	$wp_filesystem = $old_fs;
	if ( null === $old_draft ) {
		delete_option( 'uwnext_draft_theme' );
	} else {
		update_option( 'uwnext_draft_theme', $old_draft );
	}
	uwnext_test_remove_theme_tree( $fixture, $slug );
	uwnext_test_remove_theme_tree( $sibling, $sibling_slug );
}
