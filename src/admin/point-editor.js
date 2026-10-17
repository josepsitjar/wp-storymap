import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import './point-editor.scss';

const mapElement = document.getElementById( 'wp-storymap-point-map' );

if ( mapElement ) {
	const markerColorPicker = document.getElementById(
		'wp-storymap-point-marker-color'
	);
	const markerColorCode = document.getElementById(
		'wp-storymap-point-marker-color-code'
	);
	const setMarkerColor = ( color ) => {
		markerColorPicker.value = color;
		markerColorCode.value = color.toUpperCase();
		mapElement.style.setProperty( '--wp-storymap-marker-color', color );
	};
	markerColorPicker.addEventListener( 'input', () =>
		setMarkerColor( markerColorPicker.value )
	);
	markerColorCode.addEventListener( 'input', () => {
		if ( /^#[0-9A-Fa-f]{6}$/.test( markerColorCode.value ) ) {
			setMarkerColor( markerColorCode.value );
		}
	} );
	document
		.querySelectorAll( '.wp-storymap-marker-color-swatch' )
		.forEach( ( swatch ) => {
			swatch.addEventListener( 'click', () =>
				setMarkerColor( swatch.dataset.color )
			);
		} );

	const initialLatitude = Number( mapElement.dataset.lat );
	const initialLongitude = Number( mapElement.dataset.lon );
	const initialZoom = Number( mapElement.dataset.zoom ) || 2;
	const hasInitialLocation =
		mapElement.dataset.lat !== '' &&
		mapElement.dataset.lon !== '' &&
		Number.isFinite( initialLatitude ) &&
		Number.isFinite( initialLongitude );
	const map = L.map( mapElement ).setView(
		hasInitialLocation ? [ initialLatitude, initialLongitude ] : [ 0, 0 ],
		initialZoom
	);
	const latitudeInput = document.getElementById( 'wp-storymap-point-lat' );
	const longitudeInput = document.getElementById( 'wp-storymap-point-lon' );
	const zoomInput = document.getElementById( 'wp-storymap-point-zoom' );
	const locationIcon = L.divIcon( {
		className: 'wp-storymap-point-location-icon',
		html: '<span></span>',
		iconSize: [ 24, 24 ],
		iconAnchor: [ 12, 12 ],
	} );
	let marker;

	L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
	} ).addTo( map );
	zoomInput.value = initialZoom;

	const updateCoordinates = ( location ) => {
		latitudeInput.value = location.lat.toFixed( 6 );
		longitudeInput.value = location.lng.toFixed( 6 );
	};

	const placeMarker = ( location ) => {
		if ( marker ) {
			marker.setLatLng( location );
			return;
		}

		marker = L.marker( location, {
			draggable: true,
			icon: locationIcon,
		} ).addTo( map );
		marker.on( 'dragend', () => updateCoordinates( marker.getLatLng() ) );
	};

	if ( hasInitialLocation ) {
		placeMarker( L.latLng( initialLatitude, initialLongitude ) );
	}

	map.on( 'click', ( event ) => {
		placeMarker( event.latlng );
		updateCoordinates( event.latlng );
	} );
	map.on( 'zoomend', () => {
		zoomInput.value = map.getZoom();
	} );

	[ latitudeInput, longitudeInput ].forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			const latitude = Number( latitudeInput.value );
			const longitude = Number( longitudeInput.value );

			if (
				latitudeInput.value !== '' &&
				longitudeInput.value !== '' &&
				latitude >= -90 &&
				latitude <= 90 &&
				longitude >= -180 &&
				longitude <= 180
			) {
				const location = L.latLng( latitude, longitude );
				placeMarker( location );
				map.panTo( location );
			}
		} );
	} );

	const selectImageButton = document.getElementById(
		'wp-storymap-point-select-image'
	);
	const removeImageButton = document.getElementById(
		'wp-storymap-point-remove-image'
	);
	const imageIdInput = document.getElementById(
		'wp-storymap-point-image-id'
	);
	const imageUrlInput = document.getElementById(
		'wp-storymap-point-image-url'
	);
	const imagePreview = document.getElementById(
		'wp-storymap-point-image-preview'
	);

	selectImageButton.addEventListener( 'click', () => {
		const frame = window.wp.media( {
			title: selectImageButton.textContent,
			library: { type: 'image' },
			button: { text: selectImageButton.textContent },
			multiple: false,
		} );

		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			imageIdInput.value = attachment.id;
			imageUrlInput.value = attachment.url;
			imagePreview.replaceChildren();

			const preview = document.createElement( 'img' );
			preview.src = attachment.sizes?.medium?.url || attachment.url;
			preview.alt = attachment.alt || '';
			imagePreview.append( preview );
			removeImageButton.hidden = false;
		} );

		frame.open();
	} );

	removeImageButton.addEventListener( 'click', () => {
		imageIdInput.value = '';
		imageUrlInput.value = '';
		imagePreview.replaceChildren();
		removeImageButton.hidden = true;
	} );
}
