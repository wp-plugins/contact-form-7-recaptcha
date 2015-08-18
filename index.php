<?php
/*
Plugin Name: Contact Form 7 - reCAPTCHA
Description: The new reCAPTCHA for Contact Form 7 forms. Use shortcode [recaptcha].
Version: 1.1.0
Author: iambriansreed
*/

class Contact_Form_7_reCAPTCHA
{
    var $site_key;
    var $secret_key;
    var $error_message;
    var $ids;
    var $config;
    
    function __construct()
    {
        $this->ids = array();
        
        $this->config = defined('reCAPTCHA_site_key') && defined('reCAPTCHA_secret_key') && defined('reCAPTCHA_error_message');
        
        if( $this->config )
        {
            $this->site_key = reCAPTCHA_site_key;
            $this->secret_key = reCAPTCHA_secret_key;
            $this->error_message = reCAPTCHA_error_message;
        }
        else
        {
            $this->site_key = get_option( 'contact_form_7_recaptcha_site_key' );
            $this->secret_key = get_option( 'contact_form_7_recaptcha_secret_key' );
            $this->error_message = get_option( 'contact_form_7_recaptcha_error_message' );
        }
        
        $this->error_message = empty( $this->error_message ) ? "Please confirm you are not a robot." : $this->error_message;
        
        if( is_admin() )
        {
            if( ! $this->config )
            {
                add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            }
        }
        else
        {
            if ( ! empty( $this->site_key ) && ! empty( $this->secret_key ) )
            {
                add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
                add_action( 'wp_footer', array( $this, 'wp_footer' ), 999 );
                add_filter( 'wpcf7_validate', array( $this, 'wpcf7_validate' ), 999 );
            }
            
            add_action( 'wpcf7_init',  array( $this, 'wpcf7_init' ) );
        }
    }
    
    function wp_enqueue_scripts()
    {   
        wp_register_script(
            'contact_form_7_recaptcha_script',
            plugins_url( '/script.js' , __FILE__ ),
            array( 'jquery', 'google_recaptcha_script' ),
            '1.0.0',
            true
        );
        
        wp_register_script(
            'google_recaptcha_script',
            'https://www.google.com/recaptcha/api.js?onload=contact_form_7_recaptcha_callback&render=explicit',
            array( 'jquery' ),
            '1.0.0',
            true
        );
        
        wp_localize_script(
            'contact_form_7_recaptcha_script', 
            'contact_form_7_recaptcha_data', 
            array(
                'sitekey' => $this->site_key
            )
        );
    }
    
    function wp_footer()
    {
        if( count( $this->ids ) )
        {
            wp_print_scripts('contact_form_7_recaptcha_script');
        }
    }
    
    function wpcf7_init()
    {
        wpcf7_add_shortcode( 'recaptcha', array( $this, 'shortcode' ), false );
    }
    
    function wpcf7_validate( $result )
    {
        if ( isset( $_POST['contact_form_7_recaptcha'] ) )
        {
            $error = true;
            $error_message = $this->error_message;
            
            if( ! empty( $_POST['g-recaptcha-response'] ) )
            {
                $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . $this->secret_key . '&response=' . $_POST['g-recaptcha-response'];
                $request = wp_remote_get( $url );
                
                if( is_wp_error( $request ) )
                {   
                    $error = true;
                    $error_message = "An error occured when verifying the reCaptcha. The form can not be submitted at this time.";
                    if( is_user_logged_in() )
                    {
                        $error_message .= " The errors reported were:<br>";
                        foreach( $request->errors as $k=>$values )
                            $error_message .= "<strong>$k</strong>: " . implode( '<br>', $values ) . "<br>";
                        $error_message .= "These errors may occur on servers which don't support SSL with cURL.";
                    }
                }
                else
                {
                    $body = wp_remote_retrieve_body( $request );
                    $response = json_decode( $body );
                    $error = ! ( isset ( $response->success ) && 1 == $response->success );
                }
            }
            
            if( $error )
            {
                $result->invalidate( array( 'type' => 'recaptcha', 'name' => 'g-recaptcha-explicit' ), $error_message ); //. '<pre>' . print_r( $request, 1) . '</pre>');
            }
        }
        
        return $result;
    }
            
    function shortcode( $atts )
    {
        if ( empty( $this->site_key ) || empty( $this->secret_key ) )
        {
            if( is_user_logged_in() && current_user_can( 'manage_options' ) )
            {
                return '<p style="color: red;">You must define the reCAPTCHA <a style="color: red" href="'. admin_url( 'admin.php?page=wpcf7_recaptcha_settings' ) .'">site key and secrect key</a>.</p>';
            }
            
            return '';
        }
        
        $id = 'g-recaptcha-' . wp_generate_password(15, false);
        $this->ids[] = $id;
        return '<input type="hidden" name="contact_form_7_recaptcha" value="' . $id . '" class="g-recaptcha-explicit-id"><div id="' . $id . '"></div><span class="wpcf7-form-control-wrap g-recaptcha-explicit" data-sitekey="'.$this->site_key.'"></span>';
    }
    
    function admin_menu()
    {
        add_submenu_page (
			'wpcf7',
			'reCAPTCHA',
			'reCAPTCHA',
			'manage_options',
			'wpcf7_recaptcha_settings',
			array( $this, 'wpcf7_recaptcha_settings_html' )
		);
    }
    
    function wpcf7_recaptcha_settings_html()
    {
        if ( !current_user_can( 'manage_options' ) )
        {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        if ( !empty ( $_POST['update'] ) )
        {
            $this->site_key = !empty ( $_POST['contact_form_7_recaptcha_site_key'] ) ? sanitize_text_field( $_POST['contact_form_7_recaptcha_site_key'] ) : '';
            update_option ( 'contact_form_7_recaptcha_site_key', $this->site_key );
            
            $this->secret_key = !empty ( $_POST['contact_form_7_recaptcha_secret_key'] ) ? sanitize_text_field( $_POST['contact_form_7_recaptcha_secret_key'] ) : '';
            update_option ( 'contact_form_7_recaptcha_secret_key', $this->secret_key );
            
            $contact_form_7_recaptcha_message = !empty ( $_POST['contact_form_7_recaptcha_error_message'] ) ? sanitize_text_field( $_POST['contact_form_7_recaptcha_error_message'] ) : '';
            update_option ( 'contact_form_7_recaptcha_error_message', $contact_form_7_recaptcha_message );
      	
            $updated = 1;
        }
        ?>
    		<div class="wrap contact-form-7-recaptcha-wrap">
    			<h2>Contact Form 7 - reCAPTCHA Settings</h2>
    			<p>To use reCAPTCHA, you need to <a href="http://www.google.com/recaptcha/admin" target="_blank">sign up for an API key pair</a> for your site. The key pair consists of a site key and secret. The site key is used to display the widget on your site. 
                    The secret authorizes communication between your application backend and the reCAPTCHA server to verify the user's response. The secret needs to be kept safe for security purposes.
                    Once you have generated the keys, add them below or <a href="#contact-form-7-recaptcha-config" onclick="jQuery('#contact-form-7-recaptcha-config').toggle();">add the keys to your wp-config.php</a> to hide this page altogether.
    			<br>To add reCAPTCHA to a Contact Form 7 form, add <code>[recaptcha]</code> in your form. For best results this shortcode should be added before the submit button.</p>
                <div id="contact-form-7-recaptcha-config" style="display: none;">
                    <h4>Add the keys to your wp-config.php file.</h4>
<textarea readonly rows="3" cols="75">define( 'reCAPTCHA_site_key', 'YOUR_SITE_KEY_HERE' );
define( 'reCAPTCHA_secret_key', 'YOUR_SECRET_KEY_HERE' );
define( 'reCAPTCHA_error_message', 'Please confirm you aren't a robot.' );</textarea>
                </div>
                
    			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST" enctype="multipart/form-data">
    				<input type="hidden" value="1" name="update" />
    				<ul>
    					<li><input type="text" style="width: 370px;" value="<?php echo get_option( 'contact_form_7_recaptcha_site_key' ); ?>" name="contact_form_7_recaptcha_site_key" /> Site key</li>
    					<li><input type="text" style="width: 370px;" value="<?php echo get_option( 'contact_form_7_recaptcha_secret_key' ); ?>" name="contact_form_7_recaptcha_secret_key" /> Secret key</li>
    					<li><input type="text" style="width: 370px;" value="<?php echo get_option( 'contact_form_7_recaptcha_error_message' ); ?>" name="contact_form_7_recaptcha_error_message" /> Invalid captcha error message</li>
    				</ul>
    	   			<input type="submit" class="button-primary" value="Save Settings">
    			</form>
    			<?php if ( ! empty( $updated ) ): ?>
       				<p>Settings were updated successfully!</p>
       			<?php endif; ?>
    		</div>		
        <?php 
    }
}

new Contact_Form_7_reCAPTCHA();
