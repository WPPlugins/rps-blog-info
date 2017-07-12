<?php
/**
Plugin Name: RPS Plugin Framework
Plugin URI: http://redpixel.com
Description: Framework for developing RPS plugins.
Version: 2.2.6
Author: Red Pixel Studios
Author URI: http://redpixel.com
License: GPLv3

@package RPS_Plugin_Framework

Copyright 2014-2015 Red Pixel Studios, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.

*/

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) {
    die;
}

require_once dirname(__FILE__) . '/child-class-plugin-framework.php';
if ( ! class_exists( 'RPS_Plugin', false ) ) :
	/**
	 * 
	 *
	 * @since 1.0
	 * @todo May need to flush rewrite rules only when the archive slug or single slug fields on settings have been updated.
	 * @todo Should probably validate license key length on settings so the user can tell immediately if updates will work.
	 */
	class RPS_Plugin extends RPS_Plugin_Framework_Standalone {
	
		/**
		 * Upgrades should be added sequentially, from oldest to newest.
		 *
		 * @since 1.1.13
		 */
		public function upgrades() {
			return array(
				/*
				array(
					'version' => 'x.y.z',
					'routine' => array( $this, '_upgrade_x_y_z' )
				)
				*/
			);
		}
		
		/**
		 * The current version of the plugin for internal use.
		 * Be sure to keep this updated as the plugin is updated.
		 *
		 * @since 1.1.13
		 */
		public function plugin_version() {
			return '2.2.5';
		}
		
		/**
		 * The plugin's name for use in printing to the user.
		 *
		 * @since 1.1.13
		 */
		public function plugin_name() {
			return 'RPS Plugin Framework';
		}
	
		/**
		 * A unique identifier for the plugin. Used for CSS classes
		 * and the like. Uses hyphens instead of spaces.
		 *
		 * @since 1.1.13
		 */
		public function plugin_slug() {
			return 'rps-plugin-framework';
		}
	
		/**
		 * A unique prefix that identifies the plugin. Used for storing
		 * database options, naming interface elements, and so on.
		 *
		 * @since 1.1.13
		 */
		public function plugin_prefix() {
			return 'rps_plugin_framework';
		}
			
		/**
		 * A unique capability that uses the custom post type. Used for managing
		 * all the capabilities of the custom post such as edit, add, remove, etc.
		 *
		 * @since 1.1.13
		 */
		public function manage_capability() {
			return 'manage_rps_plugin_framework';
		}
		
		/**
		 * Parent page for the plugin settings page.
		 *
		 * @since 1.1.13
		 */
		public function options_page_parent() {
			return 'options-general.php';
		}
		
		/**
		 * Slug for the plugin settings page.
		 *
		 * @since 1.1.13
		 */
		public function options_page_slug() {
			return 'rps_plugin_framework_options';
		}
		
		/**
		 * Default key for checkbox arrays.
		 *
		 * @since 1.1.13
		 */
		public function checkbox_default_key() {
			return 'display';
		}
		
		/**
		 * A private instance of the plugin for internal use.
		 *
		 * @since 1.0
		 */
		private static $plugin_instance;
	
		/**
		 * An entry point wrapper to ensure that the plugin is only invoked once.
		 *
		 * @since 1.0
		 */
		public static function invoke() {
			if ( ! isset( self::$plugin_instance ) )
				self::$plugin_instance = new self;
		}
	
		/**
		 * Invoke the plugin.
		 *
		 * @since 1.0
		 */
		protected function __construct() {
			add_action( 'activated_plugin', array( &$this, '_load_first' ) );
			parent::__construct();
			add_action( 'plugins_loaded', array( &$this, '_plugins_loaded' ) );		
	
			self::_init_redux_arguments();
			self::_init_sections();
			
			add_action( 'init', array( &$this, '_init' ) );
			add_action( 'init', array( &$this, '_redux_remove_notices') );
			add_action( 'wp_dashboard_setup', array( &$this, '_redux_remove_widgets' ), 99 );
		}
								
		/**
		 * Called whenever the plugin is activated.
		 *
		 * @since 1.0
		 */
		public function _load_first() {
			// ensure path to this file is via main wp plugin path
			$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR . "/$2", __FILE__);
			$this_plugin = plugin_basename(trim($wp_path_to_this_file));
			$active_plugins = get_option('active_plugins');
			$this_plugin_key = array_search($this_plugin, $active_plugins);
			if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
				array_splice($active_plugins, $this_plugin_key, 1);
				array_unshift($active_plugins, $this_plugin);
				update_option('active_plugins', $active_plugins);
			}
		}
		
		/**
			 * Initialize the plugin and all its resources.
			 *
			 * @since 1.0
			 * @todo Modify rps_directory post type options.
			 */
		public function _init() {
			parent:: _init();
		}
		
		/**
		 * Updates roles with manage capability.
		 *
		 * @since 1.1.13
		 */
		public function _update_capabilities() {
			self::_update_capability( self::get_manage_capability(), self::get_plugin_opt_name() );
		}
		
		/**
		 * Disable Redux Framework demo mode link and admin notices.
		 *
		 * @since 2.1.1
		 */
		public function _redux_remove_notices() { // Be sure to rename this function to something more unique
		    if ( class_exists('ReduxFrameworkPlugin') ) {
		        remove_filter( 'plugin_row_meta', array( ReduxFrameworkPlugin::get_instance(), 'plugin_metalinks'), null, 2 );
		        remove_action( 'admin_notices', array( ReduxFrameworkPlugin::get_instance(), 'admin_notices' ) );
		    }
		}
		
		/**
		 * Disable Redux Framework widgets.
		 *
		 * @since 2.1.1
		 */
		public function _redux_remove_widgets() {
			remove_meta_box( 'redux_dashboard_widget', 'dashboard', 'side' );
		}
			
		/**
		 * Initialize arguments for Redux Framework.
		 *
		 * @see https://github.com/ReduxFramework/ReduxFramework/wiki/Arguments
		 * @since 1.1.10
		 */
		public function _init_redux_arguments() {
			
			if ( is_multisite() ) {
				
				$this->network_args = array(
					'opt_name'     			=> 'network_' . $this->get_plugin_opt_name(), // This is where your data is stored in the database and also becomes your global variable name.
					'display_name'			=> $this->get_plugin_name(), // Name that appears at the top of your panel
					'display_version'		=> $this->get_plugin_version(), // Version that appears at the top of your panel
					'allow_sub_menu'   		=> true, // Show the sections below the admin menu item or not
					'menu_title'			=> __( 'RPS Plugin Framework', 'rps-plugin-framework' ),
					'page'		 	 		=> 'network_' . $this->get_plugin_name(),
					'google_api_key'  	 	=> '', // Must be defined to add google fonts to the typography module
					'dev_mode'      		=> false, // Show the time the page took to load, etc
					'customizer'     		=> false, // Enable basic customizer support
					'page_priority'   		=> null, // Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
					'page_type'       		=> 'submenu', // Must be set to 'submenu' if page_parent is to be used. Options are 'menu' and 'submenu'.
					'page_parent'    		=> 'settings.php', // Admin page where the interface should appear
					'page_permissions'  	=> 'manage_options', // Permissions needed to access the options panel.
					'menu_icon'     		=> '', // Specify a custom URL to an icon
					'last_tab'      		=> '', // Force your panel to always open to a specific tab (by id)
					'page_icon'     		=> 'icon-themes', // Icon displayed in the admin panel next to your menu_title
					'page_slug'     		=> 'network_' . $this->get_options_page_slug(), // Page slug used to denote the panel
					'save_defaults'   		=> true, // On load save the defaults to DB before user clicks save or not
					'admin_bar'     		=> false, // Show the panel pages on the admin bar
					'default_show'    		=> false, // If true, shows the default value next to each field that is not the default value.
					'default_mark'    		=> '', // What to print by the field's title if the value shown is default. Suggested: *
					'show_import_export' 	=> false, // Whether to display the Import/Export tab
					'help_tabs'     		=> array(),
					'help_sidebar'    		=> '', // __( '', $this->args['domain'] );
					'hide_reset' 			=> true,
					'database'				=> 'network',
					'network_admin'			=> true,
					'network_sites' 		=> true,
					'intro_text'			=> __( 'Enables automatic updates for plugins developed and supported by Red Pixel Studios.', 'rps-plugin-framework' ),
					'ajax_save'				=> true,
				);
			
			}
			else {
				
				$this->args = array(
					
					'opt_name'     			=> $this->get_plugin_opt_name(), // This is where your data is stored in the database and also becomes your global variable name.
					'display_name'			=> $this->get_plugin_name(), // Name that appears at the top of your panel
					'display_version'		=> $this->get_plugin_version(), // Version that appears at the top of your panel
					'allow_sub_menu'   		=> true, // Show the sections below the admin menu item or not
					'menu_title'			=> __( 'RPS Plugin Framework', $this->get_plugin_slug() ),
					'page'		 	 		=> $this->get_plugin_name(),
					'google_api_key'  	 	=> '', // Must be defined to add google fonts to the typography module
					'dev_mode'      		=> false, // Show the time the page took to load, etc
					'customizer'     		=> false, // Enable basic customizer support
					'page_priority'   		=> null, // Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
					'page_type'       		=> 'submenu', // Must be set to 'submenu' if page_parent is to be used. Options are 'menu' and 'submenu'.
					'page_parent'    		=> $this->get_options_page_parent(), // Admin page where the interface should appear
					'permissions'  			=> 'manage_options', // Permissions needed to access the options panel.
					'menu_icon'     		=> '', // Specify a custom URL to an icon
					'last_tab'      		=> '', // Force your panel to always open to a specific tab (by id)
					'page_icon'     		=> 'icon-themes', // Icon displayed in the admin panel next to your menu_title
					'page_slug'     		=> $this->get_options_page_slug(), // Page slug used to denote the panel
					'save_defaults'   		=> true, // On load save the defaults to DB before user clicks save or not
					'admin_bar'     		=> false, // Show the panel pages on the admin bar
					'default_show'    		=> false, // If true, shows the default value next to each field that is not the default value.
					'default_mark'    		=> '', // What to print by the field's title if the value shown is default. Suggested: *	      
					'show_import_export' 	=> false, // Whether to display the Import/Export tab
					'help_tabs'     		=> array(),
					'help_sidebar'    		=> '', // __( '', $this->args['domain'] );  
					'hide_reset' 			=> true,
					'intro_text'			=> __( 'Enables automatic updates for plugins developed and supported by Red Pixel Studios.', 'rps-plugin-framework' ),
					'ajax_save'				=> true,
				);
			
			}
		
		}
		
		/**
		 * Initialize sections and fields for settings form.
		 *
		 * @since 1.1.10
		 */
		public function _init_sections() {	
			
			if(is_multisite()):
				
				$this->network_sections[] = array(
					'title' => __( 'License', 'rps-plugin-framework' ),
					'desc' => '',
					'icon' => 'el-icon-key',
					'submenu' => true,
					'fields' => array(
						$this->license_key_field()
					)
				
				);
			
			else:
			
				$this->sections[] = array(
					'title' => __( 'License', 'rps-plugin-framework' ),
					'desc' => '',
					'icon' => 'el-icon-key',
					'submenu' => true,
					'fields' => array(	
						$this->license_key_field()		
					)
				);
				
			endif;
		}
				
		/**
		 * Load the text domain for l10n and i18n.
		 *
		 * @since 1.0
		 */
		public function _plugins_loaded() {
			parent::_plugins_loaded();
			load_plugin_textdomain( 'rps-plugin-framework', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'lang/' );
			$this->_include_redux_framework();
		}
			
		/**
		 * Get the default setting for a field if the setting is not recorded in the options table.
		 *
		 * @since 1.1.13
		 */
		public function _default_setting( $field_id = '' ) {
			$value = '';
			$settings = get_option( $this->get_plugin_opt_name() );
	
			// check if requested field value is set
			if ( isset( $settings[$field_id] ) ) :
			
				$value = $settings[$field_id];
				
			else :
			
				foreach ( $this->sections as $section ) :
				
					foreach ( $section['fields'] as $field ) :
					
						if ( $field_id == $field['id'] ) :
						
							$value = $field['default'];
							break 2;
						
						endif;
					
					endforeach;
				
				endforeach;
			
			endif;
			
			return $value;
		}
	}
	
	RPS_Plugin::invoke();
endif;

	
?>