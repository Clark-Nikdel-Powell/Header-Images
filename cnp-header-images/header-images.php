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

////////////////////////////////////////////////////////////////////////////////
// ADMIN  /////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

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
	,	'hierarchical'          => true
	,	'query_var'             => true
	,	'rewrite'               => false
	,	'update_count_callback' => '_update_generic_term_count'
	)); // header_images

	if ( is_admin() ) {
		if (!term_exists('general-rotation', 'headerimages')) {
			wp_insert_term(
				'General Rotation', // the term
				'headerimages', // the taxonomy
				array('slug' => 'general-rotation')
			);
		}
	}
}


//  DROPDOWN FILTER  ///////////////////////////////////////

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


//  ADMIN PAGE  ////////////////////////////////////////////

add_action('admin_menu', 'header_images_settings');

function header_images_settings() {
	add_options_page('Header Images', 'Header Images', 'manage_options', 'header-images.php', 'header_images_settings_page');
}

function header_images_settings_page() {

	$slug = 'header-images';

	// Add options
	add_option('header_images_home_class');
	add_option('header_images_interior_class');
	add_option('header_images_interior_secondary_class');

	// Update/Delete Functions
	if ( isset($_POST['submit']) ) {
		update_option('header_images_home_class', $_POST['header_images_home_class']);
		update_option('header_images_interior_class', $_POST['header_images_interior_class']);
		update_option('header_images_interior_secondary_class', $_POST['header_images_interior_secondary_class']);
	}
	?>
	<div class="wrap">
	<form method="post" action="<?php echo $_SERVER['PHP_SELF'].'?page='.$slug; ?>">
	<h2>Header Images Settings</h2>
	<?php (isset($message) ? $message : ''); ?>

	<table class="form-table">
		<tr valign="top">
			<th scope="row">
				<label for="header_images_home_class">Home Class</label>
			</th>
			<td>
				<input type="text" id="header_images_home_class" name="header_images_home_class" value="<?php echo get_option('header_images_home_class'); ?>" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="header_images_interior_class">Interior Class</label>
			</th>
			<td>
				<input type="text" id="header_images_interior_class" name="header_images_interior_class" value="<?php echo get_option('header_images_interior_class'); ?>" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="header_images_interior_secondary_class">Interior Secondary Class</label>
			</th>
			<td>
				<input type="text" id="header_images_interior_secondary_class" name="header_images_interior_secondary_class" value="<?php echo get_option('header_images_interior_secondary_class'); ?>" />
			</td>
		</tr>
	</table>
	<p class="submit"><?php submit_button('Save Changes', 'primary', 'submit', false); ?></p>
	</form>
	</div>
	<?
}


////////////////////////////////////////////////////////////////////////////////
// FUNCTIONS  /////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

// Use in your theme to get all header images for a section.
function get_header_images($slug='', $numberimages=5) {

	$object = get_queried_object();

	if (empty($slug)) {
		if ( is_page() )
			$slug = $object->post_name;

		if ( is_post_type_archive() )
			$slug = $object->name;

		if ( is_search() || is_404() )
			$slug = 'general-rotation';
	}

	// First test: check the current page
	$args = array(
		'numberposts' 	=> $numberimages,
		'post_type'		=> 'attachment',
		'orderby'       => 'rand',
		'tax_query' => array(
			array(
				'taxonomy' => 'headerimages',
				'field' => 'slug',
				'terms' => $slug
			)
		),
		'fields' => 'ids'
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


////////////////////////////////////////////////////////////////////////////////
// ENQUEUE  ///////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////

function update_header_images_css() {

$output = '';
$exclude = array();

// Get the classes.
$home_class = get_option('header_images_home_class');
$interior_class = get_option('header_images_interior_class');
$interior_secondary_class = get_option('header_images_interior_secondary_class');


// Set up home image. To be condensed later.
if (!empty($home_class)) {

	$home_term = get_term_by( 'slug', 'home', 'headerimages' );
	$exclude[] = $home_term->term_id;

	$args = array(
		'numberposts' => 1
	,	'post_type' => 'attachment'
	,	'fields' => 'ids'
	,	'tax_query' => array(
			array(
				'taxonomy' => 'headerimages',
				'field' => 'slug',
				'terms' => $home_term->slug
			)
		)
	);

	$home_img_id = get_posts($args);

	if (!empty($home_img_id)) {
		$img_src_large = wp_get_attachment_image_src( $home_img_id[0], 'large' );
		$img_src_med   = wp_get_attachment_image_src( $home_img_id[0], 'medium' );
		$img_src_thumb = wp_get_attachment_image_src( $home_img_id[0], 'thumbnail' );
	

		if ($home_class == 'body') {
			$selector = $home_class. '.'. $home_term->slug;
		}

		else {
			$selector = '.'. $home_term->slug .' '. $home_class;
		}

		// Mobile first!
		$output .= $selector .' {background-image:url('. $img_src_med[0] .')}';

		// Larger size for desktop.
		$output .= '@media (min-width: 900px) {'. $selector .' {background-image:url('. $img_src_large[0] .');}}';
	}

}


// Set up general interior image. To be condensed later.
if (!empty($interior_class)) {

	$interior_term = get_term_by( 'slug', 'general-rotation', 'headerimages' );
	$exclude[] = $interior_term->term_id;

	$args = array(
		'numberposts' => 1
	,	'post_type' => 'attachment'
	,	'fields' => 'ids'
	,	'tax_query' => array(
			array(
				'taxonomy' => 'headerimages',
				'field' => 'slug',
				'terms' => $interior_term->slug
			)
		)
	);

	$interior_img_id = get_posts($args);

	if (!empty($interior_img_id)) {
		$img_src_large = wp_get_attachment_image_src( $interior_img_id[0], 'large' );
		$img_src_med   = wp_get_attachment_image_src( $interior_img_id[0], 'medium' );
		$img_src_thumb = wp_get_attachment_image_src( $interior_img_id[0], 'thumbnail' );

		if ($interior_class == 'body') {
			$selector = $interior_class. '.'. $interior_term->slug;
		}

		else {
			$selector = '.'. $interior_term->slug .' '. $interior_class;
		}

		// Mobile first!
		$output .= $selector .' {background-image:url('. $img_src_med[0] .')}';

		// Larger size for desktop.
		$output .= '@media (min-width: 900px) {'. $selector .' {background-image:url('. $img_src_large[0] .');}}';

		if (!empty($interior_secondary_class)) {
			// For image circle.
			$output .= $selector .' {background-image:url('. $img_src_thumb[0] .');}';
		}
	}
}


// Set up all other terms
$term_args = array(
	'exclude' => $exclude
);

$terms = get_terms( 'headerimages', $term_args );

foreach ($terms as $key => $term) {
	$slug = $term->slug;

	$args = array(
		'numberposts' => 1
	,	'post_type' => 'attachment'
	,	'fields' => 'ids'
	,	'orderby' => 'rand'
	,	'tax_query' => array(
			array(
				'taxonomy' => 'headerimages',
				'field' => 'slug',
				'terms' => $slug,
				'include_children' => false
			)
		)
	);

	$img = get_posts($args);

	if (!empty($img)) {
		$img_src_large = wp_get_attachment_image_src( $img[0], 'large' );
		$img_src_med   = wp_get_attachment_image_src( $img[0], 'medium' );
		$img_src_thumb = wp_get_attachment_image_src( $img[0], 'thumbnail' );

		$selector = '.'.$slug;

		if ($term->parent != 0) {
			$parent = get_term( $term->parent, 'headerimages' );
			$selector = '.'.$parent->slug.'.'.$slug;
		}

		// Mobile first!
		$output .= $selector .' '. $interior_class .' {background-image:url('. $img_src_med[0] .')}';

		// Larger size for desktop.
		$output .= '@media (min-width: 900px) {'. $selector .' '. $interior_class .' {background-image:url('. $img_src_large[0] .');}}';

		// For image circle.
		$output .= $selector .' '. $interior_secondary_class .' {background-image:url('. $img_src_thumb[0] .');}';
	}
}

file_put_contents( plugin_dir_path(__FILE__).'header-images.css', $output );

}

// CSS Generator: Runs when an attachment post is saved.
add_action( 'edit_attachment', 'update_header_images_css');

// For some reason this doesn't work. Fix it later.
// $cache = get_transient('_header_images_css_cache');
// if (!$cache) {

// 	update_header_images_css();
// 	set_transient( '_header_images_css_cache', '', 300 );
// }

// Add Styles
function add_header_image_style() {
	wp_enqueue_style( 'header-image-style', '/wp-content/plugins/cnp-header-images/header-images.css' );
}

add_action( 'wp_enqueue_scripts', 'add_header_image_style' );

// add_filter('query_vars','plugin_add_trigger');
// function plugin_add_trigger($vars) {
//     $vars[] = 'header_image_style';
//     return $vars;
// }

// add_action( 'wp_enqueue_scripts', 'add_header_image_style' );

// function add_header_image_style() {
// 	wp_enqueue_style( 'header-image-style', '/?header_image_style=1' );
// }


// add_action('template_redirect', 'plugin_trigger_check');

// function plugin_trigger_check() {

// 	if (intval(get_query_var('header_image_style')) == 1) {

// 		header('Content-type: text/css');
// 		header("Cache-Control: max-age=2592000");
// 		// header('Cache-control: must-revalidate');

// 		$cached_css = get_transient('_header_images_css_cache');

// 		if (!empty($cached_css)) {
// 			echo $cached_css;
// 		}

// 		else {

// 			// Get all the image terms
// 			$terms = get_terms( 'headerimages' );

// 			$output = '';
// 			foreach ($terms as $key => $term) {
// 				$slug = $term->slug;
// 				$img = get_header_images($slug, 1);

// 				if (!empty($img)) {
// 					$img_src_large = wp_get_attachment_image_src( $img[0], 'large' );
// 					$img_src_med   = wp_get_attachment_image_src( $img[0], 'medium' );
// 					$img_src_thumb = wp_get_attachment_image_src( $img[0], 'thumbnail' );
// 				}

// 				// Mobile first!
// 				$output .= '.'. $term->slug .' header.section .img-bg {background-image:url('. $img_src_med[0] .')}';

// 				// Larger size for desktop.
// 				$output .= '@media (min-width: 900px) {.'. $term->slug .' header.section .img-bg {background-image:url('. $img_src_large[0] .');}}';

// 				// For image circle.
// 				$output .= '.'. $term->slug .' header.section .img-circle {background-image:url('. $img_src_thumb[0] .');}';
// 			}

// 			set_transient( '_header_images_css_cache', $output, 1800 );
// 			file_put_contents( 'header-images.css' );
// 			echo $output;
// 		}

// 		exit;
// 	}
// }

