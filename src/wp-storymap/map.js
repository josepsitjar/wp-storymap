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
			right: Math.min( 360, mapContainer.clientWidth * 0.38 ),
		};
	}

	return {
		bottom: Math.round( mapContainer.clientHeight * 0.42 ) + 24,
	};
}

class StorymapOverviewControl {
	constructor( coordinates ) {
		this.coordinates = coordinates;
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
					features: this.coordinates.map( ( coordinate ) => ( {
						type: 'Feature',
						properties: {},
						geometry: {
							type: 'Point',
							coordinates: coordinate,
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
					'circle-color': '#e53935',
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

		this.currentIndex = index;
		this.previousButton.disabled = false;
		this.nextButton.disabled = index === this.points.length - 1;
		this.onSelectPoint( point );
		this.map.flyTo( {
			center: [ point.longitude, point.latitude ],
			zoom: Math.max( 12, point.zoom ),
			padding: getInfoPanelPadding( this.map ),
		} );
	}

	showOverview() {
		this.currentIndex = -1;
		this.previousButton.disabled = true;
		this.nextButton.disabled = false;
		this.onShowOverview();

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

function createPointInfoPanel( map, container, intro ) {
	const panel = document.createElement( 'section' );
	panel.className = 'storymap-point-info';
	panel.setAttribute( 'aria-live', 'polite' );
	panel.hidden = false;

	const fallbackContainer = document.createElement( 'div' );
	fallbackContainer.className = 'storymap-point-info-fallback';
	fallbackContainer.hidden = true;
	container.after( fallbackContainer );

	let activeCoordinates = null;
	let scheduledPosition = false;

	const positionPanel = () => {
		scheduledPosition = false;
		if ( panel.hidden ) {
			return;
		}

		if ( panel.parentElement !== container ) {
			fallbackContainer.hidden = true;
			container.append( panel );
		}

		const mapBounds = map.getContainer().getBoundingClientRect();
		panel.classList.remove(
			'storymap-point-info--left',
			'storymap-point-info--right',
			'storymap-point-info--top',
			'storymap-point-info--bottom',
			'storymap-point-info--desktop'
		);
		if ( mapBounds.width > 680 ) {
			const panelWidth = Math.min( 360, mapBounds.width * 0.38 );
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
			panel.style.transform = 'none';
			return;
		}

		container.classList.remove( 'storymap-has-point-info' );
		panel.style.left = '12px';
		panel.style.right = 'auto';
		panel.style.top = '12px';
		panel.style.bottom = 'auto';
		panel.style.transform = 'none';

		const panelBounds = panel.getBoundingClientRect();
		if ( ! activeCoordinates ) {
			const top = mapBounds.height - panelBounds.height;
			panel.style.left = '0';
			panel.style.top = `${ top }px`;
			return;
		}

		const markerPoint = map.project( activeCoordinates );
		const gap = 24;
		const candidates =
			mapBounds.width <= 680
				? [
						{
							name: 'bottom',
							left: 0,
							top: mapBounds.height - panelBounds.height,
						},
						{
							name: 'top',
							left: 0,
							top: 0,
						},
					]
				: [
						{
							name: 'left',
							left: 12,
							top: ( mapBounds.height - panelBounds.height ) / 2,
						},
						{
							name: 'right',
							left: mapBounds.width - panelBounds.width - 12,
							top: ( mapBounds.height - panelBounds.height ) / 2,
						},
						{
							name: 'top',
							left: ( mapBounds.width - panelBounds.width ) / 2,
							top: 12,
						},
						{
							name: 'bottom',
							left: ( mapBounds.width - panelBounds.width ) / 2,
							top: mapBounds.height - panelBounds.height - 12,
						},
					];

		const availablePosition = candidates.find(
			( candidate ) =>
				markerPoint.x < candidate.left - gap ||
				markerPoint.x > candidate.left + panelBounds.width + gap ||
				markerPoint.y < candidate.top - gap ||
				markerPoint.y > candidate.top + panelBounds.height + gap
		);

		if ( ! availablePosition ) {
			fallbackContainer.append( panel );
			fallbackContainer.hidden = false;
			return;
		}

		panel.classList.add(
			`storymap-point-info--${ availablePosition.name }`
		);
		panel.style.left = `${ availablePosition.left }px`;
		panel.style.top = `${ availablePosition.top }px`;
	};

	const schedulePosition = () => {
		if ( scheduledPosition ) {
			return;
		}

		scheduledPosition = true;
		window.requestAnimationFrame( positionPanel );
	};

	const render = ( point, coordinates ) => {
		panel.replaceChildren();
		panel.hidden = false;
		activeCoordinates = coordinates || null;

		const title = document.createElement( 'h2' );
		title.className = 'storymap-point-info-title';
		title.textContent = point.title || '';
		panel.append( title );

		if ( point.image ) {
			const image = document.createElement( 'img' );
			image.className = 'storymap-point-info-image';
			image.src = point.image;
			image.alt = point.title || '';
			image.addEventListener( 'load', schedulePosition, { once: true } );
			panel.append( image );
		}

		if ( point.description ) {
			const description = document.createElement( 'p' );
			description.className = 'storymap-point-info-description';
			description.textContent = point.description;
			panel.append( description );
		}

		schedulePosition();
	};

	const renderStory = () => {
		render( intro, null );
	};

	const renderPoint = ( point ) => {
		render( point, [ point.longitude, point.latitude ] );
	};

	const clear = () => {
		renderStory();
	};

	map.on( 'move', schedulePosition );
	map.on( 'resize', schedulePosition );
	new window.ResizeObserver( () => map.resize() ).observe( container );
	renderStory();
	return { panel, render: renderPoint, clear };
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
	const pointInfo = createPointInfoPanel( map, container, intro );
	const navigationControl = new StorymapNavigationControl(
		points,
		bounds,
		pointInfo.render,
		pointInfo.clear
	);

	points.forEach( ( point, index ) => {
		const marker = new Marker()
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
		map.addControl(
			new StorymapOverviewControl( coordinates ),
			'bottom-left'
		);
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
