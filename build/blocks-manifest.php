<?php
// This file is generated. Do not modify it manually.
return array(
	'wp-storymap' => array(
		'$schema' => 'https://schemas.wp.org/v6.0/block.json',
		'apiVersion' => 3,
		'name' => 'wp-storymap/map',
		'version' => '0.1.0',
		'title' => 'StoryMap',
		'category' => 'widgets',
		'icon' => 'location-alt',
		'description' => 'Display an interactive OpenStreetMap base map.',
		'example' => array(
			
		),
		'attributes' => array(
			'storyNumber' => array(
				'type' => 'integer',
				'default' => 0
			),
			'align' => array(
				'type' => 'string'
			)
		),
		'supports' => array(
			'align' => true,
			'html' => false
		),
		'textdomain' => 'wp-storymap',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	)
);
