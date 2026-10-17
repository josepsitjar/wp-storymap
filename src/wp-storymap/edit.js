import { useEffect, useRef } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import createStorymapMap from './map';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const containerRef = useRef( null );
	const mapRef = useRef( null );
	const { stories, isResolving } = useSelect( ( select ) => {
		const query = {
			per_page: 100,
			orderby: 'title',
			order: 'asc',
			status: [ 'publish', 'future', 'draft', 'pending', 'private' ],
			_fields: 'id,title,meta',
		};
		const core = select( 'core' );

		return {
			stories: core.getEntityRecords( 'postType', 'wp_storymap', query ),
			isResolving: core.isResolving( 'core', 'getEntityRecords', [
				'postType',
				'wp_storymap',
				query,
			] ),
		};
	}, [] );
	const availableStories = ( stories || [] ).filter(
		( story ) => Number( story.meta?.wp_storymap_story_number ) > 0
	);
	const selectedStory = availableStories.find(
		( story ) =>
			Number( story.meta.wp_storymap_story_number ) ===
			Number( attributes.storyNumber )
	);
	const storyMeta = selectedStory?.meta || {};
	const height = Math.max( 1, Number( storyMeta.wp_storymap_height ) || 500 );
	const width = Math.max( 0, Number( storyMeta.wp_storymap_width ) || 0 );
	const isFullWidth = width === 0;
	const containerClasses = [ 'storymap-map-container' ];
	if ( isFullWidth && ! attributes.align ) {
		containerClasses.push( 'alignfull' );
	}
	if ( ! isFullWidth ) {
		containerClasses.push( 'storymap-map-container--custom-width' );
	}
	const storyOptions = [
		{ label: __( 'Select a Storymap', 'wp-storymap' ), value: 0 },
		...availableStories.map( ( story ) => ( {
			label: sprintf(
				/* translators: 1: Storymap title, 2: assigned story number. */
				__( '%1$s (story %2$d)', 'wp-storymap' ),
				story.title?.rendered || __( '(untitled)', 'wp-storymap' ),
				Number( story.meta.wp_storymap_story_number )
			),
			value: Number( story.meta.wp_storymap_story_number ),
		} ) ),
	];

	useEffect( () => {
		if ( ! containerRef.current ) {
			return undefined;
		}

		mapRef.current = createStorymapMap( containerRef.current );

		return () => {
			mapRef.current?.remove();
			mapRef.current = null;
		};
	}, [] );

	useEffect( () => {
		mapRef.current?.resize();
	}, [ height, width ] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Storymap', 'wp-storymap' ) }>
					<SelectControl
						label={ __( 'Storymap', 'wp-storymap' ) }
						value={ String( attributes.storyNumber || 0 ) }
						options={ storyOptions.map( ( option ) => ( {
							...option,
							value: String( option.value ),
						} ) ) }
						disabled={
							isResolving || availableStories.length === 0
						}
						onChange={ ( value ) =>
							setAttributes( {
								storyNumber: Number.parseInt( value, 10 ) || 0,
							} )
						}
					/>
					{ isResolving && <Spinner /> }
					{ ! isResolving && availableStories.length === 0 && (
						<p>
							{ __(
								'Create a Storymap first to select it for this map.',
								'wp-storymap'
							) }
						</p>
					) }
				</PanelBody>
			</InspectorControls>
			<div
				{ ...useBlockProps( {
					className: containerClasses.join( ' ' ),
					ref: containerRef,
					'data-story-number': attributes.storyNumber || 0,
					style: {
						height: `${ height }px`,
						...( isFullWidth
							? {}
							: { width: `${ width }px`, maxWidth: 'none' } ),
					},
					role: 'region',
					'aria-label': __( 'OpenStreetMap map', 'wp-storymap' ),
				} ) }
			/>
		</>
	);
}
