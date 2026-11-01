<?php
/**
 * JSON-LD: one @graph per page, its nodes linked by @id: the business (its schema.org type, with its address, map,
 * service area and catalog of services), the website, this page with its breadcrumbs, and what the page is about (a
 * service, a service in a city, the directory of cities) with its questions and answers.
 */

defined( 'ABSPATH' ) || exit;

function mcl_place( WP_Post $city ): array {
	$state = get_post_meta( $city->ID, 'mcl_state', true );
	return array(
		'@type'            => 'City',
		'@id'              => get_permalink( $city ) . '#place',
		'name'             => get_the_title( $city ) . ', ' . $state,
		'geo'              => array( '@type' => 'GeoCoordinates', 'latitude' => (float) get_post_meta( $city->ID, 'mcl_lat', true ), 'longitude' => (float) get_post_meta( $city->ID, 'mcl_lng', true ) ),
		'containedInPlace' => array(
			'@type'            => 'AdministrativeArea',
			'name'             => get_post_meta( $city->ID, 'mcl_county', true ) . ', ' . $state,
			'containedInPlace' => array( '@type' => 'State', 'name' => $state ),
		),
	);
}

function mcl_faq_node( string $json, string $url ): ?array {
	$faq = json_decode( $json, true );
	if ( ! $faq ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => $url . '#faq',
		'isPartOf'   => array( '@id' => $url . '#webpage' ),
		'mainEntity' => array_map( fn( $qa ) => array(
			'@type'          => 'Question',
			'name'           => $qa['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa['a'] ),
		), $faq ),
	);
}

/**
 * What every page's graph starts with (the business with its catalog and the places it serves, the places, the
 * website) and what a page's own nodes refer to (the catalog, the places' @ids, the areas' names and URLs, the service
 * pages' IDs). Building it reads the areas, the service pages and the logo, so it's kept, autoloaded, in the mcl_schema
 * transient: on a page it costs no query. A changed page, area or place forgets it (below); its key covers the rest
 * (the business, the site's URL and permalinks, the logo and icon).
 */
function mcl_schema_site(): array {
	$b    = mcl_settings();
	$home = home_url( '/' );
	$key  = md5( serialize( array( $b, $home, get_option( 'permalink_structure' ), get_theme_mod( 'custom_logo' ), get_option( 'site_icon' ) ) ) );
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
	$states  = array_values( array_unique( array_filter( array_map( fn( $c ) => get_post_meta( $c->ID, 'mcl_state', true ), $cities ) ) ) );
	$codes   = array_values( array_unique( array_filter( array_map( fn( $c ) => get_post_meta( $c->ID, 'mcl_state_code', true ), $cities ) ) ) );

	$catalog = array();
	foreach ( $b['services'] as $s ) {
		$slug      = $s['slug'];
		$catalog[] = array(
			'@type'       => 'Offer',
			'itemOffered' => array( '@type' => 'Service', '@id' => isset( $pages[ $slug ] ) ? get_permalink( $pages[ $slug ] ) . '#service' : null, 'name' => $s['name'], 'serviceType' => $s['type'], 'description' => ucfirst( $s['covers'] ) . '.' ),
		);
	}

	$nodes   = array();
	$nodes[] = array(
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
		'hasOfferCatalog'           => array( '@type' => 'OfferCatalog', 'name' => ucfirst( $b['services_label'] ), 'itemListElement' => $catalog ),
		'contactPoint'              => array( '@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => $b['telephone'], 'email' => $b['email'], 'areaServed' => array_map( fn( $code ) => $b['country'] . '-' . $code, $codes ), 'availableLanguage' => 'English' ),
	);
	// The places served, once each (the business's areaServed refers to them).
	foreach ( $cities as $city ) {
		$nodes[] = mcl_place( $city );
	}
	$nodes[] = array(
		'@type'      => 'WebSite',
		'@id'        => $home . '#website',
		'url'        => $home,
		'name'       => $b['name'],
		'publisher'  => array( '@id' => $biz_id ),
		'inLanguage' => 'en-US',
	);

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
		if ( in_array( $meta_key, array( 'mcl_state', 'mcl_state_code', 'mcl_county', 'mcl_lat', 'mcl_lng' ), true ) ) {
			mcl_schema_forget();
		}
	}, 10, 3 );
}

function mcl_schema_graph(): array {
	$b      = mcl_settings();
	$c      = mcl_context();
	$site   = mcl_schema_site();
	$home   = home_url( '/' );
	$biz_id = $home . '#business';
	$graph  = $site['nodes'];
	if ( ! $c['url'] || is_404() ) {
		return $graph;
	}

	$url     = $c['url'];
	$crumbs  = array();
	foreach ( $c['trail'] as $i => [ $name, $link ] ) {
		$crumbs[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $link );
	}
	$page = array(
		'@type'       => $c['type'],
		'@id'         => $url . '#webpage',
		'url'         => $url,
		'name'        => $c['title'],
		'description' => $c['description'],
		'isPartOf'    => array( '@id' => $home . '#website' ),
		'about'       => array( '@id' => $biz_id ),
		'inLanguage'  => 'en-US',
	);
	if ( count( $crumbs ) > 1 ) {
		$page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		$graph[]            = array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $crumbs );
	}
	if ( $c['post'] ) {
		$page['datePublished'] = get_post_time( 'c', true, $c['post'] );
		$page['dateModified']  = get_post_modified_time( 'c', true, $c['post'] );
		if ( $c['image'] ) {
			$page['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $c['image'] );
		}
	}

	$post = $c['post'];
	if ( $post && 'city' === $post->post_type ) {
		// A city page: the business's services, there.
		$name    = get_the_title( $post );
		$graph[] = array(
			'@type'       => 'Service',
			'@id'         => $url . '#service',
			'name'        => $b['profession'] . ' in ' . $name . ( ( $code = get_post_meta( $post->ID, 'mcl_state_code', true ) ) ? ', ' . $code : '' ),
			'serviceType' => $b['profession'],
			'description' => $c['description'],
			'provider'    => array( '@id' => $biz_id ),
			'areaServed'  => array( '@id' => $url . '#place' ),
			'url'         => $url,
			'hasOfferCatalog' => array( '@type' => 'OfferCatalog', 'name' => ucfirst( $b['services_label'] ) . ' in ' . $name, 'itemListElement' => $site['catalog'] ),
		);
		$page['about']      = array( '@id' => $url . '#service' );
		$page['spatialCoverage'] = array( '@id' => $url . '#place' );
	} elseif ( $post && 'page' === $post->post_type && ( $slug = array_search( $post->ID, $site['services'], true ) ) ) {
		// A service page.
		$s       = mcl_service( $slug );
		$graph[] = array(
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
	}

	$faq = $post ? mcl_faq_node( (string) get_post_meta( $post->ID, 'mcl_faq', true ), $url ) : null;
	if ( is_post_type_archive( 'city' ) ) {
		$faq = mcl_faq_node( wp_json_encode( $b['archive']['faq'] ), $url );
	}
	if ( $faq ) {
		$graph[] = $faq;
	}
	$graph[] = $page;
	return $graph;
}

add_action( 'wp_head', function () {
	$graph = mcl_schema_graph();
	// Drop empty values (an @id with no page yet, a missing image) so validators see only real ones.
	$clean = function ( $v ) use ( &$clean ) {
		if ( ! is_array( $v ) ) {
			return $v;
		}
		$list = array_is_list( $v );
		$v    = array_filter( array_map( $clean, $v ), fn( $x ) => null !== $x && '' !== $x && array() !== $x );
		return $list ? array_values( $v ) : $v;
	};
	// < > & as \u escapes, so nothing in the data (an SEO title, a FAQ answer) can close the script tag.
	printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $clean( $graph ) ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}, 5 );
