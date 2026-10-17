import createStorymapMap from './map';

document
	.querySelectorAll( '.storymap-map-container[data-storymap-map]' )
	.forEach( ( container ) => {
		createStorymapMap( container );
	} );
