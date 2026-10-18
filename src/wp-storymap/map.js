import {
	config,
	LngLatBounds,
	Map as MapLibreMap,
	Marker,
	NavigationControl,
} from 'maplibre-gl';
import { __ } from '@wordpress/i18n';

config.WORKER_URL = new URL(
	'maplibre-gl/dist/maplibre-gl-worker.mjs',
	import.meta.url
).href;

const OSM_STYLE = {
	version: 8,
	sources: {
		openstreetmap: {
			type: 'raster',
			tiles: [ 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' ],
			tileSize: 256,
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		},
	},
	layers: [
		{
			id: 'openstreetmap',
			type: 'raster',
			source: 'openstreetmap',
		},
	],
};

function getInfoPanelPadding( map ) {
	const mapContainer = map.getContainer();

	if ( mapContainer.clientWidth > 680 ) {
		return {
			right: Math.min( 720, mapContainer.clientWidth * 0.76 ),
		};
	}

	return {
		bottom: Math.round( mapContainer.clientHeight * 0.42 ) + 24,
	};
}

class StorymapOverviewControl {
	constructor( points ) {
		this.points = points;
		this.coordinates = points.map( ( point ) => [
			point.longitude,
			point.latitude,
		] );
	}

	onAdd() {
		this.container = document.createElement( 'div' );
		this.container.className = 'maplibregl-ctrl storymap-overview-control';

		const mapContainer = document.createElement( 'div' );
		mapContainer.className = 'storymap-overview-map';
		mapContainer.setAttribute( 'role', 'img' );
		mapContainer.setAttribute(
			'aria-label',
			__( 'Overview map showing all story points', 'wp-storymap' )
		);
		this.container.append( mapContainer );

		this.overviewMap = new MapLibreMap( {
			container: mapContainer,
			style: OSM_STYLE,
			center: this.coordinates[ 0 ],
			zoom: 2,
			interactive: false,
			attributionControl: false,
		} );

		this.overviewMap.once( 'load', () => {
			if ( this.coordinates.length > 1 ) {
				this.overviewMap.addSource( 'storymap-overview-route', {
					type: 'geojson',
					data: {
						type: 'Feature',
						properties: {},
						geometry: {
							type: 'LineString',
							coordinates: this.coordinates,
						},
					},
				} );
				this.overviewMap.addLayer( {
					id: 'storymap-overview-route',
					type: 'line',
					source: 'storymap-overview-route',
					paint: {
						'line-color': '#e53935',
						'line-width': 2,
						'line-dasharray': [ 2, 2 ],
					},
				} );
			}

			this.overviewMap.addSource( 'storymap-overview-points', {
				type: 'geojson',
				data: {
					type: 'FeatureCollection',
					features: this.points.map( ( point ) => ( {
						type: 'Feature',
						properties: {
							color: point.color || '#3FB1CE',
						},
						geometry: {
							type: 'Point',
							coordinates: [ point.longitude, point.latitude ],
						},
					} ) ),
				},
			} );
			this.overviewMap.addLayer( {
				id: 'storymap-overview-points',
				type: 'circle',
				source: 'storymap-overview-points',
				paint: {
					'circle-radius': 4,
					'circle-color': [ 'get', 'color' ],
					'circle-stroke-color': '#ffffff',
					'circle-stroke-width': 1,
				},
			} );

			if ( this.coordinates.length === 1 ) {
				this.overviewMap.jumpTo( {
					center: this.coordinates[ 0 ],
					zoom: 7,
				} );
				return;
			}

			const bounds = new LngLatBounds();
			this.coordinates.forEach( ( coordinate ) =>
				bounds.extend( coordinate )
			);
			this.overviewMap.fitBounds( bounds, {
				padding: 18,
				maxZoom: 10,
			} );
		} );

		return this.container;
	}

	onRemove() {
		this.overviewMap.remove();
		this.container.remove();
	}
}

class StorymapNavigationControl {
	constructor( points, bounds, onSelectPoint, onShowOverview ) {
		this.points = points;
		this.bounds = bounds;
		this.onSelectPoint = onSelectPoint;
		this.onShowOverview = onShowOverview;
		this.currentIndex = -1;
	}

	onAdd( map ) {
		this.map = map;
		this.container = document.createElement( 'div' );
		this.container.className = 'storymap-navigation-control';
		this.container.setAttribute( 'role', 'group' );
		this.container.setAttribute(
			'aria-label',
			__( 'Storymap point navigation', 'wp-storymap' )
		);

		this.previousButton = this.createButton(
			'storymap-navigation-previous',
			__( 'Previous point', 'wp-storymap' ),
			-1
		);
		this.nextButton = this.createButton(
			'storymap-navigation-next',
			__( 'Next point', 'wp-storymap' ),
			1
		);
		this.container.append( this.previousButton, this.nextButton );
		this.previousButton.disabled = true;

		return this.container;
	}

	createButton( className, label, direction ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = `storymap-navigation-button ${ className }`;
		button.setAttribute( 'aria-label', label );
		button.addEventListener( 'click', () => {
			this.navigate( direction );
		} );
		return button;
	}

	navigate( direction ) {
		const nextIndex = this.currentIndex + direction;

		if ( nextIndex < -1 || nextIndex >= this.points.length ) {
			return;
		}

		if ( nextIndex === -1 ) {
			this.showOverview();
			return;
		}

		this.goToPoint( nextIndex );
	}

	goToPoint( index ) {
		const point = this.points[ index ];
		if ( ! point ) {
			return;
		}

		const direction = Math.sign( index - this.currentIndex ) || 1;
		this.currentIndex = index;
		this.updateButtons();
		this.onSelectPoint( index, direction );
		this.focusPoint( point );
	}

	showOverview() {
		const direction = Math.sign( -1 - this.currentIndex ) || -1;
		this.currentIndex = -1;
		this.updateButtons();
		this.onShowOverview( direction );

		this.focusOverview();
	}

	setActiveIndex( index ) {
		if (
			index < -1 ||
			index >= this.points.length ||
			index === this.currentIndex
		) {
			return;
		}

		this.currentIndex = index;
		this.updateButtons();

		if ( index === -1 ) {
			this.focusOverview();
			return;
		}

		this.focusPoint( this.points[ index ] );
	}

	updateButtons() {
		this.previousButton.disabled = this.currentIndex === -1;
		this.nextButton.disabled = this.currentIndex === this.points.length - 1;
	}

	focusPoint( point ) {
		this.map.flyTo( {
			center: [ point.longitude, point.latitude ],
			zoom: Math.max( 12, point.zoom ),
			padding: getInfoPanelPadding( this.map ),
		} );
	}

	focusOverview() {
		if ( this.points.length === 1 ) {
			this.map.flyTo( {
				center: [
					this.points[ 0 ].longitude,
					this.points[ 0 ].latitude,
				],
				zoom: Math.max( 12, this.points[ 0 ].zoom ),
				padding: getInfoPanelPadding( this.map ),
			} );
			return;
		}

		const panelPadding = getInfoPanelPadding( this.map );
		this.map.fitBounds( this.bounds, {
			padding: {
				top: 48,
				right: panelPadding.right || 48,
				bottom: panelPadding.bottom || 48,
				left: 48,
			},
			maxZoom: 12,
			duration: 1000,
		} );
	}

	onRemove() {
		this.container.remove();
	}
}

let pointInfoPanelId = 0;

function createPointInfoPanel( map, container, intro, points ) {
	pointInfoPanelId += 1;
	const panel = document.createElement( 'section' );
	panel.id = `wp-storymap-point-info-${ pointInfoPanelId }`;
	panel.className = 'storymap-point-info';
	panel.setAttribute( 'aria-label', __( 'Story content', 'wp-storymap' ) );

	const createSection = ( index, point, isIntro = false ) => {
		const section = document.createElement( 'article' );
		section.className = 'storymap-point-info-section';
		section.dataset.storymapIndex = `${ index }`;

		const title = document.createElement( 'h2' );
		title.className = 'storymap-point-info-title';
		title.dataset.storymapTitleIndex = `${ index }`;
		title.textContent = point.title || '';
		section.append( title );

		if ( point.image ) {
			const image = document.createElement( 'img' );
			image.className = 'storymap-point-info-image';
			image.src = point.image;
			image.alt = point.title || '';
			section.append( image );
		}

		if ( point.description ) {
			const description = document.createElement( 'p' );
			description.className = 'storymap-point-info-description';
			description.textContent = point.description;
			section.append( description );
		}

		if ( ! isIntro ) {
			section.classList.add( 'storymap-point-info-point' );
		}

		panel.append( section );
		return section;
	};

	const sections = [
		createSection( -1, intro, true ),
		...points.map( ( point, index ) => createSection( index, point ) ),
	];
	const titles = sections.map( ( section ) =>
		section.querySelector( '[data-storymap-title-index]' )
	);

	const scrollbar = document.createElement( 'div' );
	scrollbar.className = 'storymap-point-info-scrollbar';
	scrollbar.setAttribute( 'role', 'scrollbar' );
	scrollbar.setAttribute(
		'aria-label',
		__( 'Story panel scroll', 'wp-storymap' )
	);
	scrollbar.setAttribute( 'aria-controls', panel.id );
	scrollbar.setAttribute( 'aria-orientation', 'vertical' );
	scrollbar.setAttribute( 'aria-valuemin', '0' );
	scrollbar.setAttribute( 'aria-valuenow', '0' );
	scrollbar.setAttribute( 'aria-valuemax', '0' );
	scrollbar.tabIndex = 0;

	const scrollbarThumb = document.createElement( 'span' );
	scrollbarThumb.className = 'storymap-point-info-scrollbar-thumb';
	scrollbar.append( scrollbarThumb );
	panel.append( scrollbar );
	container.append( panel );

	const updateScrollbar = () => {
		const maxScroll = panel.scrollHeight - panel.clientHeight;
		const isScrollable = maxScroll > 1 && panel.clientHeight > 0;
		scrollbar.hidden = ! isScrollable;
		if ( scrollbar.hidden ) {
			return;
		}

		const trackHeight = Math.max( 0, panel.clientHeight - 16 );
		const thumbHeight = Math.min(
			trackHeight,
			Math.max(
				28,
				trackHeight * ( panel.clientHeight / panel.scrollHeight )
			)
		);
		const thumbTravel = Math.max( 0, trackHeight - thumbHeight );
		const thumbTop =
			maxScroll > 0 ? ( panel.scrollTop / maxScroll ) * thumbTravel : 0;

		scrollbar.style.left = `${ panel.clientWidth - 14 }px`;
		scrollbar.style.top = '8px';
		scrollbar.style.height = `${ trackHeight }px`;
		scrollbarThumb.style.height = `${ thumbHeight }px`;
		scrollbarThumb.style.transform = `translateY(${ thumbTop }px)`;
		scrollbar.setAttribute( 'aria-valuemax', `${ maxScroll }` );
		scrollbar.setAttribute(
			'aria-valuenow',
			`${ Math.round( panel.scrollTop ) }`
		);
	};

	let scrollbarDrag = null;
	scrollbar.addEventListener( 'pointerdown', ( event ) => {
		if ( scrollbar.hidden || event.button !== 0 ) {
			return;
		}

		event.preventDefault();
		const trackBounds = scrollbar.getBoundingClientRect();
		const maxScroll = panel.scrollHeight - panel.clientHeight;
		const thumbTravel = trackBounds.height - scrollbarThumb.offsetHeight;
		if ( event.target !== scrollbarThumb ) {
			const position = Math.max(
				0,
				Math.min(
					thumbTravel,
					event.clientY -
						trackBounds.top -
						scrollbarThumb.offsetHeight / 2
				)
			);
			panel.scrollTop =
				thumbTravel > 0 ? ( position / thumbTravel ) * maxScroll : 0;
			updateScrollbar();
		}

		scrollbarDrag = {
			pointerY: event.clientY,
			scrollTop: panel.scrollTop,
			thumbTravel,
			maxScroll,
		};
		scrollbar.setPointerCapture( event.pointerId );
	} );
	scrollbar.addEventListener( 'pointermove', ( event ) => {
		if ( ! scrollbarDrag ) {
			return;
		}

		const scrollRatio =
			scrollbarDrag.thumbTravel > 0
				? ( event.clientY - scrollbarDrag.pointerY ) /
					scrollbarDrag.thumbTravel
				: 0;
		panel.scrollTop =
			scrollbarDrag.scrollTop + scrollRatio * scrollbarDrag.maxScroll;
		updateScrollbar();
	} );
	scrollbar.addEventListener( 'pointerup', () => {
		scrollbarDrag = null;
	} );
	scrollbar.addEventListener( 'pointercancel', () => {
		scrollbarDrag = null;
	} );
	scrollbar.addEventListener( 'keydown', ( event ) => {
		const pageStep = panel.clientHeight * 0.9;
		const keySteps = {
			ArrowDown: 40,
			ArrowUp: -40,
			PageDown: pageStep,
			PageUp: -pageStep,
			Home: -panel.scrollTop,
			End: panel.scrollHeight - panel.scrollTop,
		};
		if ( ! Object.prototype.hasOwnProperty.call( keySteps, event.key ) ) {
			return;
		}

		event.preventDefault();
		panel.scrollTop += keySteps[ event.key ];
		updateScrollbar();
	} );

	let activeIndex = -1;
	let lastScrollTop = panel.scrollTop;
	let onActiveIndexChange = () => {};
	const setActiveSection = ( index, direction ) => {
		if ( index < -1 || index >= sections.length - 1 ) {
			return;
		}

		const previousSection = sections[ activeIndex + 1 ];
		previousSection?.classList.remove(
			'storymap-point-info-section--active'
		);
		previousSection?.removeAttribute( 'aria-current' );
		activeIndex = index;

		const section = sections[ index + 1 ];
		section.classList.add( 'storymap-point-info-section--active' );
		section.setAttribute( 'aria-current', 'location' );
		section.dataset.slideDirection = direction < 0 ? 'backward' : 'forward';
	};

	const scrollToSection = ( index, direction ) => {
		const section = sections[ index + 1 ];
		const title = titles[ index + 1 ];
		setActiveSection( index, direction );

		const panelTop = panel.getBoundingClientRect().top;
		const titleTop = title.getBoundingClientRect().top;
		const targetTop = Math.max(
			0,
			panel.scrollTop + titleTop - panelTop - 32
		);
		panel.scrollTo( {
			top: targetTop,
			behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' )
				.matches
				? 'auto'
				: 'smooth',
		} );
		return section;
	};

	let scrollFrame = 0;
	panel.addEventListener( 'scroll', () => {
		updateScrollbar();
		if ( scrollFrame ) {
			return;
		}

		scrollFrame = window.requestAnimationFrame( () => {
			scrollFrame = 0;
			const scrollTop = panel.scrollTop;
			const delta = scrollTop - lastScrollTop;
			if ( Math.abs( delta ) < 1 ) {
				return;
			}

			lastScrollTop = scrollTop;
			const panelBounds = panel.getBoundingClientRect();
			const crossedTitles = titles.filter( ( title ) => {
				const bounds = title.getBoundingClientRect();
				return delta > 0
					? bounds.top < panelBounds.bottom &&
							bounds.top + delta >= panelBounds.bottom
					: bounds.bottom > panelBounds.top &&
							bounds.bottom + delta <= panelBounds.top;
			} );

			if ( ! crossedTitles.length ) {
				return;
			}

			const title =
				delta > 0
					? crossedTitles[ crossedTitles.length - 1 ]
					: crossedTitles[ 0 ];
			const index = Number( title.dataset.storymapTitleIndex );
			setActiveSection( index, delta );
			onActiveIndexChange( index );
		} );
	} );

	const positionPanel = () => {
		const mapBounds = map.getContainer().getBoundingClientRect();
		if ( mapBounds.width > 680 ) {
			const panelWidth = Math.min( 720, mapBounds.width * 0.76 );
			container.style.setProperty(
				'--storymap-panel-width',
				`${ panelWidth }px`
			);
			container.classList.add( 'storymap-has-point-info' );
			panel.classList.add( 'storymap-point-info--desktop' );
			panel.style.left = 'auto';
			panel.style.right = '0';
			panel.style.top = '0';
			panel.style.bottom = '0';
		} else {
			container.classList.remove( 'storymap-has-point-info' );
			panel.classList.remove( 'storymap-point-info--desktop' );
			panel.style.left = '0';
			panel.style.right = '0';
			panel.style.top = 'auto';
			panel.style.bottom = '0';
		}

		updateScrollbar();
	};

	map.on( 'resize', positionPanel );
	new window.ResizeObserver( () => {
		map.resize();
		positionPanel();
	} ).observe( container );
	positionPanel();
	sections[ 0 ].classList.add( 'storymap-point-info-section--active' );
	sections[ 0 ].setAttribute( 'aria-current', 'location' );

	return {
		panel,
		scrollToPoint: ( index, direction ) =>
			scrollToSection( index, direction ),
		scrollToIntro: ( direction ) => scrollToSection( -1, direction ),
		setOnActiveIndexChange: ( callback ) => {
			onActiveIndexChange = callback;
		},
	};
}

export default function createStorymapMap( container ) {
	const map = new MapLibreMap( {
		container,
		style: OSM_STYLE,
		center: [ 0, 0 ],
		zoom: 2,
	} );
	map.addControl(
		new NavigationControl( { showCompass: false } ),
		'top-left'
	);
	const points = JSON.parse( container.dataset.storymapPoints || '[]' );
	const intro = JSON.parse( container.dataset.storymapIntro || '{}' );
	const coordinates = points.map( ( point ) => [
		point.longitude,
		point.latitude,
	] );
	const bounds = new LngLatBounds();
	coordinates.forEach( ( coordinate ) => bounds.extend( coordinate ) );
	const pointInfo = createPointInfoPanel( map, container, intro, points );
	const navigationControl = new StorymapNavigationControl(
		points,
		bounds,
		( index, direction ) => pointInfo.scrollToPoint( index, direction ),
		( direction ) => pointInfo.scrollToIntro( direction )
	);
	pointInfo.setOnActiveIndexChange( ( index ) =>
		navigationControl.setActiveIndex( index )
	);

	points.forEach( ( point, index ) => {
		const marker = new Marker( { color: point.color || '#3FB1CE' } )
			.setLngLat( [ point.longitude, point.latitude ] )
			.addTo( map );
		marker.getElement().addEventListener( 'click', () => {
			navigationControl.goToPoint( index );
		} );
	} );

	if ( coordinates.length > 1 ) {
		map.once( 'load', () => {
			map.addSource( 'storymap-route', {
				type: 'geojson',
				data: {
					type: 'Feature',
					properties: {},
					geometry: {
						type: 'LineString',
						coordinates,
					},
				},
			} );
			map.addLayer( {
				id: 'storymap-route',
				type: 'line',
				source: 'storymap-route',
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				paint: {
					'line-color': '#e53935',
					'line-width': 3,
					'line-dasharray': [ 2, 2 ],
				},
			} );
		} );
	}

	if ( coordinates.length ) {
		map.addControl( new StorymapOverviewControl( points ), 'bottom-left' );
		container.append( navigationControl.onAdd( map ) );

		map.once( 'load', () => {
			if ( coordinates.length === 1 ) {
				map.flyTo( {
					center: coordinates[ 0 ],
					zoom: Math.max( 12, points[ 0 ].zoom ),
					padding: getInfoPanelPadding( map ),
				} );
				return;
			}

			const panelPadding = getInfoPanelPadding( map );
			map.fitBounds( bounds, {
				padding: {
					top: 48,
					right: panelPadding.right || 48,
					bottom: panelPadding.bottom || 48,
					left: 48,
				},
				maxZoom: 12,
			} );
		} );
	}

	return map;
}
