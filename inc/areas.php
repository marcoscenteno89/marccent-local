<?php
/**
 * Areas: one page per city or area served (/<slug>/<area>/, the `city` post type), and their archive (/<slug>/), the
 * directory grouped by region. An area's page is ordinary block content; what schema, the directory and the map need
 * about the place (state, county, region, coordinates) is in its meta. What an area is called and its URL come from
 * Settings → Business (areas).
 */

defined( 'ABSPATH' ) || exit;

// The regions, in the directory's order: [ key => [ name, blurb ] ].
function mcl_regions(): array {
	$out = array();
	foreach ( mcl_settings()['regions'] as $r ) {
		$out[ $r['key'] ] = array( 'name' => $r['name'], 'blurb' => $r['blurb'] ?? '' );
	}
	return $out;
}

function mcl_register_areas(): void {
	$a = mcl_settings()['areas'];
	register_post_type( 'city', array(
		'labels'        => array(
			'name'          => $a['plural'],
			'singular_name' => $a['singular'],
			/* translators: %s: what an area is called (City) */
			'add_new_item'  => sprintf( __( 'Add %s', 'marccent-local' ), $a['singular'] ),
			/* translators: %s: what an area is called (City) */
			'edit_item'     => sprintf( __( 'Edit %s', 'marccent-local' ), $a['singular'] ),
			/* translators: %s: what areas are called (Cities) */
			'all_items'     => sprintf( __( 'All %s', 'marccent-local' ), $a['plural'] ),
			'archives'      => $a['label'],
			'menu_name'     => $a['plural'],
		),
		/* translators: 1: what areas are called (Cities), 2: the business's name */
		'description'   => sprintf( __( '%1$s %2$s serves.', 'marccent-local' ), $a['plural'], mcl_settings()['name'] ),
		'public'        => true,
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-location',
		'menu_position' => 21,
		'has_archive'   => $a['slug'],
		'rewrite'       => array( 'slug' => $a['slug'], 'with_front' => false ),
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes', 'revisions' ),
	) );
	foreach ( array( 'mcl_state', 'mcl_state_code', 'mcl_county', 'mcl_region', 'mcl_lat', 'mcl_lng' ) as $key ) {
		register_post_meta( 'city', $key, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}
}
add_action( 'init', 'mcl_register_areas' );

// The directory lists every area, in order.
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_post_type_archive( 'city' ) ) {
		$q->set( 'posts_per_page', -1 );
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
} );

// The areas' templates come with the plugin, framed by the active theme's header and footer parts.
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}
	$theme = get_stylesheet();
	$frame = fn( $main ) => '<!-- wp:template-part {"slug":"header","theme":"' . $theme . '","tagName":"div"} /-->'
		. "\n\n" . '<!-- wp:group {"tagName":"main","className":"grid cols-1 gap-0","layout":{"type":"default"}} -->'
		. "\n" . '<main class="wp-block-group grid cols-1 gap-0">' . $main . '</main>'
		. "\n" . '<!-- /wp:group -->'
		. "\n\n" . '<!-- wp:template-part {"slug":"footer","theme":"' . $theme . '","tagName":"footer"} /-->';
	$a = mcl_settings()['areas'];
	register_block_template( 'marccent-local//single-city', array(
		/* translators: %s: what an area is called (City) */
		'title'       => sprintf( __( 'Single %s', 'marccent-local' ), $a['singular'] ),
		'description' => __( 'An area served: its page content between the site header and footer.', 'marccent-local' ),
		'post_types'  => array( 'city' ),
		'content'     => $frame( '<!-- wp:post-content {"layout":{"type":"default"}} /-->' ),
	) );
	register_block_template( 'marccent-local//archive-city', array(
		'title'       => $a['label'],
		'description' => __( 'The areas archive: every area served, by region.', 'marccent-local' ),
		'content'     => $frame( '<!-- wp:marccent-local/directory /-->' ),
	) );
} );

// The directory: the archive's page, rendered here from the areas (no editor script: it's only in the template).
add_action( 'init', function () {
	register_block_type( 'marccent-local/directory', array(
		'title'           => __( 'Area Directory', 'marccent-local' ),
		'category'        => 'marccent',
		'render_callback' => 'mcl_render_directory',
	) );
} );

/**
 * A button as the theme's Button renders it (the marccent/button block's markup).
 */
function mcl_button( string $label, string $href, string $icon = 'arrow-right', string $color = 'primary' ): string {
	return sprintf(
		'<a href="%s" class="btn %s" data-component="button"><i data-component="icon" class="fa-solid fa-%s btn-icon" aria-hidden="true"></i><span class="btn-text">%s</span></a>',
		esc_url( $href ), esc_attr( $color ), esc_attr( $icon ), esc_html( $label )
	);
}

// Breadcrumbs, from Home to the current page: [ [ name, url ], … ] (the last one is the page itself).
function mcl_breadcrumbs_html( array $trail ): string {
	$items = array();
	$last  = count( $trail ) - 1;
	foreach ( $trail as $i => [ $name, $url ] ) {
		$items[] = $i === $last
			? sprintf( '<li aria-current="page">%s</li>', esc_html( $name ) )
			: sprintf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $name ) );
	}
	return '<nav class="breadcrumbs fs-sm" aria-label="Breadcrumb"><ol>' . implode( '', $items ) . '</ol></nav>';
}

function mcl_render_directory(): string {
	$s     = mcl_settings();
	$c     = $s['archive'];
	$areas = get_posts( array( 'post_type' => 'city', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	$by    = array();
	foreach ( $areas as $area ) {
		$by[ get_post_meta( $area->ID, 'mcl_region', true ) ][] = $area;
	}
	$regions = mcl_regions();
	// Areas in no known region still show, last.
	foreach ( array_diff( array_keys( $by ), array_keys( $regions ) ) as $key ) {
		$regions[ $key ] = array( 'name' => $key ? ucwords( str_replace( '-', ' ', $key ) ) : /* translators: %s: what areas are called, lowercase (cities) */ sprintf( __( 'More %s', 'marccent-local' ), strtolower( $s['areas']['plural'] ) ), 'blurb' => '' );
	}

	$section = fn( $inner, $surface = '', $id = '' ) => sprintf(
		'<section class="wp-block-marccent-section wrap wrap-fluid %s"%s><div class="wrap pad-y-lg grid cols-1">%s</div></section>',
		esc_attr( $surface ), $id ? ' id="' . esc_attr( $id ) . '"' : '', $inner
	);

	ob_start();
	// Hero: what the business does where, and the regions to jump to.
	$jump = '';
	foreach ( $regions as $key => $region ) {
		if ( ! empty( $by[ $key ] ) && count( $regions ) > 1 ) {
			$jump .= mcl_button( $region['name'], '#' . $key, 'location-dot', 'light' );
		}
	}
	echo $section(
		mcl_breadcrumbs_html( array( array( __( 'Home', 'marccent-local' ), home_url( '/' ) ), array( $s['areas']['label'], get_post_type_archive_link( 'city' ) ) ) )
		. '<h1>' . esc_html( $c['h1'] ) . '</h1>'
		. ( $c['lead'] ? '<p class="fs-lg">' . esc_html( $c['lead'] ) . '</p>' : '' )
		. ( $jump ? '<div class="grid row-wrap gap-sm">' . $jump . '</div>' : '' ),
		'bg-secondary'
	);

	// Each region: its line, then its areas as cards.
	$n = 0;
	foreach ( $regions as $key => $region ) {
		if ( empty( $by[ $key ] ) ) {
			continue;
		}
		$cards = '';
		foreach ( $by[ $key ] as $area ) {
			$name   = get_the_title( $area );
			$code   = get_post_meta( $area->ID, 'mcl_state_code', true );
			$county = get_post_meta( $area->ID, 'mcl_county', true );
			$cards .= sprintf(
				'<article class="bg border round pad grid cols-1 content-start gap-sm"><h3><a href="%1$s">%2$s</a></h3>%3$s<p>%4$s</p>%5$s</article>',
				esc_url( get_permalink( $area ) ), esc_html( $code ? "$name, $code" : $name ),
				$county ? '<p class="fs-sm opacity-75">' . esc_html( $county ) . '</p>' : '',
				esc_html( get_the_excerpt( $area ) ), mcl_button( sprintf( $c['card_button'], $name ), get_permalink( $area ), 'arrow-right', 'secondary' )
			);
		}
		echo $section( '<h2>' . esc_html( $region['name'] ) . '</h2>' . ( $region['blurb'] ? '<p>' . esc_html( $region['blurb'] ) . '</p>' : '' ) . '<div class="grid cols-3">' . $cards . '</div>', $n++ % 2 ? 'bg' : '', $key );
	}

	// Questions people ask about the service area, answered first thing.
	if ( $c['faq'] ) {
		$faq = '';
		foreach ( $c['faq'] as $qa ) {
			$faq .= '<div class="grid cols-1 gap-sm"><h3>' . esc_html( $qa['q'] ) . '</h3><p>' . esc_html( $qa['a'] ) . '</p></div>';
		}
		echo $section( '<h2>' . esc_html( $c['faq_heading'] ) . '</h2><div class="grid cols-2 gap-lg">' . $faq . '</div>', 'bg' );
	}

	// The call to action.
	if ( $c['cta_heading'] ) {
		$buttons = mcl_button( __( 'Request a Quote', 'marccent-local' ), $c['quote_href'], 'file-signature', 'light' )
			. ( $s['phone'] ? /* translators: %s: the phone number */ mcl_button( sprintf( __( 'Call %s', 'marccent-local' ), $s['phone'] ), 'tel:' . $s['telephone'], 'phone', 'dark' ) : '' );
		echo $section( '<h2>' . esc_html( $c['cta_heading'] ) . '</h2>' . ( $c['cta_text'] ? '<p>' . esc_html( $c['cta_text'] ) . '</p>' : '' ) . '<div class="grid auto-cols gap-sm">' . $buttons . '</div>', 'bg-primary' );
	}
	return ob_get_clean();
}
