<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if( ! class_exists('SWP_Depender') ) {

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	class SWP_Depender {

		public static $instance;

		public $id;

		public $dependers = array();

		public $dependencies = array();

		public $current_depender = '';

		public $strings = array();

		public $options = array();

		public $config = array();

		function __construct( $id = '', $config = array() ) {

			// works only on admin pages
			if( ! is_admin() ) return;

			if( $id )
				$this->id = $id;
			else
				$this->id = 'swp_depender';

			$default_config = array(
					'manage_dependencies_page_title' => __('Manage dependencies','swp_depender'),
					'manage_dependencies_menu_title' => __('Manage dependencies','swp_depender'),
					'is_automatic' => false,
					);

			$this->config = wp_parse_args($config, $default_config);

			add_action('init', array($this, 'init'), 10);
		}

		/* -------------------------------------------------------*
		** Core
		** -------------------------------------------------------*/

		function init() {
			// by default $this->dependers is an empty array
			$this->add_dependers( $this->dependers );

			// don't even bother
			if( empty($this->dependers) || empty($this->dependencies) ) return;

			$strings = array(
					'page_title'                     => __( 'Install Required Plugins', 'swp_depender' ),
					'menu_title'                     => __( 'Install Plugins', 'swp_depender' ),
					'manage' => __('Manage dependencies','swp_depender'),
					'installing'                     => __( 'Installing Plugin: %s', 'swp_depender' ),
					'oops'                           => __( 'Something went wrong.', 'swp_depender' ),
					'return'                         => __( 'Return to Manage Dependencies page', 'swp_depender' ),
					'return_to_dash' => __( 'Return to the Dashboard', 'swp_depender' ),
					'notice_install_required'    => _n_noop( 'The following required plugin is not installed: %2$s.', 'The following required plugins are not installed: %2$s.', 'swp_depender' ),
					//'notice_install_required'    => _n_noop( '%1$s requires the following plugin: %2$s.', '%1$s requires the following plugins: %2$s.', 'swp_depender' ),
					'notice_install_recommended' => _n_noop( 'The following recommended plugin is not installed: %2$s.', 'The following recommended plugins are not installed: %2$s.', 'swp_depender' ),
					//'notice_install_recommended' => _n_noop( '%1$s recommends the following plugin: %2$s.', '%1$s recommends the following plugins: %2$s.', 'swp_depender' ),
					'notice_activate_required'   => _n_noop( 'The following required plugin is currently inactive: %2$s.', 'The following required plugins are currently inactive: %2$s.', 'swp_depender' ),
					'notice_activate_recommended'=> _n_noop( 'The following recommended plugin is currently inactive: %2$s.', 'The following recommended plugins are currently inactive: %2$s.', 'swp_depender' ),
					'notice_cannot_install'          => __( 'Sorry, but you do not have the correct permissions to install plugins. Contact the administrator of this site for help on getting the plugin installed.', 'swp_depender' ),
                	'notice_update_to_min_version'           => _n_noop( 'The following plugin needs to be updated to its latest version to ensure maximum compatibility with %1$s: %2$s.', 'The following plugins need to be updated to their latest version to ensure maximum compatibility with %1$s: %2$s.', 'swp_depender' ),
                	'dismiss'                        => __( 'Dismiss this notice', 'swp_depender' ),
					'bulk_activate' => __('Activate','swp_depender'),
					'bulk_install' => __('Install','swp_depender'),
					'required' => __('Required','swp_depender'),
					'recommended' => __('Recommended','swp_depender'),
					'type' => __('Type','swp_depender'),
					'status' => __('Status','swp_depender'),
					'title' => __('Title','swp_depender'),
					'source' => __('Source','swp_depender'),
					'private_repository' => __('Private Repository','swp_depender'),
					'wp_repository' => __('WordPress Repository','swp_depender'),
					'not_active' => __('Installed but not activated','swp_depender'),
					'not_installed' => __('Not installed','swp_depender'),
					'active' => __('Active','swp_depender'),
					'installed' => __('Installed','swp_depender'),
					'version_not_met' => __('Version not met','swp_depender'),
					'activated_successfully' => __('<strong>%s</strong> successfully activated.','swp_depender'),
					'activated_error' => __('Error : <strong>%s</strong> not activated.','swp_depender'),
					'activated_error_is_active' => __('<strong>%s</strong> is already active.','swp_depender'),
					'installed_successfully' => __('<strong>%s</strong> successfully installed.','swp_depender'),
					'no_unresolved_dependencies' => __('No unresolved dependencies','swp_depender'),
					'complete' => __( 'All plugins installed and activated successfully. %1$s', 'swp_depender' ),
					);

			$this->strings = $strings;

			// check if we want to dismiss notice
			$this->notice_dismiss();

			add_action( 'admin_notices', array( $this, 'notices' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'thickbox' ) );
			add_action( 'admin_menu', array($this, 'add_plugin_page') );

			add_filter( 'plugins_api', array($this, 'plugin_api'), 80, 3 );
			add_filter( 'all_plugins', array($this, 'all_plugins'), 80 );
			add_filter( 'plugin_action_links', array($this, 'plugin_action_links'), 80, 4 );
			add_filter( 'network_admin_plugin_action_links', array($this, 'plugin_actions_links'), 80, 4 );
			add_filter( 'install_plugin_complete_actions', array( $this, 'plugin_installed_actions' ) );

			//add_action("{$this->id}_registered_dependers", array($this, 'add_plugin_page'));

		}

		function add_plugin_page( $dependers ) {
			$args = array(
				'page_title' => $this->config['manage_dependencies_page_title'],
				'menu_title' => $this->config['manage_dependencies_menu_title'],
				'capability' => 'edit_theme_options',
				'menu_slug' => $this->id,
				'function' => array($this, 'manage_dependencies_page'),
			);

			if( (isset($_GET['page']) && $_GET['page'] == $this->id) || $this->has_unresolved_dependencies() )
				add_plugins_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function']);
		}

		function get_string( $string_key, $depender_id = null) {
			if($depender_id && isset($this->dependers[$depender_id]['strings']) && isset($this->dependers[$depender_id]['strings'][$string_key]) )
				return $this->current_depender['strings'][$string_key];
			else if($this->current_depender && isset($this->current_depender['strings']) && isset($this->current_depender['strings'][$string_key]) )
				return $this->current_depender['strings'][$string_key];
			else
				return $this->strings[$string_key];
		}

		/* -------------------------------------------------------*
		** Dependers and Dependencies functions
		** -------------------------------------------------------*/

		function add_dependers( $dependers = array() ) {
			$dependers = apply_filters("{$this->id}_register_dependers", $dependers );

			foreach($dependers as $depender_id => $depender) {
				$this->add_depender( $depender_id, $depender );
			}
		}

		function add_depender( $depender_id, $depender = array() ) {

			if( ! isset($depender['dependencies']) ) return;
			//if( empty($depender['dependencies']) ) return;

			$defaults = array(
				'ID' => $depender_id,
				'name' => $depender_id,
			);

			$dependencies = $depender['dependencies'];

			unset($depender['dependencies']);

			$new_depender = wp_parse_args($depender, $defaults);

			$this->dependers[$depender_id] = $new_depender;

			$this->add_dependencies($depender_id, $dependencies);
		}

		function add_dependencies($depender_id, $dependencies) {
			foreach($dependencies as $key => $plugin) {
				$this->add_dependency( $depender_id, $plugin);
			}
		}

		function add_dependency( $depender_id, $plugin) {
			//if( ! isset($plugin['source']) )
				//$depender['dependencies'][$key]['source'] = 'http://wordpress.org/extend/plugins/' . $plugin['slug'] . '.zip';

			$plugin['depender_id'] = $depender_id;
			$plugin['ID'] = md5(serialize($plugin));

			// we put it after ID generation beacuse, file_path changes when
			// it is installed, slug -> file_path
			$plugin['file_path'] = $this->_get_plugin_basename_from_slug($plugin['slug']);

			$this->dependencies[ $plugin['ID'] ] = $plugin;
		}

		function set_dependerdata( $depender ) {
			$this->current_depender = $depender;
		}

		function clear_dependerdata() {
			$this->current_depender = '';
		}

		function get_unresolved_dependencies( $dependencies = array() ) {

			$unresolved = array();

			if( ! $dependencies ) $dependencies = $this->dependencies;

			foreach( $dependencies as $key => $plugin ) {
				if( $this->plugin_status($plugin) != 'active' ) {
					$unresolved[] = $plugin;
				}
			}

			return $unresolved;
		}

		function has_unresolved_dependencies( $depender_id = null ) {

			if( $depender_id ) {
				$dependencies = $this->get_depender_dependencies( $depender_id );
				$unresolved = $this->get_unresolved_dependencies( $dependencies );
			}
			else {
				$unresolved = $this->get_unresolved_dependencies( $this->dependencies );
			}

			if($unresolved) return true;

			return false;
		}


		public function populate_file_path() {

			// Add file_path key for all plugins.
			foreach ( $this->dependencies as $key => $plugin ) {
				$this->dependencies[$key]['file_path'] = $this->_get_plugin_basename_from_slug( $plugin['slug'] );
			}

		}

		static function plugin_status( $plugin ) {
			$installed_plugins = get_plugins();

			if( isset($installed_plugins[$plugin['file_path']]) && is_plugin_inactive($plugin['file_path']) ) {
				return 'not_active';
			}
			else if( ! isset($installed_plugins[$plugin['file_path']]) ) {
				return 'not_installed';
			}
			else if( isset($installed_plugins[$plugin['file_path']]) && is_plugin_active($plugin['file_path']) ) {
				// A minimum version has been specified.
				if ( isset( $plugin['version'] ) ) {
					if ( isset( $installed_plugins[$plugin['file_path']]['Version'] ) ) {
						// If the current version is less than the minimum required version, we display a message.
						if ( version_compare( $installed_plugins[$plugin['file_path']]['Version'], $plugin['version'], '<' ) ) {
							//if ( current_user_can( 'install_plugins' ) ) {
							return 'version_not_met';
						}
					}
				}

				return 'active';
			}
		}

		function get_depender( $depender_id ) {
			return $this->dependers[$depender_id];
		}

		function get_depender_dependencies( $depender_id ) {
			$dependencies = array();

			foreach($this->dependencies as $key => $plugin) {
				if($plugin['depender_id'] == $depender_id)
					$dependencies[] = $plugin;
			}

			return $dependencies;
		}

		function get_dependencies() {
			return $this->dependencies;
		}

		function get_dependency( $plugin_slug, $depender_id = null ) {
			if( empty($this->current_depender) && ! $depender_id ) return null;

			$depender_id = ($depender_id) ? $depender_id : $this->current_depender['ID'];

			foreach($this->dependencies as $plugin) {
				if($plugin['depender_id'] == $depender_id && $plugin['slug'] == $plugin_slug )
					return $plugin;
			}

			return null;
		}

		function categorize_dependencies( $dependencies ) {
			$categorized = array(
					'install_required' => array(),
					'install_recommended' => array(),
					'activate_required' => array(),
					'activate_recommended' => array(),
					);

			// if dependencies have already been categorized simply return
			if( array_intersect_key($dependencies,$categorized) ) return $dependencies;

			foreach($dependencies as $plugin) {

				if( $this->plugin_status($plugin) == 'not_installed' ) {
					if($plugin['required']) {
						$categorized['install_required'][] = $plugin;
					}
					else {
						$categorized['install_recommended'][] = $plugin;
					}
				}
				else if( $this->plugin_status($plugin) == 'not_active' ) {
					if($plugin['required']) {
						$categorized['activate_required'][] = $plugin;
					}
					else {
						$categorized['activate_recommended'][] = $plugin;
					}
				}
				else if( $this->plugin_status($plugin) == 'version_not_met' ) {
					$categorized['update_to_min_version'][] = $plugin;
				}
			}

			return $categorized;
		}

		protected function _get_plugin_basename_from_slug( $slug ) {

			$keys = array_keys( get_plugins() );

			foreach ( $keys as $key ) {
				if ( preg_match( '|^' . $slug .'/|', $key ) ) {
					return $key;
				}
			}

			return $slug;

		}

		/* -------------------------------------------------------*
		** Manage page
		** -------------------------------------------------------*/

		function is_manage_screen() {
			$screen = get_current_screen();

			if( $screen->id != $this->id ) return false;

			return $screen;
		}

		/**
		 * @return $plugins to list
		 **/

		function all_plugins( $plugins ) {
			if( ! $screen = $this->is_manage_screen() ) return $plugins;

			// reset if on our manage page
			$plugins = array();

			if( isset($this->current_depender) ) {
				foreach($this->get_depender_dependencies($this->current_depender['ID']) as $key => $plugin) {
					if( $this->plugin_status( $plugin ) == 'not_active' ) {
						$plugins[ $plugin['file_path'] ] = array_merge(get_plugin_data( WP_PLUGIN_DIR .'/'. $plugin['file_path'] ), $plugin);
					}

					if( ($status = $this->plugin_status( $plugin )) == 'not_installed' ) {
						$default_headers = array(
								'Name'        => $plugin['name'],
								'PluginURI'   => $plugin['PluginURI'],
								//'Version'     => 'Version',
								'Description' => $this->get_string($status),
								//'Author'      => 'Author',
								//'AuthorURI'   => 'Author URI',
								//'TextDomain'  => 'Text Domain',
								//'DomainPath'  => 'Domain Path',
								//'Network'     => 'Network',
								// Site Wide Only is deprecated in favor of Network.
								//'_sitewide'   => 'Site Wide Only',
								);

						$default_headers = array_merge($default_headers, $plugin);
						$plugins[ $plugin['file_path'] ] = $default_headers;
					}

				}
			}

			return $plugins;
		}

		function plugin_action_links( $actions, $plugin_id, $plugin_data, $context ) {
			if( ! $screen = $this->is_manage_screen() ) return $actions;

			$plugin_file = $plugin_data['file_path'];

			if ( $screen->in_admin( 'network' ) )
				$is_active = is_plugin_active_for_network( $plugin_file );
			else
				$is_active = is_plugin_active( $plugin_file );

			$actions = array();

			$url = add_query_arg( array(
				'page' => $this->id,
				'depender_id' => $this->current_depender['ID'],
				),
				admin_url('plugins.php')
			);

			if ( $screen->in_admin( 'network' ) ) {
				if ( $is_active ) {
					if ( current_user_can( 'manage_network_plugins' ) ) {
					}
				} else {
					if ( current_user_can( 'manage_network_plugins' ) ) {
						$url = add_query_arg(array(
							'action' => 'activate',
							'plugin' => $plugin_id,
							'plugin_status' => $context,
							'paged' => $page,
							's' => $s, 'activate-plugin_' . $plugin_id,
							),
							$url
						);
						$actions['activate'] = '<a href="' . wp_nonce_url($url, 'activate-plugin_' . $plugin_id) . '" title="' . esc_attr__('Activate this plugin for all sites in this network') . '" class="edit">' . __('Network Activate') . '</a>';
					}
				}
			} else {
				if ( $is_active ) {
				}
				else if ( ($status = $this->plugin_status( $plugin_data )) == 'not_installed' ) {
					$url = add_query_arg(array(
						'action' => 'install',
						'plugin' => $plugin_id,
						),
						$url
					);
					$actions['install'] = '<a href="' . wp_nonce_url($url, 'install-plugin_'. $plugin_id) . '" title="' . esc_attr__('Activate this plugin') . '" class="edit">' . __('Install') . '</a>';
				} else {
					$url = add_query_arg(array(
						'action' => 'activate',
						'plugin' => $plugin_id,
						),
						$url
					);
					$actions['activate'] = '<a href="' . wp_nonce_url($url, 'activate-plugin_' . $plugin_id) . '" title="' . esc_attr__('Activate this plugin') . '" class="edit">' . __('Activate') . '</a>';
				} // end if $is_active

			 } // end if $screen->in_admin( 'network' ):w


			//$actions = array();

			//if($this->plugin_status($plugin) == 'not_installed' && current_user_can('install_plugins'))
				//$actions['install'] = sprintf('<a href="?page=%s&action=%s&plugin=%s">Install</a>',$_REQUEST['page'],'install',$item['slug']);

			//if($this->plugin_status($plugin) == 'not_active') {
				//$url = add_query_arg(array(
					//'plugin' => $plugin['file_path'],
					//'action' => 'activate',
				//), admin_url('plugins.php') );

				//if ( current_user_can( 'manage_network_plugins' ) )
					//$actions['activate'] = '<a href="' . wp_nonce_url('plugins.php?action=activate&amp;plugin=' . $plugin['file_path'] . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'activate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Activate this plugin for all sites in this network') . '" class="edit">' . __('Network Activate') . '</a>';

				//$actions['activate'] = sprintf('<a href="%s">Activate</a>', $url);
			//}

			/*
			if($this->master->plugin_status($plugin) == 'version_not_met' && current_user_can('install_plugins')) {
				$url = add_query_arg(
						array(
							//'action'=>'upgrade-plugin',
							//'plugin'=>$this->master->dependencies[ $item['slug'] ]['file_path'],
							),
						network_admin_url('update-core.php')
						);
				//$url = wp_nonce_url($url);
				$actions['update'] = sprintf('<a href="%s">Update</a>', $url);
			}
			*/

			if( isset($plugin['source']) )
				$actions['download'] = sprintf('<a href="%s">Download</a>',$plugin['source']);


			return $actions;
		}

		function manage_dependencies_page() {
			global $screen;

			$this->process_action();

			set_current_screen($this->id);

			$wp_list_table = new SWP_Depender_List_Table($this);
			wp_enqueue_script('plugin-install');
			add_thickbox();

			if(!current_user_can('install_plugins')) {
				echo sprintf('<div class="%s">%s</div>', 'update-nag', $this->get_string('notice_cannot_install'));
			}

			?>
			<div class="wrap">
				<h2><?php echo $this->config['manage_dependencies_page_title']; ?></h2>
				<?php

					if( ! $this->has_unresolved_dependencies() ) {

						echo '<p>' .  sprintf( $this->get_string('complete'), '<a href="' . admin_url() . '" title="' . __( 'Return to the Dashboard', 'swp_depender' ) . '">' . __( 'Return to the Dashboard', 'swp_depender' ) . '</a>' ) . '</p>';

					}

					foreach($this->dependers as $depender_id => $depender) :
						$this->set_dependerdata( $depender );
						$wp_list_table->prepare_items();

						if( ! $this->has_unresolved_dependencies($depender_id) || ! $wp_list_table->items ) continue;

						echo sprintf('<h3 id="%s">%s</h3>',$depender_id, $depender['name']);

						echo sprintf( '<form action="%s" method="POST">',add_query_arg('page', $this->id, admin_url('plugins.php')) );
						echo sprintf('<input type="hidden" name="%s_depender" value="%s" />', $this->id, $depender_id);
						echo sprintf('<input type="hidden" name="%s_bulk_actions" value="%s" />', $this->id, $depender_id);

						$wp_list_table->display();

						echo '</form>';


					endforeach;

					$this->clear_dependerdata();
				?>
				</div>
				<?php
		}


		/* -------------------------------------------------------*
		** User options
		** -------------------------------------------------------*/

		function delete_depender_options($depender_id) {
			global $wpdb;
			$wpdb->delete( $wpdb->usermeta, array('meta_key' => "{$this->id}_{$depender_id}") );
		}

		function update_user_option($depender_id, $option, $value) {
			$this->set_user_option($depender_id, $option, $value);
		}

		function set_user_option($depender_id, $option, $value) {
			//$options = get_user_option("{$this->id}");
			$options = get_user_meta(get_current_user_id(), "{$this->id}", true);

			$options[$option] = $value;

			//update_user_option(get_current_user_id(), "{$this->id}", $options);
			update_user_meta(get_current_user_id(), "{$this->id}_{$depender_id}", $options);
		}

		function get_user_option($depender_id, $option) {
			//$options = get_user_option("{$this->id}");
			$options = get_user_meta(get_current_user_id(), "{$this->id}_{$depender_id}", true);

			if( isset($options[$option]) )
				return $options[$option];

			return false;
		}


		/* -------------------------------------------------------*
		** Notices
		** -------------------------------------------------------*/

		function notice_dismiss() {
			if( ! current_user_can('edit_theme_options') ) return;

			// set user meta to dismiss notice

			if( isset($_GET[$this->id . '_dismiss']) && isset($this->dependers[ $_GET[$this->id . '_dismiss'] ]) ) {
				$depender_id = $_GET[$this->id . '_dismiss'];
				$this->update_user_option($depender_id, 'dismiss', true);
			}
		}

		function notices() {
			global $wp_version;

			// current user should at least be able to edit theme options
			if( ! current_user_can('edit_theme_options') ) return;

			if( isset($_GET['page']) && $_GET['page'] == $this->id ) return;

			// Register the nag messages and prepare them to be processed.
			$notice_class = version_compare( $wp_version, '3.8', '<' ) ? 'updated' : 'update-nag';

			$output = '';

			foreach( $this->dependers as $depender_id => $depender ) {

				$dismissed = $this->get_user_option($depender_id, 'dismiss');

				if( $dismissed )
					continue;

				if( ! $this->has_unresolved_dependencies( $depender_id ) ) continue;

				$this->set_dependerdata( $depender );

				$notice = array();

				$notice[] = sprintf('<b>%s</b>', (isset($depender['name'])) ? $depender['name'] : $depender['id']);

				$dependencies = $this->get_depender_dependencies($depender_id);
				$categorized = $this->categorize_dependencies($dependencies);

				foreach($categorized as $action => $plugins) {
					if($plugins) {
						$notice[] = '<p>';
						$notice[] = sprintf(
								translate_nooped_plural(
										$this->get_string('notice_'.$action),
										count($plugins),
										'swp_depender'
									),
								$depender['name'],
								implode(
										', ',
										array_map(function($plugin) { return $this->thickbox_plugin_info_link($plugin); }, $plugins)
									)
								);
						$notice[] = '</p>';
					}
				}

				$notice[] = $this->notice_actions($depender_id, $dependencies);

				$output .= sprintf('<div class="%s">%s</div>', $notice_class, implode('', $notice));

				$this->clear_dependerdata( $depender );
			}

			echo $output;
		}


		function notice_actions($depender_id, $dependencies) {
			//$dependencies = $this->categorize_dependencies($dependencies);

			$output = array();

			//foreach($dependencies as $action => $plugins) {
				//if( in_array($action, array('install_required','install_recommended')) ) {
					//$output['install'] = sprintf('<a href="">%s</a>', 'Install plugins');
				//}

				//if( in_array($action, array('activate_required','activate_recommended')) ) {
					//$output['activate'] = sprintf('<a href="">%s</a>', 'Activate plugins');
				//}
			//}

			$output['manage'] = sprintf('<a href="%s#%s">%s</a>', add_query_arg('page', $this->id, admin_url('plugins.php')), $depender_id, $this->get_string('manage'));
			$output['dismiss'] = sprintf('<a href="%s">%s</a>', add_query_arg($this->id . '_dismiss',$depender_id), $this->get_string('dismiss'));

			return '<p>'. implode(' | ', $output) .'</p>';
		}

		/* -------------------------------------------------------*
		** THICKBOX
		** -------------------------------------------------------*/

		/**
		 * Generate thickbox link for plugins "View details" or "Readme"
		 **/

		function thickbox_plugin_info_link( $plugin ) {
			$slug = $plugin['slug'];

			$output = '';
			if( ! isset($plugin['source']) || preg_match( '|^http://wordpress.org/extend/plugins/|', $plugin['source'] ) ) {
				$url = add_query_arg(
					array(
						'tab'       => 'plugin-information',
						'plugin'    => $plugin['slug'],
						'depender_id' => $plugin['depender_id'],
						'TB_iframe' => 'true',
						'width'     => '640',
						'height'    => '500',
					),
					network_admin_url( 'plugin-install.php' )
				);

				$output = '<b><a href="'.$url.'" class="thickbox" title="' . $plugin['name'] . '">'.$plugin['name'].'</a></b>';
			}
			elseif( isset($plugin['wp_json_readme']) ) {
				$url = add_query_arg(
					array(
						'tab'       => 'plugin-information',
						'plugin'    => $plugin['ID'],
						'depender_id' => $plugin['depender_id'],
						'TB_iframe' => 'true',
						'width'     => '640',
						'height'    => '500',
					),
					network_admin_url( 'plugin-install.php' )
				);

				$output = '<b><a href="'.$url.'" class="thickbox" title="' . $plugin['name'] . '">'.$plugin['name'].'</a></b>';
			}
			else {
				$output = '<b>'. $plugin['name'] .'</b>';
			}

			return $output;
		}

		function thickbox() {
			add_thickbox();
		}

		/* -------------------------------------------------------*
		** INSTALL, UPDATE, ACTIVATE
		** -------------------------------------------------------*/

		public function plugin_installed_actions( $install_actions ) {

			// Remove action links on the install page.
			if ( (isset($_GET['page']) && $_GET['page'] == $this->id) || (isset($_GET['plugin']) && isset($this->dependencies[$_GET['plugin']])) ) {
				echo '<p>';
				echo sprintf('<a href="%s#%s">%s</a>', add_query_arg('page', $this->id, admin_url('plugins.php')), $depender_id, $this->get_string('return'));
				echo '</p>';
				return false;
			}

			return $install_actions;

		}

		public function plugin_activate( $plugin ) {

			$installed_plugins = get_plugins();
			if(is_plugin_active($plugin['file_path']) || ! isset($installed_plugins[ $plugin['file_path'] ]) )
				return;

			$plugin_id = $plugin['slug'];
			$plugin_file = $plugin['file_path'];

			check_admin_referer('activate-plugin_' . $plugin_id);

			$result = activate_plugin($plugin_file); //, self_admin_url('plugins.php?error=true&plugin=' . $plugin_file), is_network_admin() );
			$this->populate_file_path(); // Re-populate the file path now that the plugin has been installed and activated.

			if ( is_wp_error( $result ) ) {
				echo '<div id="message" class="error"><p>' . $result->get_error_message() . '</p></div>';
			} else {
				echo '<div class="updated"><p>' . sprintf($this->get_string('activated_successfully'), $plugin['name']) . '</p></div>';

				//echo '<p>';
				//echo sprintf('<a href="%s#%s">%s</a>', add_query_arg('page', $this->id, admin_url('plugins.php')), $depender_id, $this->get_string('return'));
				//echo '</p>';
				//wp_die($result);
			}

			if ( ! is_network_admin() ) {
				$recent = (array) get_option( 'recently_activated' );
				unset( $recent[ $plugin_file ] );
				update_option( 'recently_activated', $recent );
			}

		}


		function plugin_install( $plugin ) {
			$this->do_plugin_install( $plugin );
		}

		/**
		 * Installs a plugin or activates a plugin depending on the hover
		 * link clicked by the user.
		 *
		 * Checks the $_GET variable to see which actions have been
		 * passed and responds with the appropriate method.
		 *
		 * Uses WP_Filesystem to process and handle the plugin installation
		 * method.
		 *
		 * @since 1.0.0
		 *
		 * @uses WP_Filesystem
		 * @uses WP_Error
		 * @uses WP_Upgrader
		 * @uses Plugin_Upgrader
		 * @uses Plugin_Installer_Skin
		 *
		 * @return boolean True on success, false on failure
		 */
		protected function do_plugin_install( $plugin = array() ) {

			// All plugin information will be stored in an array for processing.

			// Checks for actions from hover links to process the installation.
			if ( $plugin ) {
				$plugin = $this->get_dependency( $_GET['plugin'] );
				check_admin_referer( 'install-plugin_' . $plugin['slug'] );

				//$plugin['name']   = $_GET['plugin_name']; // Plugin name.
				//$plugin['slug']   = $_GET['plugin']; // Plugin slug.
				//$plugin['source'] = $_GET['plugin_source']; // Plugin source.

				// Pass all necessary information via URL if WP_Filesystem is needed.
				$url = wp_nonce_url(
						add_query_arg(
							array(
								'page'          => $this->id,
								'plugin'        => $plugin['slug'],
								'plugin_name'   => $plugin['name'],
								'plugin_source' => $plugin['source'],
								'swp-depender-install' => 'install-plugin',
								),
							admin_url( 'plugins.php' )
							),
						'install-plugin_' . $plugin['slug']
						);
				$method = ''; // Leave blank so WP_Filesystem can populate it as necessary.
				$fields = array( 'spw-depender-install' ); // Extra fields to pass to WP_Filesystem.

				if ( false === ( $creds = request_filesystem_credentials( $url, $method, false, false, $fields ) ) ) {
					return true;
				}

				if ( ! WP_Filesystem( $creds ) ) {
					request_filesystem_credentials( $url, $method, true, false, $fields ); // Setup WP_Filesystem.
					return true;
				}

				require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; // Need for plugins_api.
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; // Need for upgrade classes.

				// Set plugin source to WordPress API link if available.
				if ( ! isset( $plugin['source'] ) ) {
					$api = plugins_api( 'plugin_information', array( 'slug' => $plugin['slug'], 'fields' => array( 'sections' => false ) ) );

					if ( is_wp_error( $api ) ) {
						wp_die( $this->get_string('oops') . var_dump( $api ) );
					}

					if ( isset( $api->download_link ) ) {
						$plugin['source'] = $api->download_link;
					}
				}

				// Set type, based on whether the source starts with http:// or https://.
				//$type = preg_match( '|^http(s)?://|', $plugin['source'] ) ? 'web' : 'upload';

				// Prep variables for Plugin_Installer_Skin class.
				$title = sprintf( $this->get_string('installing'), $plugin['name'] );
				$url   = add_query_arg( array( 'action' => 'install-plugin', 'plugin' => $plugin['slug'] ), 'update.php' );
				if ( isset( $_GET['from'] ) ) {
					$url .= add_query_arg( 'from', urlencode( stripslashes( $_GET['from'] ) ), $url );
				}

				$nonce = 'install-plugin_' . $plugin['slug'];

				// Prefix a default path to pre-packaged plugins.
				//$source = ( 'upload' == $type ) ? $this->default_path . $plugin['source'] : $plugin['source'];
				$source = $plugin['source'];

				// Create a new instance of Plugin_Upgrader.
				$upgrader = new Plugin_Upgrader( $skin = new Plugin_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'plugin', 'api' ) ) );

				// Perform the action and install the plugin from the $source urldecode().
				$upgrader->install( $source );

				// Flush plugins cache so we can make sure that the installed plugins list is always up to date.
				wp_cache_flush();

				// Only activate plugins if the config option is set to true.
				if ( $this->config['is_automatic'] ) {
					$plugin_activate = $upgrader->plugin_info(); // Grab the plugin info from the Plugin_Upgrader method.
					$activate        = activate_plugin( $plugin_activate ); // Activate the plugin.
					$this->populate_file_path(); // Re-populate the file path now that the plugin has been installed and activated.

					if ( is_wp_error( $activate ) ) {
						echo '<div id="message" class="error"><p>' . $activate->get_error_message() . '</p></div>';
						wp_die();
						return true; // End it here if there is an error with automatic activation
					}
					else {
						echo '<p>' . $this->get_string('plugin_activated') . '</p>';
					}
				}

				// Display message based on if all plugins are now active or not.
				$complete = array();
				foreach ( $this->get_dependencies() as $plugin ) {
					if ( ! is_plugin_active( $plugin['file_path'] ) ) {
						$complete[] = $plugin;
						break;
					}
					// Nothing to store.
					else {
						$complete[] = '';
					}
				}

				// Filter out any empty entries.
				$complete = array_filter( $complete );

				// All plugins are active, so we display the complete string and hide the plugin menu.
				if ( empty( $complete ) ) {
					echo '<p>' .  sprintf( $this->get_string('complete'), '<a href="' . admin_url() . '" title="' . $this->get_string('return_to_dash') . '">' . $this->get_string('return_to_dash') . '</a>' ) . '</p>';
					echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
				}

				wp_die();
				return true;
			}

			wp_die();
			return false;

		}


		public function process_action( $action = '' ) {

			if( ! $action && isset($_GET['action']) && isset($_GET['depender_id']) && isset($_GET['plugin']) ) {

				$this->set_dependerdata( $this->get_depender($_GET['depender_id']) );

				//var_dump(array_column($this->dependencies, 'depender_id', 'slug'));

				// TODO : this should be fixed to depender_id

				if( $_GET['action'] == 'activate' && ($plugin = $this->get_dependency($_GET['plugin'])) ) {
					$this->plugin_activate( $plugin );
				}

				if( $_GET['action'] == 'install' && ($plugin = $this->get_dependency($_GET['plugin'])) ) {
					$this->plugin_install( $plugin );
				}

				$this->clear_dependerdata();
			}
		}


		/* -------------------------------------------------------*
		** Self hosted json readme parsing
		** -------------------------------------------------------*/


		public function plugin_api($result, $action, $args) {

			if( ! isset($args->slug) )
				return $result;

			// TODO : also check if source is external
			foreach($this->get_dependencies() as $plugin_id => $plugin) {
				if($plugin_id == $args->slug && isset($plugin['wp_json_readme'])) {
					if(  $plugin_info = $this->requestInfo($plugin) ) {
						return $this->toWpFormat($plugin_info);
					}
				}
			}

			return $result;
		}


		public function requestInfo($plugin, $queryArgs = array()) {
			//Query args to append to the URL. Plugins can add their own by using a filter callback (see addQueryArgFilter()).
			// TODO :  get installedVersion
			 //if( ! $this->current_depender ) return null;

			$installedVersion = ''; //$this->getInstalledVersion();
			$queryArgs['installed_version'] = ($installedVersion !== null) ? $installedVersion : '';

			//Various options for the wp_remote_get() call. Plugins can filter these, too.
			$options = array(
				'timeout' => 10, //seconds
				'headers' => array(
					'Accept' => 'application/json'
				),
			);

			$result = wp_remote_get(
				$plugin['wp_json_readme'],
				$options
			);

			//Try to parse the response
			$pluginInfo = null;
			if ( !is_wp_error($result) && isset($result['response']['code']) && ($result['response']['code'] == 200) && !empty($result['body']) ){
				$pluginInfo = $this->fromJson($result['body']);
				$pluginInfo['ID'] = $plugin['ID'];
				$pluginInfo['slug'] = $plugin['ID'];

				// otherwise it downloads from the download url that is specified in the Readme json
				if($plugin['source'])
					$pluginInfo['download_url'] = $plugin['source'];
			}

			return $pluginInfo;
		}


		public function fromJson($json, $triggerErrors = false){
			/** @var StdClass $apiResponse */
			$apiResponse = json_decode($json);
			if ( empty($apiResponse) || !is_object($apiResponse) ){
				if ( $triggerErrors ) {
					trigger_error(
							"Failed to parse plugin metadata. Try validating your .json file with http://jsonlint.com/",
							E_USER_NOTICE
							);
				}
				return null;
			}

			//Very, very basic validation.
			$valid = isset($apiResponse->name) && !empty($apiResponse->name) && isset($apiResponse->version) && !empty($apiResponse->version);
			if ( !$valid ){
				if ( $triggerErrors ) {
					trigger_error(
							"The plugin metadata file does not contain the required 'name' and/or 'version' keys.",
							E_USER_NOTICE
							);
				}
				return null;
			}

			$info = array();
			foreach(get_object_vars($apiResponse) as $key => $value){
				$info[$key] = $value;
			}

			return $info;
		}


		public function toWpFormat( $plugin_info ){
			$info = new StdClass;

			//The custom update API is built so that many fields have the same name and format
			//as those returned by the native WordPress.org API. These can be assigned directly.
			$sameFormat = array(
					'name', 'slug', 'version', 'requires', 'tested', 'rating', 'upgrade_notice',
					'num_ratings', 'downloaded', 'homepage', 'last_updated',
					);
			foreach($sameFormat as $field){
				if ( isset($plugin_info[$field]) ) {
					$info->$field = $plugin_info[$field];
				} else {
					$info->$field = null;
				}
			}

			//Other fields need to be renamed and/or transformed.
			$info->download_link = $plugin_info['download_url'];

			if ( !empty($plugin_info['author_homepage']) ){
				$info->author = sprintf('<a href="%s">%s</a>', $plugin_info['author_homepage'], $plugin_info['author']);
			} else {
				$info->author = $plugin_info['author'];
			}

			if ( is_object($plugin_info['sections']) ){
				$info->sections = get_object_vars($plugin_info['sections']);
			} elseif ( is_array($plugin_info['sections']) ) {
				$info->sections = $plugin_info['sections'];
			} else {
				$info->sections = array('description' => '');
			}

			return $info;
		}



	}

	// inital global instance;
	$swp_depender = new SWP_Depender();
}






if ( ! class_exists( 'SWP_Depender_List_Table' ) ) {

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	}

	class SWP_Depender_List_Table extends WP_List_Table {

		private $master;
		private $depender;

		/** ************************************************************************
		 * Normally we would be querying data from a database and manipulating that
		 * for use in your list table. For this example, we're going to simplify it
		 * slightly and create a pre-built array. Think of this as the data that might
		 * be returned by $wpdb->query().
		 *
		 * @var array
		 **************************************************************************/

		/** ************************************************************************
		 * REQUIRED. Set up a constructor that references the parent constructor. We
		 * use the parent reference to set some default configs.
		 ***************************************************************************/
		function __construct( & $master ) {

			$this->master = $master;
			$this->depender = $master->current_depender;

			global $status, $page;
			//Set parent defaults
			parent::__construct( array(
						'singular'  => 'plugin',     //singular name of the listed records
						'plural'    => 'plugins',    //plural name of the listed records
						'ajax'      => false        //does this table support ajax?
						) );
		}



		/** ************************************************************************
		 * Recommended. This method is called when the parent class can't find a method
		 * specifically build for a given column. Generally, it's recommended to include
		 * one method for each column you want to render, keeping your package class
		 * neat and organized. For example, if the class needs to process a column
		 * named 'title', it would first see if a method named $this->column_title()
		 * exists - if it does, that method will be used. If it doesn't, this one will
		 * be used. Generally, you should try to use custom column methods as much as
		 * possible.
		 *
		 * Since we have defined a column_title() method later on, this method doesn't
		 * need to concern itself with any column with a name of 'title'. Instead, it
		 * needs to handle everything else.
		 *
		 * For more detailed insight into how columns are handled, take a look at
		 * WP_List_Table::single_row_columns()
		 *
		 * @param array $item A singular item (one full row's worth of data)
		 * @param array $column_name The name/slug of the column to be processed
		 * @return string Text or HTML to be placed inside the column <td>
		 **************************************************************************/
		function column_default($item, $column_name){
			switch($column_name){
				case 'title':
				case 'type':
				case 'source':
				case 'status':
					return $item[$column_name];
				default:
					return print_r($item,true); //Show the whole array for troubleshooting purposes
			}
		}


		/** ************************************************************************
		 * Recommended. This is a custom column method and is responsible for what
		 * is rendered in any column with a name/slug of 'title'. Every time the class
		 * needs to render a column, it first looks for a method named
		 * column_{$column_title} - if it exists, that method is run. If it doesn't
		 * exist, column_default() is called instead.
		 *
		 * This example also illustrates how to implement rollover actions. Actions
		 * should be an associative array formatted as 'slug'=>'link html' - and you
		 * will need to generate the URLs yourself. You could even ensure the links
		 *
		 *
		 * @see WP_List_Table::::single_row_columns()
		 * @param array $item A singular item (one full row's worth of data)
		 * @return string Text to be placed inside the column <td> (movie title only)
		 **************************************************************************/
		function column_title($item){

			//Build row actions
			$plugin = $this->master->get_dependency( $item['slug'] );

			$actions = apply_filters('plugin_action_links', array(), $plugin_id = $plugin['slug'], $plugin, $context = '');

			if( empty($actions) )
				$actions[] = '&nbsp;';

			//Return the title contents
			return sprintf('%1$s <span style="color:silver; display:none;">(slug:%2$s)</span>%3$s',
					/*$1%s*/ $item['title'],
					/*$2%s*/ $item['slug'],
					/*$3%s*/ $this->row_actions($actions)
					);
		}


		/** ************************************************************************
		 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
		 * is given special treatment when columns are processed. It ALWAYS needs to
		 * have it's own method.
		 *
		 * @see WP_List_Table::::single_row_columns()
		 * @param array $item A singular item (one full row's worth of data)
		 * @return string Text to be placed inside the column <td> (movie title only)
		 **************************************************************************/
		function column_cb($item){
			return sprintf(
					'<input type="checkbox" name="%1$s[]" value="%2$s" />',
					/*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
					/*$2%s*/ $item['slug']                //The value of the checkbox should be the record's id
					);
		}


		/** ************************************************************************
		 * REQUIRED! This method dictates the table's columns and titles. This should
		 * return an array where the key is the column slug (and class) and the value
		 * is the column's title text. If you need a checkbox for bulk actions, refer
		 * to the $columns array below.
		 *
		 * The 'cb' column is treated differently than the rest. If including a checkbox
		 * column in your table you must create a column_cb() method. If you don't need
		 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
		 *
		 * @see WP_List_Table::::single_row_columns()
		 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
		 **************************************************************************/
		function get_columns(){
			$columns = array(
					//'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
					'title'     => __('Plugin'),
					'type'    => __('Type'),
					'source'  => __('Source'),
					'status'  => __('Status'),
					);
			return $columns;
		}


		/** ************************************************************************
		 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
		 * you will need to register it here. This should return an array where the
		 * key is the column that needs to be sortable, and the value is db column to
		 * sort by. Often, the key and value will be the same, but this is not always
		 * the case (as the value is a column name from the database, not the list table).
		 *
		 * This method merely defines which columns should be sortable and makes them
		 * clickable - it does not handle the actual sorting. You still need to detect
		 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
		 * your data accordingly (usually by modifying your query).
		 *
		 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
		 **************************************************************************/
		function get_sortable_columns() {
			$sortable_columns = array(
					'title'     => array('title',false),     //true means it's already sorted
					'type'    => array('type',false),
					'source'  => array('source',false),
					'status'  => array('status',false),
					);
			return $sortable_columns;
		}


		// TODO : Bluk actions

		/** ************************************************************************
		 * Optional. If you need to include bulk actions in your list table, this is
		 * the place to define them. Bulk actions are an associative array in the format
		 * 'slug'=>'Visible Title'
		 *
		 * If this method returns an empty value, no bulk action will be rendered. If
		 * you specify any bulk actions, the bulk actions box will be rendered with
		 * the table automatically on display().
		 *
		 * Also note that list tables are not automatically wrapped in <form> elements,
		 * so you will need to create those manually in order for bulk actions to function.
		 *
		 * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
		 **************************************************************************/
		//function get_bulk_actions() {
			//$actions = array(
					//'install'    => __('Install','swp_depender'),
					//'activate'    => __('Activate','swp_depender'),
					//);
			//return $actions;
		//}


		/** ************************************************************************
		 * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
		 * For this example package, we will handle it in the class to keep things
		 * clean and organized.
		 *
		 * @see $this->prepare_items()
		 **************************************************************************/
		//function process_bulk_action() {

			//if( ! array_key_exists($this->master->id.'_depender',$_POST) || $_POST[$this->master->id.'_depender'] != $this->depender['id']) return;

			////Detect when a bulk action is being triggered...
			//if( 'install'===$this->current_action() && isset($_POST['plugin']) ) {
				//foreach($_POST['plugin'] as $key => $plugin_slug) {
					//$plugin = $this->master->dependencies[$plugin_slug];
					//$this->master->plugin_install($plugin);
				//}
			//}

			//if( 'activate'===$this->current_action() && isset($_POST['plugin']) ) {
				//foreach($_POST['plugin'] as $key => $plugin_slug) {
					//$plugin = $this->master->dependencies[$plugin_slug];
					//$this->master->plugin_activate($plugin);
				//}
			//}

		//}


		/** ************************************************************************
		 * REQUIRED! This is where you prepare your data for display. This method will
		 * usually be used to query the database, sort and filter the data, and generally
		 * get it ready to be displayed. At a minimum, we should set $this->items and
		 * $this->set_pagination_args(), although the following properties and methods
		 * are frequently interacted with here...
		 *
		 * @global WPDB $wpdb
		 * @uses $this->_column_headers
		 * @uses $this->items
		 * @uses $this->get_columns()
		 * @uses $this->get_sortable_columns()
		 * @uses $this->get_pagenum()
		 * @uses $this->set_pagination_args()
		 **************************************************************************/
		function prepare_items() {
			global $wpdb; //This is used only if making any database queries

			/**
			 * First, lets decide how many records per page to show
			 */
			$per_page = 1000;


			/**
			 * REQUIRED. Now we need to define our column headers. This includes a complete
			 * array of columns to be displayed (slugs & titles), a list of columns
			 * to keep hidden, and a list of columns that are sortable. Each of these
			 * can be defined in another method (as we've done here) before being
			 * used to build the value for our _column_headers property.
			 */
			$columns = $this->get_columns();
			$hidden = array();
			$sortable = $this->get_sortable_columns();


			/**
			 * REQUIRED. Finally, we build an array to be used by the class for column
			 * headers. The $this->_column_headers property takes an array which contains
			 * 3 other arrays. One for all columns, one for hidden columns, and one
			 * for sortable columns.
			 */
			$this->_column_headers = array($columns, $hidden, $sortable);


			/**
			 * Optional. You can handle your bulk actions however you see fit. In this
			 * case, we'll handle them within our package just to keep things clean.
			 */
			//$this->process_bulk_action();


			/**
			 * Instead of querying a database, we're going to fetch the example data
			 * property we created for use in this plugin. This makes this example
			 * package slightly different than one you might build on your own. In
			 * this example, we'll be using array manipulation to sort and paginate
			 * our data. In a real-world implementation, you will probably want to
			 * use sort and pagination data to build a custom query instead, as you'll
			 * be able to use your precisely-queried data immediately.
			 */
			$data = array();
			$plugins = apply_filters('all_plugins', array());

			foreach($plugins as $key => $plugin) {
				$plugin_source = ( ! isset($plugin['source']) || preg_match( '|^http://wordpress.org/extend/plugins/|', $plugin['source'] ) ) ? $this->master->get_string('wp_repository') : $this->master->get_string('private_repository');

				$plugin_status = $this->master->plugin_status($plugin);
				if( $plugin_status == 'active' ) continue;

				$data[$key] = array(
					'slug' => $plugin['slug'],
					'title' => $this->master->thickbox_plugin_info_link($plugin),
					'type' => (isset($plugin['required']) && $plugin['required']) ? $this->master->get_string('required') : $this->master->get_string('recommended'),
					'source' => $plugin_source,
					'status' => $this->master->get_string($plugin_status),
				);
			}

			/**
			 * This checks for sorting input and sorts the data in our array accordingly.
			 *
			 * In a real-world situation involving a database, you would probably want
			 * to handle sorting by passing the 'orderby' and 'order' values directly
			 * to a custom query. The returned data will be pre-sorted, and this array
			 * sorting technique would be unnecessary.
			 */

			usort($data, array($this, 'usort_reorder'));


			/***********************************************************************
			 * ---------------------------------------------------------------------
			 * vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
			 *
			 * In a real-world situation, this is where you would place your query.
			 *
			 * For information on making queries in WordPress, see this Codex entry:
			 * http://codex.wordpress.org/Class_Reference/wpdb
			 *
			 * ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
			 * ---------------------------------------------------------------------
			 **********************************************************************/


			/**
			 * REQUIRED for pagination. Let's figure out what page the user is currently
			 * looking at. We'll need this later, so you should always include it in
			 * your own package classes.
			 */
			$current_page = $this->get_pagenum();

			/**
			 * REQUIRED for pagination. Let's check how many items are in our data array.
			 * In real-world use, this would be the total number of items in your database,
			 * without filtering. We'll need this later, so you should always include it
			 * in your own package classes.
			 */
			$total_items = count($data);


			/**
			 * The WP_List_Table class does not handle pagination for us, so we need
			 * to ensure that the data is trimmed to only the current page. We can use
			 * array_slice() to
			 */
			$data = array_slice($data,(($current_page-1)*$per_page),$per_page);



			/**
			 * REQUIRED. Now we can add our *sorted* data to the items property, where
			 * it can be used by the rest of the class.
			 */
			$this->items = $data;


			/**
			 * REQUIRED. We also have to register our pagination options & calculations.
			 */
			$this->set_pagination_args( array(
						'total_items' => $total_items,                  //WE have to calculate the total number of items
						'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
						'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
						) );
		}

		function usort_reorder($a,$b){
			$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'title'; //If no sort, default to title
			$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
			$result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
			return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
		}

	}
}
