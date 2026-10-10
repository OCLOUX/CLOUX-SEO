<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_Audit {

	private $options;

	public function __construct( $options ) {
		$this->options = $options;
	}

	public static function categories() {
		return array(
			'titles'  => 'Titres et metas',
			'content' => 'Contenu et structure',
			'technic' => 'SEO technique',
			'schema'  => 'Schema.org',
			'geo'     => 'GEO et agents IA',
			'eeat'    => 'E-E-A-T',
		);
	}

	private static function severity( $weight ) {
		if ( $weight >= 5 ) {
			return 'critical';
		}
		if ( $weight >= 3 ) {
			return 'high';
		}
		if ( $weight >= 2 ) {
			return 'medium';
		}
		return 'low';
	}

	private static function finding( $id, $category, $label, $status, $weight, $finding, $recommendation, $verify ) {
		return array(
			'id'             => $id,
			'category'       => $category,
			'label'          => $label,
			'status'         => $status,
			'weight'         => $weight,
			'severity'       => self::severity( $weight ),
			'finding'        => $finding,
			'recommendation' => $recommendation,
			'verify'         => $verify,
		);
	}

	private static function get( $url ) {
		return wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 5, 'user-agent' => 'CLOUX-SEO/' . CLOUX_SEO_VERSION . ' (+https://github.com/OCLOUX/CLOUX-SEO)' ) );
	}

	public static function score( $findings, $extra = array() ) {
		$total = 0;
		$got   = 0;
		$cats  = array();
		foreach ( $findings as $f ) {
			$w = (int) $f['weight'];
			$total += $w;
			if ( 'pass' === $f['status'] ) {
				$got += $w;
			} elseif ( 'warn' === $f['status'] ) {
				$got += $w / 2;
			}
			if ( ! isset( $cats[ $f['category'] ] ) ) {
				$cats[ $f['category'] ] = array( 'pass' => 0, 'total' => 0 );
			}
			$cats[ $f['category'] ]['total'] += $w;
			if ( 'pass' === $f['status'] ) {
				$cats[ $f['category'] ]['pass'] += $w;
			} elseif ( 'warn' === $f['status'] ) {
				$cats[ $f['category'] ]['pass'] += $w / 2;
			}
		}
		foreach ( $cats as $cat => $data ) {
			$cats[ $cat ]['score'] = $data['total'] ? (int) round( 100 * $data['pass'] / $data['total'] ) : 0;
		}
		return array_merge(
			array(
				'date'       => current_time( 'mysql' ),
				'score'      => $total ? (int) round( 100 * $got / $total ) : 0,
				'categories' => $cats,
				'findings'   => array_values( $findings ),
			),
			$extra
		);
	}

	public function run() {
		$t0       = microtime( true );
		$response = self::get( home_url( '/' ) );
		$elapsed  = round( microtime( true ) - $t0, 2 );

		$findings   = array_merge( $this->local_checks(), self::analyze_html( $response, home_url( '/' ) ) );
		$findings[] = self::finding( 'response-time', 'technic', 'Temps de réponse', ( $elapsed <= 2 ) ? 'pass' : 'warn', 2, 'Page d’accueil chargée en ' . $elapsed . ' s.', 'Cibler moins de 2 s : cache, CDN, images optimisées.', 'curl -w %{time_total} -so /dev/null ' . home_url( '/' ) );

		$report = self::score( $findings, array( 'site' => home_url( '/' ), 'type' => 'self', 'elapsed' => $elapsed ) );
		update_option( 'cloux_seo_last_report', $report, false );
		return $report;
	}

	private function local_checks() {
		$findings = array();
		$posts    = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 5 ) );

		if ( empty( $posts ) ) {
			$findings[] = self::finding( 'no-posts', 'content', 'Contenu publié', 'warn', 2, 'Aucun article publié.', 'Publier du contenu régulièrement.', 'La liste des articles publiés est vide.' );
			return $findings;
		}

		$bad_titles = 0;
		$no_desc    = 0;
		$no_bio     = 0;
		$n          = count( $posts );

		foreach ( $posts as $post ) {
			$title = get_the_title( $post );
			$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
			if ( $len < 20 || $len > 70 ) {
				$bad_titles++;
			}

			$desc = (string) get_post_meta( $post->ID, 'rank_math_description', true );
			if ( '' === $desc ) {
				$desc = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
			}
			if ( '' === trim( $desc ) ) {
				$desc = (string) $post->post_excerpt;
			}
			if ( '' === trim( $desc ) ) {
				$no_desc++;
			}

			$user = get_userdata( (int) $post->post_author );
			if ( ! $user || '' === trim( (string) $user->description ) ) {
				$no_bio++;
			}
		}

		$findings[] = self::finding( 'titles-posts', 'titles', 'Longueur des titres d’articles', ( 0 === $bad_titles ) ? 'pass' : ( ( $bad_titles >= $n ) ? 'fail' : 'warn' ), 3, $bad_titles . '/' . $n . ' titres hors plage 20-70 caractères.', 'Viser des titres de 30 à 60 caractères.', 'Recompter la longueur des titres des 5 derniers articles.' );

		$findings[] = self::finding( 'desc-posts', 'titles', 'Meta description par article', ( 0 === $no_desc ) ? 'pass' : ( ( $no_desc >= $n ) ? 'fail' : 'warn' ), 4, $no_desc . '/' . $n . ' articles sans meta description.', 'Renseigner la meta description (Rank Math) ou l’extrait de chaque article.', 'Champ Rank Math « Description » vide sur l’article.' );

		$findings[] = self::finding( 'author-bio', 'eeat', 'Biographies d’auteurs', ( 0 === $no_bio ) ? 'pass' : ( ( $no_bio >= $n ) ? 'fail' : 'warn' ), 2, $no_bio . '/' . $n . ' articles dont l’auteur n’a pas de biographie.', 'Remplir la biographie du profil auteur (E-E-A-T).', 'Profil utilisateur, champ Biographie vide.' );

		$lines  = preg_split( '/\r\n|\r|\n/', (string) $this->options['sameas'] );
		$lines  = is_array( $lines ) ? $lines : array();
		$sameas = count( array_filter( array_map( 'trim', $lines ) ) );
		$findings[] = self::finding( 'sameas', 'eeat', 'Profils externes (sameAs)', ( $sameas >= 2 ) ? 'pass' : 'warn', 2, $sameas . ' URL(s) sameAs configurée(s).', 'Renseigner au moins 2 profils externes (X, QRZ, LinkedIn…) dans les réglages.', 'Option sameas inférieure à 2 URL.' );

		$findings[] = self::finding( 'contact', 'eeat', 'Email de contact', $this->options['contact_email'] ? 'pass' : 'warn', 2, $this->options['contact_email'] ? $this->options['contact_email'] : 'Aucun email configuré.', 'Renseigner un email de contact dans les réglages.', 'Option contact_email vide.' );

		return $findings;
	}

	public static function analyze_html( $response, $url ) {
		$findings = array();

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$detail = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
			$findings[] = self::finding( 'http', 'technic', 'Disponibilité de la page', 'fail', 10, 'Réponse invalide : ' . $detail, 'Corriger le serveur ou vérifier le domaine.', 'curl -sSI ' . $url );
			return $findings;
		}

		$html = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $html ) ) {
			$findings[] = self::finding( 'http-empty', 'technic', 'Contenu de la page', 'fail', 10, 'La page ne renvoie aucun contenu.', 'Corriger la génération de la page.', 'curl -s ' . $url . ' | wc -c' );
			return $findings;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		$https = ( 0 === strpos( $url, 'https://' ) );
		$findings[] = self::finding( 'https', 'technic', 'HTTPS', $https ? 'pass' : 'fail', 8, $https ? 'Site servi en HTTPS.' : 'Site servi en HTTP simple.', 'Passer le site en HTTPS et forcer la redirection.', 'curl -sSI http://… | head -1' );

		$title_node = $dom->getElementsByTagName( 'title' )->item( 0 );
		$title = $title_node ? trim( (string) $title_node->textContent ) : '';
		$tlen  = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
		if ( '' === $title ) {
			$status = 'fail';
		} elseif ( $tlen < 20 || $tlen > 70 ) {
			$status = 'warn';
		} else {
			$status = 'pass';
		}
		$findings[] = self::finding( 'title-tag', 'titles', 'Balise title', $status, 4, 'Title : ' . $title . ' (' . $tlen . ' caractères).', 'Un title unique de 30 à 60 caractères par page.', 'Balise title dans la source HTML.' );

		$desc = '';
		$viewport = false;
		$og = 0;
		$robotsmeta = '';
		foreach ( $dom->getElementsByTagName( 'meta' ) as $meta ) {
			$name    = strtolower( (string) $meta->getAttribute( 'name' ) );
			$prop    = strtolower( (string) $meta->getAttribute( 'property' ) );
			$content = (string) $meta->getAttribute( 'content' );
			if ( 'description' === $name ) {
				$desc = $content;
			}
			if ( 'viewport' === $name ) {
				$viewport = true;
			}
			if ( 'robots' === $name ) {
				$robotsmeta = $content;
			}
			if ( 'og:title' === $name || 'og:title' === $prop ) {
				$og++;
			}
		}
		$dlen = function_exists( 'mb_strlen' ) ? mb_strlen( $desc ) : strlen( $desc );
		if ( '' === $desc ) {
			$status = 'fail';
		} elseif ( $dlen < 50 || $dlen > 165 ) {
			$status = 'warn';
		} else {
			$status = 'pass';
		}
		$findings[] = self::finding( 'meta-desc', 'titles', 'Meta description', $status, 4, 'Meta description de ' . $dlen . ' caractères.', 'Une meta description de 120 à 160 caractères avec le mot-clé principal.', 'Balise meta name description dans la source.' );

		$h1 = $dom->getElementsByTagName( 'h1' )->length;
		$findings[] = self::finding( 'h1', 'content', 'Balise H1 unique', ( 1 === $h1 ) ? 'pass' : ( ( 0 === $h1 ) ? 'fail' : 'warn' ), 4, $h1 . ' balise(s) H1 détectée(s).', 'Une seule balise H1 par page, portant le sujet principal.', 'Compter les h1 dans la source HTML.' );

		$h2 = $dom->getElementsByTagName( 'h2' )->length;
		$findings[] = self::finding( 'h2', 'content', 'Structure H2', ( $h2 > 0 ) ? 'pass' : 'warn', 2, $h2 . ' balise(s) H2.', 'Découper le contenu en sections H2.', 'Hiérarchie des titres de la page.' );

		$imgs  = $dom->getElementsByTagName( 'img' );
		$noalt = 0;
		foreach ( $imgs as $img ) {
			if ( '' === trim( (string) $img->getAttribute( 'alt' ) ) ) {
				$noalt++;
			}
		}
		$ratio = $imgs->length ? $noalt / $imgs->length : 0;
		if ( 0 === $imgs->length || 0 === $noalt ) {
			$status = 'pass';
		} elseif ( $ratio < 0.3 ) {
			$status = 'warn';
		} else {
			$status = 'fail';
		}
		$findings[] = self::finding( 'img-alt', 'content', 'Textes alternatifs', $status, 4, $noalt . '/' . $imgs->length . ' images sans attribut alt.', 'Décrire chaque image avec un alt pertinent.', 'Comparer le nombre de img et d attributs alt dans la source.' );

		$canonical = false;
		foreach ( $dom->getElementsByTagName( 'link' ) as $link ) {
			if ( 'canonical' === strtolower( (string) $link->getAttribute( 'rel' ) ) ) {
				$canonical = true;
			}
		}
		$findings[] = self::finding( 'canonical', 'technic', 'URL canonique', $canonical ? 'pass' : 'warn', 3, $canonical ? 'Canonical présente.' : 'Aucune balise canonical.', 'Une canonical par page pour éviter le contenu dupliqué.', 'Link rel canonical dans la source.' );

		$noindex = ( false !== stripos( $robotsmeta, 'noindex' ) );
		$findings[] = self::finding( 'noindex', 'technic', 'Indexation', $noindex ? 'fail' : 'pass', 10, $noindex ? 'Meta robots avec noindex : page exclue des moteurs.' : 'Page indexable.', 'Retirer noindex si la page doit être indexée.', 'Meta name robots dans la source.' );

		$findings[] = self::finding( 'viewport', 'technic', 'Viewport mobile', $viewport ? 'pass' : 'warn', 2, $viewport ? 'Meta viewport présente.' : 'Meta viewport absente.', 'Ajouter une meta viewport pour le mobile.', 'Meta name viewport dans la source.' );

		$findings[] = self::finding( 'og', 'technic', 'Open Graph', $og ? 'pass' : 'warn', 2, $og ? 'Balises Open Graph détectées.' : 'Aucune balise og:title.', 'Générer les balises Open Graph pour les partages.', 'Meta property og:title dans la source.' );

		$size = (int) ( strlen( $html ) / 1024 );
		$findings[] = self::finding( 'page-size', 'technic', 'Poids du HTML', ( $size <= 500 ) ? 'pass' : 'warn', 2, 'HTML de ' . $size . ' Ko.', 'Cibler moins de 500 Ko de HTML.', 'curl -s ' . $url . ' | wc -c' );

		$types = array();
		foreach ( $dom->getElementsByTagName( 'script' ) as $script ) {
			if ( 'application/ld+json' === (string) $script->getAttribute( 'type' ) ) {
				$data = json_decode( $script->textContent, true );
				if ( is_array( $data ) ) {
					$types = array_merge( $types, self::jsonld_types( $data ) );
				}
			}
		}
		$types = array_values( array_unique( $types ) );
		if ( empty( $types ) ) {
			$status = 'fail';
			$detail = 'Aucun JSON-LD détecté.';
		} else {
			$status = ( count( $types ) >= 2 ) ? 'pass' : 'warn';
			$detail = 'Types détectés : ' . implode( ', ', $types );
		}
		$findings[] = self::finding( 'jsonld', 'schema', 'Données structurées JSON-LD', $status, 4, $detail, 'Générer Organization, WebSite, Article et BreadcrumbList (Rank Math ou module Schema).', 'Outil Rich Results de Google.' );

		$p    = wp_parse_url( $url );
		$root = $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) . '/';

		$robots_response = self::get( $root . 'robots.txt' );
		$robots_body     = (string) wp_remote_retrieve_body( $robots_response );
		$robots_ok       = ( ! is_wp_error( $robots_response ) && 200 === (int) wp_remote_retrieve_response_code( $robots_response ) );
		$has_sitemap     = (bool) preg_match( '/Sitemap:\s*\S+/i', $robots_body );
		$findings[] = self::finding( 'robots', 'technic', 'robots.txt et sitemap déclaré', ( $robots_ok && $has_sitemap ) ? 'pass' : 'fail', 3, $robots_ok ? ( $has_sitemap ? 'robots.txt présent avec sitemap déclaré.' : 'robots.txt présent sans directive Sitemap.' ) : 'robots.txt inaccessible.', 'Déclarer le sitemap dans robots.txt.', 'curl -s ' . $root . 'robots.txt | grep -i sitemap' );

		$sitemap_url = '';
		if ( preg_match( '/Sitemap:\s*(\S+)/i', $robots_body, $m ) ) {
			$sitemap_url = $m[1];
		}
		$sitemap_ok = false;
		if ( $sitemap_url ) {
			$sr = self::get( $sitemap_url );
			$sitemap_ok = ( ! is_wp_error( $sr ) && 200 === (int) wp_remote_retrieve_response_code( $sr ) );
		}
		$findings[] = self::finding( 'sitemap', 'technic', 'Sitemap accessible', $sitemap_ok ? 'pass' : 'fail', 3, $sitemap_url ? ( $sitemap_ok ? 'Sitemap accessible : ' . $sitemap_url : 'Sitemap en erreur : ' . $sitemap_url ) : 'Aucune URL de sitemap trouvée dans robots.txt.', 'Corriger ou déclarer le sitemap.', 'curl -sSI le-sitemap | head -1' );

		$llms    = self::get( $root . 'llms.txt' );
		$llms_ok = ( ! is_wp_error( $llms ) && 200 === (int) wp_remote_retrieve_response_code( $llms ) && strlen( (string) wp_remote_retrieve_body( $llms ) ) > 50 );
		$findings[] = self::finding( 'llms', 'geo', 'Fichier llms.txt', $llms_ok ? 'pass' : 'fail', 3, $llms_ok ? 'llms.txt servi avec du contenu.' : 'llms.txt absent ou vide.', 'Générer un llms.txt (module GEO) pour les agents IA.', 'curl -s ' . $root . 'llms.txt' );

		$catalog    = self::get( $root . 'ai-catalog.json' );
		$catalog_ok = ( ! is_wp_error( $catalog ) && 200 === (int) wp_remote_retrieve_response_code( $catalog ) );
		$findings[] = self::finding( 'ai-catalog', 'geo', 'Catalogue ai-catalog.json', $catalog_ok ? 'pass' : 'warn', 2, $catalog_ok ? 'ai-catalog.json servi.' : 'ai-catalog.json absent.', 'Exposer un catalogue JSON des contenus pour les agents IA.', 'curl -s ' . $root . 'ai-catalog.json' );

		$ai_bots = (bool) preg_match( '/(GPTBot|ClaudeBot|PerplexityBot|Google-Extended)/i', $robots_body );
		$findings[] = self::finding( 'ai-robots', 'geo', 'Règles agents IA dans robots.txt', $ai_bots ? 'pass' : 'warn', 2, $ai_bots ? 'Règles explicites pour au moins un agent IA.' : 'Aucune règle explicite pour GPTBot, ClaudeBot ou PerplexityBot.', 'Définir Allow ou Disallow par agent IA (module GEO).', 'curl -s ' . $root . 'robots.txt | grep -i gptbot' );

		return $findings;
	}

	private static function jsonld_types( $data ) {
		$types = array();
		if ( isset( $data['@type'] ) ) {
			$types[] = is_array( $data['@type'] ) ? implode( '+', $data['@type'] ) : $data['@type'];
		}
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$types = array_merge( $types, self::jsonld_types( $value ) );
			}
		}
		return $types;
	}
}
