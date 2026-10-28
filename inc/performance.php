<?php
/**
 * What WordPress adds to every page that this site doesn't use: emoji scripts, oEmbed discovery, feed links (no
 * blog), the generator and editing-client links, and the classic-theme styles. Images in the content get their real sizes and lazy loading.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	add_filter( 'emoji_svg_url', '__return_false' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'classic-theme-styles' );
}, 20 );

// The first image of a page (its hero, above the fold) loads first; the rest wait until they're near.
add_filter( 'wp_content_img_tag', function ( $img, $context, $attachment_id ) {
	static $seen = 0;
	if ( 'the_content' !== $context ) {
		return $img;
	}
	++$seen;
	if ( 1 === $seen ) {
		$img = str_replace( ' loading="lazy"', '', $img );
		if ( ! str_contains( $img, 'fetchpriority' ) ) {
			$img = str_replace( '<img ', '<img fetchpriority="high" ', $img );
		}
	} elseif ( ! str_contains( $img, 'loading=' ) ) {
		$img = str_replace( '<img ', '<img loading="lazy" ', $img );
	}
	if ( ! str_contains( $img, 'decoding=' ) ) {
		$img = str_replace( '<img ', '<img decoding="async" ', $img );
	}
	return $img;
}, 10, 3 );
