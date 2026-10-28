<?php
/**
 * Plugin Name: Marccent Local
 * Description: A small local business on Marccent: its details (name, phone, location, services), a page for each city or area it serves with their archive, and the search and answer-engine layer (titles, descriptions, Open Graph, JSON-LD, robots.txt, llms.txt, sitemaps) and a map in the theme's colors. Set it up under Settings → Business.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.0
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

register_activation_hook( __FILE__, function () {
	mcl_register_areas();
	flush_rewrite_rules( false );
} );

// The plugin's few styles (breadcrumbs, chips, tables): inline after the theme's, too small for a request of their own.
add_action( 'enqueue_block_assets', function () {
	wp_add_inline_style( 'marccent', (string) file_get_contents( MCL_DIR . '/assets/site.css' ) );
}, 20 );

// Whether the page has a map (.local-map), seen as its blocks render: a block theme renders them all before the head.
add_filter( 'render_block', function ( $html ) {
	if ( ! is_admin() && $html && str_contains( $html, 'local-map' ) ) {
		$GLOBALS['mcl_has_map'] = true;
	}
	return $html;
} );

// The map (assets/map.js), in the site palette's two colors, on pages with one; without a Maps key it's Google's
// plain embed.
add_action( 'wp_enqueue_scripts', function () {
	if ( empty( $GLOBALS['mcl_has_map'] ) ) {
		return;
	}
	$palette = mcl_palette();
	wp_enqueue_script( 'marccent-local-map', plugins_url( 'assets/map.js', __FILE__ ), array(), filemtime( MCL_DIR . '/assets/map.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_add_inline_script( 'marccent-local-map', 'window.marccentLocalMap = ' . wp_json_encode( array( 'key' => mcl_settings()['maps_key'], 'primary' => $palette[0], 'secondary' => $palette[1] ) ) . ';', 'before' );
} );

/**
 * The site's two brand colors (Marccent Blocks' starting palette, the marccent_defaults option), for the map.
 */
function mcl_palette(): array {
	$d  = function_exists( 'marccent_blocks_defaults' ) ? marccent_blocks_defaults() : array();
	$id = $d['palette'] ?? '';
	foreach ( $d['palettes'] ?? array() as $p ) {
		if ( ( $p['id'] ?? '' ) === $id ) {
			return array( $p['primary'], $p['secondary'] );
		}
	}
	return array( '#1f64cf', '#e4572e' );
}
