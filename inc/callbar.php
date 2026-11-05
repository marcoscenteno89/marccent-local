<?php
/**
 * Call bar: on phones, a bar fixed to the bottom of the screen with Call (the business's phone, Settings → Business)
 * and Get a quote (a link: the quote form's anchor). Most local visitors are on a phone, and these are the two things
 * they came to do. The mcl_callbar option; Settings → Business, at the end. Hidden on wider screens, where
 * the header's call button and the page's forms are in view.
 */

defined( 'ABSPATH' ) || exit;

const MCL_CALLBAR = 'mcl_callbar';

function mcl_callbar_defaults(): array {
	return array(
		'on'    => false,
		'call'  => __( 'Call', 'marccent-local' ),
		'quote' => __( 'Get a quote', 'marccent-local' ),
		'href'  => '#form',
	);
}

function mcl_callbar(): array {
	$saved = get_option( MCL_CALLBAR, array() );
	return array_merge( mcl_callbar_defaults(), is_array( $saved ) ? $saved : array() );
}

function mcl_callbar_sanitize( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$d     = mcl_callbar_defaults();
	$href  = trim( (string) wp_unslash( $input['href'] ?? '' ) );
	return array(
		'on'    => ! empty( $input['on'] ),
		'call'  => sanitize_text_field( (string) wp_unslash( $input['call'] ?? '' ) ) ?: $d['call'],
		// Empty on purpose: a call button only. Not given at all (an import): the default.
		'quote' => array_key_exists( 'quote', $input ) ? sanitize_text_field( (string) wp_unslash( $input['quote'] ) ) : $d['quote'],
		// An anchor (#form) as it is; anything else must be a web link.
		'href'  => preg_match( '/^#[A-Za-z][\w-]*$/', $href ) ? $href : esc_url_raw( $href ),
	);
}

add_action( 'admin_init', function () {
	register_setting( 'marccent-local', MCL_CALLBAR, array( 'type' => 'array', 'sanitize_callback' => 'mcl_callbar_sanitize' ) );
} );

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

// Its part of Settings → Business, at the end.
function mcl_callbar_fields(): void {
	$c    = mcl_callbar();
	$name = fn( $key ) => esc_attr( MCL_CALLBAR . "[$key]" );
	echo '<h2>' . esc_html__( 'Call bar', 'marccent-local' ) . '</h2>';
	echo '<p>' . esc_html__( 'On phones, a bar along the bottom of the screen with a call button (the phone above) and a link to the quote form.', 'marccent-local' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s" value="1" %s> %s</label></td></tr>', esc_html__( 'Bar', 'marccent-local' ), $name( 'on' ), checked( $c['on'], true, false ), esc_html__( 'Show it on phones', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $name
	printf( '<tr><th scope="row">%s</th><td><input name="%s" value="%s" aria-label="%s"> <input name="%s" value="%s" aria-label="%s"><p class="description">%s</p></td></tr>', esc_html__( 'Buttons', 'marccent-local' ), $name( 'call' ), esc_attr( $c['call'] ), esc_attr__( 'Call button', 'marccent-local' ), $name( 'quote' ), esc_attr( $c['quote'] ), esc_attr__( 'Quote button', 'marccent-local' ), esc_html__( 'Leave the second empty for a call button only.', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<tr><th scope="row"><label for="mcl-callbar-href">%s</label></th><td><input id="mcl-callbar-href" name="%s" value="%s" class="regular-text" placeholder="#form"><p class="description">%s</p></td></tr>', esc_html__( 'Quote link', 'marccent-local' ), $name( 'href' ), esc_attr( $c['href'] ), esc_html__( 'The quote form’s anchor (#form), or a page’s address.', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</table>';
}

add_filter( 'marccent_export_parts', function ( $parts ) {
	$parts['callbar'] = array(
		'label'  => __( 'Call bar', 'marccent-local' ),
		'export' => MCL_CALLBAR,
		'import' => fn( array $data ) => update_option( MCL_CALLBAR, mcl_callbar_sanitize( $data ) ),
	);
	return $parts;
} );
