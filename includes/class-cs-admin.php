<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_Admin {

	private $core;

	public function __construct( $core ) {
		$this->core = $core;

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_cloux_seo_run_audit', array( $this, 'ajax_run' ) );
		add_action( 'wp_ajax_cloux_seo_remote', array( $this, 'ajax_remote' ) );
		add_action( 'admin_post_cloux_seo_export', array( $this, 'export' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function menu() {
		add_menu_page( 'CLOUX SEO', 'CLOUX SEO', 'manage_options', 'cloux-seo', array( $this, 'page_dashboard' ), 'dashicons-chart-line', 90 );
		add_submenu_page( 'cloux-seo', 'Audit multi-domaines', 'Audit multi-domaines', 'manage_options', 'cloux-seo-remote', array( $this, 'page_remote' ) );
		add_submenu_page( 'cloux-seo', 'Réglages CLOUX SEO', 'Réglages', 'manage_options', 'cloux-seo-settings', array( $this, 'page_settings' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, 'cloux-seo' ) ) {
			return;
		}
		wp_enqueue_style( 'cloux-seo-admin', CLOUX_SEO_URL . 'assets/css/admin.css', array(), CLOUX_SEO_VERSION );
		wp_enqueue_script( 'cloux-seo-admin', CLOUX_SEO_URL . 'assets/js/admin.js', array( 'jquery' ), CLOUX_SEO_VERSION, true );
		wp_localize_script( 'cloux-seo-admin', 'clouxSeo', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'cloux_seo_admin' ) ) );
	}

	public function register_settings() {
		register_setting( 'cloux_seo_group', 'cloux_seo_options', array( 'sanitize_callback' => array( $this, 'sanitize' ) ) );
	}

	public function ajax_run() {
		check_ajax_referer( 'cloux_seo_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Interdit', 403 );
		}
		wp_send_json_success( $this->core->audit->run() );
	}

	public function ajax_remote() {
		check_ajax_referer( 'cloux_seo_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Interdit', 403 );
		}
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		if ( 'add' === $do ) {
			$r = $this->core->remote->add_domain( $domain );
			if ( is_wp_error( $r ) ) {
				wp_send_json_error( $r->get_error_message() );
			}
		} elseif ( 'remove' === $do ) {
			$this->core->remote->remove_domain( $domain );
		} elseif ( 'audit' === $do ) {
			wp_send_json_success( $this->core->remote->audit_domain( $domain ) );
		}
		wp_send_json_success();
	}

	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Interdit' );
		}
		check_admin_referer( 'cloux_seo_export' );

		$format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'json';
		$domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
		$report = get_option( 'cloux_seo_last_report', array() );

		if ( $domain ) {
			$key = CS_Remote::normalize( $domain );
			if ( ! is_wp_error( $key ) ) {
				$list = CS_Remote::domains();
				if ( isset( $list[ $key ]['report'] ) ) {
					$report = $list[ $key ]['report'];
				}
			}
		}

		$stamp = gmdate( 'Ymd-His' );
		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=cloux-seo-' . $stamp . '.csv' );
			echo CS_Report::csv( $report );
		} else {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=cloux-seo-' . $stamp . '.json' );
			echo CS_Report::json( $report );
		}
		exit;
	}

	public function page_dashboard() {
		$report      = get_option( 'cloux_seo_last_report', array() );
		$cats        = CS_Audit::categories();
		$score       = isset( $report['score'] ) ? (int) $report['score'] : 0;
		$export_json = wp_nonce_url( admin_url( 'admin-post.php?action=cloux_seo_export&format=json' ), 'cloux_seo_export' );
		$export_csv  = wp_nonce_url( admin_url( 'admin-post.php?action=cloux_seo_export&format=csv' ), 'cloux_seo_export' );
		?>
		<div class='wrap'>
			<h1>CLOUX SEO — Audit du site</h1>
			<p>
				<button id='cloux-seo-run' class='button button-primary'>Lancer l’audit</button>
				<a class='button' href='<?php echo esc_url( $export_json ); ?>'>Export JSON</a>
				<a class='button' href='<?php echo esc_url( $export_csv ); ?>'>Export CSV</a>
			</p>
			<div class='cloux-seo-score' style='--score:<?php echo (int) $score; ?>'><span><?php echo (int) $score; ?>/100</span></div>
			<?php if ( isset( $report['date'] ) ) : ?>
				<p>Dernier audit : <strong><?php echo esc_html( $report['date'] ); ?></strong> — audit complet automatique chaque semaine.</p>
			<?php else : ?>
				<p>Aucun audit pour le moment. Cliquer sur « Lancer l’audit ».</p>
			<?php endif; ?>
			<?php if ( ! empty( $report['categories'] ) ) : ?>
				<h2>Scores par domaine de contrôle</h2>
				<table class='widefat striped'>
					<thead><tr><th>Domaine</th><th>Score</th></tr></thead>
					<tbody>
						<?php foreach ( $report['categories'] as $key => $data ) : ?>
							<tr><td><?php echo esc_html( isset( $cats[ $key ] ) ? $cats[ $key ] : $key ); ?></td><td><?php echo (int) $data['score']; ?>/100</td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<h2>Détail des contrôles</h2>
			<?php echo CS_Report::table( $report ); ?>
		</div>
		<?php
	}

	public function page_remote() {
		$domains = CS_Remote::domains();
		?>
		<div class='wrap'>
			<h1>CLOUX SEO — Audit multi-domaines</h1>
			<p>Analyse n’importe quel domaine : HTML, metas, canonique, JSON-LD, robots.txt, sitemap, llms.txt, agents IA. Les contrôles basés sur la base WordPress (metas Yoast ou Rank Math, auteurs) ne s’appliquent qu’au site local.</p>
			<p>
				<input type='url' id='cloux-seo-domain' class='regular-text' placeholder='https://exemple.fr'>
				<button id='cloux-seo-add' class='button button-primary'>Ajouter le domaine</button>
			</p>
			<table class='widefat striped'>
				<thead><tr><th>Domaine</th><th>Score</th><th>Dernier audit</th><th>Actions</th></tr></thead>
				<tbody>
				<?php if ( empty( $domains ) ) : ?>
					<tr><td colspan='4'>Aucun domaine suivi.</td></tr>
				<?php else : ?>
					<?php foreach ( $domains as $base => $data ) : ?>
						<?php
						$score    = isset( $data['report']['score'] ) ? (int) $data['report']['score'] : null;
						$csv_url  = wp_nonce_url( admin_url( 'admin-post.php?action=cloux_seo_export&format=csv&domain=' . rawurlencode( $base ) ), 'cloux_seo_export' );
						$json_url = wp_nonce_url( admin_url( 'admin-post.php?action=cloux_seo_export&format=json&domain=' . rawurlencode( $base ) ), 'cloux_seo_export' );
						?>
						<tr>
							<td><a href='<?php echo esc_url( $base ); ?>' target='_blank' rel='noopener'><?php echo esc_html( $base ); ?></a></td>
							<td class='cloux-seo-remote-score'><?php echo null === $score ? '—' : $score . '/100'; ?></td>
							<td class='cloux-seo-remote-date'><?php echo isset( $data['report']['date'] ) ? esc_html( $data['report']['date'] ) : '—'; ?></td>
							<td>
								<button class='button cloux-seo-audit' data-domain='<?php echo esc_attr( $base ); ?>'>Auditer</button>
								<a class='button' href='<?php echo esc_url( $json_url ); ?>'>JSON</a>
								<a class='button' href='<?php echo esc_url( $csv_url ); ?>'>CSV</a>
								<button class='button cloux-seo-remove' data-domain='<?php echo esc_attr( $base ); ?>'>Supprimer</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function sanitize( $input ) {
		$out = CLOUX_SEO::defaults();

		$out['site_type']       = ( isset( $input['site_type'] ) && in_array( $input['site_type'], array( 'blog', 'org', 'local' ), true ) ) ? $input['site_type'] : 'blog';
		$out['org_name']        = isset( $input['org_name'] ) ? sanitize_text_field( $input['org_name'] ) : '';
		$out['org_description'] = isset( $input['org_description'] ) ? sanitize_textarea_field( $input['org_description'] ) : '';
		$out['contact_email']   = isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '';
		$out['street_address']  = isset( $input['street_address'] ) ? sanitize_text_field( $input['street_address'] ) : '';
		$out['postal_code']     = isset( $input['postal_code'] ) ? sanitize_text_field( $input['postal_code'] ) : '';
		$out['address_locality'] = isset( $input['address_locality'] ) ? sanitize_text_field( $input['address_locality'] ) : '';
		$out['geo_lat']         = isset( $input['geo_lat'] ) ? sanitize_text_field( $input['geo_lat'] ) : '';
		$out['geo_lng']         = isset( $input['geo_lng'] ) ? sanitize_text_field( $input['geo_lng'] ) : '';

		$sameas = isset( $input['sameas'] ) ? (string) $input['sameas'] : '';
		$lines  = preg_split( '/\r\n|\r|\n/', $sameas );
		$lines  = is_array( $lines ) ? $lines : array();
		$lines  = array_filter( array_map( 'trim', $lines ) );
		$out['sameas'] = implode( PHP_EOL, array_filter( $lines, 'esc_url_raw' ) );

		$out['schema_mode'] = ( isset( $input['schema_mode'] ) && in_array( $input['schema_mode'], array( 'auto', 'on', 'off' ), true ) ) ? $input['schema_mode'] : 'auto';
		$out['geo_enabled']     = empty( $input['geo_enabled'] ) ? 0 : 1;
		$out['eeat_author_box'] = empty( $input['eeat_author_box'] ) ? 0 : 1;
		$out['eeat_dates']      = empty( $input['eeat_dates'] ) ? 0 : 1;

		foreach ( CS_GEO::known_bots() as $bot ) {
			$out['ai_bots'][ $bot ] = empty( $input['ai_bots'][ $bot ] ) ? 0 : 1;
		}
		return $out;
	}

	public function page_settings() {
		$o = CLOUX_SEO::get_options();
		?>
		<div class='wrap'>
			<h1>CLOUX SEO — Réglages</h1>
			<form method='post' action='options.php'>
				<?php settings_fields( 'cloux_seo_group' ); ?>
				<h2 class='title'>Identité du site (E-E-A-T, Schema.org)</h2>
				<table class='form-table'>
					<tr>
						<th scope='row'><label for='cs-site-type'>Type de site</label></th>
						<td>
							<select id='cs-site-type' name='cloux_seo_options[site_type]'>
								<option value='blog' <?php selected( $o['site_type'], 'blog' ); ?>>Blog ou site personnel</option>
								<option value='org' <?php selected( $o['site_type'], 'org' ); ?>>Organisation ou association</option>
								<option value='local' <?php selected( $o['site_type'], 'local' ); ?>>Activité locale (LocalBusiness)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope='row'><label for='cs-org-name'>Nom</label></th>
						<td><input type='text' id='cs-org-name' class='regular-text' name='cloux_seo_options[org_name]' value='<?php echo esc_attr( $o['org_name'] ); ?>'></td>
					</tr>
					<tr>
						<th scope='row'><label for='cs-org-desc'>Description</label></th>
						<td><textarea id='cs-org-desc' class='large-text' rows='3' name='cloux_seo_options[org_description]'><?php echo esc_textarea( $o['org_description'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope='row'><label for='cs-email'>Email de contact</label></th>
						<td><input type='email' id='cs-email' class='regular-text' name='cloux_seo_options[contact_email]' value='<?php echo esc_attr( $o['contact_email'] ); ?>'></td>
					</tr>
					<tr>
						<th scope='row'><label for='cs-sameas'>Profils externes (sameAs)</label></th>
						<td>
							<textarea id='cs-sameas' class='large-text' rows='4' name='cloux_seo_options[sameas]'><?php echo esc_textarea( $o['sameas'] ); ?></textarea>
							<p class='description'>Une URL par ligne (X, QRZ, LinkedIn…).</p>
						</td>
					</tr>
				</table>

				<h2 class='title'>SEO local (LocalBusiness)</h2>
				<table class='form-table'>
					<tr>
						<th scope='row'><label for='cs-street'>Adresse</label></th>
						<td><input type='text' id='cs-street' class='regular-text' name='cloux_seo_options[street_address]' value='<?php echo esc_attr( $o['street_address'] ); ?>'></td>
					</tr>
					<tr>
						<th scope='row'><label for='cs-cp'>Code postal</label></th>
						<td><input type='text' id='cs-cp' name='cloux_seo_options[postal_code]' value='<?php echo esc_attr( $o['postal_code'] ); ?>'></td>
					</tr>
					<tr>
						<th scope='row'><label for='cs-city'>Ville</label></th>
						<td><input type='text' id='cs-city' name='cloux_seo_options[address_locality]' value='<?php echo esc_attr( $o['address_locality'] ); ?>'></td>
					</tr>
					<tr>
						<th scope='row'>Coordonnées GPS</th>
						<td>
							<input type='text' name='cloux_seo_options[geo_lat]' value='<?php echo esc_attr( $o['geo_lat'] ); ?>' placeholder='latitude'>
							<input type='text' name='cloux_seo_options[geo_lng]' value='<?php echo esc_attr( $o['geo_lng'] ); ?>' placeholder='longitude'>
						</td>
					</tr>
				</table>

				<h2 class='title'>Modules</h2>
				<table class='form-table'>
					<tr>
						<th scope='row'><label for='cs-schema'>Schema JSON-LD</label></th>
						<td>
							<select id='cs-schema' name='cloux_seo_options[schema_mode]'>
								<option value='auto' <?php selected( $o['schema_mode'], 'auto' ); ?>>Auto (désactivé si Yoast ou Rank Math actif)</option>
								<option value='on' <?php selected( $o['schema_mode'], 'on' ); ?>>Toujours actif</option>
								<option value='off' <?php selected( $o['schema_mode'], 'off' ); ?>>Désactivé</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope='row'>GEO / agents IA</th>
						<td><label><input type='checkbox' name='cloux_seo_options[geo_enabled]' value='1' <?php checked( ! empty( $o['geo_enabled'] ) ); ?>> Activer llms.txt, ai-catalog.json et les règles robots.txt pour agents IA</label></td>
					</tr>
					<tr>
						<th scope='row'>Encart auteur</th>
						<td><label><input type='checkbox' name='cloux_seo_options[eeat_author_box]' value='1' <?php checked( ! empty( $o['eeat_author_box'] ) ); ?>> Afficher l’encart auteur en fin d’article</label></td>
					</tr>
					<tr>
						<th scope='row'>Dates E-E-A-T</th>
						<td><label><input type='checkbox' name='cloux_seo_options[eeat_dates]' value='1' <?php checked( ! empty( $o['eeat_dates'] ) ); ?>> Afficher publication et mise à jour en fin d’article</label></td>
					</tr>
				</table>

				<h2 class='title'>Agents IA dans robots.txt</h2>
				<fieldset>
					<?php foreach ( CS_GEO::known_bots() as $bot ) : ?>
						<label style='display:block;margin-bottom:6px;'>
							<input type='checkbox' name='cloux_seo_options[ai_bots][<?php echo esc_attr( $bot ); ?>]' value='1' <?php checked( ! empty( $o['ai_bots'][ $bot ] ) ); ?>>
							<?php echo esc_html( $bot ); ?>
						</label>
					<?php endforeach; ?>
					<p class='description'>Coché : Allow. Décoché : Disallow.</p>
				</fieldset>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
