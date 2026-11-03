<?php
/**
 * Opening hours (Settings → Business): the week's hours, the days that differ (holidays, a short day), and the
 * business's locations when it has several. Search reads them in the schema (openingHoursSpecification, its special
 * days, a LocalBusiness per location) and llms.txt; pages show them with shortcodes:
 *
 *   [business_hours]      the week, today in bold, and the coming days that differ
 *   [open_now]            "Open now · until 5 PM", or "Closed · opens Mon 8 AM"
 *   [business_locations]  each location: its name, address, phone, hours and map link
 *
 * A day's hours: "08:00-17:00", or two spans, "08:00-12:00, 13:00-17:00"; empty, closed. Times are the site's
 * (Settings → General → Timezone).
 */

defined( 'ABSPATH' ) || exit;

const MCL_DAYS = array( 'mo' => 'Monday', 'tu' => 'Tuesday', 'we' => 'Wednesday', 'th' => 'Thursday', 'fr' => 'Friday', 'sa' => 'Saturday', 'su' => 'Sunday' );

// "08:00-12:00, 13:00-17:00" → [ [ '08:00', '12:00' ], [ '13:00', '17:00' ] ]; what doesn't parse is left out.
function mcl_spans( string $hours ): array {
	$spans = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $hours ) ) ) as $span ) {
		if ( preg_match( '/^(\d{1,2}):?(\d{2})\s*[-–]\s*(\d{1,2}):?(\d{2})$/', $span, $m ) && $m[1] < 25 && $m[3] < 25 && $m[2] < 60 && $m[4] < 60 ) {
			$spans[] = array( sprintf( '%02d:%s', $m[1], $m[2] ), sprintf( '%02d:%s', $m[3], $m[4] ) );
		}
	}
	return $spans;
}

function mcl_spans_text( array $spans ): string {
	return implode( ', ', array_map( fn( $s ) => "$s[0]-$s[1]", $spans ) );
}

function mcl_hours_sanitize( array $hours ): array {
	$out = array();
	foreach ( array_keys( MCL_DAYS ) as $day ) {
		$out[ $day ] = mcl_spans_text( mcl_spans( (string) ( $hours[ $day ] ?? '' ) ) );
	}
	return $out;
}

// One line each: "2026-12-25 closed Christmas", "2026-12-24 09:00-12:00 Christmas Eve".
function mcl_closures_parse( string $text ): array {
	$out = array();
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		if ( preg_match( '/^\s*(\d{4}-\d{2}-\d{2})\s+(closed|[\d:\-–,\s]+?)(?:\s+(.*))?$/i', $line, $m ) ) {
			$out[] = array( 'date' => $m[1], 'hours' => 'closed' === strtolower( $m[2] ) ? '' : trim( $m[2] ), 'note' => $m[3] ?? '' );
		}
	}
	return mcl_closures_sanitize( $out );
}

function mcl_closures_sanitize( array $list ): array {
	$out = array();
	foreach ( $list as $c ) {
		$date = (string) ( $c['date'] ?? '' );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			$out[ $date ] = array( 'date' => $date, 'hours' => mcl_spans_text( mcl_spans( (string) ( $c['hours'] ?? '' ) ) ), 'note' => sanitize_text_field( (string) ( $c['note'] ?? '' ) ) );
		}
	}
	ksort( $out );
	return array_values( $out );
}

// The spans a day has: its exception's if it has one, else its weekday's.
function mcl_day_spans( DateTimeInterface $day, ?array $hours = null ): array {
	$b     = mcl_settings();
	$hours = $hours ?? $b['hours'];
	foreach ( $b['closures'] as $c ) {
		if ( $c['date'] === $day->format( 'Y-m-d' ) ) {
			return mcl_spans( $c['hours'] );
		}
	}
	return mcl_spans( (string) ( $hours[ strtolower( substr( $day->format( 'D' ), 0, 2 ) ) ] ?? '' ) );
}

function mcl_has_hours( ?array $hours = null ): bool {
	return (bool) array_filter( $hours ?? mcl_settings()['hours'] );
}

function mcl_time_text( string $hm ): string {
	return wp_date( str_ends_with( $hm, ':00' ) ? 'g A' : 'g:i A', strtotime( "2000-01-01 $hm" ), new DateTimeZone( 'UTC' ) );
}

/**
 * Whether it's open right now: [ open, text ] ("Open now · until 5 PM", "Closed · opens Mon 8 AM"), looking a week
 * ahead for the next opening.
 */
function mcl_open_status( ?array $hours = null ): array {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	$hm  = $now->format( 'H:i' );
	foreach ( mcl_day_spans( $now, $hours ) as [ $open, $close ] ) {
		if ( $hm >= $open && $hm < $close ) {
			/* translators: %s: a time, 5 PM */
			return array( true, sprintf( __( 'Open now · until %s', 'marccent-local' ), mcl_time_text( $close ) ) );
		}
	}
	for ( $i = 0; $i < 8; $i++ ) {
		$day = $now->modify( "+$i day" );
		foreach ( mcl_day_spans( $day, $hours ) as [ $open ] ) {
			if ( $i > 0 || $open > $hm ) {
				$when = 0 === $i ? '' : ( 1 === $i ? __( 'tomorrow', 'marccent-local' ) : wp_date( 'D', $day->getTimestamp() ) ) . ' ';
				/* translators: %s: when, "tomorrow 8 AM", "Mon 8 AM" */
				return array( false, sprintf( __( 'Closed · opens %s', 'marccent-local' ), $when . mcl_time_text( $open ) ) );
			}
		}
	}
	return array( false, __( 'Closed', 'marccent-local' ) );
}

/* Schema */

// The week's hours as schema.org's OpeningHoursSpecification, days with the same hours together.
function mcl_hours_schema( ?array $hours = null ): array {
	$hours = $hours ?? mcl_settings()['hours'];
	$same  = array();
	foreach ( MCL_DAYS as $key => $name ) {
		foreach ( mcl_spans( (string) ( $hours[ $key ] ?? '' ) ) as [ $open, $close ] ) {
			$same[ "$open-$close" ][] = $name;
		}
	}
	$out = array();
	foreach ( $same as $span => $days ) {
		[ $open, $close ] = explode( '-', $span );
		$out[]            = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => $open, 'closes' => $close );
	}
	return $out;
}

// The coming days that differ (the next 90): a closed day opens and closes at 00:00, as Google reads it.
function mcl_special_schema(): array {
	$today = wp_date( 'Y-m-d' );
	$until = wp_date( 'Y-m-d', time() + 90 * DAY_IN_SECONDS );
	$out   = array();
	foreach ( mcl_settings()['closures'] as $c ) {
		if ( $c['date'] < $today || $c['date'] > $until ) {
			continue;
		}
		$spans = mcl_spans( $c['hours'] ) ?: array( array( '00:00', '00:00' ) );
		foreach ( $spans as [ $open, $close ] ) {
			$out[] = array( '@type' => 'OpeningHoursSpecification', 'validFrom' => $c['date'], 'validThrough' => $c['date'], 'opens' => $open, 'closes' => $close );
		}
	}
	return $out;
}

// The locations, each with its fields; the business's hours when one has none.
function mcl_locations(): array {
	$out = array();
	foreach ( (array) apply_filters( 'mcl_locations', mcl_settings()['locations'] ) as $l ) {
		if ( ! is_array( $l ) || empty( $l['name'] ) ) {
			continue;
		}
		$l          = array_merge( array( 'slug' => sanitize_title( $l['name'] ), 'street' => '', 'city' => '', 'region' => '', 'postal' => '', 'telephone' => '', 'phone' => '', 'lat' => '', 'lng' => '', 'map' => '', 'hours' => array() ), $l );
		$l['hours'] = mcl_has_hours( (array) $l['hours'] ) ? mcl_hours_sanitize( (array) $l['hours'] ) : mcl_settings()['hours'];
		$out[]      = $l;
	}
	return $out;
}

// The schema nodes of the locations: one LocalBusiness each, part of the business.
function mcl_locations_schema( array $b, string $biz_id ): array {
	$nodes = array();
	foreach ( mcl_locations() as $l ) {
		$nodes[] = array(
			'@type'                     => $b['type'] ?: 'LocalBusiness',
			'@id'                       => home_url( '/' ) . '#location-' . sanitize_title( $l['slug'] ),
			'name'                      => $b['name'] . ' – ' . $l['name'],
			'parentOrganization'        => array( '@id' => $biz_id ),
			'url'                       => home_url( '/' ),
			'telephone'                 => $l['telephone'] ?: $b['telephone'],
			'address'                   => array( '@type' => 'PostalAddress', 'streetAddress' => $l['street'], 'addressLocality' => $l['city'], 'addressRegion' => $l['region'], 'postalCode' => $l['postal'], 'addressCountry' => $b['country'] ),
			'geo'                       => '' !== (string) $l['lat'] ? array( '@type' => 'GeoCoordinates', 'latitude' => (float) $l['lat'], 'longitude' => (float) $l['lng'] ) : null,
			'hasMap'                    => $l['map'],
			'openingHoursSpecification' => mcl_hours_schema( $l['hours'] ),
		);
	}
	return $nodes;
}

/* Shortcodes */

add_shortcode( 'open_now', function () {
	if ( ! mcl_has_hours() ) {
		return '';
	}
	[ $open, $text ] = mcl_open_status();
	return sprintf( '<span class="mcl-open %s">%s</span>', $open ? 'is-open' : 'is-closed', esc_html( $text ) );
} );

function mcl_hours_table( array $hours ): string {
	$today = strtolower( substr( ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'D' ), 0, 2 ) ); // English, as the keys
	$rows  = '';
	foreach ( MCL_DAYS as $key => $name ) {
		$spans = mcl_spans( (string) ( $hours[ $key ] ?? '' ) );
		$text  = $spans ? implode( ', ', array_map( fn( $s ) => mcl_time_text( $s[0] ) . '–' . mcl_time_text( $s[1] ), $spans ) ) : __( 'Closed', 'marccent-local' );
		$rows .= sprintf( '<tr%s><th scope="row">%s</th><td>%s</td></tr>', $key === $today ? ' class="is-today"' : '', esc_html( wp_date( 'l', strtotime( "next $name 12:00" ) ) ), esc_html( $text ) );
	}
	return '<table class="mcl-hours">' . $rows . '</table>';
}

add_shortcode( 'business_hours', function () {
	$b = mcl_settings();
	if ( ! mcl_has_hours() ) {
		return '';
	}
	$html  = mcl_hours_table( $b['hours'] );
	$today = wp_date( 'Y-m-d' );
	$soon  = array_filter( $b['closures'], fn( $c ) => $c['date'] >= $today && $c['date'] <= wp_date( 'Y-m-d', time() + 60 * DAY_IN_SECONDS ) );
	if ( $soon ) {
		$items = '';
		foreach ( $soon as $c ) {
			$spans  = mcl_spans( $c['hours'] );
			$items .= sprintf(
				'<li><strong>%s</strong>: %s%s</li>',
				esc_html( wp_date( 'D, M j', strtotime( $c['date'] . ' 12:00' ) ) ),
				esc_html( $spans ? implode( ', ', array_map( fn( $s ) => mcl_time_text( $s[0] ) . '–' . mcl_time_text( $s[1] ), $spans ) ) : __( 'Closed', 'marccent-local' ) ),
				$c['note'] ? ' (' . esc_html( $c['note'] ) . ')' : ''
			);
		}
		$html .= '<p class="mcl-hours-note">' . esc_html__( 'Coming up:', 'marccent-local' ) . '</p><ul class="mcl-closures">' . $items . '</ul>';
	}
	return $html;
} );

add_shortcode( 'business_locations', function () {
	$out = '';
	foreach ( mcl_locations() as $l ) {
		$address = trim( $l['street'] . ', ' . trim( $l['city'] . ', ' . $l['region'] . ' ' . $l['postal'], ', ' ), ', ' );
		[ $open, $text ] = mcl_has_hours( $l['hours'] ) ? mcl_open_status( $l['hours'] ) : array( false, '' );
		$out .= sprintf(
			'<div class="mcl-location card pad round"><h3>%s</h3><p>%s</p>%s%s%s%s</div>',
			esc_html( $l['name'] ),
			esc_html( $address ),
			$l['phone'] ? sprintf( '<p><a href="tel:%s">%s</a></p>', esc_attr( $l['telephone'] ?: $l['phone'] ), esc_html( $l['phone'] ) ) : '',
			$text ? sprintf( '<p class="mcl-open %s">%s</p>', $open ? 'is-open' : 'is-closed', esc_html( $text ) ) : '',
			mcl_has_hours( $l['hours'] ) ? mcl_hours_table( $l['hours'] ) : '',
			$l['map'] ? sprintf( '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url( $l['map'] ), esc_html__( 'Directions', 'marccent-local' ) ) : ''
		);
	}
	return $out ? '<div class="mcl-locations grid cols-2 gap">' . $out . '</div>' : '';
} );

/* Settings → Business: the week, then the days that differ */

function mcl_hours_fields( array $s ): void {
	echo '<h2>' . esc_html__( 'Opening hours', 'marccent-local' ) . '</h2>';
	echo '<p>' . esc_html__( 'Each day’s hours, like 08:00-17:00, or two spans: 08:00-12:00, 13:00-17:00. Empty: closed. Search shows them, and so do [business_hours] and [open_now] on a page.', 'marccent-local' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	foreach ( MCL_DAYS as $key => $name ) {
		printf( '<tr><th scope="row"><label for="mcl-hours-%1$s">%2$s</label></th><td><input id="mcl-hours-%1$s" name="%3$s[hours][%1$s]" value="%4$s" placeholder="%5$s" class="regular-text"></td></tr>', esc_attr( $key ), esc_html( wp_date( 'l', strtotime( "next $name 12:00" ) ) ), esc_attr( MCL_OPTION ), esc_attr( $s['hours'][ $key ] ?? '' ), esc_attr__( 'Closed', 'marccent-local' ) );
	}
	$lines = implode( "\n", array_map( fn( $c ) => trim( $c['date'] . ' ' . ( $c['hours'] ?: 'closed' ) . ' ' . $c['note'] ), $s['closures'] ) );
	printf( '<tr><th scope="row"><label for="mcl-closures">%s</label></th><td><textarea id="mcl-closures" name="%s[closures]" rows="4" class="large-text code">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'Days that differ', 'marccent-local' ), esc_attr( MCL_OPTION ), esc_textarea( $lines ), esc_html__( 'One per line: the date, closed or its hours, and a note. 2026-12-25 closed Christmas · 2026-12-24 09:00-12:00 Christmas Eve', 'marccent-local' ) );
	echo '</table>';
}

add_action( 'wp_enqueue_scripts', function () {
	wp_add_inline_style( 'marccent', '.mcl-hours{border-collapse:collapse}.mcl-hours th{text-align:left;font-weight:normal;padding:.2rem 1.5rem .2rem 0}.mcl-hours td{padding:.2rem 0}.mcl-hours .is-today{font-weight:bold}.mcl-open.is-open{color:var(--success,#15803d)}.mcl-open.is-closed{opacity:.8}.mcl-closures{margin:.25rem 0 0;padding-left:1.25rem}' );
}, 20 );
