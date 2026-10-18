import {
	Button,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

function StorymapDocumentSettings() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		'wp_storymap',
		'meta'
	);
	const storyMeta = meta || {};
	const coverImageId = Number( storyMeta.wp_storymap_image ) || 0;
	const coverImage = useSelect(
		( select ) =>
			coverImageId ? select( 'core' ).getMedia( coverImageId ) : null,
		[ coverImageId ]
	);

	if ( postType !== 'wp_storymap' ) {
		return null;
	}

	const updateMeta = ( key, value ) => {
		setMeta( { ...storyMeta, [ key ]: value } );
	};

	const updateDimension = ( key, value ) => {
		const dimension = Math.max( 1, Number.parseInt( value, 10 ) || 1 );
		updateMeta( key, dimension );
	};

	const updateWidth = ( value ) => {
		const width =
			value === '' ? 0 : Math.max( 1, Number.parseInt( value, 10 ) || 1 );
		updateMeta( 'wp_storymap_width', width );
	};

	return (
		<PluginDocumentSettingPanel
			name="wp-storymap-settings"
			title={ __( 'Storymap settings', 'wp-storymap' ) }
		>
			<TextareaControl
				label={ __( 'Story description', 'wp-storymap' ) }
				value={ storyMeta.wp_storymap_description || '' }
				onChange={ ( value ) =>
					updateMeta( 'wp_storymap_description', value )
				}
			/>
			{ coverImage?.source_url && (
				<img
					src={ coverImage.source_url }
					alt={ coverImage.alt_text || '' }
					style={ {
						display: 'block',
						height: 'auto',
						maxWidth: '100%',
					} }
				/>
			) }
			<MediaUploadCheck>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ coverImageId }
					onSelect={ ( media ) =>
						updateMeta( 'wp_storymap_image', media.id )
					}
					render={ ( { open } ) => (
						<Button variant="secondary" onClick={ open }>
							{ coverImageId
								? __( 'Replace story image', 'wp-storymap' )
								: __( 'Choose story image', 'wp-storymap' ) }
						</Button>
					) }
				/>
			</MediaUploadCheck>
			<p>{ __( 'Story image (optional).', 'wp-storymap' ) }</p>
			{ coverImageId > 0 && (
				<Button
					variant="tertiary"
					isDestructive
					onClick={ () => updateMeta( 'wp_storymap_image', 0 ) }
				>
					{ __( 'Remove story image', 'wp-storymap' ) }
				</Button>
			) }
			<SelectControl
				label={ __( 'Basemap', 'wp-storymap' ) }
				value={ storyMeta.wp_storymap_basemap || 'osm' }
				options={ [
					{
						label: __( 'OpenStreetMap', 'wp-storymap' ),
						value: 'osm',
					},
					{
						label: __( 'Satellite', 'wp-storymap' ),
						value: 'satellite',
					},
					{ label: __( 'Hybrid', 'wp-storymap' ), value: 'hybrid' },
					{ label: __( 'Relief', 'wp-storymap' ), value: 'relief' },
					{
						label: __( 'Cycling map', 'wp-storymap' ),
						value: 'cyclemap',
					},
					{
						label: __( 'World Street Map', 'wp-storymap' ),
						value: 'worldstreetmap',
					},
				] }
				onChange={ ( value ) =>
					updateMeta( 'wp_storymap_basemap', value )
				}
			/>
			<TextControl
				label={ __( 'Map height (px)', 'wp-storymap' ) }
				type="number"
				min="1"
				step="1"
				value={ storyMeta.wp_storymap_height || 500 }
				onChange={ ( value ) =>
					updateDimension( 'wp_storymap_height', value )
				}
			/>
			<TextControl
				label={ __( 'Map width (px)', 'wp-storymap' ) }
				type="number"
				min="0"
				step="1"
				value={ storyMeta.wp_storymap_width || '' }
				help={ __(
					'Leave empty to use the full screen width.',
					'wp-storymap'
				) }
				onChange={ updateWidth }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'wp-storymap-document-settings', {
	render: StorymapDocumentSettings,
} );
