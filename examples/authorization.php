<?php
/**
 * Example to demonstrate authorization using WP_Twitter_Client.
 * This demo will add sub menu page to the Settings menu where it renders
 * a button to authorize the app. Once authorized, you should be able to
 * see the twitter screen_name.
 *
 * Copyright (C) 2013  Akeda Bagus <admin@gedex.web.id>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Adjust the PATH to where `WP_Twitter_Client.php` resides
require_once( STYLESHEETPATH . '/WP_Twitter_Client/WP_Twitter_Client.php' );

// Go to https://dev.twitter.com/apps, create an app and paste
// update the consumer_key and consumer_secret below
define( 'CONSUMER_KEY',    'DL9ziNzAbLmShjW8sSYxw' );
define( 'CONSUMER_SECRET', 'l5NCQTBHv4VNVAIx0rb6R1oRoh21XPuqiy0kAfw8xnQ' );

class Example_Auth_WP_Twitter_Client {
	/**
	 * URL to Authorize the app.
	 *
	 * @constant AUTH_URL
	 */
	const AUTH_URL = 'https://api.twitter.com/oauth/authorize?oauth_token=%s';

	/**
	 * Option's name to store access token.
	 *
	 * @constant TOKENS_OPTION
	 */
	const TOKENS_OPTION = 'wp_twitter_client_tokens';

	/**
	 * Option's name to store temporary error message.
	 *
	 * @constant ERROR_OPTION
	 */
	const ERROR_OPTION = 'wp_twitter_client_error';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->page_title  = __( 'Example Auth WP_Twitter_Client Page', 'theme-domain' );
		$this->menu_title  = __( 'Example Auth WP_Twitter_Client', 'theme-domain' );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Save instance to WP_Twitter_Client
		$this->t = new WP_Twitter_Client( array(
			'consumer_key'    => CONSUMER_KEY,
			'consumer_secret' => CONSUMER_SECRET,
		) );

		// Temporary request token
		$this->request_token = '';

		// Once access token is retrieved, a response
		// from `users/show` is kept here
		$this->profile = null;
	}

	/**
	 * Add sub menu page to the Settings menu and action
	 * when the page loads.
	 *
	 * @return void
	 */
	public function admin_menu() {
		$this->admin_page = add_options_page( $this->page_title, $this->menu_title, 'manage_options', __CLASS__, array( $this, 'settings_page' ) );

		add_action( 'load-' . $this->admin_page, array( $this, 'on_this_page_load' ) );
	}

	/**
	 * Does the following things when this page loads:
	 * 1. 3-legged OAuth flow.
	 * 2. Once access token is retrieved make a call to `users/show`.
	 * 3. Check if there's an error to be shown.
	 *
	 * @return void
	 */
	public function on_this_page_load() {
		$screen = get_current_screen();

		if ( $this->admin_page != $screen->id )
			return;

		// URL to current setting page.
		$url_to_redirect = admin_url( 'options-general.php?page=' . __CLASS__ );

		// Check if Twitter is redirecting back
		if ( isset( $_REQUEST['oauth_verifier'] ) && isset( $_REQUEST['page_nonce'] ) ) {
			// Verify page nonce
			if ( ! wp_verify_nonce( $_REQUEST['page_nonce'], $this->admin_page ) ) {
				$error_message = __( 'Failed to verify page_nonce', 'theme-domain' );
				delete_option( self::TOKENS_OPTION );
				update_option( self::ERROR_OPTION, $error_message );
				$url_to_redirect = add_query_arg( 'got_error', true, $url_to_redirect );
				wp_redirect( $url_to_redirect );
				exit();
			}

			$resp = $this->t->request('POST', 'oauth/access_token', array(
				'parameters' => array(
					'oauth_token'    => $_REQUEST['oauth_token'],
					'oauth_verifier' => $_REQUEST['oauth_verifier'],
				),
			));

			if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
				wp_parse_str( wp_remote_retrieve_body( $resp ), $tokens );
				update_option( self::TOKENS_OPTION, $tokens );
			} else {
				$error_message = sprintf(
					'<strong>%s</strong> %s',
					sprintf( __( 'Status code %s: ', 'theme-domain' ), wp_remote_retrieve_response_code( $resp ) ),
					wp_remote_retrieve_body( $resp )
				);
				delete_option( self::TOKENS_OPTION );
				update_option( self::ERROR_OPTION, $error_message );
				$url_to_redirect = add_query_arg( 'got_error', true, $url_to_redirect );
			}
			wp_redirect( $url_to_redirect );
			exit();
		}

		// Check if we have access token
		$tokens = get_option( self::TOKENS_OPTION );
		if ( $tokens ) {
			// Lets use the token to retrieve 'users/show' resource
			$resp = $this->t->request('GET', 'users/show', array(
				'parameters' => array(
					'oauth_token'        => $tokens['oauth_token'],
					'oauth_token_secret' => $tokens['oauth_token_secret'],
					'user_id'            => $tokens['user_id'],
				),
				'format' => 'json',
			));

			if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
				$this->profile = (array)json_decode( wp_remote_retrieve_body( $resp ) );
			} else {
				$error_message = sprintf(
					'<strong>%s</strong> %s',
					sprintf( __( 'Status code %s: ', 'theme-domain' ), wp_remote_retrieve_response_code( $resp ) ),
					wp_remote_retrieve_body( $resp )
				);
				delete_option( self::TOKENS_OPTION );
				update_option( self::ERROR_OPTION, $error_message );
				$url_to_redirect = add_query_arg( 'got_error', true, $url_to_redirect );
				wp_redirect( $url_to_redirect );
				exit();
			}
		}

		// Add nonce for this page
		$oath_callback = add_query_arg( 'page_nonce', wp_create_nonce( $this->admin_page ), $url_to_redirect );

		// Make a call for request token
		$resp = $this->t->request('POST', 'oauth/request_token', array(
			'parameters' => array(
				'oauth_callback' => $oath_callback,
			),
		));

		// We always read new request token so that you can try authorize anytime
		// even after you got access token.
		if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
			wp_parse_str( wp_remote_retrieve_body( $resp ), $body );
			$this->request_token = $body['oauth_token'];
		}

		// Check if there's an error to be shown
		if ( isset( $_REQUEST['got_error'] ) ) {
			add_action( 'admin_notices', array( $this, 'output_admin_notices' ) );
		}
	}

	/**
	 * Output admin notices.
	 */
	public function output_admin_notices() {
		$screen = get_current_screen();

		if ( $this->admin_page != $screen->id )
			return;

		$message = get_option( self::ERROR_OPTION );
		if ( $message ) {
			printf( '<div class="error fade"><p>%s</p></div>', $message );
		}
		delete_option( self::ERROR_OPTION );
	}

	/**
	 * Render the setting page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$remove_token_url = add_query_arg( 'remove_tokens', true );
		?>
		<div class="wrap">
			<h2><?php echo esc_html( $this->page_title ); ?></h2>

			<?php if ( $this->profile ): ?>
			<p class="column-links"><?php echo esc_html( sprintf( __('Hola %s!', 'theme-domain'), $this->profile['screen_name'] ) ); ?></p>
			<?php endif; ?>

			<p class="column-links">
				<a href="<?php echo esc_url( sprintf( self::AUTH_URL, $this->request_token ) ) ?>" class="button"><?php _e('Authorize with Twitter', 'theme-domain'); ?></a>
			</p>
		</div>
		<!-- / wrap -->
		<?php
	}
}
new Example_Auth_WP_Twitter_Client();
