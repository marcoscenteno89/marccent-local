<?php
/**
 * Plugin Name: Marccent Local
 * Description: A small local business on Marccent: reads its details (the site's Business, Services, Locations… field groups, under Site content) and its cities (the site's City posts) for its JSON-LD (the business, its services and the places it serves, its opening hours and locations), the hours shortcodes, the phone call bar and the map's default place. It makes no post types or field groups: those are the site's, in Fields. Tracking codes and cookie consent are the Marccent WP theme's; titles, descriptions, sitemaps, redirects, IndexNow and llms.txt are Rank Math's.
 * Version: 0.5.0
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Requires Plugins: marccent-blocks, marccent-content
 * Author: Marcos Centeno
 * License: GPL-2.0-or-later
 * Text Domain: marccent-local
 */

defined( 'ABSPATH' ) || exit;

const MCL_DIR = __DIR__;

require MCL_DIR . '/inc/settings.php';
require MCL_DIR . '/inc/areas.php';
require MCL_DIR . '/inc/schema.php';
require MCL_DIR . '/inc/callbar.php';
require MCL_DIR . '/inc/hours.php';

// The plugin's few styles (breadcrumbs, chips, tables): inline after the theme's, too small for a request of their own.
add_action( 'enqueue_block_assets', function () {
	wp_add_inline_style( 'marccent', (string) file_get_contents( MCL_DIR . '/assets/site.css' ) );
}, 20 );

// A Map block left empty (Marccent Blocks) shows the business: its coordinates, its name and city on the pin, its
// Google Maps link, and Google's embed of it for a site without a key.
add_filter( 'marccent_blocks_map', function ( array $map ) {
	$b     = mcl_settings();
	$where = implode( ', ', array_filter( array( $b['name'], $b['street'], $b['city'], trim( $b['region'] . ' ' . $b['postal'] ) ) ) );
	return $map + array_filter( array(
		'lat'   => $b['lat'],
		'lng'   => $b['lng'],
		'title' => $where,
		'link'  => $b['map'],
		'embed' => 'https://www.google.com/maps?q=' . rawurlencode( $where ) . '&output=embed',
	) );
} );
