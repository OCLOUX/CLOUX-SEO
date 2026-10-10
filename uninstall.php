<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cloux_seo_options' );
delete_option( 'cloux_seo_last_report' );
delete_option( 'cloux_seo_remote_domains' );
wp_clear_scheduled_hook( 'cloux_seo_weekly_audit' );
