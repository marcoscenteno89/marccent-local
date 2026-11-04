<?php
/**
 * Plugin Name: Marccent Local
 * Description: A small local business on Marccent: its details (name, phone, location, opening hours, locations, services), a page for each city or area it serves with their archive, the search and answer-engine layer (titles, descriptions, Open Graph, JSON-LD, robots.txt, llms.txt, sitemaps, IndexNow), redirects with a 404 log, tracking codes with cookie consent, and a map in the theme's colors. Set it up under Settings → Business and Tools → Redirects.
 * Version: 0.2.1
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Requires Plugins: marccent-blocks
 * Author: Marcos Centeno
 * License: GPL-2.0-or-later
 * Text Domain: marccent-local
 */

defined( 'ABSPATH' ) || exit;

const MCL_DIR = __DIR__;

require MCL_DIR . '/inc/settings.php';
require MCL_DIR . '/inc/areas.php';
require MCL_DIR . '/inc/seo.php';
require MCL_DIR . '/inc/schema.php';
require MCL_DIR . '/inc/answers.php';
require MCL_DIR . '/inc/performance.php';
require MCL_DIR . '/inc/tracking.php';
require MCL_DIR . '/inc/consent.php';
require MCL_DIR . '/inc/callbar.php';
require MCL_DIR . '/inc/hours.php';
require MCL_DIR . '/inc/redirects.php';
require MCL_DIR . '/inc/indexnow.php';

// The plugin's options in one query if they aren't autoloaded or don't exist yet (a site that hasn't saved them), not
// one each on every page.
wp_prime_option_caches( array( MCL_OPTION, MCL_TRACKING, MCL_CONSENT, MCL_REDIRECTS ) );

register_activation_hook( __FILE__, function () {
	mcl_register_areas();
	flush_rewrite_rules( false );
} );

// The plugin's few styles (breadcrumbs, chips, tables): inline after the theme's, too small for a request of their own.
add_action( 'enqueue_block_assets', function () {
	wp_add_inline_style( 'marccent', (string) file_get_contents( MCL_DIR . '/assets/site.css' ) );
}, 20 );

// The map is marccent-theme's (data-component="map": Marccent Blocks loads the theme's engine, which draws it). Its key
// is the business's (Settings → Business) for the whole page, so it stays out of the content; without one, the
// map's data-embed is Google's plain embed.
add_action( 'wp_enqueue_scripts', function () {
	$key = mcl_settings()['maps_key'];
	if ( $key && wp_script_is( 'marccent', 'registered' ) ) {
		wp_add_inline_script( 'marccent', 'window.marccentMapsKey = ' . wp_json_encode( $key ) . ';', 'before' );
	}
}, 21 );
