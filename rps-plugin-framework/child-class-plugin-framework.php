<?php

/**
 * RPS Plugin Framework Main Class
 *
 * @package RPS_Plugin_Framework
 * @since 2.0
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) {
    die;
}

require_once dirname(__FILE__) . '/class-plugin-framework.php';

if ( ! class_exists( 'RPS_Plugin_Framework_Standalone', false ) ) :

abstract class RPS_Plugin_Framework_Standalone extends RPS_Plugin_Framework{
		
	/**
	 * Invoke the plugin.
	 *
	 * @since 1.0
	 */
	protected function __construct() {
		parent::__construct();	
	}

	/**
	 * Initialize the plugin framework.
	 *
	 * @since 1.0
	 */
	protected function _init() {
		parent::_init();
		add_filter( 'site_transient_update_plugins', array( &$this, '_pre_set_site_transient_update_plugins' ) );
		add_filter( 'plugins_api', array( &$this, '_plugins_api' ), 10, 3 );
	}
	
	/**
	 * Check for updates to the plugin.
	 *
	 * @since 1.0
	 */
	public function _pre_set_site_transient_update_plugins( $transient ) {
		$license_key = '';
		
		if ( empty( $transient->checked ) )
			return $transient;
		
		$plugin_slug = self::get_plugin_slug();
		$plugin_opt_name = self::get_plugin_opt_name();
		
		if ( ! isset( $transient->checked[ $plugin_slug . '/' . $plugin_slug . '.php' ] ) )
			return $transient;
		
		if( is_multisite() ):
		
			$license_key = $this->get_license_key('network_',  $plugin_opt_name);
		
		else:
		
			$license_key = $this->get_license_key('', $plugin_opt_name);
		
		endif;
		
		$request_string = $this->prepare_request( 'plugin_update_check', $license_key, $plugin_slug, $this->get_plugin_version() );
	
		$response = wp_remote_post(
			$this->get_api_url(),
			$request_string
		);
		
		if ( ! is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
			$wp_meta = @unserialize( wp_remote_retrieve_body( $response ) );
			if ( is_object( $wp_meta ) && ! empty( $wp_meta ) ) {				
				$obj = new \stdClass();
				$obj->slug = $wp_meta->slug;
				$obj->new_version = $wp_meta->new_version;
				if( isset( $wp_meta->url ) )
					$obj->url = $wp_meta->url; 
				if( isset( $wp_meta->package ) )
					$obj->package = $wp_meta->package;
				$obj->plugin = $plugin_slug . '/' . $plugin_slug . '.php';
				
				$transient->response[ $plugin_slug . '/' . $plugin_slug . '.php' ] = $obj;
			}
		}
		
		return $transient;
	}
	
	/**
	 * Handle regular API calls for WordPress plugins. This should post information
	 * to our API server and do whatever needs to be done with that information.
	 *
	 * @since 1.0
	 * @todo May need to figure out a way to set the site option for a license key in case of a multisite config wanting to use a single license without having to add the string to every site's options.
	 */
	public function _plugins_api( $default, $action, $args ) {
		$plugin_name = self::get_plugin_name();
		$plugin_slug = self::get_plugin_slug();
		$plugin_opt_name = self::get_plugin_opt_name();
		
		if( is_multisite() ):
		
			$license_key = $this->get_license_key('network_', $plugin_opt_name);
		
		else:
		
			$license_key = $this->get_license_key('', $plugin_opt_name);
		
		endif;
		
		if ( ! isset( $args->slug ) || $plugin_slug != $args->slug )
			return $default;
		
		$update_plugins = get_site_transient( 'update_plugins' );
		
		if ( false === $update_plugins || ! isset( $update_plugins->response[ $plugin_slug . '/' . $plugin_slug . '.php' ] ) )
			return new WP_Error( 'plugins_api_failed', 'Unable to determine what version to upgrade to.', 'update_plugins transient did not have required information.' );
				
		$request_string = $this->prepare_request($action, $license_key, $plugin_slug, $this->get_plugin_version() );
				
		$response = wp_remote_post(
			$this->get_api_url(),
			$request_string
		);
		
		if ( is_wp_error( $response ) ) {
			$wp_meta = new WP_Error( 'plugins_api_failed', 'An Unexpected HTTP Error occurred during the API request.', $response->get_error_message() );
		} else {
			$wp_meta = @unserialize( wp_remote_retrieve_body( $response ) );
			
			if ( ! is_object( $wp_meta ) || empty( $wp_meta ) )
				$wp_meta = new WP_Error( 'plugins_api_failed', 'An unknown error occurred.', wp_remote_retrieve_body( $response ) );
		}
		
		//add the plugin name to wp_meta before it is returned to eliminate notice about missing property
		$wp_meta->name = $plugin_name;
		$wp_meta->plugin = $plugin_slug . '/' . $plugin_slug . '.php';

		return $wp_meta;
	}
	
	private function get_license_key($network_prefix, $plugin_opt_name){
		$license_key = '';
		if( isset( $GLOBALS[$network_prefix . $plugin_opt_name]['same_license_key'] ) and $GLOBALS[$network_prefix . $plugin_opt_name]['same_license_key'] === '1' ):
			$license_key = $GLOBALS[$network_prefix . '_rps_plugin_framework']['license_key'];
		elseif( isset( $GLOBALS[$network_prefix . $plugin_opt_name]['license_key'] ) ):
			$license_key = $GLOBALS[$network_prefix . $plugin_opt_name]['license_key'];
		endif;
		
		return $license_key;
	}
	
	/**
	 * Prepares the parameters for the api Request
	 *
	 * @since 2.1.7
	 */
	private function prepare_request($action, $license_key, $plugin_slug, $version) {
		global $wp_version;
		
		return array(
			'body' => array(
				'action' => $action,
				'license_key' => $license_key,
				'package' => $plugin_slug,
				'version' => $version
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
		);	
	}
		
	private function get_api_url() {
		return 'https://api.redpixel.com/wordpress/automatic-updates/packages.php';
	}
	
	/**
	 * Define a standard for the license key field to be used in _init_sections.
	 *
	 * @since 1.0
	 * @todo preserve the license key in the event of a reset
	 */
	public function conditional_license_key_field() {
		$license_key_field = array();
		
		$license_key_field['id'] = 'license_key';
		$license_key_field['type'] = 'text';
		$license_key_field['title'] = __( 'Keys', 'rps-plugin-framework' );
		$license_key_field['default'] = '';
		$license_key_field['required'] = array('same_license_key','equals','0');
		$license_key_field['description'] = ( is_multisite() ) ? __( 'License key for all sites on the network.', 'rps-plugins-framework' ) : __( 'License key for this site.', 'rps-plugins-framework' );
		
		return $license_key_field;
	}
	
	/**
	 * Define a boolean to use same license key as rps-plugin-framework
	 *
	 * @since 1.0
	 * @todo preserve the license key in the event of a reset
	 */
	public function use_same_license_key() {
		$license_key_switch = array(
			'id' => 'same_license_key',
			'type' => 'switch',
			'title' => __( 'Use RPS-Plugin-Framework License Key', 'rps-plugin-framework' ),
			'default' => false,
			'description' => __( 'Use the license key specified in the RPS Plugin Framework plugin.', 'rps-plugin-framework') 
		);
		
		return $license_key_switch;
	}
	
	/**
	 * Gets the rps-plugin-framework license key
	 *
	 * @since 1.0
	 */
	private function get_rps_plugin_framework_license_key(){
		
		$license_key = '';
		return $license_key;
	}


}

endif;

?>