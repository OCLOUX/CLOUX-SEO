<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_EEAT {

	private $options;

	public function __construct( $options ) {
		$this->options = $options;

		add_filter( 'user_contactmethods', array( $this, 'contact_methods' ) );

		if ( ! empty( $this->options['eeat_author_box'] ) ) {
			add_filter( 'the_content', array( $this, 'author_box' ), 20 );
		}
		if ( ! empty( $this->options['eeat_dates'] ) ) {
			add_filter( 'the_content', array( $this, 'dates_block' ), 21 );
		}
	}

	public function contact_methods( $methods ) {
		$methods['cloux_seo_sameas'] = 'CLOUX SEO — profils externes (sameAs, une URL par ligne)';
		return $methods;
	}

	public static function user_same_as( $user_id ) {
		$raw  = (string) get_user_meta( $user_id, 'cloux_seo_sameas', true );
		$urls = preg_split( '/\r\n|\r|\n/', $raw );
		$urls = is_array( $urls ) ? $urls : array();
		$urls = array_filter( array_map( 'trim', $urls ) );
		return array_values( array_filter( $urls, 'esc_url_raw' ) );
	}

	public function author_box( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$user = get_userdata( (int) get_post_field( 'post_author' ) );
		if ( ! $user ) {
			return $content;
		}

		$links = '';
		foreach ( self::user_same_as( $user->ID ) as $url ) {
			$host   = wp_parse_url( $url, PHP_URL_HOST );
			$host   = $host ? $host : $url;
			$links .= " <a href='" . esc_url( $url ) . "' rel='me nofollow noopener' target='_blank'>" . esc_html( $host ) . '</a>';
		}

		$box  = "<div class='cloux-seo-author-box'>";
		$box .= get_avatar( $user->ID, 72 );
		$box .= "<div class='cloux-seo-author-text'><span class='cloux-seo-author-name'>" . esc_html( $user->display_name ) . '</span>';
		if ( $user->description ) {
			$box .= '<p>' . esc_html( $user->description ) . '</p>';
		}
		if ( $links ) {
			$box .= "<p class='cloux-seo-author-links'>" . $links . '</p>';
		}
		$box .= '</div></div>';

		return $content . $box;
	}

	public function dates_block( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$published = get_the_date( 'c' );
		$modified  = get_the_modified_date( 'c' );
		if ( $modified === $published ) {
			return $content;
		}
		$block  = "<p class='cloux-seo-dates'>";
		$block .= sprintf(
			'Publié le <time datetime="%1$s">%2$s</time> — mis à jour le <time datetime="%3$s">%4$s</time>',
			esc_attr( $published ),
			esc_html( get_the_date() ),
			esc_attr( $modified ),
			esc_html( get_the_modified_date() )
		);
		$block .= '</p>';
		return $content . $block;
	}
}
