<?php
/**
 * Plugin Name: WP PHP Password
 * Plugin URI:  https://github.com/timnashcouk/wp-php-password
 * Description: Replaces wp_hash_password and wp_check_password with password_hash and password_verify.
 * Author:      Tim Nash
 * Author URI:  https://timnash.co.uk
 * Version:     1.0.0
 * Licence:     MIT
 *
 * @package wp-php-password
 */

$wp_php_password_error = null;

if ( ! function_exists( 'wp_check_password' ) ) {
	/**
	 * Determine if the plaintext password matches the encrypted password hash.
	 *
	 * If the password hash is not encrypted using the PASSWORD_DEFAULT (bcrypt)
	 * algorithm, the password will be rehashed and updated once verified.
	 *
	 * @link https://www.php.net/manual/en/function.password-verify.php
	 * @link https://www.php.net/manual/en/function.password-needs-rehash.php
	 *
	 * @param  string     $password The password in plaintext.
	 * @param  string     $hash     The hashed password to check against.
	 * @param  string|int $user_id  The optional user ID.
	 * @return bool
	 */
	function wp_check_password( string $password, string $hash, int|string $user_id = '' ): bool {
		if ( ! password_needs_rehash( $hash, apply_filters( 'wp_php_hash_password_algorithm', PASSWORD_DEFAULT ), apply_filters( 'wp_hash_password_options', array() ) ) ) {
			return apply_filters(
				'check_password',
				password_verify( $password, $hash ),
				$password,
				$hash,
				$user_id
			);
		}

		global $wp_hasher;

		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$wp_hasher = new PasswordHash( 8, true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		if ( ! empty( $user_id ) && $wp_hasher->CheckPassword( $password, $hash ) ) {
			$hash = wp_set_password( $password, $user_id );
		}

		return apply_filters(
			'check_password',
			password_verify( $password, $hash ),
			$password,
			$hash,
			$user_id
		);
	}
} elseif ( ! is_object( $wp_php_password_error ) ) {
		$wp_php_password_error = new WP_Error( 'wp_php_password', __( 'WP PHP Password is active but something else is overrding password functions.', 'wp-php-password' ) );
}

if ( ! function_exists( 'wp_hash_password' ) ) {
	/**
	 * Hash the provided password with additional filter
	 * to allow alternative algorithm.
	 *
	 * @link https://www.php.net/manual/en/function.password-hash.php
	 *
	 * @param  string $password The password in plain text.
	 * @return string
	 */
	function wp_hash_password( string $password ): string {

		return password_hash(
			$password,
			apply_filters( 'wp_php_hash_password_algorithm', PASSWORD_DEFAULT ),
			apply_filters( 'wp_hash_password_options', array() )
		);
	}
} elseif ( ! is_wp_error( $wp_php_password_error ) ) {
		$wp_php_password_error = new WP_Error( 'wp_php_password', __( 'WP PHP Password is active but something else is overrding password functions.', 'wp-php-password' ) );
}

if ( ! function_exists( 'wp_set_password' ) ) {
	/**
	 * Hash and update the user's password.
	 *
	 * @param  string $password The new user password in plaintext.
	 * @param  int    $user_id  The user ID.
	 * @return string
	 */
	function wp_set_password( string $password, int $user_id ): string {
		$hash           = wp_hash_password( $password );
		$is_api_request = apply_filters(
			'application_password_is_api_request',
			( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST )
		);

		if ( ! $is_api_request ) {
			global $wpdb;

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->users,
				array(
					'user_pass'           => $hash,
					'user_activation_key' => '',
				),
				array( 'ID' => $user_id )
			);

			clean_user_cache( $user_id );

			return $hash;
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return '';
		}

		$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );

		if ( empty( $passwords ) ) {
			return '';
		}

		global $wp_hasher;

		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$wp_hasher = new PasswordHash( 8, true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		foreach ( $passwords as $key => $value ) {
			if ( ! $wp_hasher->CheckPassword( $password, $value['password'] ) ) {
				continue;
			}

			$passwords[ $key ]['password'] = $hash;
		}

		update_user_meta(
			$user_id,
			WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS,
			$passwords
		);

		return $hash;
	}
} elseif ( ! is_wp_error( $wp_php_password_error ) ) {
		$wp_php_password_error = new WP_Error( 'wp_php_password', __( 'WP PHP Password is active but something else is overrding password functions.', 'wp-php-password' ) );
}

if ( is_wp_error( $wp_php_password_error ) ) {
	add_action(
		'all_admin_notices',
		function () use ( $wp_php_password_error ): void {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $wp_php_password_error->get_error_message() )
			);
		}
	);
}
