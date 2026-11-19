<?php
/** Unlimited-owned release discovery. GPL-2.0-or-later. */
defined( 'ABSPATH' ) || exit;

final class UWNext_Update_Source {
    const REPOSITORY = 'https://github.com/ConsciouslyAwesome/unlimited-wp-mcp-next';
    const API = 'https://api.github.com/repos/ConsciouslyAwesome/unlimited-wp-mcp-next/releases/latest';
    const SLUG = 'unlimited-wp-mcp-next';
    const CACHE = 'uwnext_release_metadata';
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'update_plugins_github.com', array( $this, 'update_offer' ), 10, 4 );
        add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
    }

    /** Core retains its normal update comparison, compatibility checks and auto-update policy. */
    public function update_offer( $update, $plugin_data, $plugin_file, $locales ) {
        if ( UWNext_PLUGIN_BASENAME !== $plugin_file ) { return $update; }
        $release = $this->release();
        if ( ! $release ) { return false; }
        return array(
            'id' => self::REPOSITORY,
            'slug' => self::SLUG,
            'version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
        );
    }

    public function plugin_information( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) || self::SLUG !== ( $args->slug ?? '' ) ) {
            return $result;
        }
        $release = $this->release();
        if ( ! $release ) {
            return new WP_Error( 'uwnext_release_unavailable', __( 'Unlimited release information is temporarily unavailable.', 'unlimited-wp-mcp-next' ) );
        }
        return (object) array(
            'name' => UWNext_NAME,
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => 'Unlimited WP MCP contributors',
            'homepage' => self::REPOSITORY,
            'download_link' => $release['package'],
            'sections' => array( 'changelog' => '<pre>' . esc_html( $release['notes'] ) . '</pre>' ),
        );
    }

    /** Only published stable releases with the installable plugin asset are update offers. */
    private function release() {
        $cached = get_site_transient( self::CACHE );
        if ( is_array( $cached ) && array_key_exists( 'release', $cached ) ) { return $cached['release']; }
        $response = wp_remote_get( self::API, array(
            'timeout' => 10,
            'redirection' => 0,
            'limit_response_size' => 262144,
            'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Unlimited-WP-MCP-Next/' . UWNext_VERSION ),
        ) );
        $release = false;
        if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $data ) && isset( $data['draft'], $data['prerelease'], $data['tag_name'] )
                && false === $data['draft'] && false === $data['prerelease'] && is_string( $data['tag_name'] )
                && preg_match( '/^v?(\d+\.\d+\.\d+)$/D', $data['tag_name'], $version ) ) {
                $tag = rawurlencode( $data['tag_name'] );
                $package = self::REPOSITORY . '/releases/download/' . $tag . '/' . self::SLUG . '.zip';
                foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
                    if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === self::SLUG . '.zip'
                        && ( $asset['state'] ?? '' ) === 'uploaded' && (int) ( $asset['size'] ?? 0 ) > 0
                        && ( $asset['browser_download_url'] ?? '' ) === $package ) {
                        $release = array(
                            'version' => $version[1],
                            'url' => self::REPOSITORY . '/releases/tag/' . $tag,
                            'package' => $package,
                            'notes' => is_string( $data['body'] ?? null ) ? $data['body'] : '',
                        );
                        break;
                    }
                }
            }
        }
        // Cache failures briefly too, avoiding repeated calls when GitHub is unavailable/rate limited.
        set_site_transient( self::CACHE, array( 'release' => $release ), $release ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
        return $release;
    }
}
