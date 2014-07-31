<?
/*

Plugin Name: CNP Header Images
Plugin URI: http://clarknikdelpowell.com/plugins/header-groups
Description: Include Media Library images in general or section-specific header image pools.
Author: Josh Nederveld
Author URI: http://clarknikdelpowell.com
Version: 0.1

Copyright 2012  Josh Nederveld  (email : joshn@clarknikdelpowell.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

add_action('init', 'cnp_header_images');

function cnp_header_images() {
	register_taxonomy(
		'headerimages',
		'attachment',
		array(
			'labels' => array(
				'name'          => 'Header Images'
			,	'singular_name' => 'Header Image'
			,	'search_items'  => 'Search Header Images'
			,	'edit_item'     => 'Edit Header Image'
			,	'add_new_item'  => 'Add New Header Image'
		)
	,	'hierarchical' => true
	,	'query_var'    => true
	,	'rewrite'      => false
	)); // header_images

	if ( !term_exists('general-rotation', 'headerimages') ) {
		wp_insert_term(
			'General Rotation', // the term
			'headerimages', // the taxonomy
			array('slug' => 'general-rotation')
		);
	}
}

//  DROPDOWN FILTER  ///////////////////////////////////

function add_headerimages_filters() {
	global $pagenow;

	// must set this to the admin page you want the filter(s) displayed on
	if( $pagenow == 'upload.php' ){

		$tax_slug = 'headerimages';
		$tax_obj  = get_taxonomy($tax_slug);
		$tax_name = $tax_obj->labels->name;

		$args = array(
			'hide_empty' => false
		);

		$terms = get_terms( $tax_slug, $args );
		?><!-- <? print_r($terms); ?> --><?

		if ( count($terms) > 0 ) {
			echo "<select name='$tax_slug' id='$tax_slug' class='postform'>";
			echo "<option value=''>Show All $tax_name</option>";
			foreach ($terms as $term) {
				// $_GET[$tax_slug] == $term->slug ? ' selected="selected"' : ''
				echo '<option value="'. $term->slug .'">' . $term->name .'</option>';
			}
			echo "</select>";
		}
	}
}
add_action( 'restrict_manage_posts', 'add_headerimages_filters' );

function get_header_images() {

	$object = get_queried_object();

	if (is_page())
		$slug = $object->post_name;

	if ( is_post_type_archive() )
		$slug = $object->name;

	// First test: check the current page
	$args = array(
		'numberposts' 	=> 5,
		'post_type'		=> 'attachment',
		'orderby'       => 'rand',
		'tax_query' => array(
			array(
				'taxonomy' => 'headerimages',
				'field' => 'slug',
				'terms' => $slug
			)
		)
	);

	$sectionimages = get_posts($args);

	if (!empty($sectionimages))
		return $sectionimages;

	// Second test: if there aren't any defined for the current page, check any parent pages
	$old_post = 0;
	$old_post = $object->post_parent;

	while (!$sectionimages && $old_post != 0) {
		$current_post = get_post($old_post);
		$args['tax_query'][0]['terms'] = $current_post->post_name;
		$sectionimages = get_posts($args);
		$old_post = $current_post->post_parent;
	}

	// Third test: if there aren't ANY set, use the General Rotation
	if (!$sectionimages && $old_post == 0) {

		$args['tax_query'][0]['terms'] = 'general-rotation';
		$sectionimages = get_posts($args);
		
	}

	// Assuming you want them random, shuffle it up a bit
	shuffle($sectionimages);

	return $sectionimages;
}
?>