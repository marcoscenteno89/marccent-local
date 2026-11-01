<?php
/**
 * Tracking codes (the mcl_tracking option): raw HTML pasted from Google Tag Manager, Analytics, Meta and the like, in
 * the three places they ask for: in <head>, right after <body> opens (GTM's noscript) and before </body>. Its own
 * option, so a content import that writes marccent_local whole leaves the codes as they are. Settings → Business edits
 * it, below the details.
 */

defined( 'ABSPATH' ) || exit;

const MCL_TRACKING = 'mcl_tracking';

// Where each code goes: [ key => [ hook, priority ] ].
const MCL_TRACKING_HOOKS = array(
	'head'       => array( 'wp_head', 1 ),
	'body_open'  => array( 'wp_body_open', 1 ),
	'body_close' => array( 'wp_footer', 99 ),
);

function mcl_tracking(): array {
	$saved = get_option( MCL_TRACKING, array() );
	return array_merge( array_fill_keys( array_keys( MCL_TRACKING_HOOKS ), '' ), is_array( $saved ) ? $saved : array() );
}

add_action( 'admin_init', function () {
	register_setting( 'marccent-local', MCL_TRACKING, array( 'type' => 'array', 'sanitize_callback' => 'mcl_tracking_sanitize' ) );
} );

// Scripts are the point, so the HTML is stored as pasted, and only by someone allowed to post unfiltered HTML.
function mcl_tracking_sanitize( $input ): array {
	$old = mcl_tracking();
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		add_settings_error( MCL_TRACKING, 'unfiltered_html', __( 'Only an administrator who can post unfiltered HTML can change the tracking codes; they were kept.', 'marccent-local' ) );
		return $old;
	}
	$out = array();
	foreach ( $old as $key => $value ) {
		$out[ $key ] = trim( (string) wp_unslash( $input[ $key ] ?? $value ) );
	}
	return $out;
}

// On the front end only: not in feeds, embeds or the customizer's preview.
foreach ( MCL_TRACKING_HOOKS as $key => [ $hook, $priority ] ) {
	add_action( $hook, function () use ( $key ) {
		if ( is_admin() || is_feed() || is_embed() || is_customize_preview() ) {
			return;
		}
		$code = mcl_tracking()[ $key ];
		if ( '' !== $code ) {
			echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- an administrator's tracking code, as pasted.
		}
	}, $priority );
}

// Its section of Settings → Business.
function mcl_tracking_fields(): void {
	$codes  = mcl_tracking();
	$places = array(
		'head'       => array( __( 'In <head>', 'marccent-local' ), __( 'Google Tag Manager\'s script, gtag.js (Analytics, Ads), Meta Pixel, verification meta tags.', 'marccent-local' ) ),
		'body_open'  => array( __( 'After <body>', 'marccent-local' ), __( 'Right after the body opens: Google Tag Manager\'s <code>&lt;noscript&gt;</code> iframe.', 'marccent-local' ) ),
		'body_close' => array( __( 'Before </body>', 'marccent-local' ), __( 'Chat widgets, heatmaps and anything that shouldn\'t hold up the page.', 'marccent-local' ) ),
	);
	echo '<h2>' . esc_html__( 'Tracking codes', 'marccent-local' ) . '</h2>';
	echo '<p>' . esc_html__( 'Paste each code as its provider gives it, script tags and all. They run on every page of the site.', 'marccent-local' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	foreach ( $places as $key => [ $label, $help ] ) {
		printf(
			'<tr><th scope="row"><label for="mcl-tracking-%1$s">%2$s</label></th><td><textarea id="mcl-tracking-%1$s" name="%3$s" rows="5" class="large-text code" spellcheck="false">%4$s</textarea><p class="description">%5$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( MCL_TRACKING . "[$key]" ),
			esc_textarea( $codes[ $key ] ),
			wp_kses_post( $help )
		);
	}
	echo '</table>';
}

// Tools → Marccent export: the codes, put back only by someone allowed to post them (or WP-CLI).
add_filter( 'marccent_export_parts', function ( $parts ) {
	$parts['tracking'] = array(
		'label'  => __( 'Tracking codes', 'marccent-local' ),
		'export' => 'mcl_tracking',
		'import' => function ( array $data ) {
			if ( ! current_user_can( 'unfiltered_html' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return new WP_Error( 'mcl_tracking', __( 'Only an administrator who can post unfiltered HTML can import tracking codes.', 'marccent-local' ) );
			}
			$codes = array();
			foreach ( MCL_TRACKING_HOOKS as $key => $hook ) {
				$codes[ $key ] = trim( (string) ( $data[ $key ] ?? '' ) );
			}
			remove_all_filters( 'sanitize_option_' . MCL_TRACKING ); // checked above; WP-CLI has no user
			update_option( MCL_TRACKING, $codes );
		},
	);
	return $parts;
} );
