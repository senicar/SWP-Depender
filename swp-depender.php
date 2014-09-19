<?php
/*
Plugin Name: SWP Depender
Description: Plugin dependency library for WordPress
Plugin URL:
Version: 0.0.1
Author: Senicar
Author URI: http://senicar.net
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------------*
** CONST
** -------------------------------------------------------*/

define('SWP_DEPENDER_PATH', plugin_dir_path(__FILE__));
define('SWP_DEPENDER_URL', plugin_dir_url(__FILE__));

/* -------------------------------------------------------*
** REQUIRE
** -------------------------------------------------------*/

require_once 'swp-depender.class.php';

/**
 * there is a global $swp_depender variable available
 * but if you prefer branded Manage menu item and page
 * create a new SWP_Depender like this
 *
 *	$custom_depender = new SWP_Depender(
 *		$id = 'custom_depender',
 *		$config = array(
 *			'manage_dependencies_page_title' => __('Custom Manage dependencies'),
 *			'manage_dependencies_menu_title' => __('Custom Manage dependencies'),
 *		)
 *	);
 *
 **/

/* -------------------------------------------------------*
** Dependencies
** -------------------------------------------------------*/

/**
 * hook to filter {depender-id}_register_dependers
 * default: swp_depender_register_dependers
 **/

add_filter('swp_depender_register_dependers', 'custom_depender_register');
function custom_depender_register( $dependers ) {

	$dependers['my_plugin'] = array(
			'name' => 'My Plugin',
			'dependencies' => array(
				array(
					'name'     => 'Meta-Box',
					'slug'     => 'meta-box',
					'required' => true,
					),
				array(
					'name'           => 'Dummy Plugin Sample',
					'slug'           => 'dummy-plugin-sample',
					'required'       => false,
					'source'         => 'https://github.com/senicar/SWP-Depender/raw/master/plugins/dummy-plugin-sample.zip',
					'wp_json_readme' => 'https://github.com/senicar/SWP-Depender/raw/master/plugins/dummy-plugin-sample.json',
					),
				),
			);

	$dependers['my_plugin_slug2'] = array(
			'name' => 'My Plugin 2',
			'dependencies' => array(
				array(
					'name'           => 'Dummy Plugin Sample',
					'slug'           => 'dummy-plugin-sample',
					'required'       => true,
					'source'         => SWP_DEPENDER_URL . 'plugins/dummy-plugin-sample.zip',
					'wp_json_readme' => SWP_DEPENDER_URL . 'plugins/dummy-plugin-sample.json',
					),
				),
			);

	return $dependers;
}

/* -------------------------------------------------------*
** Activate / Deactivate
** -------------------------------------------------------*/

// TODO : Clear all swp_depender settings, they use update_user_option which is multisite oriented

function swp_depender_activate() {
}

register_activation_hook( __FILE__, 'swp_depender_activate' );

function swp_depender_deactivate() {
	global $swp_depender;
	$swp_depender->delete_depender_options('my_plugin');
	$swp_depender->delete_depender_options('my_plugin_slug2');
}
register_deactivation_hook( __FILE__, 'swp_depender_deactivate' );
