<?php
/**
 * WP_Twitter_Client - Library to help WordPress developer working with Twitter REST API.
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

class WP_Twitter_Client {

	/**
	 * This library version.
	 *
	 * @constant VERSION
	 */
	const VERSION = '0.1.0';

	/**
	 * Twitter API version.
	 *
	 * @constant TWITTER_API_VERSION
	 */
	const TWITTER_API_VERSION = '1.1';

	/**
	 * Twitter API domain.
	 *
	 * @constant TWITTER_API_DOMAIN
	 */
	const TWITTER_API_DOMAIN = 'api.twitter.com';

	/**
	 * Twitter OAuth version.
	 *
	 * @constant TWITTER_OAUTH_VERSION
	 */
	const TWITTER_OAUTH_VERSION = '1.0';

	/**
	 * Settings that will be used to make a call to Twitter API.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Array of ready-to-use (already sorted) parameters for building signature base string.
	 * `oauth_signature` is excluded in this parameters.
	 *
	 * @see `::_set_parameters`
	 * @var array
	 */
	private $parameters_for_sbs;

	/**
	 * Array of ready-to-use (already sorted) parameters for building 'Authorization' header string.
	 *
	 * @see `::_set_parameters`
	 * @var array
	 */
	private $parameters_for_authorization;

	/**
	 * Parameters for signature base string minus standard OAuth parameters.
	 *
	 * @var array
	 */
	private $parameters_for_request;

	/**
	 * Constructor.
	 *
	 * @param array $settings Array of configuration settings.
	 */
	public function __construct( $settings = array() ) {
		$this->settings = wp_parse_args(
			$settings,
			array(
				'consumer_key'           => '',
				'consumer_secret'        => '',

				'oauth_token'            => '',
				'oauth_token_secret'     => '',
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => '',
				'oauth_nonce'            => '',
				'oauth_version'          => self::TWITTER_OAUTH_VERSION,

				'user_agent'             => apply_filters( 'wp_twitter_client_user_agent', __CLASS__ . '/' . self::VERSION ),
			)
		);
	}

	/**
	 * RFC3986 compliant encoding functions.
	 *
	 * @link https://dev.twitter.com/docs/auth/percent-encoding-parameters
	 * @link http://tools.ietf.org/html/rfc3986#section-2.1
	 */
	protected function _percent_encode( $string ) {
		return str_replace( '%7E', '~', rawurlencode( $string ) );
	}

	/**
	 * Colleting parameters to be used for building signature base string
	 * and 'Authorization' header.
	 *
	 * @param array $parameters Request parameters
	 * @return void
	 */
	protected function _set_parameters( $parameters ) {
		$parameters = wp_parse_args( $parameters, $this->_get_oauth_parameters() );

		// `oauth_signature` MUST be excluded from parameters collection
		if ( isset( $parameters['oauth_signature'] ) )
			unset( $parameters['oauth_signature'] );

		// Sort the list of parameters alphabetically by percent-encoded key.
		uksort( $parameters, 'strcmp' );

		// For each key/value pair:
		// - Append the encoded key to the output string.
		// - Append the '=' character to the output string.
		// - Append the encoded value to the output string.
		// - If there are more key/value pairs remaining, append a '&' character to the output string.
		$params_str = array();
		$this->parameters_for_sbs = array();
		foreach ( $parameters as $k => $v ) {
			$k = $this->_percent_encode( $k );
			$v = $this->_percent_encode( $v );

			$this->parameters_for_sbs[ $k ] = $v;
			$params_str[] = $k . '=' . $v;
		}

		// Standard OAuth parameters that ready-to-use
		$this->parameters_for_authorization = array_intersect_key( $this->_get_oauth_parameters(), $this->parameters_for_sbs );

		if ( isset( $this->parameters_for_sbs['oauth_callback'] ) ) {
			$this->parameters_for_authorization['oauth_callback'] = $this->parameters_for_sbs['oauth_callback'];
			unset( $this->parameters_for_sbs['oauth_callback'] );
		}

		if ( isset( $this->parameters_for_sbs['oauth_verifier'] ) ) {
			$this->parameters_for_authorization['oauth_verifier'] = $this->parameters_for_sbs['oauth_verifier'];
			unset( $this->parameters_for_sbs['oauth_verifier'] );
		}

		// Request parameters
		$this->parameters_for_request = array_diff_key( $this->parameters_for_sbs, $this->_get_oauth_parameters() );

		$this->parameters_for_sbs = $params_str;
	}

	/**
	 * Returns standard OAuth parameters to be included in the signature.
	 *
	 * @return array
	 */
	private function _get_oauth_parameters() {
		// Some values do not need to be rawurlencoded because
		// we already know it will return the same string.
		$parameters = array(
			'oauth_consumer_key'     => $this->_percent_encode( $this->settings['consumer_key'] ),
			'oauth_nonce'            => $this->_percent_encode( $this->settings['oauth_nonce'] ),
			'oauth_signature_method' => $this->settings['oauth_signature_method'],
			'oauth_timestamp'        => $this->_percent_encode( $this->settings['oauth_timestamp'] ),
			'oauth_token'            => $this->settings['oauth_token'],
			'oauth_version'          => $this->settings['oauth_version'],
		);

		if ( ! $parameters['oauth_token'] )
			unset( $parameters['oauth_token'] );
		else
			$parameters['oauth_token'] = $this->_percent_encode( $parameters['oauth_token'] );

		return apply_filters( 'wp_twitter_client_oauth_parameters', $parameters );
	}

	/**
	 *
	 */
	protected function _get_authorized_header_string() {
		// Append the oauth_signature
		$this->parameters_for_authorization['oauth_signature'] = $this->_get_signature();

		uksort( $this->parameters_for_authorization, 'strcmp' );

		$auth_str = array();
		foreach ( $this->parameters_for_authorization as $k => $v ) {
			$auth_str[] = $k . '="' . $v . '"';
		}

		return apply_filters( 'wp_twitter_client_authorization_header', 'OAuth ' . implode( ', ', $auth_str ) );
	}

	/**
	 * Returns a combined string of 'HTTP Method', 'Base URL', and 'Parameters string'.
	 *
	 * @link https://dev.twitter.com/docs/auth/creating-signature
	 * @see `_get_signature`
	 * @return string
	 */
	protected function _get_signature_base_string() {
		// First, 'HTTP Method'
		$base_string = $this->settings['http_method'] . '&';

		// Append percent-encoded 'Base URL'
		$base_string .= $this->_percent_encode( $this->settings['base_url'] ) . '&';

		// Lastly, 'Parameters string'
		$base_string .= $this->_percent_encode( implode( '&', $this->parameters_for_sbs ) );

		return apply_filters( 'wp_twitter_client_signature_base_string', $base_string );
	}

	/**
	 * Returns a signing key which will be used to generate the signature.
	 * The signing key is simply the percent encoded consumer secret, followed
	 * by '&' char, followed by the percent encoded token secret.
	 *
	 * @link https://dev.twitter.com/docs/auth/creating-signature
	 * @see `_get_signature`
	 * @return string
	 */
	protected function _get_signing_key() {
		return apply_filters( 'wp_twitter_client_signing_key', $this->_percent_encode( $this->settings['consumer_secret']) . '&' .  $this->_percent_encode( $this->settings['oauth_token_secret'] ) );
	}

	/**
	 * Returns an OAuth 1.0a HMAC-SHA1 signature for a HTTP request.
	 *
	 * @link https://dev.twitter.com/docs/auth/creating-signature
	 * @return string
	 */
	protected function _get_signature( $args = array() ) {
		return apply_filters( 'wp_twitter_client_signature', $this->_percent_encode( base64_encode( hash_hmac( 'sha1', $this->_get_signature_base_string(), $this->_get_signing_key(), true ) ) ) );
	}

	/**
	 * Make an authorized request to the Twitter API. This method
	 * uses `wp_remote_request`.
	 *
	 * @param string $http_method Whether GET or POST
	 * @param string $resource Resource as seen on https://dev.twitter.com/docs/api/1.1
	 * @param array $args
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	public function request( $http_method, $resource, $args = array() ) {
		$defaults = array(
			'headers'        => array( 'User-Agent' => $this->settings['user_agent'], ),
			'parameters'     => array(),
			'version_in_url' => true,
			'body'           => array(),
			'format'         => '',
		);

		extract( wp_parse_args( $args, $defaults ) );

		$base_url = 'https://' . self::TWITTER_API_DOMAIN;

		// The oauth/* resources don't have /TWITTER_API_VERSION/ appears in url
		if ( $version_in_url && strpos( $resource, 'oauth' ) === false )
			$base_url .= '/' . self::TWITTER_API_VERSION;

		$base_url .= '/' . $resource;

		if ( $format )
			$base_url .= '.' . $format;

		$_original_settings = $this->settings;

		$this->settings['http_method'] = $http_method = apply_filters( 'wp_twitter_client_http_method', strtoupper( $http_method ) );

		$this->settings['base_url'] = apply_filters( 'wp_twitter_client_base_url', $base_url );

		if ( isset( $parameters['oauth_callback'] ) )
			$this->settings['oauth_callback'] = $parameters['oauth_callback'];

		if ( isset( $parameters['oauth_token'] ) )
			$this->settings['oauth_token'] = $parameters['oauth_token'];

		if ( isset( $parameters['oauth_token_secret'] ) )
			$this->settings['oauth_token_secret'] = $parameters['oauth_token_secret'];

		if ( ! $this->settings['oauth_timestamp'] )
			$this->settings['oauth_timestamp'] = time();

		if ( ! $this->settings['oauth_nonce'] )
			$this->settings['oauth_nonce'] = wp_create_nonce();

		// Collecting parameters to be used for signature base string and 'Authorization' header
		$this->_set_parameters( $parameters );

		// Set 'Authorization' header
		$headers['Authorization'] = $this->_get_authorized_header_string();

		$url = $this->settings['base_url'];
		switch ( $http_method ) {
			case 'POST':
				if ( ! empty( $body ) ) {
					$body = wp_parse_args( $body, $this->parameters_for_request );
				} else {
					$body = $this->parameters_for_request;
				}
				break;
			default: // GET
				// Modify $url with $parameters appended as query string
				$params = array();
				foreach ( $this->parameters_for_request as $k => $v ) {
					$params[] = $k . '=' . $v;
				}
				if ( !empty($params) )
					$url .= '?' . implode( '&', $params );
		}

		// Reset settings so we can call `request` multiple times
		// with the same instance
		$this->settings = $_original_settings;

		return wp_remote_request( $url, array(
			'method'     => $http_method,
			'headers'    => $headers,
			'body'       => $body,
		) );
	}
}
