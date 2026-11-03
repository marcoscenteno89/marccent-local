<?php
/**
 * Consent: Google's Consent Mode v2 and a small cookie banner (the mcl_consent option; Settings → Business, under the
 * tracking codes). On, every page starts with ads and analytics storage denied, before any tracking code runs; the
 * banner asks once, and the visitor's answer (the mcl_consent cookie, six months) updates Google's tags at once and on
 * every later page. Google's tags then measure without cookies until a visitor accepts.
 *
 * Tags that don't read Consent Mode (Meta's pixel, heatmaps, chat widgets) can wait for consent: with "Hold the codes",
 * the tracking codes are printed inert (<template>) and only run once the visitor accepts.
 *
 * A link to #cookie-settings anywhere on the site (a footer link) opens the banner again.
 */

defined( 'ABSPATH' ) || exit;

const MCL_CONSENT = 'mcl_consent';

function mcl_consent_defaults(): array {
	return array(
		'on'     => false,
		'hold'   => false,
		'text'   => __( 'We use cookies to see how the site is used and to measure our ads. Is that OK?', 'marccent-local' ),
		'accept' => __( 'Accept', 'marccent-local' ),
		'reject' => __( 'No thanks', 'marccent-local' ),
		'policy' => '',
	);
}

function mcl_consent(): array {
	$saved = get_option( MCL_CONSENT, array() );
	return array_merge( mcl_consent_defaults(), is_array( $saved ) ? $saved : array() );
}

function mcl_consent_sanitize( $input ): array {
	$input = is_array( $input ) ? $input : array();
	return array(
		'on'     => ! empty( $input['on'] ),
		'hold'   => ! empty( $input['hold'] ),
		'text'   => sanitize_textarea_field( (string) wp_unslash( $input['text'] ?? '' ) ) ?: mcl_consent_defaults()['text'],
		'accept' => sanitize_text_field( (string) wp_unslash( $input['accept'] ?? '' ) ) ?: mcl_consent_defaults()['accept'],
		'reject' => sanitize_text_field( (string) wp_unslash( $input['reject'] ?? '' ) ) ?: mcl_consent_defaults()['reject'],
		'policy' => esc_url_raw( (string) wp_unslash( $input['policy'] ?? '' ) ),
	);
}

add_action( 'admin_init', function () {
	register_setting( 'marccent-local', MCL_CONSENT, array( 'type' => 'array', 'sanitize_callback' => 'mcl_consent_sanitize' ) );
} );

function mcl_consent_front(): bool {
	return mcl_consent()['on'] && ! is_admin() && ! is_feed() && ! is_embed() && ! is_customize_preview();
}

// First thing in <head>, before the tracking codes (priority 1): denied by default, or the visitor's earlier answer.
add_action( 'wp_head', function () {
	if ( ! mcl_consent_front() ) {
		return;
	}
	?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', functionality_storage: 'granted', security_storage: 'granted', wait_for_update: 500});
(function () {
	var m = document.cookie.match(/(?:^|; )mcl_consent=(yes|no)/);
	if (m && m[1] === 'yes') {
		gtag('consent', 'update', {ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted'});
	}
	window.mclConsent = m ? m[1] : '';
})();
</script>
	<?php
}, 0 );

// "Hold the codes": the tracking codes inert until the visitor accepts (mcl_consent_release, below, runs them).
add_filter( 'mcl_tracking_code', function ( string $code ) {
	if ( '' === $code || ! mcl_consent_front() || ! mcl_consent()['hold'] ) {
		return $code;
	}
	return '<template data-mcl-consent>' . $code . '</template>';
} );

// The banner and its script, at the end of the page.
add_action( 'wp_footer', function () {
	if ( ! mcl_consent_front() ) {
		return;
	}
	$c = mcl_consent();
	?>
<div id="mcl-consent" class="mcl-consent" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Cookies', 'marccent-local' ); ?>" hidden>
	<p><?php echo esc_html( $c['text'] ); ?><?php if ( $c['policy'] ) : ?> <a href="<?php echo esc_url( $c['policy'] ); ?>"><?php esc_html_e( 'Privacy policy', 'marccent-local' ); ?></a><?php endif; ?></p>
	<div class="mcl-consent__actions">
		<button type="button" class="btn" data-consent="no"><?php echo esc_html( $c['reject'] ); ?></button>
		<button type="button" class="btn primary" data-consent="yes"><?php echo esc_html( $c['accept'] ); ?></button>
	</div>
</div>
<script>
(function () {
	var box = document.getElementById('mcl-consent');
	var granted = {ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted'};
	var denied = {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied'};
	// The held codes, run: each script made anew (a script cloned from a template doesn't run).
	var release = function () {
		document.querySelectorAll('template[data-mcl-consent]').forEach(function (t) {
			var frag = t.content.cloneNode(true);
			frag.querySelectorAll('script').forEach(function (old) {
				var s = document.createElement('script');
				for (var i = 0; i < old.attributes.length; i++) s.setAttribute(old.attributes[i].name, old.attributes[i].value);
				s.text = old.text;
				old.replaceWith(s);
			});
			t.replaceWith(frag);
		});
	};
	var answer = function (yes) {
		document.cookie = 'mcl_consent=' + (yes ? 'yes' : 'no') + ';path=/;max-age=15552000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
		gtag('consent', 'update', yes ? granted : denied);
		dataLayer.push({event: 'mcl_consent', consent: yes ? 'yes' : 'no'});
		if (yes) release();
		box.hidden = true;
	};
	box.addEventListener('click', function (e) {
		var b = e.target.closest('[data-consent]');
		if (b) answer(b.dataset.consent === 'yes');
	});
	document.addEventListener('click', function (e) {
		var a = e.target.closest('a[href$="#cookie-settings"]');
		if (a) {
			e.preventDefault();
			box.hidden = false;
		}
	});
	if (window.mclConsent === 'yes') release();
	else if (!window.mclConsent) box.hidden = false;
})();
</script>
	<?php
}, 100 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! mcl_consent()['on'] ) {
		return;
	}
	wp_add_inline_style( 'marccent', '.mcl-consent{position:fixed;inset:auto 1rem 1rem auto;z-index:9999;max-width:24rem;padding:1rem 1.25rem;border-radius:var(--radius,.75rem);background:var(--surface,#fff);color:var(--text,#111);box-shadow:0 10px 30px rgb(0 0 0/.2);font-size:.9rem}.mcl-consent[hidden]{display:none}.mcl-consent p{margin:0 0 .75rem}.mcl-consent__actions{display:flex;gap:.5rem;justify-content:flex-end}@media (max-width:30rem){.mcl-consent{inset:auto .5rem .5rem .5rem;max-width:none}}' );
}, 20 );

// Its part of Settings → Business, after the tracking codes.
function mcl_consent_fields(): void {
	$c    = mcl_consent();
	$name = fn( $key ) => esc_attr( MCL_CONSENT . "[$key]" );
	echo '<h2>' . esc_html__( 'Cookie consent', 'marccent-local' ) . '</h2>';
	echo '<p>' . esc_html__( 'Google Consent Mode v2 with a small banner: Google’s tags (Analytics, Ads, Tag Manager) wait for the visitor’s answer and measure without cookies until then. Needed for visitors from the EU and UK, and for Google Ads’ audiences anywhere.', 'marccent-local' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s" value="1" %s> %s</label></td></tr>', esc_html__( 'Banner', 'marccent-local' ), $name( 'on' ), checked( $c['on'], true, false ), esc_html__( 'Ask visitors, and start Google’s tags denied until they answer', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $name
	printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s" value="1" %s> %s</label><p class="description">%s</p></td></tr>', esc_html__( 'Other tags', 'marccent-local' ), $name( 'hold' ), checked( $c['hold'], true, false ), esc_html__( 'Hold the tracking codes until the visitor accepts', 'marccent-local' ), esc_html__( 'For tags that don’t read Consent Mode: Meta’s pixel, heatmaps, chat widgets. Google’s tags don’t need it.', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<tr><th scope="row"><label for="mcl-consent-text">%s</label></th><td><textarea id="mcl-consent-text" name="%s" rows="2" class="large-text">%s</textarea></td></tr>', esc_html__( 'Its words', 'marccent-local' ), $name( 'text' ), esc_textarea( $c['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<tr><th scope="row">%s</th><td><input name="%s" value="%s" aria-label="%s"> <input name="%s" value="%s" aria-label="%s"></td></tr>', esc_html__( 'Buttons', 'marccent-local' ), $name( 'accept' ), esc_attr( $c['accept'] ), esc_attr__( 'Accept', 'marccent-local' ), $name( 'reject' ), esc_attr( $c['reject'] ), esc_attr__( 'Reject', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<tr><th scope="row"><label for="mcl-consent-policy">%s</label></th><td><input id="mcl-consent-policy" name="%s" value="%s" class="regular-text" placeholder="/privacy-policy/"><p class="description">%s</p></td></tr>', esc_html__( 'Privacy policy', 'marccent-local' ), $name( 'policy' ), esc_attr( $c['policy'] ), esc_html__( 'A link in the banner. Any link to #cookie-settings (in the footer, say) opens the banner again.', 'marccent-local' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</table>';
}

add_filter( 'marccent_export_parts', function ( $parts ) {
	$parts['consent'] = array(
		'label'  => __( 'Cookie consent', 'marccent-local' ),
		'export' => 'mcl_consent',
		'import' => fn( array $data ) => update_option( MCL_CONSENT, mcl_consent_sanitize( $data ) ),
	);
	return $parts;
} );
