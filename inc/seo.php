<?php
/**
 * Search: each page's title and description (its mcl_seo_title / mcl_seo_description meta, else its own), the
 * canonical URL, Open Graph and Twitter cards, the robots directives, and the sitemaps (lastmod, the cities archive,
 * no user sitemaps).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	foreach ( array( 'page', 'city' ) as $type ) {
		// Plain text; the FAQ is JSON (questions and answers), so it keeps its characters but is checked to be JSON.
		foreach ( array( 'mcl_seo_title', 'mcl_seo_description', 'mcl_faq' ) as $key ) {
			register_post_meta( $type, $key, array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				'sanitize_callback' => 'mcl_faq' === $key
					? fn( $v ) => null !== json_decode( (string) $v ) ? $v : ''
					: 'sanitize_text_field',
			) );
		}
	}
} );

/**
 * What the current request is about, for every tag that describes it: title, description, URL, image, the
 * breadcrumb trail ([ name, url ] pairs from Home) and the post (if it's one).
 */
function mcl_context(): array {
	static $ctx = null;
	if ( null !== $ctx ) {
		return $ctx;
	}
	$b    = mcl_settings();
	$home = array( 'Home', home_url( '/' ) );
	$ctx  = array( 'post' => null, 'title' => '', 'description' => '', 'url' => '', 'image' => '', 'trail' => array( $home ), 'type' => 'WebPage' );

	if ( is_post_type_archive( 'city' ) ) {
		$a                  = mcl_settings()['archive'];
		$ctx['title']       = $a['title'];
		$ctx['description'] = $a['description'];
		$ctx['url']         = get_post_type_archive_link( 'city' );
		$ctx['trail'][]     = array( $b['areas']['label'], $ctx['url'] );
		$ctx['type']        = 'CollectionPage';
	} elseif ( is_singular() ) {
		$post               = get_queried_object();
		$ctx['post']        = $post;
		$ctx['title']       = get_post_meta( $post->ID, 'mcl_seo_title', true ) ?: get_the_title( $post );
		$ctx['description'] = get_post_meta( $post->ID, 'mcl_seo_description', true ) ?: wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 30, '…' );
		$ctx['url']         = get_permalink( $post );
		if ( has_post_thumbnail( $post ) ) {
			$ctx['image'] = get_the_post_thumbnail_url( $post, 'large' );
		}
		if ( 'city' === $post->post_type ) {
			$ctx['trail'][] = array( $b['areas']['label'], get_post_type_archive_link( 'city' ) );
		}
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$ctx['trail'][] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
		}
		if ( ! is_front_page() ) {
			$ctx['trail'][] = array( get_the_title( $post ), $ctx['url'] );
		}
		if ( is_front_page() ) {
			$ctx['url'] = home_url( '/' );
		}
	} else {
		$ctx['url'] = home_url( add_query_arg( array() ) );
	}
	// The default share image: the brand's.
	if ( ! $ctx['image'] ) {
		$ctx['image'] = get_site_icon_url( 512 );
	}
	// Every title ends with the brand: a title without one (no " | ") gets the business name.
	if ( $ctx['title'] && false === strpos( $ctx['title'], ' | ' ) ) {
		$ctx['title'] .= ' | ' . $b['name'];
	}
	return $ctx;
}

add_filter( 'pre_get_document_title', function ( $title ) {
	$t = mcl_context()['title'];
	return $t ? esc_html( $t ) : $title;
} );

// Canonical URLs come from here, archives included (WordPress only writes them for single posts).
remove_action( 'wp_head', 'rel_canonical' );

add_action( 'wp_head', function () {
	$c = mcl_context();
	$b = mcl_settings();
	if ( is_404() || is_search() ) {
		return;
	}
	$tags = array();
	if ( $c['description'] ) {
		$tags[] = sprintf( '<meta name="description" content="%s">', esc_attr( $c['description'] ) );
	}
	if ( $c['url'] ) {
		$tags[] = sprintf( '<link rel="canonical" href="%s">', esc_url( $c['url'] ) );
	}
	$og = array(
		'og:type'        => is_front_page() ? 'website' : 'article',
		'og:site_name'   => $b['name'],
		'og:locale'      => 'en_US',
		'og:title'       => $c['title'] ?: wp_get_document_title(),
		'og:description' => $c['description'],
		'og:url'         => $c['url'],
		'og:image'       => $c['image'],
	);
	foreach ( array_filter( $og ) as $property => $content ) {
		$tags[] = sprintf( '<meta property="%s" content="%s">', esc_attr( $property ), esc_attr( $content ) );
	}
	if ( $c['post'] ) {
		$tags[] = sprintf( '<meta property="article:modified_time" content="%s">', esc_attr( get_post_modified_time( 'c', true, $c['post'] ) ) );
	}
	$tags[] = '<meta name="twitter:card" content="summary_large_image">';
	// A city page says where it is about.
	if ( $c['post'] && 'city' === $c['post']->post_type ) {
		$id     = $c['post']->ID;
		$tags[] = sprintf( '<meta name="geo.region" content="US-%s">', esc_attr( get_post_meta( $id, 'mcl_state_code', true ) ) );
		$tags[] = sprintf( '<meta name="geo.placename" content="%s">', esc_attr( get_the_title( $id ) . ', ' . get_post_meta( $id, 'mcl_state', true ) ) );
		$tags[] = sprintf( '<meta name="geo.position" content="%s;%s">', esc_attr( get_post_meta( $id, 'mcl_lat', true ) ), esc_attr( get_post_meta( $id, 'mcl_lng', true ) ) );
	}
	echo implode( "\n", $tags ) . "\n";
}, 1 );

// Full snippets and image previews for search and answer engines; nothing indexed from search results or 404s.
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_search() || is_404() ) {
		return array( 'noindex' => true, 'follow' => true );
	}
	return array_merge( $robots, array( 'max-snippet' => '-1', 'max-image-preview' => 'large', 'max-video-preview' => '-1' ) );
} );

// Sitemaps: pages and cities only, each with its last change, and the cities archive first among the cities.
add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
add_filter( 'wp_sitemaps_taxonomies', '__return_empty_array' );
add_filter( 'wp_sitemaps_post_types', function ( $types ) {
	return array_intersect_key( $types, array_flip( array( 'page', 'city' ) ) );
} );
add_filter( 'wp_sitemaps_posts_entry', function ( $entry, $post ) {
	$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
	return $entry;
}, 10, 2 );
add_filter( 'wp_sitemaps_posts_pre_url_list', function ( $list, $type, $page ) {
	if ( 'city' !== $type || 1 !== (int) $page ) {
		return $list;
	}
	remove_all_filters( 'wp_sitemaps_posts_pre_url_list' );
	$provider = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
	$urls     = $provider->get_url_list( 1, 'city' );
	$latest   = get_posts( array( 'post_type' => 'city', 'numberposts' => 1, 'orderby' => 'modified' ) );
	array_unshift( $urls, array_filter( array( 'loc' => get_post_type_archive_link( 'city' ), 'lastmod' => $latest ? get_post_modified_time( 'c', true, $latest[0] ) : null ) ) );
	return $urls;
}, 10, 3 );
