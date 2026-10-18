=== Wp Storymap ===
Contributors:      The WordPress Contributors
Tags:              block
Tested up to:      6.8
Stable tag:        0.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Create Storymaps using native WordPress content and display an interactive OpenStreetMap base map in the editor and on the public page. Each Storymap can have an introductory description and image, shown at the start of the information panel. Public maps display the selected Storymap's points connected in order by a red dashed line, with a small overview map in the lower-left showing the full extent of its points. The information panel contains the introduction and all points in order; scrolling through a point title centers the map on that point. Side arrows remain available to scroll through points in order, with directional slide transitions. On wide maps, the panel fills the map height on the right and the map keeps the selected point in the uncovered area; on mobile, the panel is placed at the bottom. Maps fill the screen width by default; enter a pixel width in Storymap settings to use a custom width. Map height uses the Storymap settings. Each Storymap has an independent story number, separate from its WordPress post ID. A page or post can contain multiple map blocks, each assigned to a different Storymap. The WordPress admin includes a Leaflet-based point form that saves points to the existing legacy points table, using the selected Storymap's number in the legacy `storyId` field.

== Description ==

This is the long description. No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload the plugin files to the `/wp-content/plugins/wp-storymap` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Create and save one or more Storymaps.
1. Add a Storymap map block to a page or post and choose which Storymap it displays in the block settings.
1. Open Storymaps > Add Point to select a location, add its details and image, and assign it to a Storymap.
1. Open Storymaps > All Points to browse points, use Edit for full editing, Quick Edit for the title and description, or Trash to temporarily hide a point. Points can be restored from the Trash view.


== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 0.1.0 =
* Release

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above. This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation." Arbitrary sections will be shown below the built-in sections outlined above.
