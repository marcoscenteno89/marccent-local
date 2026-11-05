<?php
/**
 * The business's part of the page's JSON-LD, which Marccent Content builds (its inc/schema.php: the website, the page,
 * its breadcrumbs, what the fields and the Loops show): the business itself (the site's Organization, as its schema.org
 * type, with its address, map, service area and catalog of services), its locations and the places it serves, a
 * Service on a service page or a city page, and the areas archive (its words, list and questions). Without Marccent
 * Content there's no JSON-LD.
 */

defined( 'ABSPATH' ) || exit;

// The areas archive: its title and description are Settings → Business's (the archive's words).
add_filter( 'marccent_schema_context', function ( array $ctx ) {
	if ( is_post_type_archive( 'city' ) ) {
		$a                  = mcl_settings()['archive'];
		$ctx['title']       = $a['title'] ?: $ctx['title'];
		$ctx['description'] = $a['description'] ?: $ctx['description'];
	}
	return $ctx;
} );

// A place the business serves: its city's node from its fields' schema (Fields → Field groups → City details), else
// built here from the city's fields.
function mcl_place( WP_Post $city ): array {
	$node = function_exists( 'mcc_schema_post_node' ) ? mcc_schema_post_node( $city ) : null;
	if ( $node ) {
		return $node;
	}
	$state = get_post_meta( $city->ID, 'state', true );
	return array(
		'@type'            => 'City',
		'@id'              => get_permalink( $city ) . '#place',
		'url'              => get_permalink( $city ),
		'name'             => get_the_title( $city ) . ', ' . $state,
		'geo'              => array( '@type' => 'GeoCoordinates', 'latitude' => (float) get_post_meta( $city->ID, 'latitude', true ), 'longitude' => (float) get_post_meta( $city->ID, 'longitude', true ) ),
		'containedInPlace' => array(
			'@type'            => 'AdministrativeArea',
			'name'             => get_post_meta( $city->ID, 'county', true ) . ', ' . $state,
			'containedInPlace' => array( '@type' => 'State', 'name' => $state ),
		),
	);
}

// A question and its answer: [ question, answer ], from { question, answer } (or { q, a }, as older saves have them).
function mcl_question( $qa ): array {
	$qa = (array) $qa;
	return array( (string) ( $qa['question'] ?? $qa['q'] ?? '' ), (string) ( $qa['answer'] ?? $qa['a'] ?? '' ) );
}

// A page's questions, [ { question, answer } ]: its Questions rows (Marccent Content), or JSON as older imports saved them.
function mcl_faq_node( $faq, string $url ): ?array {
	$faq = is_array( $faq ) ? $faq : json_decode( (string) $faq, true );
	$faq = array_values( array_filter( array_map( 'mcl_question', (array) $faq ), fn( $qa ) => '' !== $qa[0] && '' !== $qa[1] ) );
	if ( ! $faq ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => $url . '#faq',
		'isPartOf'   => array( '@id' => $url . '#webpage' ),
		'mainEntity' => array_map( fn( $qa ) => array(
			'@type'          => 'Question',
			'name'           => $qa[0],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ),
		), $faq ),
	);
}

/**
 * What every page's graph has (the business with its catalog and the places it serves, its locations, the places) and what a page's own nodes refer to (the catalog, the places' @ids, the areas' names and URLs, the service
 * pages' IDs). Building it reads the areas, the service pages and the logo, so it's kept, autoloaded, in the mcl_schema
 * transient: on a page it costs no query. A changed page, area or place forgets it (below); its key covers the rest
 * (the business, the site's URL and permalinks, the logo and icon).
 */
function mcl_schema_site(): array {
	$b    = mcl_settings();
	$home = home_url( '/' );
	$key  = md5( serialize( array( $b, wp_date( 'Y-m-d' ), $home, get_option( 'permalink_structure' ), get_theme_mod( 'custom_logo' ), get_option( 'site_icon' ), get_option( 'marccent_content' ) ) ) ); // the fields' schemas too: the places
	$site = get_transient( 'mcl_schema' );
	if ( is_array( $site ) && ( $site['key'] ?? '' ) === $key ) {
		return $site;
	}

	$biz_id  = $home . '#business';
	$cities  = get_posts( array( 'post_type' => 'city', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
	$pages   = mcl_service_pages();
	$logo_id = get_theme_mod( 'custom_logo' );
	$served  = array_map( fn( $city ) => array( '@id' => get_permalink( $city ) . '#place' ), $cities );
	// The states the areas are in (names for areaServed, codes for the contact point).
	$states  = array_values( array_unique( array_filter( array_map( fn( $c ) => get_post_meta( $c->ID, 'state', true ), $cities ) ) ) );
	$codes   = array_values( array_unique( array_filter( array_map( fn( $c ) => get_post_meta( $c->ID, 'state_code', true ), $cities ) ) ) );

	$catalog = array();
	foreach ( $b['services'] as $s ) {
		$slug      = $s['slug'];
		$catalog[] = array(
			'@type'       => 'Offer',
			'itemOffered' => array( '@type' => 'Service', '@id' => isset( $pages[ $slug ] ) ? get_permalink( $pages[ $slug ] ) . '#service' : null, 'name' => $s['name'], 'serviceType' => $s['type'], 'description' => ucfirst( $s['covers'] ) . '.' ),
		);
	}

	$locations = mcl_locations_schema( $b, $biz_id );
	$nodes     = array();
	$nodes[]   = array(
		'@type'                     => $b['type'] ?: 'LocalBusiness',
		'@id'                       => $biz_id,
		'name'                      => $b['name'],
		'alternateName'             => $b['alternate'],
		'description'               => $b['summary'],
		'url'                       => $home,
		'telephone'                 => $b['telephone'],
		'email'                     => $b['email'],
		'logo'                      => $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : get_site_icon_url( 512 ),
		'image'                     => get_site_icon_url( 512 ),
		'address'                   => array(
			'@type'           => 'PostalAddress',
			'addressLocality' => $b['city'],
			'addressRegion'   => $b['region'],
			'addressCountry'  => $b['country'],
		),
		'geo'                       => '' !== $b['lat'] ? array( '@type' => 'GeoCoordinates', 'latitude' => (float) $b['lat'], 'longitude' => (float) $b['lng'] ) : null,
		'hasMap'                    => $b['map'],
		'sameAs'                    => $b['same_as'],
		'areaServed'                => array_merge(
			$served,
			array_map( fn( $state ) => array( '@type' => 'State', 'name' => $state ), $states )
		),
		'knowsAbout'                => $b['knows_about'],
		'openingHoursSpecification' => mcl_hours_schema(),
		'specialOpeningHoursSpecification' => mcl_special_schema(),
		'department'                => array_map( fn( $l ) => array( '@id' => $l['@id'] ), $locations ),
		'hasOfferCatalog'           => array( '@type' => 'OfferCatalog', 'name' => ucfirst( $b['services_label'] ), 'itemListElement' => $catalog ),
		'contactPoint'              => array( '@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => $b['telephone'], 'email' => $b['email'], 'areaServed' => array_map( fn( $code ) => $b['country'] . '-' . $code, $codes ), 'availableLanguage' => 'English' ),
	);
	// Its locations, each a business of its own (the business's departments).
	$nodes = array_merge( $nodes, $locations );
	// The places served, once each (the business's areaServed refers to them).
	foreach ( $cities as $city ) {
		$nodes[] = mcl_place( $city );
	}

	$site = array(
		'key'      => $key,
		'nodes'    => $nodes,
		'catalog'  => $catalog,
		'served'   => $served,
		'areas'    => array_map( fn( $city ) => array( get_the_title( $city ), get_permalink( $city ) ), $cities ),
		'services' => array_map( fn( $p ) => $p->ID, $pages ),
	);
	set_transient( 'mcl_schema', $site );
	return $site;
}

// Forget the shared part when a page or an area changes (its title, URL, order or status) or a place's details do; the
// next page builds it again. Only if it's there: an import touches many posts and meta.
function mcl_schema_forget(): void {
	if ( false !== get_transient( 'mcl_schema' ) ) {
		delete_transient( 'mcl_schema' );
	}
}
add_action( 'clean_post_cache', function ( $id, $post ) {
	if ( in_array( $post->post_type, array( 'page', 'city' ), true ) ) {
		mcl_schema_forget();
	}
}, 10, 2 );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $mcl_hook ) {
	add_action( $mcl_hook, function ( $meta_ids, $object_id, $meta_key ) {
		if ( in_array( $meta_key, array( 'state', 'state_code', 'county', 'latitude', 'longitude' ), true ) ) {
			mcl_schema_forget();
		}
	}, 10, 3 );
}

// The business is the site's Organization.
add_filter( 'marccent_schema_organization', fn() => mcl_schema_site()['nodes'][0] );

// Its locations and places on every page; a Service on a city page or a service page; the areas archive's list and
// questions.
add_filter( 'marccent_schema_graph', function ( array $graph, array $c ) {
	$b      = mcl_settings();
	$site   = mcl_schema_site();
	$biz_id = home_url( '/' ) . '#business';
	foreach ( array_slice( $site['nodes'], 1 ) as $node ) {
		$graph[ $node['@id'] ] = ( $graph[ $node['@id'] ] ?? array() ) + $node;
	}
	$url = $c['url'];
	if ( ! $url || ! isset( $graph[ $url . '#webpage' ] ) ) {
		return $graph;
	}
	$page = &$graph[ $url . '#webpage' ];
	$post = $c['post'];
	if ( $post && 'city' === $post->post_type ) {
		// A city page: the business's services, there.
		$name                       = get_the_title( $post );
		$graph[ $url . '#service' ] = array(
			'@type'           => 'Service',
			'@id'             => $url . '#service',
			'name'            => $b['profession'] . ' in ' . $name . ( ( $code = get_post_meta( $post->ID, 'state_code', true ) ) ? ', ' . $code : '' ),
			'serviceType'     => $b['profession'],
			'description'     => $c['description'],
			'provider'        => array( '@id' => $biz_id ),
			'areaServed'      => array( '@id' => $url . '#place' ),
			'url'             => $url,
			'hasOfferCatalog' => array( '@type' => 'OfferCatalog', 'name' => ucfirst( $b['services_label'] ) . ' in ' . $name, 'itemListElement' => $site['catalog'] ),
		);
		$page['about']           = array( '@id' => $url . '#service' );
		$page['spatialCoverage'] = array( '@id' => $url . '#place' );
	} elseif ( $post && 'page' === $post->post_type && ( $slug = array_search( $post->ID, $site['services'], true ) ) ) {
		// A service page.
		$s                          = mcl_service( $slug );
		$graph[ $url . '#service' ] = array(
			'@type'       => 'Service',
			'@id'         => $url . '#service',
			'name'        => $s['name'],
			'serviceType' => $s['type'],
			'description' => $c['description'],
			'provider'    => array( '@id' => $biz_id ),
			'areaServed'  => $site['served'],
			'url'         => $url,
		);
		$page['about'] = array( '@id' => $url . '#service' );
	} elseif ( is_post_type_archive( 'city' ) ) {
		$page['mainEntity'] = array(
			'@type'           => 'ItemList',
			'name'            => $b['areas']['plural'] . ' served',
			'itemListElement' => array_map( fn( $area, $i ) => array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $area[0], 'url' => $area[1] ), $site['areas'], array_keys( $site['areas'] ) ),
		);
		$faq = mcl_faq_node( wp_json_encode( $b['archive']['faq'] ), $url );
		if ( $faq ) {
			$graph[ $faq['@id'] ] = $faq;
		}
	}
	unset( $page );
	return $graph;
}, 10, 2 );
