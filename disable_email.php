<?php
/*
Plugin Name: Disable Email
Plugin URI: http://www.uprise.nl
Description: Prevents wp_mail from emailing from the given server location.
Version: 1.0
Author: Arjan Snaterse
Author URI: http://www.uprise.nl
*/

if( !class_exists( 'UPRS_Disable_Email' ) ) {
	
	class UPRS_Disable_Email {
		var $option_name = 'disable_email_options';
		var $server_name = '';
		var $override_email = '';

		function __construct() {

			$this->server_name = $this->get_server_name();
			$this->override_email = $this->get_override_email();

			if( is_admin() ) {
				add_action( 'admin_menu', array( &$this, 'init' ) );
				add_action( 'admin_init', array( &$this, 'initialize_settings' ) );
			}
		}

		function init() {
			add_options_page( __('Disable Email settings', 'disable-email'), 'Disable Email', 'manage_options', 'disable-email', array( &$this, 'settings_page' ) );
		}

		/**
		 * 
		 * return (void) - returns true if email is disabled. Email will be disabled by default. You can only enable email when the given hostname is EXACT the same as the current hostname of the server.
		 */
		function is_disabled() {
			return $_SERVER['HTTP_HOST'] === $this->server_name;
		}

		function get_server_name() {
			return $this->get_option( 'server_name' );
		}

		function get_override_email() {
			return $this->get_option( 'override_email' );
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
				'server_name',
				__( 'Server hostname', 'wprf' ),
				array( &$this, 'add_field_server_name' ),
				$this->option_name,
				'disable_email_section'
			);

			add_settings_field(
				'override_email',
				__( 'Override \'to\' address', 'wprf' ),
				array( &$this, 'add_field_override_email' ),
				$this->option_name,
				'disable_email_section'
			);
		}

		function add_field_server_name() {
			$server_name = $this->get_option( 'server_name' );
			
			echo '<input type="text" name="disable_email_options[server_name]" id="disable_email_options-server_name" class="regular-text" value="' . $server_name . '">';
			echo '<br><small>' . __( 'Enter the host name of the live website.', 'disable-email' ) . '</small>';
		}

		function add_field_override_email() {
			$override_email = $this->get_option( 'override_email' );
			
			echo '<input type="text" name="disable_email_options[override_email]" id="disable_email_options-override_email" class="regular-text" value="' . $override_email . '">';
			echo '<br><small>' . __( 'Fill in this field to force all emails to this email address. Leave empty to just disable email.', 'disable-email' ) . '</small>';
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
	}
}

$UPRS_Disable_Email = new UPRS_Disable_Email();
$GLOBALS['UPRS_Disable_Email'] = $UPRS_Disable_Email;

// Disabled wp_mail by redefining it (long live pluggable functions, blugh)
if( !$UPRS_Disable_Email->is_disabled() && !function_exists('wp_mail') ) {

	function wp_mail( $to = '', $subject, $message, $headers = '', $attachments = array() ) {
		global $UPRS_Disable_Email;

		// If override email is not set, just disable email. If set, overwrite $to address and send mail. Complete function is copied from pluggable.php right out of WordPress core.
		$to = $UPRS_Disable_Email->get_override_email();

		if( $to == '' )
			return false;

		// Compact the input, apply the filters, and extract them back out
		extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

		if ( !is_array($attachments) )
			$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );

		global $phpmailer;

		// (Re)create it, if it's gone missing
		if ( !is_object( $phpmailer ) || !is_a( $phpmailer, 'PHPMailer' ) ) {
			require_once ABSPATH . WPINC . '/class-phpmailer.php';
			require_once ABSPATH . WPINC . '/class-smtp.php';
			$phpmailer = new PHPMailer( true );
		}

		// Headers
		if ( empty( $headers ) ) {
			$headers = array();
		} else {
			if ( !is_array( $headers ) ) {
				// Explode the headers out, so this function can take both
				// string headers and an array of headers.
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}
			$headers = array();
			$cc = array();
			$bcc = array();

			// If it's actually got contents
			if ( !empty( $tempheaders ) ) {
				// Iterate through the raw headers
				foreach ( (array) $tempheaders as $header ) {
					if ( strpos($header, ':') === false ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts = preg_split('/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					// Explode them out
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew
					$name    = trim( $name    );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a From: header if it's there
						case 'from':
							if ( strpos($content, '<' ) !== false ) {
								// So... making my life hard again?
								$from_name = substr( $content, 0, strpos( $content, '<' ) - 1 );
								$from_name = str_replace( '"', '', $from_name );
								$from_name = trim( $from_name );

								$from_email = substr( $content, strpos( $content, '<' ) + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );
							} else {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( strpos( $content, ';' ) !== false ) {
								list( $type, $charset ) = explode( ';', $content );
								$content_type = trim( $type );
								if ( false !== stripos( $charset, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset ) );
								} elseif ( false !== stripos( $charset, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset ) );
									$charset = '';
								}
							} else {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						default:
							// Add it to our grand headers array
							$headers[trim( $name )] = trim( $content );
							break;
					}
				}
			}
		}

		// Empty out the values that may be set
		$phpmailer->ClearAddresses();
		$phpmailer->ClearAllRecipients();
		$phpmailer->ClearAttachments();
		$phpmailer->ClearBCCs();
		$phpmailer->ClearCCs();
		$phpmailer->ClearCustomHeaders();
		$phpmailer->ClearReplyTos();

		// From email and name
		// If we don't have a name from the input headers
		if ( !isset( $from_name ) )
			$from_name = 'WordPress';

		/* If we don't have an email from the input headers default to wordpress@$sitename
		 * Some hosts will block outgoing mail from this address if it doesn't exist but
		 * there's no easy alternative. Defaulting to admin_email might appear to be another
		 * option but some hosts may refuse to relay mail from an unknown domain. See
		 * http://trac.wordpress.org/ticket/5007.
		 */

		if ( !isset( $from_email ) ) {
			// Get the site domain and get rid of www.
			$sitename = strtolower( $_SERVER['SERVER_NAME'] );
			if ( substr( $sitename, 0, 4 ) == 'www.' ) {
				$sitename = substr( $sitename, 4 );
			}

			$from_email = 'wordpress@' . $sitename;
		}

		// Plugin authors can override the potentially troublesome default
		$phpmailer->From     = apply_filters( 'wp_mail_from'     , $from_email );
		$phpmailer->FromName = apply_filters( 'wp_mail_from_name', $from_name  );

		// Set destination addresses
		if ( !is_array( $to ) )
			$to = explode( ',', $to );

		foreach ( (array) $to as $recipient ) {
			try {
				// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
				$recipient_name = '';
				if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
					if ( count( $matches ) == 3 ) {
						$recipient_name = $matches[1];
						$recipient = $matches[2];
					}
				}
				$phpmailer->AddAddress( $recipient, $recipient_name);
			} catch ( phpmailerException $e ) {
				continue;
			}
		}

		// Set mail's subject and body
		$phpmailer->Subject = $subject;
		$phpmailer->Body    = $message;

		// Add any CC and BCC recipients
		if ( !empty( $cc ) ) {
			foreach ( (array) $cc as $recipient ) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient = $matches[2];
						}
					}
					$phpmailer->AddCc( $recipient, $recipient_name );
				} catch ( phpmailerException $e ) {
					continue;
				}
			}
		}

		if ( !empty( $bcc ) ) {
			foreach ( (array) $bcc as $recipient) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient = $matches[2];
						}
					}
					$phpmailer->AddBcc( $recipient, $recipient_name );
				} catch ( phpmailerException $e ) {
					continue;
				}
			}
		}

		// Set to use PHP's mail()
		$phpmailer->IsMail();

		// Set Content-Type and charset
		// If we don't have a content-type from the input headers
		if ( !isset( $content_type ) )
			$content_type = 'text/plain';

		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		$phpmailer->ContentType = $content_type;

		// Set whether it's plaintext, depending on $content_type
		if ( 'text/html' == $content_type )
			$phpmailer->IsHTML( true );

		// If we don't have a charset from the input headers
		if ( !isset( $charset ) )
			$charset = get_bloginfo( 'charset' );

		// Set the content-type and charset
		$phpmailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

		// Set custom headers
		if ( !empty( $headers ) ) {
			foreach( (array) $headers as $name => $content ) {
				$phpmailer->AddCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
			}

			if ( false !== stripos( $content_type, 'multipart' ) && ! empty($boundary) )
				$phpmailer->AddCustomHeader( sprintf( "Content-Type: %s;\n\t boundary=\"%s\"", $content_type, $boundary ) );
		}

		if ( !empty( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				try {
					$phpmailer->AddAttachment($attachment);
				} catch ( phpmailerException $e ) {
					continue;
				}
			}
		}

		do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );

		// Send!
		try {
			$phpmailer->Send();
		} catch ( phpmailerException $e ) {
			return false;
		}

		return true;
	}
}


?>