<?php
/**
 * The business, once: the site's own field groups (Fields → Field groups, edited under Site content), which this plugin
 * reads by name and never makes: Business (its details and Monday…Sunday hours; {{ business.phone }} in any block) and
 * a group's rows for every list, Other names (business_names), Profiles, Specialties, Services, Closed days
 * (closures), Locations, Regions and the Call bar. A site without a group gets its defaults. Schema, the hours and the
 * map read them all through mcl_settings(), so every mention of the business agrees. A new site gets the groups by
 * starting from another site's spec (types.json, or Tools → Marccent export).
 */

defined( 'ABSPATH' ) || exit;

const MCL_DAY_FIELDS = array( 'mo' => 'monday', 'tu' => 'tuesday', 'we' => 'wednesday', 'th' => 'thursday', 'fr' => 'friday', 'sa' => 'saturday', 'su' => 'sunday' );

function mcl_group( string $name ): array {
	return function_exists( 'mcc_group_value' ) ? mcc_group_value( $name ) : array();
}

// A day's hours from a group's monday…sunday: [ mo => '08:00-17:00', … ].
function mcl_group_hours( array $values ): array {
	$hours = array();
	foreach ( MCL_DAY_FIELDS as $key => $day ) {
		$hours[ $key ] = (string) ( $values[ $day ] ?? '' );
	}
	return mcl_hours_sanitize( $hours );
}

// A coordinate: a number field left empty is 0, which is no place.
function mcl_coordinate( $value ): string {
	return is_numeric( $value ) && 0.0 !== (float) $value ? (string) $value : '';
}

/**
 * Everything the plugin reads about the business, from the groups, in one shape (with defaults for what's empty):
 * name, alternate, type, telephone, phone, email, street, city, region, postal, country, lat, lng, map, same_as, summary, service_area,
 * profession, services_label, knows_about, services_parent, services, regions, hours, closures, locations.
 */
function mcl_settings(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$b       = mcl_group( 'business' );
	$text    = fn( $value, $default = '' ) => '' !== trim( (string) $value ) ? trim( (string) $value ) : $default;
	$column  = fn( $group, $field ) => array_values( array_filter( array_map( 'trim', array_column( mcl_group( $group ), $field ) ) ) );
	$cache   = array(
		'name'            => $text( $b['name'] ?? '', get_bloginfo( 'name' ) ),
		'alternate'       => $column( 'business_names', 'name' ),
		'type'            => $text( $b['schema_type'] ?? '', 'LocalBusiness' ),
		'telephone'       => $text( $b['telephone'] ?? '' ),
		'phone'           => $text( $b['phone'] ?? '' ),
		'email'           => $text( $b['email'] ?? '' ),
		'street'          => $text( $b['street'] ?? '' ),
		'city'            => $text( $b['city'] ?? '' ),
		'region'          => $text( $b['state'] ?? '' ),
		'postal'          => $text( $b['postal_code'] ?? '' ),
		'country'         => $text( $b['country'] ?? '', 'US' ),
		'lat'             => mcl_coordinate( $b['latitude'] ?? '' ),
		'lng'             => mcl_coordinate( $b['longitude'] ?? '' ),
		'map'             => $text( $b['map_link'] ?? '' ),
		'same_as'         => $column( 'profiles', 'link' ),
		'summary'         => $text( $b['summary'] ?? '', get_bloginfo( 'description' ) ),
		'service_area'    => $text( $b['service_area'] ?? '' ),
		'profession'      => $text( $b['profession'] ?? '', 'Local business' ),
		'services_label'  => $text( $b['services_label'] ?? '', 'services' ),
		'knows_about'     => $column( 'specialties', 'specialty' ),
		'services_parent' => $text( $b['services_page'] ?? '', 'services' ),
		'services'        => array_values( array_filter( mcl_group( 'services' ), fn( $s ) => '' !== ( $s['slug'] ?? '' ) ) ),
		'regions'         => mcl_group( 'regions' ),
		'hours'           => mcl_group_hours( $b ),
		'closures'        => mcl_closures_sanitize( mcl_group( 'closures' ) ),
		'locations'       => array_map( fn( $l ) => array(
			'slug'      => $l['slug'] ?? '',
			'name'      => $l['name'] ?? '',
			'street'    => $l['street'] ?? '',
			'city'      => $l['city'] ?? '',
			'region'    => $l['state'] ?? '',
			'postal'    => $l['postal_code'] ?? '',
			'telephone' => $l['telephone'] ?? '',
			'phone'     => $l['phone'] ?? '',
			'lat'       => mcl_coordinate( $l['latitude'] ?? '' ),
			'lng'       => mcl_coordinate( $l['longitude'] ?? '' ),
			'map'       => $l['map_link'] ?? '',
			'hours'     => mcl_group_hours( $l ),
		), mcl_group( 'locations' ) ),
	);
	return $cache;
}

// The service pages, as [ slug => WP_Post ] in the services' order (the published children of the services page): the
// services page, then all of them at once, not a lookup each.
function mcl_service_pages(): array {
	$s      = mcl_settings();
	$slugs  = array_column( $s['services'], 'slug' );
	$parent = $slugs ? get_page_by_path( $s['services_parent'] ) : null;
	if ( ! $parent ) {
		return array();
	}
	$found = array();
	foreach ( get_posts( array( 'post_type' => 'page', 'post_parent' => $parent->ID, 'post_name__in' => $slugs, 'numberposts' => -1, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) ) as $page ) {
		$found[ $page->post_name ] = $page;
	}
	$out = array();
	foreach ( $slugs as $slug ) {
		if ( isset( $found[ $slug ] ) ) {
			$out[ $slug ] = $found[ $slug ];
		}
	}
	return $out;
}

function mcl_service( string $slug ): ?array {
	foreach ( mcl_settings()['services'] as $service ) {
		if ( $service['slug'] === $slug ) {
			return $service;
		}
	}
	return null;
}

/**
 * The business as older versions kept it (Settings → Business's marccent_local option, the mcl_callbar option), as
 * the groups' values: [ group => value ], for the one-time move below. The areas archive's words went with the
 * archive (the Service Areas page has them now), so they aren't kept.
 */
function mcl_groups_from_settings( array $old, array $callbar = array() ): array {
	$rows  = fn( $list, $field ) => array_map( fn( $value ) => array( $field => $value ), array_values( array_filter( (array) $list ) ) );
	$days  = fn( $hours ) => array_combine( array_values( MCL_DAY_FIELDS ), array_map( fn( $key ) => (string) ( ( (array) $hours )[ $key ] ?? '' ), array_keys( MCL_DAY_FIELDS ) ) );
	$out   = array(
		'business'               => array(
			'name'           => $old['name'] ?? '',
			'schema_type'    => $old['type'] ?? '',
			'profession'     => $old['profession'] ?? '',
			'services_label' => $old['services_label'] ?? '',
			'summary'        => $old['summary'] ?? '',
			'telephone'      => $old['telephone'] ?? '',
			'phone'          => $old['phone'] ?? '',
			'email'          => $old['email'] ?? '',
			'street'         => $old['street'] ?? '',
			'city'           => $old['city'] ?? '',
			'state'          => $old['region'] ?? '',
			'postal_code'    => $old['postal'] ?? '',
			'country'        => $old['country'] ?? '',
			'latitude'       => $old['lat'] ?? '',
			'longitude'      => $old['lng'] ?? '',
			'map_link'       => $old['map'] ?? '',
			'service_area'   => $old['service_area'] ?? '',
			'services_page'  => $old['services_parent'] ?? '',
		) + $days( $old['hours'] ?? array() ),
		'business_names'         => $rows( $old['alternate'] ?? array(), 'name' ),
		'profiles'               => $rows( $old['same_as'] ?? array(), 'link' ),
		'specialties'            => $rows( $old['knows_about'] ?? array(), 'specialty' ),
		'services'               => array_values( (array) ( $old['services'] ?? array() ) ),
		'closures'               => array_values( (array) ( $old['closures'] ?? array() ) ),
		'locations'              => array_map( fn( $l ) => array(
			'slug'        => $l['slug'] ?? '',
			'name'        => $l['name'] ?? '',
			'street'      => $l['street'] ?? '',
			'city'        => $l['city'] ?? '',
			'state'       => $l['region'] ?? '',
			'postal_code' => $l['postal'] ?? '',
			'telephone'   => $l['telephone'] ?? '',
			'phone'       => $l['phone'] ?? '',
			'latitude'    => $l['lat'] ?? '',
			'longitude'   => $l['lng'] ?? '',
			'map_link'    => $l['map'] ?? '',
		) + $days( $l['hours'] ?? array() ), array_values( array_filter( (array) ( $old['locations'] ?? array() ), 'is_array' ) ) ),
	);
	if ( ! empty( $old['regions'] ) ) {
		$out['regions'] = array_values( (array) $old['regions'] );
	}
	if ( $callbar ) {
		$out['call_bar'] = array(
			'show'        => ! empty( $callbar['on'] ),
			'call_label'  => $callbar['call'] ?? '',
			'quote_label' => $callbar['quote'] ?? '',
			'quote_link'  => $callbar['href'] ?? '',
		);
	}
	return $out;
}

// Once: Settings → Business's option (and the call bar's) into the groups, then gone; the regions only when the site
// has no Regions rows yet. First thing on init; a Maps key Marccent Blocks hasn't taken yet (init, 10) stays for it.
add_action( 'init', function () {
	$old = get_option( 'marccent_local', null );
	// Only once the site has the Business group to put it in (Fields → Field groups).
	if ( ! is_array( $old ) || array( 'maps_key' ) === array_keys( $old ) || ! function_exists( 'mcc_site_groups' ) || ! isset( mcc_site_groups()['business'] ) ) {
		return;
	}
	$values = mcl_groups_from_settings( $old, (array) get_option( 'mcl_callbar', array() ) );
	if ( mcl_group( 'regions' ) ) {
		unset( $values['regions'] );
	}
	mcc_save_site_values( $values, false );
	empty( $old['maps_key'] ) ? delete_option( 'marccent_local' ) : update_option( 'marccent_local', array( 'maps_key' => $old['maps_key'] ) );
	delete_option( 'mcl_callbar' );
	delete_transient( 'mcl_schema' );
}, 1 );
