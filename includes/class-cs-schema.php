<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_Schema {

	private $options;

	public function __construct( $options ) {
		$this->options = $options;
		if ( ! $this->enabled() ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	private function enabled() {
		$mode = isset( $this->options['schema_mode'] ) ? $this->options['schema_mode'] : 'auto';
		if ( 'off' === $mode ) {
			return false;
		}
		if ( 'auto' === $mode && $this->seo_plugin_active() ) {
			return false;
		}
		return true;
	}

	private function seo_plugin_active() {
		return defined( 'RANK_MATH_VERSION' )
			|| class_exists( 'RankMath' )
			|| defined( 'WPSEO_VERSION' )
			|| class_exists( 'WPSEO_Options' );
	}

	public function output() {
		$graph   = array();
		$graph[] = $this->organization();
		$graph[] = $this->website();

		if ( is_singular( 'post' ) ) {
			$article = $this->article();
			if ( $article ) {
				$graph[] = $article;
			}
			$breadcrumb = $this->breadcrumb();
			if ( $breadcrumb ) {
				$graph[] = $breadcrumb;
			}
		}

		if ( empty( $graph ) ) {
			return;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . PHP_EOL;
	}

	private function org_name() {
		return $this->options['org_name'] ? $this->options['org_name'] : get_bloginfo( 'name' );
	}

	private function same_as() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $this->options['sameas'] );
		$lines = is_array( $lines ) ? $lines : array();
		$lines = array_filter( array_map( 'trim', $lines ) );
		return array_values( array_filter( $lines, 'esc_url_raw' ) );
	}

	private function organization() {
		$org = array(
			'@type'  => ( $this->options['street_address'] && 'local' === $this->options['site_type'] ) ? 'LocalBusiness' : 'Organization',
			'@id'     => home_url( '/#organization' ),
			'name'    => $this->org_name(),
			'url'     => home_url( '/' ),
			'sameAs'  => $this->same_as(),
		);

		if ( $this->options['org_description'] ) {
			$org['description'] = $this->options['org_description'];
		}
		if ( $this->options['contact_email'] ) {
			$org['contactPoint'] = array( '@type' => 'ContactPoint', 'email' => $this->options['contact_email'], 'contactType' => 'contact' );
		}
		if ( $this->options['street_address'] ) {
			$org['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $this->options['street_address'],
				'postalCode'      => $this->options['postal_code'],
				'addressLocality' => $this->options['address_locality'],
				'addressCountry'  => 'FR',
			);
		}
		if ( $this->options['geo_lat'] && $this->options['geo_lng'] ) {
			$org['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => $this->options['geo_lat'], 'longitude' => $this->options['geo_lng'] );
		}
		return $org;
	}

	private function website() {
		return array(
			'@type'      => 'WebSite',
			'@id'        => home_url( '/#website' ),
			'url'        => home_url( '/' ),
			'name'       => $this->org_name(),
			'inLanguage' => get_bloginfo( 'language' ),
			'publisher'  => array( '@id' => home_url( '/#organization' ) ),
		);
	}

	private function article() {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		$article = array(
			'@type'            => 'Article',
			'@id'              => get_permalink( $post ) . '#article',
			'headline'         => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => get_permalink( $post ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'wordCount'        => str_word_count( wp_strip_all_tags( $post->post_content ) ),
		);
		$user = get_userdata( (int) $post->post_author );
		if ( $user ) {
			$author = array( '@type' => 'Person', 'name' => $user->display_name, '@id' => home_url( '/#author-' . $user->user_nicename ) );
			$sameas = CS_EEAT::user_same_as( $user->ID );
			if ( $sameas ) {
				$author['sameAs'] = $sameas;
			}
			$article['author'] = $author;
		}
		$thumb = get_the_post_thumbnail_url( $post, 'large' );
		if ( $thumb ) {
			$article['image'] = $thumb;
		}
		return $article;
	}

	private function breadcrumb() {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		$items[] = array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => home_url( '/' ) );
		$pos     = 2;
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title( $ancestor_id ), 'item' => get_permalink( $ancestor_id ) );
			$pos++;
		}
		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title( $post ) );
		return array( '@type' => 'BreadcrumbList', '@id' => get_permalink( $post ) . '#breadcrumb', 'itemListElement' => $items );
	}
}
