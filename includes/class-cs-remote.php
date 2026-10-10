<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_Remote {

	const OPTION = 'cloux_seo_remote_domains';

	public static function normalize( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) ) {
			return new WP_Error( 'cloux_seo_remote', 'Domaine invalide.' );
		}
		return $p['scheme'] . '://' . $p['host'] . '/';
	}

	public static function domains() {
		$list = get_option( self::OPTION, array() );
		return is_array( $list ) ? $list : array();
	}

	public function add_domain( $url ) {
		$base = self::normalize( $url );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$list = self::domains();
		if ( isset( $list[ $base ] ) ) {
			return new WP_Error( 'cloux_seo_remote', 'Domaine déjà suivi.' );
		}
		$list[ $base ] = array( 'added' => current_time( 'mysql' ), 'report' => null );
		update_option( self::OPTION, $list, false );
		return true;
	}

	public function remove_domain( $url ) {
		$base = self::normalize( $url );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$list = self::domains();
		if ( isset( $list[ $base ] ) ) {
			unset( $list[ $base ] );
			update_option( self::OPTION, $list, false );
		}
		return true;
	}

	public function audit_domain( $url ) {
		$base = self::normalize( $url );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$response = wp_remote_get( $base, array( 'timeout' => 20, 'redirection' => 5, 'user-agent' => 'CLOUX-SEO/' . CLOUX_SEO_VERSION . ' (+https://github.com/OCLOUX/CLOUX-SEO)' ) );
		$report   = CS_Audit::score( CS_Audit::analyze_html( $response, $base ), array( 'site' => $base, 'type' => 'remote' ) );

		$list = self::domains();
		if ( ! isset( $list[ $base ] ) ) {
			$list[ $base ] = array( 'added' => current_time( 'mysql' ), 'report' => null );
		}
		$list[ $base ]['report'] = $report;
		update_option( self::OPTION, $list, false );
		return $report;
	}

	public function run_all() {
		foreach ( array_keys( self::domains() ) as $base ) {
			$this->audit_domain( $base );
		}
	}
}
