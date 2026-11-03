<?php
/**
 * IndexNow: when a page is published, changed or taken down, its address goes to the IndexNow engines (Bing, Yandex,
 * Seznam, Naver…), which crawl it within minutes instead of waiting for their next visit. Google doesn't take part; it
 * has the sitemap.
 *
 * The site's key is made once (the mcl_indexnow_key option) and served at /<key>.txt, which is how the engines know the
 * addresses come from the site. Changes are gathered and sent a minute later in one request (WP-Cron), so an import or
 * a burst of edits is one ping. Off under Settings → Business, on a site that asks search engines not to index it, and
 * on a .localhost, .test, .local or .example address.
 */

defined( 'ABSPATH' ) || exit;

function mcl_indexnow_on(): bool {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return mcl_settings()['indexnow'] && '1' === (string) get_option( 'blog_public' )
		&& ! preg_match( '/(^localhost|\.localhost|\.test|\.local|\.example|\.invalid)$/', $host )
		&& ! filter_var( $host, FILTER_VALIDATE_IP );
}

function mcl_indexnow_key(): string {
	$key = (string) get_option( 'mcl_indexnow_key', '' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'mcl_indexnow_key', $key, true );
	}
	return $key;
}

// The key file, /<key>.txt.
add_action( 'parse_request', function ( WP $wp ) {
	$key = (string) get_option( 'mcl_indexnow_key', '' );
	if ( '' !== $key && $wp->request === $key . '.txt' ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $key );
		exit;
	}
} );

// A public post published, updated or taken down: its address in the queue.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( ( 'publish' !== $new && 'publish' !== $old ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! is_post_type_viewable( $post->post_type ) || '' !== $post->post_password || ! mcl_indexnow_on() ) {
		return;
	}
	$url = 'publish' === $new ? get_permalink( $post ) : home_url( '/' . $post->post_name . '/' ); // taken down: its last address
	mcl_indexnow_queue( array( $url ) );
}, 10, 3 );

function mcl_indexnow_queue( array $urls ): void {
	$queue = (array) get_option( 'mcl_indexnow_queue', array() );
	update_option( 'mcl_indexnow_queue', array_values( array_unique( array_merge( $queue, $urls ) ) ), false );
	if ( ! wp_next_scheduled( 'mcl_indexnow_send' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'mcl_indexnow_send' );
	}
}

add_action( 'mcl_indexnow_send', function () {
	$urls = array_slice( (array) get_option( 'mcl_indexnow_queue', array() ), 0, 10000 );
	delete_option( 'mcl_indexnow_queue' );
	if ( ! $urls || ! mcl_indexnow_on() ) {
		return;
	}
	$key      = mcl_indexnow_key();
	$response = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
		'timeout' => 10,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'    => wp_json_encode( array(
			'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
			'key'         => $key,
			'keyLocation' => home_url( "/$key.txt" ),
			'urlList'     => $urls,
		) ),
	) );
	update_option( 'mcl_indexnow_last', array( 'time' => time(), 'count' => count( $urls ), 'status' => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ), false );
} );
