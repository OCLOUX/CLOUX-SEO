<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_GEO {

	private $options;

	public function __construct( $options ) {
		$this->options = $options;

		if ( empty( $this->options['geo_enabled'] ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_filter( 'robots_txt', array( $this, 'robots' ), 10, 2 );
	}

	public static function register_rewrites() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?cloux_seo=llms', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?cloux_seo=llms-full', 'top' );
		add_rewrite_rule( '^ai-catalog\.json$', 'index.php?cloux_seo=ai-catalog', 'top' );
	}

	public static function known_bots() {
		return array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'meta-externalagent', 'Bytespider' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'cloux_seo';
		return $vars;
	}

	public function template_redirect() {
		$route = get_query_var( 'cloux_seo' );
		if ( '' === $route ) {
			return;
		}
		nocache_headers();
		if ( 'llms' === $route ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo $this->llms_content( false );
			exit;
		}
		if ( 'llms-full' === $route ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo $this->llms_content( true );
			exit;
		}
		if ( 'ai-catalog' === $route ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( $this->catalog_data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
		}
	}

	public function robots( $output, $public ) {
		$lines = PHP_EOL . '# CLOUX SEO — accès des agents IA (GEO)' . PHP_EOL;
		foreach ( self::known_bots() as $bot ) {
			$allow  = ! empty( $this->options['ai_bots'][ $bot ] );
			$lines .= 'User-agent: ' . $bot . PHP_EOL . ( $allow ? 'Allow: /' : 'Disallow: /' ) . PHP_EOL;
		}
		return $output . $lines;
	}

	private function org_name() {
		return $this->options['org_name'] ? $this->options['org_name'] : get_bloginfo( 'name' );
	}

	private function excerpt( $post ) {
		if ( $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		return wp_html_excerpt( wp_strip_all_tags( $post->post_content ), 300, '…' );
	}

	private function entries() {
		$entries = array();
		$pages   = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
		foreach ( $pages as $page ) {
			$entries[] = array( 'type' => 'page', 'title' => get_the_title( $page ), 'url' => get_permalink( $page ), 'description' => $this->excerpt( $page ) );
		}
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 50 ) );
		foreach ( $posts as $post ) {
			$entries[] = array( 'type' => 'post', 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'description' => $this->excerpt( $post ) );
		}
		return $entries;
	}

	public function catalog_data() {
		return array(
			'name'        => $this->org_name(),
			'url'         => home_url( '/' ),
			'description' => $this->options['org_description'] ? $this->options['org_description'] : get_bloginfo( 'description' ),
			'language'    => get_bloginfo( 'language' ),
			'generator'   => 'CLOUX SEO ' . CLOUX_SEO_VERSION,
			'content'     => $this->entries(),
		);
	}

	public function llms_content( $full ) {
		$about = $this->options['org_description'] ? $this->options['org_description'] : get_bloginfo( 'description' );

		$out  = '# ' . $this->org_name() . PHP_EOL . PHP_EOL;
		$out .= '> ' . $about . PHP_EOL . PHP_EOL;
		$out .= 'Site : ' . home_url( '/' ) . PHP_EOL . PHP_EOL;

		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 50 ) );

		if ( $pages ) {
			$out .= '## Pages' . PHP_EOL . PHP_EOL;
			foreach ( $pages as $page ) {
				$out .= '- [' . get_the_title( $page ) . '](' . get_permalink( $page ) . ') : ' . $this->excerpt( $page ) . PHP_EOL;
			}
			$out .= PHP_EOL;
		}

		if ( $posts ) {
			$out .= '## Articles' . PHP_EOL . PHP_EOL;
			foreach ( $posts as $post ) {
				$out .= '- [' . get_the_title( $post ) . '](' . get_permalink( $post ) . ') : ' . $this->excerpt( $post ) . PHP_EOL;
				if ( $full ) {
					$out .= wp_html_excerpt( wp_strip_all_tags( $post->post_content ), 2000, '…' ) . PHP_EOL . PHP_EOL;
				}
			}
		}

		return apply_filters( 'cloux_seo_llms_content', $out, $full );
	}
}
