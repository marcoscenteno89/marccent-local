<?php
/**
 * Deleting the plugin: its settings go, on every site of a network. The city pages (the city post type) and the SEO
 * fields on posts stay: they're the site's content, and they come back as they were if the plugin is installed again.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mcl_uninstall = function () {
	delete_option( 'marccent_local' );
	delete_option( 'mcl_flush_rewrites' );
	delete_option( 'mcl_tracking' );
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
