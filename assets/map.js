/*
 * The map (.local-map, in a site's content): a Google map (Maps JavaScript API) on white in the site palette's two colors,
 * roads and water in tints of the primary, highways and the pin in the secondary. It loads only when the map is close
 * to the screen, so the page doesn't pay for it up front. Without a key (Settings → Business), or if Google refuses
 * the key, it's Google's plain embed.
 *
 * Markup: <div class="local-map" data-lat data-lng data-zoom data-title data-link data-embed>…a link for no-script…</div>
 */
( () => {
	const config = window.marccentLocalMap || {};
	const PRIMARY = config.primary || '#1f64cf';
	const SECONDARY = config.secondary || '#e4572e';
	// A color mixed with white: amount 0 is the color, 1 is white.
	const tint = ( hex, amount ) => '#' + hex.replace( '#', '' ).match( /../g )
		.map( ( c ) => Math.round( parseInt( c, 16 ) + ( 255 - parseInt( c, 16 ) ) * amount ).toString( 16 ).padStart( 2, '0' ) ).join( '' );
	const STYLES = [
		{ elementType: 'geometry', stylers: [ { color: '#ffffff' } ] },
		{ featureType: 'landscape.man_made', elementType: 'geometry', stylers: [ { color: tint( PRIMARY, 0.96 ) } ] },
		{ featureType: 'poi', stylers: [ { visibility: 'off' } ] },
		{ featureType: 'poi.park', stylers: [ { visibility: 'on' } ] },
		{ featureType: 'poi.park', elementType: 'geometry', stylers: [ { color: tint( PRIMARY, 0.9 ) } ] },
		{ featureType: 'poi.park', elementType: 'labels', stylers: [ { visibility: 'off' } ] },
		{ featureType: 'transit', stylers: [ { visibility: 'off' } ] },
		{ featureType: 'road', elementType: 'geometry.fill', stylers: [ { color: tint( PRIMARY, 0.8 ) } ] },
		{ featureType: 'road', elementType: 'geometry.stroke', stylers: [ { color: '#ffffff' } ] },
		{ featureType: 'road.arterial', elementType: 'geometry.fill', stylers: [ { color: tint( PRIMARY, 0.6 ) } ] },
		{ featureType: 'road.highway', elementType: 'geometry.fill', stylers: [ { color: tint( SECONDARY, 0.45 ) } ] },
		{ featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [ { color: SECONDARY }, { weight: 0.6 } ] },
		{ featureType: 'road', elementType: 'labels.icon', stylers: [ { visibility: 'off' } ] },
		{ featureType: 'water', elementType: 'geometry', stylers: [ { color: tint( PRIMARY, 0.5 ) } ] },
		{ featureType: 'administrative', elementType: 'geometry.stroke', stylers: [ { color: SECONDARY }, { weight: 1 } ] },
		{ elementType: 'labels.text.fill', stylers: [ { color: '#24414d' } ] },
		{ elementType: 'labels.text.stroke', stylers: [ { color: '#ffffff' }, { weight: 3 } ] },
		{ featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [ { color: PRIMARY } ] },
	];
	// The pin: an orange drop with a white dot.
	const PIN = {
		path: 'M12 0C5.4 0 0 5.2 0 11.7 0 20.4 12 32 12 32s12-11.6 12-20.3C24 5.2 18.6 0 12 0z',
		fillColor: SECONDARY, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2, scale: 1.2,
	};

	// The content's URLs, only when they're https Google Maps addresses (a javascript: or other URL would run on the page).
	const MAPS_HOSTS = [ 'maps.google.com', 'www.google.com', 'google.com', 'maps.app.goo.gl' ];
	const mapsUrl = ( url ) => {
		try {
			const u = new URL( url, location.href );
			return u.protocol === 'https:' && MAPS_HOSTS.includes( u.hostname ) ? u.href : null;
		} catch {
			return null;
		}
	};

	const embed = ( el ) => {
		const src = mapsUrl( el.dataset.embed );
		if ( ! src ) return;
		const frame = Object.assign( document.createElement( 'iframe' ), {
			src, title: el.dataset.title || '', loading: 'lazy', className: 'round w-100',
		} );
		frame.style.cssText = 'border:0;height:100%;min-height:20rem';
		el.replaceChildren( frame );
	};

	// The Maps script, once, however many maps there are.
	let script = null;
	const loadMaps = ( key ) => {
		script ??= new Promise( ( resolve, reject ) => {
			window.marccentLocalMapsReady = resolve;
			const s = Object.assign( document.createElement( 'script' ), {
				src: `https://maps.googleapis.com/maps/api/js?key=${ encodeURIComponent( key ) }&v=weekly&loading=async&callback=marccentLocalMapsReady`,
				async: true,
				onerror: reject,
			} );
			document.head.append( s );
		} );
		return script;
	};

	const draw = ( el ) => {
		const key = config.key;
		if ( ! key ) return embed( el );
		// A key Google refuses (wrong site, API off) calls this instead of drawing: show the embed.
		window.gm_authFailure = () => document.querySelectorAll( '.local-map' ).forEach( embed );
		loadMaps( key ).then( () => {
			const g = window.google.maps;
			const position = { lat: Number( el.dataset.lat ), lng: Number( el.dataset.lng ) };
			el.replaceChildren();
			const map = new g.Map( el, {
				center: position, zoom: Number( el.dataset.zoom ) || 10, styles: STYLES,
				mapTypeControl: false, streetViewControl: false, fullscreenControl: false, clickableIcons: false,
			} );
			const marker = new g.Marker( { map, position, title: el.dataset.title, icon: { ...PIN, anchor: new g.Point( 12, 32 ) } } );
			const link = mapsUrl( el.dataset.link );
			if ( link ) marker.addListener( 'click', () => window.open( link, '_blank', 'noopener' ) );
		}, () => embed( el ) );
	};

	const maps = document.querySelectorAll( '.local-map' );
	if ( ! maps.length ) return;
	const io = new IntersectionObserver( ( entries ) => entries.forEach( ( e ) => {
		if ( e.isIntersecting ) {
			io.unobserve( e.target );
			draw( e.target );
		}
	} ), { rootMargin: '400px' } );
	maps.forEach( ( el ) => io.observe( el ) );
} )();
