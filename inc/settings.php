<?php
/**
 * The business, once (the marccent_local option): name, phone, location (NAP), profiles, its services and the regions
 * its areas are grouped in, the areas' names and URL, and the words of the areas archive. Schema, llms.txt, the
 * directory and the map all read it, so every mention of the business agrees. Settings → Business edits it; a site's
 * content import can write it whole.
 */

defined( 'ABSPATH' ) || exit;

const MCL_OPTION = 'marccent_local';

function mcl_defaults(): array {
	return array(
		'name'            => get_bloginfo( 'name' ),
		'alternate'       => array(),          // other names people use
		'type'            => 'LocalBusiness', // its schema.org type: GeneralContractor, Plumber, HVACBusiness…
		'telephone'       => '',               // +1-555-555-0100
		'phone'           => '',               // (555) 555-0100, as shown
		'email'           => '',
		'city'            => '',               // no street address: the business is listed by city
		'region'          => '',               // IL
		'country'         => 'US',
		'lat'             => '',
		'lng'             => '',
		'map'             => '',               // a Google Maps link to the business
		'same_as'         => array(),          // its profiles (Facebook, Instagram…)
		'summary'         => get_bloginfo( 'description' ),
		'service_area'    => '',               // one line: where it works
		'profession'      => 'Local business', // what it is, in a phrase: Plumber
		'services_label'  => 'services',       // its services as a group: plumbing services
		'knows_about'     => array(),
		'services_parent' => 'services',       // the page the service pages are children of
		'services'        => array(),          // [ { slug, name, type, covers } ]
		'regions'         => array(),          // [ { key, name, blurb } ]: the areas' groups, in order
		'areas'           => array( 'singular' => 'City', 'plural' => 'Cities', 'label' => 'Service Areas', 'slug' => 'service-areas' ),
		'archive'         => array( 'title' => '', 'description' => '', 'h1' => 'Service Areas', 'lead' => '', 'card_button' => 'More about %s', 'faq_heading' => 'Questions', 'faq' => array(), 'cta_heading' => '', 'cta_text' => '', 'quote_href' => '#form' ),
		'maps_key'        => '',               // a browser key for the Maps JavaScript API
	);
}

function mcl_settings(): array {
	static $cache = null;
	if ( null === $cache ) {
		$saved = get_option( MCL_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$d     = mcl_defaults();
		$cache = array_merge( $d, $saved );
		foreach ( array( 'areas', 'archive' ) as $group ) {
			$cache[ $group ] = array_merge( $d[ $group ], (array) ( $saved[ $group ] ?? array() ) );
		}
	}
	return $cache;
}

// The service pages, as [ slug => WP_Post ] (children of the services page).
function mcl_service_pages(): array {
	$s   = mcl_settings();
	$out = array();
	foreach ( $s['services'] as $service ) {
		$page = get_page_by_path( $s['services_parent'] . '/' . $service['slug'] );
		if ( $page ) {
			$out[ $service['slug'] ] = $page;
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

/*
 * Settings → Business. Plain fields for the details; the lists (services, regions, the archive's words) as JSON, with
 * an example each, since their shape is fixed and few people will edit them by hand.
 */
add_action( 'admin_menu', function () {
	add_options_page( 'Business', 'Business', 'manage_options', 'marccent-local', 'mcl_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'marccent-local', MCL_OPTION, array( 'type' => 'array', 'sanitize_callback' => 'mcl_sanitize' ) );
} );

const MCL_LINES = array( 'alternate', 'same_as', 'knows_about' );
const MCL_JSON  = array( 'services', 'regions', 'areas', 'archive' );

function mcl_sanitize( $input ): array {
	$old = mcl_settings();
	$out = array();
	foreach ( mcl_defaults() as $key => $default ) {
		$value = $input[ $key ] ?? $old[ $key ];
		if ( in_array( $key, MCL_LINES, true ) ) {
			$out[ $key ] = is_array( $value ) ? $value : array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $value ) ) ) );
		} elseif ( in_array( $key, MCL_JSON, true ) ) {
			$decoded = is_array( $value ) ? $value : json_decode( wp_unslash( (string) $value ), true );
			if ( ! is_array( $decoded ) ) {
				add_settings_error( MCL_OPTION, $key, sprintf( '%s is not valid JSON; the previous value was kept.', ucfirst( $key ) ) );
				$decoded = $old[ $key ];
			}
			$out[ $key ] = $decoded;
		} elseif ( in_array( $key, array( 'map' ), true ) ) {
			$out[ $key ] = esc_url_raw( (string) $value );
		} elseif ( 'summary' === $key ) {
			$out[ $key ] = sanitize_textarea_field( (string) $value );
		} else {
			$out[ $key ] = sanitize_text_field( trim( (string) $value, " \t\n\r\0\x0B\"'" ) );
		}
	}
	if ( ( $out['areas']['slug'] ?? '' ) !== $old['areas']['slug'] ) {
		update_option( 'mcl_flush_rewrites', 1 );
	}
	return $out;
}

// A changed areas URL takes effect once the rewrite rules are rebuilt, on the next load.
add_action( 'init', function () {
	if ( get_option( 'mcl_flush_rewrites' ) ) {
		delete_option( 'mcl_flush_rewrites' );
		flush_rewrite_rules( false );
	}
}, 99 );

function mcl_settings_page(): void {
	$s     = mcl_settings();
	$field = function ( $key, $label, $help = '' ) use ( $s ) {
		$name = MCL_OPTION . "[$key]";
		echo '<tr><th scope="row"><label for="mcl-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( in_array( $key, MCL_LINES, true ) ) {
			printf( '<textarea id="mcl-%s" name="%s" rows="3" class="large-text">%s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( implode( "\n", $s[ $key ] ) ) );
		} elseif ( in_array( $key, MCL_JSON, true ) ) {
			printf( '<textarea id="mcl-%s" name="%s" rows="8" class="large-text code">%s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( wp_json_encode( $s[ $key ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		} elseif ( 'summary' === $key ) {
			printf( '<textarea id="mcl-%s" name="%s" rows="3" class="large-text">%s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( $s[ $key ] ) );
		} else {
			printf( '<input id="mcl-%s" name="%s" value="%s" class="regular-text">', esc_attr( $key ), esc_attr( $name ), esc_attr( (string) $s[ $key ] ) );
		}
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	echo '<div class="wrap"><h1>Business</h1><form method="post" action="options.php">';
	settings_fields( 'marccent-local' );
	echo '<h2>Details</h2><table class="form-table" role="presentation">';
	$field( 'name', 'Name' );
	$field( 'alternate', 'Other names', 'One per line.' );
	$field( 'type', 'Kind of business', 'Its <a href="https://schema.org/LocalBusiness" target="_blank" rel="noopener">schema.org type</a>: GeneralContractor, Plumber, HVACBusiness, LocalBusiness…' );
	$field( 'summary', 'Summary', 'Two or three sentences: what it does and where. Search and AI answers quote it.' );
	$field( 'telephone', 'Phone (for links)', '+1-555-555-0100' );
	$field( 'phone', 'Phone (as shown)', '(555) 555-0100' );
	$field( 'email', 'Email' );
	$field( 'city', 'City', 'No street address: the business is listed by city.' );
	$field( 'region', 'State', 'Two letters: ID' );
	$field( 'country', 'Country', 'Two letters: US' );
	$field( 'lat', 'Latitude', 'Where the map centers.' );
	$field( 'lng', 'Longitude' );
	$field( 'map', 'Google Maps link' );
	$field( 'same_as', 'Profiles', 'Facebook, Instagram… one link per line.' );
	$field( 'profession', 'What it is', 'In a phrase: Plumber. Used as "Plumber in Springfield, IL".' );
	$field( 'services_label', 'Its services, together', 'plumbing services' );
	$field( 'service_area', 'Service area', 'One line: Springfield and the towns around it in central Illinois' );
	$field( 'knows_about', 'Specialties', 'One per line.' );
	$field( 'maps_key', 'Google Maps key', 'A browser key with the Maps JavaScript API, restricted to this site. Empty: Google\'s plain embed.' );
	echo '</table><h2>Services and areas</h2><table class="form-table" role="presentation">';
	$field( 'services_parent', 'Services page', 'The slug of the page the service pages sit under.' );
	$field( 'services', 'Services', 'JSON: <code>[{"slug": "drains", "name": "Drain Cleaning", "type": "Drain cleaning", "covers": "kitchen, bath and main lines…"}]</code>' );
	$field( 'regions', 'Regions', 'JSON, the groups the areas are listed in: <code>[{"key": "central-illinois", "name": "Central Illinois", "blurb": "…"}]</code>' );
	$field( 'areas', 'Areas', 'JSON: what an area is called and the archive\'s URL: <code>{"singular": "City", "plural": "Cities", "label": "Service Areas", "slug": "service-areas"}</code>' );
	$field( 'archive', 'Areas archive', 'JSON, the archive page\'s words: <code>{"title", "description", "h1", "lead", "card_button": "More about %s", "faq_heading", "faq": [{"q", "a"}], "cta_heading", "cta_text", "quote_href"}</code>' );
	echo '</table>';
	submit_button();
	echo '</form></div>';
}
