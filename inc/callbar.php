<?php
/**
 * Call bar: on phones, a bar fixed to the bottom of the screen with Call (the Business group's phone) and Get a quote
 * (a link: the quote form's anchor). Most local visitors are on a phone, and these are the two things they came to do.
 * Site content → Call bar turns it on. Hidden on wider screens, where the header's call button and the page's forms
 * are in view.
 */

defined( 'ABSPATH' ) || exit;

// The Call bar group (Site content), with its words when they're left empty.
function mcl_callbar(): array {
	$c    = mcl_group( 'call_bar' );
	$href = trim( (string) ( $c['quote_link'] ?? '' ) );
	return array(
		'on'    => ! empty( $c['show'] ),
		'call'  => trim( (string) ( $c['call_label'] ?? '' ) ) ?: __( 'Call', 'marccent-local' ),
		'quote' => trim( (string) ( $c['quote_label'] ?? '' ) ),
		// An anchor (#form) as it is; anything else must be a web link.
		'href'  => preg_match( '/^#[A-Za-z][\w-]*$/', $href ) ? $href : esc_url_raw( $href ),
	);
}

function mcl_callbar_front(): bool {
	return mcl_callbar()['on'] && ! is_admin() && ! is_feed() && ! is_embed() && ! is_customize_preview();
}

// The theme's button (Marccent Blocks), or a plain link without it.
function mcl_callbar_button( string $label, string $href, string $icon, string $color ): string {
	if ( function_exists( 'marccent_blocks_button' ) ) {
		return marccent_blocks_button( array( 'label' => $label, 'href' => $href, 'icon' => $icon, 'classes' => $color ) );
	}
	return sprintf( '<a class="btn %s" href="%s">%s</a>', esc_attr( $color ), esc_url( $href ), esc_html( $label ) );
}

// Before Marccent Blocks prints the page's late assets (wp_footer, 1), so the bar's icons come with them.
add_action( 'wp_footer', function () {
	if ( ! mcl_callbar_front() ) {
		return;
	}
	$c   = mcl_callbar();
	$s   = mcl_settings();
	$tel = $s['telephone'] ?: $s['phone'];
	if ( function_exists( 'marccent_blocks_page' ) ) {
		$page                            = &marccent_blocks_page();
		$page['icons']['phone']          = true;
		$page['icons']['file-signature'] = true;
	}
	$buttons = ( $tel ? mcl_callbar_button( $c['call'], 'tel:' . preg_replace( '/[^\d+]/', '', $tel ), 'phone', 'primary' ) : '' )
		. ( $c['quote'] && $c['href'] ? mcl_callbar_button( $c['quote'], $c['href'], 'file-signature', 'secondary' ) : '' );
	if ( $buttons ) {
		printf( '<nav class="mcl-callbar" aria-label="%s">%s</nav>', esc_attr__( 'Call or get a quote', 'marccent-local' ), $buttons ); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped
	}
}, 0 );

// Room under the page for the bar, and the cookie banner above it.
add_action( 'wp_enqueue_scripts', function () {
	if ( ! mcl_callbar()['on'] ) {
		return;
	}
	wp_add_inline_style( 'marccent', '.mcl-callbar{position:fixed;inset:auto 0 0 0;z-index:9990;display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:.5rem;padding:.5rem max(.75rem,env(safe-area-inset-right)) calc(.5rem + env(safe-area-inset-bottom)) max(.75rem,env(safe-area-inset-left));background:var(--body-bg,#fff);box-shadow:0 -.25rem 1rem rgb(0 0 0/.15)}.mcl-callbar .btn{width:100%;justify-content:center}@media (min-width:48.01rem){.mcl-callbar{display:none}}@media (max-width:48rem){body{padding-bottom:calc(4.25rem + env(safe-area-inset-bottom))}.marccent-consent{bottom:calc(4.75rem + env(safe-area-inset-bottom))}}' );
}, 20 );
