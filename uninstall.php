<?php
/**
 * Deleting the plugin: its settings go (the business, tracking codes, consent, redirects and the 404 log, the IndexNow key), on every site of a network. The city pages (the city post type) and the SEO
 * fields on posts stay: they're the site's content, and they come back as they were if the plugin is installed again.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mcl_uninstall = function () {
	delete_option( 'marccent_local' );
	delete_option( 'mcl_flush_rewrites' );
	delete_option( 'mcl_tracking' );
	foreach ( array( 'mcl_consent', 'mcl_redirects', 'mcl_404_log', 'mcl_indexnow_key', 'mcl_indexnow_queue', 'mcl_indexnow_last' ) as $mcl_option ) {
		delete_option( $mcl_option );
	}
	delete_transient( 'mcl_schema' );
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $mcl_site ) {
		switch_to_blog( $mcl_site );
		$mcl_uninstall();
		restore_current_blog();
	}
} else {
	$mcl_uninstall();
}
