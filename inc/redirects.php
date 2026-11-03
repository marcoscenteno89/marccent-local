<?php
/**
 * Redirects and the 404 log (Tools → Redirects): an old site's addresses sent to the new ones, so links and search
 * results keep working after a move. Each redirect is a path on this site and where it goes:
 *
 *   /old-page          → /new-page          (301, moved for good)
 *   /services/*        → /our-services/*    (a path and everything under it; * carries the rest over)
 *   /promo             → https://other.site (302, for now)
 *   /discontinued      → (410, gone: nothing to send it to)
 *
 * Kept in the mcl_redirects option (autoloaded: every request checks it, with no query); the addresses that weren't
 * found, with how often and from where, in mcl_404_log (not autoloaded: only a 404 reads it). A logged 404 is one click
 * from a redirect.
 */

defined( 'ABSPATH' ) || exit;

const MCL_REDIRECTS = 'mcl_redirects';
const MCL_404_LOG   = 'mcl_404_log';
const MCL_404_MAX   = 200; // the log keeps the most recent addresses only

// [ from => [ 'to' => …, 'code' => 301|302|307|410, 'hits' => n ] ], from a path ("/old-page", "/services/*").
function mcl_redirects(): array {
	$saved = get_option( MCL_REDIRECTS, array() );
	return is_array( $saved ) ? $saved : array();
}

// A path as redirects compare it: no host or query, a leading slash, no trailing one, lower case.
function mcl_redirect_path( string $url ): string {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$path = '/' . trim( rawurldecode( $path ), '/' );
	return strtolower( $path );
}

// One redirect's from, to and code, checked; null when it isn't usable.
function mcl_redirect_clean( string $from, string $to, int $code ): ?array {
	$from = trim( $from );
	$base = mcl_redirect_path( rtrim( $from, '*' ) );
	$from = str_ends_with( $from, '*' ) ? rtrim( $base, '/' ) . '/*' : $base;
	$code =in_array( $code, array( 301, 302, 307, 410 ), true ) ? $code : 301;
	$to   = trim( $to );
	if ( 410 !== $code ) {
		$to = str_starts_with( $to, '/' ) ? '/' . ltrim( $to, '/' ) : esc_url_raw( $to, array( 'http', 'https' ) );
		if ( '' === $to ) {
			return null;
		}
	} else {
		$to = '';
	}
	if ( '/' === $base || ( 410 !== $code && mcl_redirect_path( $to ) === $from && ! str_contains( $to, '://' ) ) ) {
		return null; // the home page, or a path sent to itself: a loop
	}
	return array( 'from' => $from, 'to' => $to, 'code' => $code );
}

/**
 * Where a path goes, if a redirect covers it: an exact one first, then the longest "/path/*" that it's under.
 * [ to (a full URL, or '' for 410), code, from ] or null.
 */
function mcl_redirect_match( string $path ): ?array {
	$all = mcl_redirects();
	if ( isset( $all[ $path ] ) ) {
		return array( $all[ $path ]['to'], (int) $all[ $path ]['code'], $path );
	}
	$best = null;
	foreach ( $all as $from => $r ) {
		if ( str_ends_with( $from, '/*' ) ) {
			$base = substr( $from, 0, -2 );
			if ( ( $path === $base || str_starts_with( $path, $base . '/' ) ) && ( ! $best || strlen( $base ) > strlen( $best[2] ) ) ) {
				// The rest of the path, each segment URL-encoded: it can't change where the address goes (a backslash,
				// "//", "http:") but only extend the administrator's target.
				$rest = implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', substr( $path, strlen( $base ) ) ), 'strlen' ) ) );
				$to   = str_ends_with( $r['to'], '*' ) ? rtrim( substr( $r['to'], 0, -1 ), '/' ) . ( '' !== $rest ? '/' . $rest : '' ) : $r['to'];
				$best = array( $to, (int) $r['code'], $base, $from );
			}
		}
	}
	return $best ? array( $best[0], $best[1], $best[3] ) : null;
}

// Before WordPress looks the address up: a redirect wins, so a page that still exists can be moved too.
add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! mcl_redirects() ) {
		return;
	}
	$uri   = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only its path is compared
	$home  = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$path  = mcl_redirect_path( $uri );
	$base  = mcl_redirect_path( $home );
	$path  = '/' !== $base && str_starts_with( $path, $base ) ? '/' . ltrim( substr( $path, strlen( $base ) ), '/' ) : $path;
	$match = mcl_redirect_match( $path );
	if ( ! $match ) {
		return;
	}
	[ $to, $code, $from ] = $match;
	$all = mcl_redirects();
	$all[ $from ]['hits'] = (int) ( $all[ $from ]['hits'] ?? 0 ) + 1;
	update_option( MCL_REDIRECTS, $all, true );
	if ( 410 === $code ) {
		status_header( 410 );
		nocache_headers();
		$GLOBALS['wp_query']->set_404();
		return; // the theme's 404 template, with a 410 status
	}
	// A path and what's under it ("/old/*"): the address keeps its trailing slash, so it isn't redirected once more.
	if ( str_ends_with( $from, '/*' ) && str_ends_with( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' ) && ! str_ends_with( $to, '/' ) && ! str_contains( $to, '?' ) ) {
		$to .= '/';
	}
	$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	// Absolute or not by the stored target, never by what the visitor's path added to it.
	$url   = str_starts_with( mcl_redirects()[ $from ]['to'] ?? '', '/' ) ? home_url( '/' . ltrim( $to, '/' ) ) : $to;
	if ( '' !== $query && ! str_contains( $url, '?' ) ) {
		$url .= '?' . $query; // campaign tags and the like carry over
	}
	// wp_redirect, not wp_safe_redirect: an administrator chose the address, other sites included.
	wp_redirect( $url, $code, 'Marccent Local' ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
}, 1 );

// A 404: logged (the path, how many times, the last time and where from), unless it's a bot's probe for a file.
add_action( 'template_redirect', function () {
	if ( ! is_404() || 410 === http_response_code() ) {
		return;
	}
	$path = mcl_redirect_path( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a path, escaped when shown
	if ( preg_match( '/\.(php|env|git|asp|aspx|jsp|cgi|sql|bak|ini|log|xml\.gz)$|^\/(wp-admin|wp-includes|\.well-known|xmlrpc)/i', $path ) || strlen( $path ) > 300 ) {
		return;
	}
	$log = get_option( MCL_404_LOG, array() );
	$log = is_array( $log ) ? $log : array();
	$ref = esc_url_raw( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized as a URL
	$row = $log[ $path ] ?? array( 'hits' => 0, 'first' => time() );
	unset( $log[ $path ] ); // to the end: the most recent last
	$log[ $path ] = array( 'hits' => $row['hits'] + 1, 'first' => $row['first'], 'last' => time(), 'ref' => $ref ?: ( $row['ref'] ?? '' ) );
	if ( count( $log ) > MCL_404_MAX ) {
		$log = array_slice( $log, -MCL_404_MAX, null, true );
	}
	update_option( MCL_404_LOG, $log, false );
}, 99 );

/* Tools → Redirects */

add_action( 'admin_menu', function () {
	add_management_page( __( 'Redirects', 'marccent-local' ), __( 'Redirects', 'marccent-local' ), 'manage_options', 'mcl-redirects', 'mcl_redirects_page' );
} );

add_action( 'admin_post_mcl_redirects', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'marccent-local' ) );
	}
	check_admin_referer( 'mcl_redirects' );
	$do     = sanitize_key( $_POST['do'] ?? '' );
	$all    = mcl_redirects();
	$notice = 'saved';
	if ( 'add' === $do ) {
		$r = mcl_redirect_clean( (string) wp_unslash( $_POST['from'] ?? '' ), (string) wp_unslash( $_POST['to'] ?? '' ), (int) ( $_POST['code'] ?? 301 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked by mcl_redirect_clean
		if ( $r ) {
			$all[ $r['from'] ] = array( 'to' => $r['to'], 'code' => $r['code'], 'hits' => $all[ $r['from'] ]['hits'] ?? 0 );
			$log               = get_option( MCL_404_LOG, array() );
			if ( is_array( $log ) && isset( $log[ $r['from'] ] ) ) {
				unset( $log[ $r['from'] ] ); // handled now
				update_option( MCL_404_LOG, $log, false );
			}
		} else {
			$notice = 'invalid';
		}
	} elseif ( 'delete' === $do ) {
		foreach ( (array) ( $_POST['paths'] ?? array() ) as $from ) {
			unset( $all[ (string) wp_unslash( $from ) ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a key looked up, never output
		}
	} elseif ( 'clear_log' === $do ) {
		update_option( MCL_404_LOG, array(), false );
	} elseif ( 'ignore' === $do ) {
		$log = get_option( MCL_404_LOG, array() );
		unset( $log[ (string) wp_unslash( $_POST['path'] ?? '' ) ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a key looked up
		update_option( MCL_404_LOG, $log, false );
	}
	ksort( $all );
	update_option( MCL_REDIRECTS, $all, true );
	wp_safe_redirect( add_query_arg( 'mcl', $notice, admin_url( 'tools.php?page=mcl-redirects' ) ) );
	exit;
} );

function mcl_redirects_page(): void {
	$all    = mcl_redirects();
	$log    = array_reverse( (array) get_option( MCL_404_LOG, array() ), true );
	$status = sanitize_key( $_GET['mcl'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- a notice only
	$from   = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- prefills the form
	$codes  = array(
		301 => __( '301 Moved for good', 'marccent-local' ),
		302 => __( '302 Moved for now', 'marccent-local' ),
		307 => __( '307 Moved for now (keeps the method)', 'marccent-local' ),
		410 => __( '410 Gone (nowhere to send it)', 'marccent-local' ),
	);
	$form = function ( string $do, string $inner, string $button = '', string $class = 'button' ) {
		printf( '<form method="post" action="%s" style="display:inline">', esc_url( admin_url( 'admin-post.php' ) ) );
		echo '<input type="hidden" name="action" value="mcl_redirects"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		wp_nonce_field( 'mcl_redirects' );
		echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped below
		if ( $button ) {
			printf( '<button class="%s">%s</button>', esc_attr( $class ), esc_html( $button ) );
		}
		echo '</form>';
	};

	echo '<div class="wrap"><h1>' . esc_html__( 'Redirects', 'marccent-local' ) . '</h1>';
	if ( 'saved' === $status ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'marccent-local' ) . '</p></div>';
	} elseif ( 'invalid' === $status ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'That redirect wasn’t saved: give a path on this site (not the home page) and where it goes, a path or a full address, and not to itself.', 'marccent-local' ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'Send old addresses to new ones, so links and search results keep working after a move. A path ending in * covers everything under it; put * at the end of where it goes to carry the rest of the address over.', 'marccent-local' ) . '</p>';

	echo '<h2>' . esc_html__( 'Add a redirect', 'marccent-local' ) . '</h2>';
	$options = '';
	foreach ( $codes as $code => $label ) {
		$options .= sprintf( '<option value="%d">%s</option>', $code, esc_html( $label ) );
	}
	$form( 'add', sprintf(
		'<p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input name="from" value="%s" placeholder="/old-page" class="regular-text" required aria-label="%s"> → <input name="to" placeholder="/new-page" class="regular-text" aria-label="%s"> <select name="code" aria-label="%s">%s</select> ',
		esc_attr( $from ),
		esc_attr__( 'From (a path on this site)', 'marccent-local' ),
		esc_attr__( 'To (a path or a full address)', 'marccent-local' ),
		esc_attr__( 'Kind', 'marccent-local' ),
		$options
	), __( 'Add redirect', 'marccent-local' ), 'button button-primary' );
	echo '</p>';

	/* translators: %d: how many redirects */
	echo '<h2>' . esc_html( sprintf( _n( '%d redirect', '%d redirects', count( $all ), 'marccent-local' ), count( $all ) ) ) . '</h2>';
	if ( $all ) {
		$rows = '';
		foreach ( $all as $path => $r ) {
			$rows .= sprintf(
				'<tr><th scope="row" class="check-column"><input type="checkbox" name="paths[]" value="%1$s" aria-label="%1$s"></th><td><code>%1$s</code></td><td>%2$s</td><td>%3$d</td><td>%4$d</td></tr>',
				esc_attr( $path ),
				410 === (int) $r['code'] ? '<em>' . esc_html__( 'gone', 'marccent-local' ) . '</em>' : '<code>' . esc_html( $r['to'] ) . '</code>',
				(int) $r['code'],
				(int) ( $r['hits'] ?? 0 )
			);
		}
		$form( 'delete', '<table class="widefat striped" style="margin-bottom:8px"><thead><tr><td class="check-column"></td><th>' . esc_html__( 'From', 'marccent-local' ) . '</th><th>' . esc_html__( 'To', 'marccent-local' ) . '</th><th>' . esc_html__( 'Kind', 'marccent-local' ) . '</th><th>' . esc_html__( 'Used', 'marccent-local' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>', __( 'Delete the ticked ones', 'marccent-local' ) );
	}

	/* translators: %d: how many addresses */
	echo '<h2 style="margin-top:2rem">' . esc_html( sprintf( __( 'Not found (%d)', 'marccent-local' ), count( $log ) ) ) . '</h2>';
	echo '<p>' . esc_html__( 'Addresses visitors asked for that don’t exist, most recent first. Redirect the ones that should go somewhere.', 'marccent-local' ) . '</p>';
	if ( $log ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Address', 'marccent-local' ) . '</th><th>' . esc_html__( 'Times', 'marccent-local' ) . '</th><th>' . esc_html__( 'Last', 'marccent-local' ) . '</th><th>' . esc_html__( 'Linked from', 'marccent-local' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $log as $path => $row ) {
			printf(
				'<tr><td><code>%s</code></td><td>%d</td><td>%s</td><td>%s</td><td><a class="button button-small" href="%s">%s</a> ',
				esc_html( $path ),
				(int) $row['hits'],
				esc_html( human_time_diff( (int) $row['last'] ) ),
				$row['ref'] ? '<a href="' . esc_url( $row['ref'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_parse_url( $row['ref'], PHP_URL_HOST ) . wp_parse_url( $row['ref'], PHP_URL_PATH ) ) . '</a>' : '—',
				esc_url( add_query_arg( 'from', rawurlencode( $path ), admin_url( 'tools.php?page=mcl-redirects' ) ) ),
				esc_html__( 'Redirect', 'marccent-local' )
			);
			$form( 'ignore', '<input type="hidden" name="path" value="' . esc_attr( $path ) . '">', __( 'Ignore', 'marccent-local' ), 'button button-small' );
			echo '</td></tr>';
		}
		echo '</tbody></table><p>';
		$form( 'clear_log', '', __( 'Clear the list', 'marccent-local' ) );
		echo '</p>';
	} else {
		echo '<p><em>' . esc_html__( 'None yet.', 'marccent-local' ) . '</em></p>';
	}
	echo '</div>';
}

// Tools → Marccent export: the redirects (not the 404 log, which is this site's own).
add_filter( 'marccent_export_parts', function ( $parts ) {
	$parts['redirects'] = array(
		'label'  => __( 'Redirects (Tools → Redirects)', 'marccent-local' ),
		'export' => fn() => array_map( fn( $r ) => array( 'to' => $r['to'], 'code' => (int) $r['code'] ), mcl_redirects() ),
		'import' => function ( array $data ) {
			$all = array();
			foreach ( $data as $from => $r ) {
				$clean = is_array( $r ) ? mcl_redirect_clean( (string) $from, (string) ( $r['to'] ?? '' ), (int) ( $r['code'] ?? 301 ) ) : null;
				if ( $clean ) {
					$all[ $clean['from'] ] = array( 'to' => $clean['to'], 'code' => $clean['code'], 'hits' => 0 );
				}
			}
			ksort( $all );
			update_option( MCL_REDIRECTS, $all, true );
		},
	);
	return $parts;
} );
