SWP Depender
============


SWP Depender is a heavily modified fork of [TGM Plugin Activation](https://github.com/thomasgriffin/TGM-Plugin-Activation) library.

It is not as featured as TGMPA, currently it does not support Bulk activation/installation, but it does have a few unique features:

* Multiple branded instances of `SWP_Depender` class

  Each instance creates it's own admin page to display and manage its dependencies


* Multiple dependers

  Each `SWP_Depender` instance can have multiple dependers, each with its own set of plugin dependencies


* Depender based Notidications

  Each depender has its own notification, so user can easily see which dependencies belong to which depender.


* Display privately hosted plugins Readme with Thickbox

  via `wp_json_readme` option that links to json output of plugins readme file.


## Custom instance ##

A global `$swp_depender` instance is created but you can create your own branded instance for your plugin/theme only

```php
require_once 'swp-depender.class.php';

 $custom_depender = new SWP_Depender(
 	$id = 'custom_depender',
 	$config = array(
 		'manage_dependencies_page_title' => __('Custom Manage dependencies'),
 		'manage_dependencies_menu_title' => __('Custom Manage dependencies'),
 	)
 );

```


## Register Dependencies ##

Hook to filter `{depender-id}_register_dependers`

Default: `swp_depender_register_dependers`

```php
add_filter('swp_depender_register_dependers', 'register_dependencies');
function register_dependencies( $dependers ) {

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
```


