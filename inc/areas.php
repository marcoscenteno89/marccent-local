<?php
/**
 * Areas: the cities or areas the business serves are the site's `city` posts (Fields → Post types: their names and
 * address), with their City details fields (state, state_code, county, region, latitude, longitude). Their list is a
 * page built with Loops (the regions, then each region's cities). This plugin only reads them, for the schema.
 */

defined( 'ABSPATH' ) || exit;

// The regions, in their order: [ key => [ name, blurb ] ]: the site's Regions rows (Site content), which the pages'
// Loops list too.
function mcl_regions(): array {
	$out = array();
	foreach ( mcl_settings()['regions'] as $r ) {
		if ( ! empty( $r['key'] ) ) {
			$out[ $r['key'] ] = array( 'name' => $r['name'] ?? $r['key'], 'blurb' => $r['blurb'] ?? '' );
		}
	}
	return $out;
}

// A city's own fields (City details, in full words), which the schema reads.
const MCL_CITY_FIELDS = array( 'state', 'state_code', 'county', 'region', 'latitude', 'longitude' );

// Once: the names older versions saved (mcl_state, mcl_lat…, a page's mcl_faq) renamed to the full words.
add_action( 'init', function () {
	global $wpdb;
	if ( get_option( 'mcl_full_word_fields' ) ) {
		return;
	}
	$renames = array( 'mcl_state' => 'state', 'mcl_state_code' => 'state_code', 'mcl_county' => 'county', 'mcl_region' => 'region', 'mcl_lat' => 'latitude', 'mcl_lng' => 'longitude', 'mcl_faq' => 'questions' );
	foreach ( $renames as $old => $new ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => $new ), array( 'meta_key' => $old ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	wp_cache_flush();
	update_option( 'mcl_full_word_fields', 1 );
}, 1 );
