<?php
/*
Plugin Name: Disable Email
Plugin URI: http://www.uprise.nl
Description: Prevents wp_mail from emailing from the given server locations.
Version: 1.0
Author: Arjan Snaterse
Author URI: http://www.uprise.nl
*/

if( !class_exists( 'UPRS_Disable_Email' ) ) {
	
	class UPRS_Disable_Email {
		var $option_name = 'disable_email_options';
		var $server_names = array();

		function __construct() {

			// Define server environments first
			$this->server_names = $this->get_server_names();

			if( is_admin() ) {
				add_action( 'admin_menu', array( &$this, 'init' ) );
				add_action( 'admin_init', array( &$this, 'initialize_settings' ) );
			}
		}

		function init() {
			add_options_page( __('Disable Email settings', 'disable-email'), 'Disable Email', 'manage_options', 'disable-email', array( &$this, 'settings_page' ) );
		}

		function is_disabled() {
			return $this->strpos_arr( $_SERVER['HTTP_HOST'], $this->server_names ) !== false;
		}

		function get_server_names() {
			$server_names = $this->get_option( 'server_names' );
			$server_names = explode( "\n", $server_names );

			return $server_names;
		}

		function settings_page() {

			echo '<div class="wrap">';
			echo '<div id="icon-options-general" class="icon32"><br></div>';
			echo '<h2>' . __( 'Disable Email settings', 'disable-email' ) . '</h2>';

			settings_errors();
			echo '<form method="post" action="options.php">';

			settings_fields( $this->option_name );
			do_settings_sections( $this->option_name );
			submit_button();

			echo '</form>';

			echo '<pre>';
			var_dump( $this->server_names );
			echo '</pre>';

			

			if( isset( $_GET['mail_test'] ) ) {
				$success = wp_mail( 'arjan@uprise.nl', 'test mail', 'blaaat' );
				echo '<pre>';
				var_dump( $success );
				echo '</pre>';
			}

			echo '</div><!-- .wrap -->';
		}

		function initialize_settings() {

			// Load translations
			load_plugin_textdomain( 'disable-email', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			// If the theme options don't exist, create them.  
			if( false == get_option( $this->option_name ) ) {  
				add_option( $this->option_name );  
			}

			register_setting( $this->option_name, $this->option_name );

			add_settings_section(  
		        'disable_email_section',
		        __('Disable Email Options', 'disable-email'),
		        create_function('', 'echo "<div class=\"description\">" . __("The Disable Email plugin will disable the functionality of the wp_mail() function. This may come handy in development environments.", "disable-email") . "</div>";'),
		        $this->option_name
		    );

			add_settings_field(
				'server_names',
				__( 'Server hostnames', 'wprf' ),
				array( &$this, 'add_field_server_names' ),
				$this->option_name,
				'disable_email_section'
			);		    
		}

		function add_field_server_names() {
			$server_names = $this->get_option( 'server_names' );
			
			echo '<textarea name="disable_email_options[server_names]" id="disable_email_options-server_names" class="large-text code" rows="5">' . $server_names . '</textarea>';
			echo '<br><small>' . __( 'Enter the host names for whoch you want to disable wp_mail function. Enter one hostname per line', 'disable-email' ) . '</small>';
		}


		function get_option( $option, $options_name = 'disable_email_options' ) {
			$return_option = false;
			
			if( $options_name != '' ) {
				$options = get_option( $options_name );
				$return_option = !empty($options[$option]) ? maybe_unserialize( $options[$option] ) : false;
			}
			else {
				$return_option = maybe_unserialize( get_option( $option ) );
			}

			return $return_option;
		}

		function strpos_arr( $haystack, $needle ) {
		    if( !is_array( $needle ) )
		    	$needle = array( $needle );
		    foreach( $needle as $what ) {
		    	if( $what == '' )
					return false;
		    	if( ($pos = strpos( $haystack, $what ) ) !== false )
		        	return $pos;
		    }

		    return false;
		}
	}
}

$UPRS_Disable_Email = new UPRS_Disable_Email();

// Disabled wp_mail by redefining it (long live pluggable functions, blugh)
if( $UPRS_Disable_Email->is_disabled() && !function_exists('wp_mail') ) {
	function wp_mail( $to = '', $subject, $message, $headers = '', $attachments = array() ) {
		return false;
	}
}


?>