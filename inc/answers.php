<?php
/**
 * Answer engines (AI search and assistants): /llms.txt, a plain Markdown summary of the business with links to every
 * service and city page, and a robots.txt that welcomes their crawlers and points to the sitemap and llms.txt.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'parse_request', function ( WP $wp ) {
	if ( 'llms.txt' !== $wp->request ) {
		return;
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo mcl_llms_txt();
	exit;
} );

function mcl_llms_txt(): string {
	$b     = mcl_settings();
	$lines = array( '# ' . $b['name'], '', '> ' . $b['summary'], '' );
	$lines[] = sprintf( '- Based in: %s, %s', $b['city'], $b['region'] );
	$lines[] = '- Phone: ' . $b['phone'];
	$lines[] = '- Email: ' . $b['email'];
	$lines[] = '- Website: ' . home_url( '/' );
	if ( $b['service_area'] ) {
		$lines[] = '- Service area: ' . $b['service_area'];
	}
	$lines[] = '';

	$lines[] = '## Services';
	$lines[] = '';
	$services = get_page_by_path( $b['services_parent'] );
	if ( $services ) {
		$lines[] = sprintf( '- [All %s](%s): overview and how to choose', $b['services_label'], get_permalink( $services ) );
	}
	foreach ( mcl_service_pages() as $slug => $page ) {
		$s       = mcl_service( $slug );
		$lines[] = sprintf( '- [%s](%s): %s', $s['name'], get_permalink( $page ), $s['covers'] );
	}
	$lines[] = '';

	$lines[] = '## ' . $b['areas']['plural'] . ' served';
	$lines[] = '';
	$lines[] = sprintf( '- [All %s](%s)', strtolower( $b['areas']['label'] ), get_post_type_archive_link( 'city' ) );
	$regions = mcl_regions();
	$cities  = get_posts( array( 'post_type' => 'city', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
	foreach ( $cities as $city ) {
		$lines[] = sprintf(
			'- [%s, %s](%s): %s (%s)',
			get_the_title( $city ), get_post_meta( $city->ID, 'mcl_state_code', true ), get_permalink( $city ),
			get_the_excerpt( $city ), $regions[ get_post_meta( $city->ID, 'mcl_region', true ) ]['name'] ?? ''
		);
	}
	$lines[] = '';

	// The other top-level pages: everything but the home page, the services page and the areas.
	$lines[] = '## Other pages';
	$lines[] = '';
	foreach ( get_pages( array( 'parent' => 0, 'sort_column' => 'menu_order' ) ) as $page ) {
		if ( (int) get_option( 'page_on_front' ) !== $page->ID && $page->post_name !== $b['services_parent'] ) {
			$lines[] = sprintf( '- [%s](%s)', get_the_title( $page ), get_permalink( $page ) );
		}
	}
	return implode( "\n", $lines ) . "\n";
}

add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	// Search and answer engines' crawlers, named so a later blanket rule can't shut them out by accident.
	$bots = array( 'Googlebot', 'Bingbot', 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'PerplexityBot', 'ClaudeBot', 'Claude-SearchBot', 'Applebot', 'Google-Extended' );
	$out  = $output;
	foreach ( $bots as $bot ) {
		$out .= "\nUser-agent: $bot\nAllow: /\nDisallow: /wp-admin/\n";
	}
	$out .= "\n# A summary for language models: " . home_url( '/llms.txt' ) . "\n";
	return $out;
}, 10, 2 );
