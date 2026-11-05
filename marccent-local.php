<?php
/**
 * Plugin Name: Marccent Local
 * Description: A small local business on Marccent: its details (name, phone, location, opening hours, locations, services), a page for each city or area it serves with their archive, its JSON-LD (the business, its services and cities, each page's questions), tracking codes with cookie consent, the phone call bar and a map in the theme's colors. Titles, descriptions, sitemaps, redirects, IndexNow and llms.txt are Rank Math's. Set it up under Settings → Business.
 * Version: 0.3.0
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
require MCL_DIR . '/inc/schema.php';
require MCL_DIR . '/inc/tracking.php';
require MCL_DIR . '/inc/consent.php';
require MCL_DIR . '/inc/callbar.php';
require MCL_DIR . '/inc/hours.php';

// The plugin's options in one query if they aren't autoloaded or don't exist yet (a site that hasn't saved them), not
// one each on every page.
wp_prime_option_caches( array( MCL_OPTION, MCL_TRACKING, MCL_CONSENT ) );

register_activation_hook( __FILE__, function () {
	mcl_register_areas();
	flush_rewrite_rules( false );
} );

// The plugin's few styles (breadcrumbs, chips, tables): inline after the theme's, too small for a request of their own.
add_action( 'enqueue_block_assets', function () {
	wp_add_inline_style( 'marccent', (string) file_get_contents( MCL_DIR . '/assets/site.css' ) );
}, 20 );

// A Map block left empty (Marccent Blocks) shows the business: its coordinates, its name and city on the pin, its
// Google Maps link, and Google's embed of it for a site without a key.
add_filter( 'marccent_blocks_map', function ( array $map ) {
	$b     = mcl_settings();
	$where = trim( $b['name'] . ', ' . $b['city'] . ( $b['region'] ? ', ' . $b['region'] : '' ), ', ' );
	return $map + array_filter( array(
		'lat'   => $b['lat'],
		'lng'   => $b['lng'],
		'title' => $where,
		'link'  => $b['map'],
		'embed' => 'https://www.google.com/maps?q=' . rawurlencode( $where ) . '&output=embed',
	) );
} );
