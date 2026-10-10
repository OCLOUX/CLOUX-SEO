<?php
/**
 * Plugin Name: CLOUX SEO
 * Plugin URI:  https://github.com/OCLOUX/CLOUX-SEO
 * Description: Suite SEO pour WordPress : audit technique du site et de n’importe quel domaine externe, Schema.org JSON-LD, GEO/AEO (llms.txt, ai-catalog.json, robots.txt agents IA), signaux E-E-A-T, scoring 0-100 et exports CSV/JSON. Complémentaire de Rank Math.
 * Version:     1.0.0
 * Author:      OCLOUX
 * Author URI:  https://www.cloux.net
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloux-seo
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLOUX_SEO_VERSION', '1.0.0' );
define( 'CLOUX_SEO_FILE', __FILE__ );
define( 'CLOUX_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLOUX_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once CLOUX_SEO_DIR . 'includes/class-cs-eeat.php';
require_once CLOUX_SEO_DIR . 'includes/class-cs-schema.php';
require_once CLOUX_SEO_DIR . 'includes/class-cs-geo.php';
require_once CLOUX_SEO_DIR . 'includes/class-cs-audit.php';
require_once CLOUX_SEO_DIR . 'includes/class-cs-remote.php';
require_once CLOUX_SEO_DIR . 'includes/class-cs-report.php';
require_once CLOUX_SEO_DIR . 'includes/class-cs-admin.php';

register_activation_hook( __FILE__, array( 'CLOUX_SEO', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CLOUX_SEO', 'deactivate' ) );

final class CLOUX_SEO {

	private static $instance = null;

	public $options;
	public $eeat;
	public $schema;
	public $geo;
	public $audit;
	public $remote;
	public $report;
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->options = self::get_options();
		$this->eeat    = new CS_EEAT( $this->options );
		$this->schema  = new CS_Schema( $this->options );
		$this->geo     = new CS_GEO( $this->options );
		$this->audit   = new CS_Audit( $this->options );
		$this->remote  = new CS_Remote();
		$this->report  = new CS_Report();
		$this->admin   = new CS_Admin( $this );

		add_action( 'cloux_seo_weekly_audit', array( $this, 'cron_audit' ) );
	}

	public function cron_audit() {
		$this->audit->run();
		$this->remote->run_all();
	}

	public static function defaults() {
		return array(
			'site_type'        => 'blog',
			'org_name'         => '',
			'org_description'  => '',
			'contact_email'    => '',
			'street_address'   => '',
			'postal_code'      => '',
			'address_locality' => '',
			'geo_lat'          => '',
			'geo_lng'          => '',
			'sameas'           => '',
			'schema_mode'      => 'auto',
			'geo_enabled'      => 1,
			'eeat_author_box'  => 1,
			'eeat_dates'       => 1,
			'ai_bots'          => array(
				'GPTBot'             => 1,
				'OAI-SearchBot'      => 1,
				'ChatGPT-User'       => 1,
				'ClaudeBot'          => 1,
				'Claude-Web'         => 1,
				'anthropic-ai'       => 1,
				'PerplexityBot'     => 1,
				'Google-Extended'   => 1,
				'Applebot-Extended'  => 1,
				'CCBot'              => 0,
				'meta-externalagent' => 1,
				'Bytespider'         => 0,
			),
		);
	}

	public static function get_options() {
		return wp_parse_args( (array) get_option( 'cloux_seo_options', array() ), self::defaults() );
	}

	public static function activate() {
		if ( false === get_option( 'cloux_seo_options', false ) ) {
			add_option( 'cloux_seo_options', self::defaults(), '', false );
		}
		CS_GEO::register_rewrites();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'cloux_seo_weekly_audit' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'cloux_seo_weekly_audit' );
		}
	}

	public static function deactivate() {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'cloux_seo_weekly_audit' );
	}
}

CLOUX_SEO::instance();
