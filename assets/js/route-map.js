/**
 * Draws the lines of route diagrams as SVG paths. A branch is one path from where it leaves the
 * route to where it comes back, so its dashes run on evenly through the curves; the stylesheet
 * alone could only piece dashed lines together stop by stop. Without this script the stylesheet
 * draws simpler lines.
 *
 * The line along the route joins the dots of its stops, and the stops of branches that carry it on
 * before its first stop or after its last one. It is dashed between two stops that not every trip
 * calls at (is-partial-after). Branches beside the route (is-detour, is-ends, is-joins) are dashed.
 */
( function () {
	const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
	const CURVE_EM = 0.8;
	const AXIS_DOTS = ':scope > .gtfs-route-map__stop > .gtfs-route-map__name > .gtfs-route-map__dot, :scope > .gtfs-route-map__extension .gtfs-route-map__dot';
	const BRANCHES_BESIDE = '.gtfs-route-map__branch:is(.is-detour, .is-ends, .is-joins)';

	function centre( element, origin ) {
		const box = element.getBoundingClientRect();

		return { x: box.left + box.width / 2 - origin.left, y: box.top + box.height / 2 - origin.top };
	}

	function addPath( svg, outline, isDashed ) {
		const path = document.createElementNS( SVG_NAMESPACE, 'path' );

		path.setAttribute( 'd', outline );
		path.classList.toggle( 'is-dashed', isDashed );
		svg.append( path );
	}

	function isDashedBetween( dot, nextDot ) {
		const onExtension = ( element ) => element.closest( '.gtfs-route-map__extension' ) !== null;

		return onExtension( dot ) || onExtension( nextDot ) || dot.closest( 'li' ).classList.contains( 'is-partial-after' );
	}

	/**
	 * Stretches between stops drawn the same way become one path, so dashes do not start over at every stop.
	 */
	function drawRoute( svg, dots, origin ) {
		const points = dots.map( ( dot ) => centre( dot, origin ) );
		let stretchStart = 0;

		for ( let index = 1; index < dots.length; index++ ) {
			const isDashed = isDashedBetween( dots[ index - 1 ], dots[ index ] );
			const nextIsDashed = index + 1 < dots.length ? isDashedBetween( dots[ index ], dots[ index + 1 ] ) : null;

			if ( nextIsDashed !== isDashed ) {
				addPath( svg, `M ${ points[ stretchStart ].x } ${ points[ stretchStart ].y } V ${ points[ index ].y }`, isDashed );
				stretchStart = index;
			}
		}
	}

	function drawBranch( svg, branch, routeX, origin, curve ) {
		const dots = branch.querySelectorAll( '.gtfs-route-map__dot' );

		if ( ! dots.length ) {
			return;
		}

		const box = branch.getBoundingClientRect();
		const top = box.top - origin.top;
		const bottom = box.bottom - origin.top;
		const first = centre( dots[ 0 ], origin );
		const last = centre( dots[ dots.length - 1 ], origin );
		const x = first.x;
		const towards = Math.sign( x - routeX ) || 1;
		const radius = Math.min( curve, Math.abs( x - routeX ), ( bottom - top ) / 2 );
		const leaveRoute = `M ${ routeX } ${ top } H ${ x - towards * radius } Q ${ x } ${ top } ${ x } ${ top + radius }`;
		const backToRoute = `V ${ bottom - radius } Q ${ x } ${ bottom } ${ x - towards * radius } ${ bottom } H ${ routeX }`;

		if ( branch.classList.contains( 'is-detour' ) ) {
			addPath( svg, `${ leaveRoute } ${ backToRoute }`, true );
		} else if ( branch.classList.contains( 'is-ends' ) ) {
			addPath( svg, `${ leaveRoute } V ${ last.y }`, true );
		} else {
			addPath( svg, `M ${ x } ${ first.y } ${ backToRoute }`, true );
		}
	}

	function draw( map, svg ) {
		const origin = svg.getBoundingClientRect();
		const routeDots = [ ...map.querySelectorAll( AXIS_DOTS ) ];

		svg.replaceChildren();

		if ( ! routeDots.length ) {
			return;
		}

		const curve = parseFloat( getComputedStyle( map ).fontSize ) * CURVE_EM;
		const routeX = centre( routeDots[ 0 ], origin ).x;

		drawRoute( svg, routeDots, origin );
		map.querySelectorAll( BRANCHES_BESIDE ).forEach( ( branch ) => drawBranch( svg, branch, routeX, origin, curve ) );
	}

	document.querySelectorAll( '.gtfs-route-map' ).forEach( ( map ) => {
		const container = map.parentElement;
		const svg = document.createElementNS( SVG_NAMESPACE, 'svg' );
		let pending = 0;

		const redraw = () => {
			cancelAnimationFrame( pending );
			pending = requestAnimationFrame( () => draw( map, svg ) );
		};

		if ( getComputedStyle( container ).position === 'static' ) {
			container.style.position = 'relative';
		}

		svg.setAttribute( 'aria-hidden', 'true' );
		svg.classList.add( 'gtfs-route-map__lines' );
		container.prepend( svg );
		map.classList.add( 'is-drawn' );

		new ResizeObserver( redraw ).observe( map );
		document.fonts?.ready.then( redraw );
		redraw();
	} );
} )();
