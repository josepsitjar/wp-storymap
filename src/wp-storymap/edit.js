import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import createStorymapMap from './map';
import './editor.scss';

function getStoryTitle( title ) {
	if ( typeof title === 'string' ) {
		return title;
	}

	return title?.raw ?? title?.rendered ?? '';
}

export default function Edit( { attributes, setAttributes } ) {
	const containerRef = useRef( null );
	const mapRef = useRef( null );
	const updateIntroRef = useRef( null );
	const [ liveDescription, setLiveDescription ] = useState( null );
	const {
		stories,
		isResolving,
		currentStory,
		currentPostType,
		currentPostId,
		currentPostTitle,
	} = useSelect( ( select ) => {
		const query = {
			per_page: 100,
			orderby: 'title',
			order: 'asc',
			status: [ 'publish', 'future', 'draft', 'pending', 'private' ],
			_fields: 'id,title,meta',
		};
		const core = select( 'core' );
		const editor = select( 'core/editor' );
		const postType = editor.getCurrentPostType();
		const postId = editor.getCurrentPostId();

		return {
			stories: core.getEntityRecords( 'postType', 'wp_storymap', query ),
			currentPostType: postType,
			currentPostId: postId,
			currentPostTitle: editor.getEditedPostAttribute( 'title' ),
			currentStory:
				postType === 'wp_storymap'
					? core.getEditedEntityRecord(
							'postType',
							'wp_storymap',
							postId
						)
					: null,
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
	const selectedStoryId = selectedStory?.id;
	const selectedStoryRecord = useSelect(
		( select ) =>
			selectedStoryId
				? select( 'core' ).getEditedEntityRecord(
						'postType',
						'wp_storymap',
						selectedStoryId
					)
				: null,
		[ selectedStoryId ]
	);
	const storyMeta =
		selectedStoryRecord?.meta ||
		selectedStory?.meta ||
		( attributes.storyNumber ? {} : currentStory?.meta ) ||
		{};
	const coverImageId = Number( storyMeta.wp_storymap_image ) || 0;
	const coverImage = useSelect(
		( select ) =>
			coverImageId ? select( 'core' ).getMedia( coverImageId ) : null,
		[ coverImageId ]
	);
	let storyTitle = '';
	if ( selectedStoryId ) {
		storyTitle = getStoryTitle(
			selectedStoryRecord?.title ?? selectedStory?.title
		);
	} else if ( currentPostType === 'wp_storymap' ) {
		storyTitle = getStoryTitle( currentPostTitle );
	}
	const descriptionPostId =
		currentPostType === 'wp_storymap' &&
		( ! selectedStoryId || selectedStoryId === currentPostId )
			? currentPostId
			: 0;
	const description =
		liveDescription?.postId === descriptionPostId
			? liveDescription.value
			: storyMeta.wp_storymap_description || '';
	const storyIntro = useMemo(
		() => ( {
			title: storyTitle,
			description,
			image:
				coverImage?.media_details?.sizes?.large?.source_url ||
				coverImage?.source_url ||
				'',
		} ),
		[ storyTitle, description, coverImage ]
	);
	const height = Math.max( 1, Number( storyMeta.wp_storymap_height ) || 500 );
	const width = Math.max( 0, Number( storyMeta.wp_storymap_width ) || 0 );
	const basemap = storyMeta.wp_storymap_basemap || 'osm';
	const arrowColor = storyMeta.wp_storymap_arrow_color || '#000000';
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
		if ( ! descriptionPostId ) {
			setLiveDescription( null );
			return undefined;
		}

		let activeEditor = null;
		let activeEditorListener = null;
		const updateDescription = ( editor ) => {
			setLiveDescription( {
				postId: descriptionPostId,
				value: editor.getContent(),
			} );
		};
		const attachEditor = ( event ) => {
			const editor =
				event?.editor ||
				window.tinymce?.get( 'wp_storymap_description' );
			if (
				! editor ||
				editor.id !== 'wp_storymap_description' ||
				activeEditor === editor
			) {
				return;
			}

			activeEditor = editor;
			updateDescription( editor );
			activeEditorListener = () => updateDescription( editor );
			editor.on(
				'change input keyup undo redo SetContent',
				activeEditorListener
			);
		};
		const textarea = document.getElementById( 'wp_storymap_description' );
		const updateTextarea = () => {
			if ( textarea ) {
				setLiveDescription( {
					postId: descriptionPostId,
					value: textarea.value,
				} );
			}
		};

		attachEditor();
		window.tinymce?.on( 'AddEditor', attachEditor );
		textarea?.addEventListener( 'input', updateTextarea );

		return () => {
			window.tinymce?.off( 'AddEditor', attachEditor );
			textarea?.removeEventListener( 'input', updateTextarea );
			activeEditor?.off(
				'change input keyup undo redo SetContent',
				activeEditorListener
			);
		};
	}, [ descriptionPostId ] );

	useEffect( () => {
		if ( ! containerRef.current ) {
			return undefined;
		}

		mapRef.current = createStorymapMap(
			containerRef.current,
			( updateIntro ) => {
				updateIntroRef.current = updateIntro;
			}
		);

		return () => {
			mapRef.current?.remove();
			mapRef.current = null;
			updateIntroRef.current = null;
		};
	}, [ basemap ] );

	useEffect( () => {
		updateIntroRef.current?.( storyIntro );
	}, [ storyIntro ] );

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
					'data-storymap-basemap': basemap,
					'data-storymap-intro': JSON.stringify( storyIntro ),
					style: {
						height: `${ height }px`,
						'--storymap-arrow-color': arrowColor,
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
