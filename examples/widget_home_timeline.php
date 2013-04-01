<?php
/**
 * Example to demonstrate rendering collection of the most recent Tweets and
 * retweets posted by the authenticating user and the users they follow
 * using WP_Twitter_Client. This demo will add sub menu page to the Settings menu
 * where it renders a button to authorize the app. Once authorized,
 * you should be able to see the twitter screen_name.
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

class Example_Widget_Home_Timeline_WP_Twitter_Client {
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
		$this->page_title  = __( 'Example Widget Home Timeline WP_Twitter_Client Page', 'theme-domain' );
		$this->menu_title  = __( 'Example Widget Home Timeline WP_Twitter_Client', 'theme-domain' );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

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
	 * Register the widget.
	 */
	public function register_widget() {
		register_widget( 'WP_Twitter_Client_Widget' );
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
	 * Fetch the tweets by making a call to `statuses/home_timeline` resource.
	 *
	 * @param int $count The number of records to retrieve.
	 * @return array
	 */
	public function get_tweets( $count = 20 ) {
		$tweets = array();

		$tokens = get_option( self::TOKENS_OPTION );
		if ( $tokens ) {
			$resp = $this->t->request('GET', 'statuses/home_timeline', array(
				'parameters' => array(
					'oauth_token'        => $tokens['oauth_token'],
					'oauth_token_secret' => $tokens['oauth_token_secret'],
					'user_id'            => $tokens['user_id'],
					'count'              => $count,
				),
				'format' => 'json',
			));

			if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
				$tweets = (array)json_decode( wp_remote_retrieve_body( $resp ) );
			}
		}

		return $tweets;
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
			<p class="column-links"><?php echo esc_html( sprintf( __('Hola %s! Your widget is ready!', 'theme-domain'), $this->profile['screen_name'] ) ); ?></p>
			<?php endif; ?>

			<p class="column-links">
				<a href="<?php echo esc_url( sprintf( self::AUTH_URL, $this->request_token ) ) ?>" class="button"><?php _e('Authorize with Twitter', 'theme-domain'); ?></a>
			</p>
		</div>
		<!-- / wrap -->
		<?php
	}
}

/**
 * Widget to render collection of the most recent Tweets and
 * retweets posted by the authenticating user and the users they follow.
 */
class WP_Twitter_Client_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct( strtolower( __CLASS__ ), __( 'WP Twitter Client widget', 'theme-domain' ), array(
			'description' => __( 'Collection of the most recent Tweets and retweets.', 'theme-domain' ),
			'classname'   => strtolower( __CLASS__ ),
		) );
	}

	public function widget( $args, $instance ) {
		global $wp_twitter_client_example_instance;

		extract( $args );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Tweets', 'theme-domain' ) : $instance['title'] );

		// Specifies the number of records to retrieve
		$count = intval( $instance['count'] );
		if ( ! $count )
 			$count = 20;

 		echo $before_widget;
 		echo $before_title . $title . $after_title;

 		$tweets = get_transient( $this->id );
 		if ( $tweets ) {
 			echo $this->_print_tweets( $tweets );
 		} else {
 			$tweets = $wp_twitter_client_example_instance->get_tweets( $count );
 			if ( ! $tweets ) {
 				echo '<p><em>' . __( 'No tweets. Make sure to authorize the app', 'theme-domain' ) . '</em></p>';
 			} else {
 				set_transient( $this->id, $tweets, 60 * 60 );
 				echo $this->_print_tweets( $tweets );
 			}
 		}

 		echo $after_widget;
	}

	private function _print_tweets( $tweets ) {
		ob_start();
		echo '<ol style="list-style: decimal">';
		foreach ( $tweets as $tweet ) {
			echo '<li>' . esc_html( $tweet->text ) . '</li>';
		}
		echo '</ol>';

		return ob_get_clean();
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance['title'] = esc_html( $new_instance['title'] );
		$new_instance['count'] = intval( $new_instance['count'] );
		delete_transient( $this->id );

		return $new_instance;
	}

	public function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title' => '',
			'count' => 20,
		) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('title') ); ?>"><?php _e( 'Title', 'theme-domain' ); ?></label>
			<input class="widefat" type="text" id="<?php echo esc_attr( $this->get_field_id('title') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title') ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('count') ); ?>"><?php _e( 'Count', 'theme-domain' ); ?></label>
			<input class="widefat" type="number" id="<?php echo esc_attr( $this->get_field_id('count') ); ?>" name="<?php echo esc_attr( $this->get_field_name('count') ); ?>" value="<?php echo esc_attr( $instance['count'] ); ?>">
		</p>
		<?php
	}
}

global $wp_twitter_client_example_instance;

$wp_twitter_client_example_instance = new Example_Widget_Home_Timeline_WP_Twitter_Client();
