<?php

/**
 * RPS Plugin Framework Main Class
 *
 * @package RPS_Plugin_Framework
 * @since 2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RPS_Plugin_Framework', false ) ) :

abstract class RPS_Plugin_Framework {

	/**
	 * Initialize framework class variables.
	 *
	 * @since 1.0
	 */
	 
	public $plugin_active_redux_framework = false;
	public $ReduxFramework;
	public $args = array();
	public $sections = array();
	public $fields = array();
	public $defaults = array();
	public $NetworkReduxFramework;
	public $network_args = array();
	public $network_sections = array();
	
	protected abstract function upgrades();
	protected abstract function plugin_version();
	protected abstract function plugin_name();
	protected abstract function plugin_slug();
	protected abstract function plugin_prefix();
	protected abstract function manage_capability();
	protected abstract function options_page_parent();
	protected abstract function options_page_slug();
	protected abstract function checkbox_default_key();
	
	public function get_upgrades() {
		return ( is_array( $this->upgrades() ) ) ? $this->upgrades() : array();
	}
	
	public function get_plugin_version() {
		return $this->plugin_version();
	}
		
	public function get_plugin_name() {
		return $this->plugin_name();
	}
		
	public function get_plugin_slug() {
		return $this->plugin_slug();
	}
		
	public function get_plugin_prefix() {
		return $this->plugin_prefix();
	}
	
	public function get_plugin_opt_name() {
		return '_' . $this->plugin_prefix();
	}
		
	public function get_checkbox_default_key() {
		return $this->checkbox_default_key();
	}
		
	public function get_options_page_parent() {
		return $this->options_page_parent();
	}
		
	public function get_options_page_slug() {
		return $this->options_page_slug();
	}
		
	public function get_manage_capability() {
		return $this->manage_capability();
	}
		
	/**
	 * Invoke the plugin.
	 *
	 * @since 1.0
	 */
	protected function __construct() {		
		if ( is_multisite() ) add_action( 'network_admin_notices', array( $this, '_display_redux_dependency_notice' ) );
		add_action( 'admin_notices', array( $this, '_display_redux_dependency_notice' ) );
		add_action( 'admin_init', array( $this, '_dismiss_redux_dependency_notice' ) );
		
		add_filter( 'plugin_action_links_' . self::get_plugin_slug() . '/' . self::get_plugin_slug() . '.php', array( $this, '_settings_link' ) );
		add_action( 'update_option_' . $this->get_plugin_opt_name(), array( $this, '_update_capabilities' ) );
		
		//prevent output before headers when WP_DEBUG enabled and _deprecated_function is called
		add_filter( 'deprecated_function_trigger_error', function(){ return false; } );
		
	}

	/**
	 * Initialize the plugin framework.
	 *
	 * @since 1.0
	 */
	protected function _init() {		
		$this->upgrade();
		$this->_init_fields();
	}
	
	/**
	 * Load the text domain for l10n and i18n.
	 *
	 * @since 1.0
	 */
	protected function _plugins_loaded() {
		load_plugin_textdomain( 'rps-plugin-framework', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'lang/' );
	}

	/**
	 * Displays a notice about the redux framework dependency.
	 *
	 * @since 1.0
	 */
	public function _display_redux_dependency_notice() {
		self::_display_plugin_dependency_notice(
			array(
				'dependency_prefix' => 'redux_framework',
				'dependency_slug' => 'redux-framework',
				'dependency_name' => 'Redux Framework',
				'dependency_version' => '3.1.9',
				'dependency_dismiss_text' => __( 'Dismiss', 'rps-plugin-framework' ),
				'dependency_text' => _x( '%1$s uses the %2$s plugin to change default settings.', '1:this plugin name 2:plugin name required for this plugin', 'rps-plugin-framework' ),
				'dependency_version_text' => _x( '%1$s requires a minumum version %2$s of the %3$s plugin.', '1:this plugin name 2:plugin required version 3:plugin name required for this plugin', 'rps-plugin-framework' ),
				'dependency_about_link' => 'http://reduxframework.com',
				'dependency_directory' => 'redux-framework',
				'dependency_filename' => 'redux-framework.php',
			)
		);
	}
	
	/**
	 * Dismisses the notice for the redux framework.
	 *
	 * @since 1.0
	 */
	public function _dismiss_redux_dependency_notice() {
		self::_dismiss_plugin_dependency_notice(
			array(
				'dependency_prefix' => 'redux_framework',
			)
		);
	}

	/**
	 * Displays an admin notice on plugin activation.
	 *
	 * @since 1.0
	 * @todo May want to sanitize and validate argument inputs
	 */
	public function _display_plugin_dependency_notice( $args = array() ) {
		if ( empty( $args ) )
			return;
			
		$defaults = array(
			'dependency_prefix' => '',
			'dependency_slug' => '',
			'dependency_name' => '',
			'dependency_version' => '',
			'dependency_dismiss_text' => '',
			'dependency_text' => '',
			'dependency_version_text' => '',
			'dependency_about_link' => '',
			'dependency_directory' => '',
			'dependency_filename' => '',			
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		// bail if any of the args are empty
		foreach ( $args as $arg ) :

			if ( empty( $arg ) )
				return;

		endforeach;
		
		extract( $args, EXTR_SKIP );
				
		global $pagenow;
		global $current_user;
		
		$setting = 'setting_error_' . $this->get_plugin_prefix() . '_' . $dependency_prefix;
		
		if ( ! get_user_meta( $current_user->ID, $setting . '_dismiss' ) and 'plugins.php' == $pagenow and current_user_can( 'install_plugins' ) ) :
				
			if ( ! self::_is_plugin_active( $dependency_directory, $dependency_filename ) ) :
				echo '<div id="' . $setting . '" class="error settings-error"><p>';
				echo sprintf( $dependency_text, $this->get_plugin_name(),'<a href="' . $dependency_about_link . '" target="_blank">' . $dependency_name . '</a>' );
				echo '<a href="?' . $setting . '_dismiss=0" style="float:right;">' . $dependency_dismiss_text . '</a>';
				echo '</p></div>';
			endif;
			
			if ( !self::_is_plugin_minimum_version( $dependency_directory, $dependency_filename, $dependency_version) ) {
				echo '<div id="' . $setting . '" class="error settings-error"><p>';
				echo sprintf( $dependency_version_text, $this->get_plugin_name(), $dependency_version,'<a href="' . $dependency_about_link . '" target="_blank">' . $dependency_name . '</a>' );
				echo '<a href="?' . $setting . '_dismiss=0" style="float:right;">' . $dependency_dismiss_text . '</a>';
				echo '</p></div>';
			}
			
		endif;
	}
	
	/**
	 * Allows user to dismiss the admin notice permanently.
	 *
	 * @since 1.0
	 */
	public function _dismiss_plugin_dependency_notice( $args = array() ) {
		if ( empty( $args ) )
			return;
			
		$defaults = array(
			'dependency_prefix' => '',
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		// bail if any of the args are empty
		foreach ( $args as $arg ) :

			if ( empty( $arg ) )
				return;

		endforeach;
		
		extract( $args, EXTR_SKIP );
		
		global $current_user;
		$setting = 'setting_error_' . $this->get_plugin_prefix() . '_' . $dependency_prefix;
		
		if ( isset( $_GET[$setting . '_dismiss'] ) and '0' == $_GET[$setting . '_dismiss'] ) :
			add_user_meta( $current_user->ID, $setting . '_dismiss', 'true', true );
		endif;
	}
	
	/**
	 * Includes the Redux Framework.
	 *
	 * @since 1.0
	 */
	 public function _include_redux_framework() {
		$redux_plugin_directory = 'redux-framework';
		$redux_plugin_filename = 'redux-framework.php';
		
		//used when RPS Plugin Framework is in plugin directory
		$redux_plugin_path = trailingslashit( dirname( plugin_dir_path( __FILE__ ) ) ) . trailingslashit( $redux_plugin_directory );
		
		//used when RPS Plugin Framework is embedded in plugin directory
		$redux_plugin_path_embed = trailingslashit( dirname( dirname( plugin_dir_path( __FILE__ ) ) ) ) . trailingslashit( $redux_plugin_directory );
		
		$this->plugin_active_redux_framework = self::_is_plugin_active( $redux_plugin_directory, $redux_plugin_filename );
		
		if ( $this->plugin_active_redux_framework ) {
			
			if ( file_exists( $redux_plugin_path . 'ReduxCore/framework.php' ) ) {
				require_once( $redux_plugin_path . $redux_plugin_filename );
			}
			elseif( file_exists( $redux_plugin_path_embed . 'ReduxCore/framework.php' ) ) {
				require_once( $redux_plugin_path_embed . $redux_plugin_filename );
			}
			
			if ( is_multisite() ) $this->NetworkReduxFramework = new ReduxFramework( $this->network_sections, $this->network_args );
			$this->ReduxFramework = new ReduxFramework( $this->sections, $this->args );			
		}
	 }

	/**
	 * Retrieves roles field options for Redux Framework.
	 *
	 * @return array
	 * @since 1.0
	 */
	public function _role_field_options() {
		global $wp_roles;
		
		if ( ! isset( $wp_roles ) ) 
			$wp_roles = new WP_Roles();
		
		$output = array();
		
		foreach( $wp_roles->roles as $key => $value ) :
			
			$output[$key] = $value['name'];
				
		endforeach;
		
		return $output;
	}
	
	/**
	 * Retrieves roles field defaults for Redux Framework.
	 *
	 * @return array
	 * @since 1.0
	 */
	public function _role_field_defaults( $roles_with_capability = array() ) {
		_deprecated_function( __FUNCTION__, '1.0.3', null );
	}
	
	/**
	 * Initializes an array of fields from the defined sections.
	 *
	 * @since 2.1.3
	 */
    public function _init_fields() {
	    
        foreach ( $this->sections as $section ) {
			
			if ( isset( $section['fields'] ) ) {
			
				foreach( $section['fields'] as $field ) {
			
					if ( isset( $field['id'] ) ) {
					
						$this->fields[$field['id']] = $field;
						
					}
			
				}
			
			}
			
        }
        
    }
	
	/**
	 * Retrieves the default value for a specific shortcode attribute.
	 *
	 * @since 2.1.3
	 */
	public function _shortcode_default( $id = '' ) {
		$defaults = self::_get_defaults();
		return isset( $defaults[$id] ) ? $defaults[$id] : '';
	}

	/**
	 * Builds an array of default values for the shortcode attributes.
	 *
	 * @return array Array of defaults.
	 * @since 2.1.3
	 */
	public function _get_defaults() {
		static $defaults_array;
		
		if ( isset( $this->fields ) and empty( $defaults_array ) ) :
			
			$defaults_array = array();
			
			$plugin_settings = null;
			
			if ( $this->plugin_active_redux_framework ) :
				
				if ( isset( $this->args['opt_name'] ) ) :
					
					$plugin_settings = get_option( $this->args['opt_name'] );
					
				endif;
				
			endif;
			
			foreach( $this->fields as $field ) :
				
				$setting = '';
				
				// plugin setting available
				if ( isset( $plugin_settings[$field['id']] )  ) :
				
					$setting = $plugin_settings[$field['id']];
				
				// plugin setting not available so get the default value from the fields array
				elseif ( isset( $field['default'] ) ) :
				
					$setting = $field['default'];
				
				endif;
				
				// process the setting to get the value based on the field type
				if( is_array( $setting ) ) :
					
					switch ( true ) :
						
						// a checkbox which has one or more possible values
						case ( 'checkbox' == $field['type'] ) :
							$defaults_array[$field['id']] = ( ! is_array( $setting ) ) ? $setting[self::get_checkbox_default_key()] : '';
							break;
						
						// a sortable list of checkboxes with key as the value and value as the switch
						case ( 'sortable' == $field['type'] and 'checkbox' == $field['mode'] ) :
							$setting_values = array();
							
							foreach ( $setting as $key => $value ) :
								if ( ! empty( $value ) ) $setting_values[] = $key;
							endforeach;
														
							$defaults_array[$field['id']] = $setting_values;
							break;
							
					endswitch;
					
				else :
				
					$defaults_array[$field['id']] = $setting;
				
				endif;					
			
			endforeach;
			
		endif;
		
		return $defaults_array;
	}

	/**
	 * Sanitizes and validates the values passed through shortcode attributes.
	 * If not within tolerance then fallback to the shortcode default.
	 *
	 * @since 2.1.3
	 */
	public function _filter_shortcode_attribute( $field = '', $value = '' ) {
		$filtered_value = '';
		
		if ( '' != $field and '' != $value ) {
		
			if( array_key_exists( $field, $this->fields ) ) {
		
				$field_props = $this->fields[$field];
				$filtered_value = '';
				
				switch ( true ) {
					
					// radio and select menus - 'options' is an array and 'default' is a string identifying the default key in the options array
					case ( 'radio' == $field_props['type'] or 'select' == $field_props['type'] ) :
						$value = sanitize_text_field( $value );
						$filtered_value = ( array_key_exists( $value, $field_props['options'] ) ) ? $value : self::_shortcode_default( $field );
						//error_log('RPS Plugin Framework: '.$value);
						break;
						
					// sortable checkbox - list of checkbox items that may be sorted but provided as comma separated values via shortcode
					case ( 'sortable' == $field_props['type'] and 'checkbox' == $field_props['mode'] ) :
						$value = array_map( 'trim', explode( ',', trim( sanitize_text_field( $value ) ) ) );
						
						foreach ( $value as $element ) :
							if ( array_key_exists( $element, $field_props['options'] ) )
								$filtered_value[] = $element;
						endforeach;
						
						if ( empty( $filtered_value ) and ! is_array( $filtered_value ) )
							$filtered_value = self::_shortcode_default( $field );
						break;
					
					// slider - value is a string number with a 'min' and 'max' defined
					case ( 'slider' == $field_props['type'] ) :
						$value = intval( sanitize_text_field( $value ) );
						$filtered_value = ( $value >= intval( $field_props['min'] ) and $value <= intval( $field_props['max'] ) ) ? $value : self::_shortcode_default( $field );
						break;
						
					// checkbox - value is either '1', on or '0' off
					case ( 'checkbox' == $field_props['type'] or 'switch' == $field_props['type'] ) :
						$value = sanitize_text_field( $value );
						$filtered_value = ( 'true' == $value ) ? '1' : '0';
						break;
						
					// just sanitize the text field
					default :
						$value = sanitize_text_field( $value );
	
				}
			
			}
			else {
			
				// handle attributes for fields not set in $this->fields
				$value = sanitize_text_field( $value );
				$value = explode( ',', $value );
				$value = array_map( 'trim', $value );
						
			}
			
		}
		
		return ( '' != $filtered_value ) ? $filtered_value : $value;
	}

	/**
	 * Helper method to check if a specified plugin is active.
	 * Should be called at plugins_loaded hook.
	 *
	 * @return boolean True if plugin is active.
	 * @since 1.0
	 */
	public function _is_plugin_active( $plugin_directory = '', $plugin_filename = '' ) {
   		$result = false;

		if ( ! function_exists( 'is_plugin_active_for_network' ) or ! function_exists( 'is_plugin_active' ) )
    		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$active_plugins = array();
		$active_site_plugins = array_values( get_option( 'active_plugins', array() ) );
		$active_network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );

		$active_plugins = array_merge( $active_site_plugins, $active_network_plugins );
		
		if ( in_array( trailingslashit( $plugin_directory ) . $plugin_filename, $active_plugins ) )
			$result = true;
				
		return $result;
	}

	/**
	 * Helper method to check if a specified plugin is active.
	 * Should be called at plugins_loaded hook.
	 *
	 * @return boolean True if plugin is at least the version indicated.
	 * @since 1.0
	 */
	public function _is_plugin_minimum_version( $plugin_directory = '', $plugin_filename = '', $minimum_version='' ) {
   		$result = false;
   		
   		// get all the information for the plugins
   		$all_plugins = get_plugins();
   		
   		if ( isset( $all_plugins[trailingslashit( $plugin_directory ) . $plugin_filename] ) ) {
	   		$current_plugin = $all_plugins[trailingslashit( $plugin_directory ) . $plugin_filename];
	   		
	   		// split the versions
	   		$minimum_version_array = explode( '.', $minimum_version );
	   		$current_version_array = explode( '.', $current_plugin['Version'] );
	   		
	   		while ( count( $minimum_version_array ) < count( $current_version_array ) ) {
		   		$minimum_version_array[] = 0;
	   		}
	   		while ( count( $current_version_array ) < count( $minimum_version_array ) ) {
		   		$current_version_array[] = 0;
	   		}
	   		
	   		$resolved = false;
	   		$i = 0;
	   		
	   		while( !$resolved && ( $i < count( $current_version_array ) ) ) {
		   		if ( intval( $current_version_array[$i] ) != intval( $minimum_version_array[$i] ) ) {
			   		$resolved = true;
			   		
			   		if ( intval( $current_version_array[$i] ) > intval( $minimum_version_array[$i] ) ) {
				   		$result = true;
			   		}
		   		}
		   		
		   		$i++;
	   		}
	   		
	   		if ( !$resolved ) { // they have to be equal versions
		   		$result = true;
	   		}
   		}
				
		return $result;
	}
	
	/**
	 * Add the plugin's base capability to specific roles if they exist.
	 *
	 * @since 1.0
	 */
	public function _set_plugin_caps( $permissions = array() ) {
		_deprecated_function( __FUNCTION__, '1.0.3', null );
	}
	
	/**
	 * Adds capability by first removing it from all roles then adding only to administrator role
	 *
	 * @since 1.0.3
	 * @deprecated 2.1.5
	 */
	public function _add_capability_on_activate( $field = '' ) {
		_deprecated_function( __FUNCTION__, '2.1.5', null );
		if ( empty( $field ) )
			return;
			
		global $wp_roles;
		$role_field_defaults = self::_set_role_field_defaults();
		
		if ( ! isset( $wp_roles ) ) 
			$wp_roles = new WP_Roles();
						
		foreach( $wp_roles->roles as $key => $value ) {

			$role = get_role( $key );
			$role->remove_cap( $this->get_manage_capability() );

		}
		
		if ( is_array( $role_field_defaults ) ) {

			foreach( $role_field_defaults as $role_slug => $value ) {
				
				$role = get_role( $role_slug );
				if ( $role !== null )
					$role->add_cap( $field );
				
			}
			
		}

	}
	
	/**
	 * Sets roles that should by default have the capability unless set otherwise.
	 *
	 * @since 2.1.1
	 */
	public function _set_role_field_defaults() {
		return array( 'administrator' => '1', 'infoapp' => '1' );
	}

	/**
	 * Adds capability by first removing it from all roles then adding only to administrator role.
	 * If permissions have been granted by the plugin options settings then respect them.
	 *
	 * @since 1.0.3
	 */
	public function _update_capability( $capability = '', $plugin_opt_name = '' ) {
		if ( empty( $capability ) )
			return;
			
		global $wp_roles;
		$role_field_defaults = array();
		
		if ( ! isset( $wp_roles ) ) 
			$wp_roles = new WP_Roles();
						
		foreach( $wp_roles->roles as $key => $value ) {

			$role = get_role( $key );
			$role->remove_cap( $capability );

		}
		
		if ( ! empty( $plugin_opt_name ) ) {
			
			$plugin_settings = get_option( $plugin_opt_name );

			if ( is_array( $plugin_settings ) and array_key_exists( $capability, $plugin_settings ) and ! empty( $plugin_settings[$capability] ) ) {

				$role_field_defaults = $plugin_settings[$capability];
				
			}
			
		}
		elseif ( empty( $role_field_defaults ) ) {
			
			$role_field_defaults = self::_set_role_field_defaults();

		}
		
		if ( is_array( $role_field_defaults ) and ! empty( $role_field_defaults ) ) {

			foreach( $role_field_defaults as $role_slug => $value ) {
				
				$role = get_role( $role_slug );
				
				if ( $role !== null and $value == 1 )
					$role->add_cap( $capability );
				
			}
			
		}

	}
	
	/**
	 * Generates a link to the Settings page on the plugin entry on the plugins page.
	 *
	 * @since 1.0
	 */
	public function _settings_link( $links ) {
	    if ( $this->plugin_active_redux_framework ) :
		    $settings_link = '<a href="' . $this->get_options_page_parent() . '?page=' . $this->get_options_page_slug() . '">' . __( 'Settings', 'rps-plugins-framework' ) . '</a>';
		  	array_push( $links, $settings_link );
		endif;
		
	  	return $links;
	}
	
	/**
	 * Define a standard for the license key field to be used in _init_sections.
	 *
	 * @since 1.0
	 * @todo preserve the license key in the event of a reset
	 */
	public function license_key_field() {
		$license_key_field = array();
		
		$license_key_field['id'] = 'license_key';
		$license_key_field['type'] = 'text';
		$license_key_field['title'] = __( 'Keys', 'rps-plugin-framework' );
		$license_key_field['default'] = '';
		$license_key_field['description'] = ( is_multisite() ) ? __( 'License key for all sites on the network.', 'rps-plugins-framework' ) : __( 'License key for this site.', 'rps-plugins-framework' );
		
		return $license_key_field;
	}
	
	/**
	 * Safely upgrade the plugin from older versions.
	 *
	 * @since 1.0
	 */
	private function upgrade() {
		// Determine what version of the plugin is being used.
		$version = get_option( $this->get_plugin_opt_name() . '_version' );
		
		// If this is the current version of the plugin, abort the upgrade.
		if ( $this->get_plugin_version() == $version )
			return;
		
		// If there is no version, this is a clean install.
		if ( false === $version )
			$version = $this->get_plugin_version();
		
		// Upgrades should be added sequentially, from oldest to newest.
		$upgrades = $this->get_upgrades();
		
		// Perform necessary upgrades.
		foreach ( $upgrades as $upgrade )
			if ( version_compare( $version, $upgrade[ 'version' ], '<' ) )
				call_user_func( $upgrade[ 'routine' ] );
		
		// Update the plugin version to the current version of the plugin.
		update_option( $this->get_plugin_opt_name() . '_version', $this->get_plugin_version() );
	}
	
	/**
	 * Validator to handle parsing an options text field.
	 *
	 * @since 2.1.3
	 */
	public function _redux_validate_text( $field, $value, $existing_value )  {
	    $error = false;
	    $raw_values = array(); // the initial value trimmed, stripped of tags and converted to an array of trimmed values
	    $filtered_values = array(); // used to build a list of clean directory names
	    $error_values = array(); // used to track directories that fail to pass the test
	    $special_chars = array("?", "[", "]", "/", "\\", "=", "<",">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}");	    
	    $raw_values = array_map( 'trim', explode( ',', strip_tags( trim( $value ) ) ));
	    
	    foreach( $raw_values as $string ) {
		    //strip all tags
		    $string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		    $string = strip_tags( $string );
		    
		    //check for the presence of special characters
	    	if ( '' != $string ) :
	    	
	    		$special_char_found = false;
	    	
				// Iterate through the special characters.
				foreach ( $special_chars as $character ) :
					
					if ( stristr( $string, $character ) ) :
						
						$special_char_found = true;
						$error_values[] = $string;
						break;
					
					endif;
					
				endforeach;
			
				if ( ! $special_char_found )
					$filtered_values[] = $string;
				
	    	endif;
		    
	    }
	        
	    $value = ( ! empty( $filtered_values ) ) ? implode( ',', $filtered_values ) : '';
	
	    if ( ! empty( $error_values ) ) :
	
	        $error = true;
	        $field['msg'] = __( 'The following were removed from the list because they contained invalid characters: ', 'rps-plugin-framework' ) . implode( ',', $error_values ) . '.';
	
	    endif;
	    
	    $return['value'] = $value;
	
	    if ( $error ) {
	        $return['error'] = $field;
	    }
	
	    return $return;
	}

}

endif;

?>