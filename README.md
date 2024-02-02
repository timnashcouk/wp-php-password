# WP PHP Password
Basic Replacement for WordPress Built in Passwords forked from Roots [Password Bcrypt](https://github.com/roots/wp-password-bcrypt/)

This plugin replaces the default Password hasher PHPass used by WordPress with native PHP hashing functions allowing it to take advantage of improved algorithms like Bcrypt and Argon2.

This plugin is based on the roots original but also provides a few quality of life improvements over the Roots version which hasn't had much love in a while.

Primarily:
- Wrapped in `function_exists` which means it will work with PHPStan and static analysis tools
- Supports more then just Bcrypt for example can be extended to support Argon2

## Installation
Install manually into mu-plugins folder

> If installed as a regular plugin, may not run properly if another plugin is overriding password features.

### Switching to Argon
```
function my_password_algo( $algo ){
    return PASSWORD_ARGON2ID;
}
add_filter( 'wp_php_hash_password_algorithm', 'my_password_algo' );
```
Your version of PHP must be compiled with [argon2 support](https://wiki.php.net/rfc/argon2_password_hash_enhancements) for the above to work. Also if you make use of WP-CLI then make sure the CLI version of PHP is also compiled with Argon.

You might also want to make use of `wp_hash_password_options` filter to provide some alternative options:
```
function my_password_options( $options ){
    $supports = [
        'memory_cost' => 2048, 
        'time_cost' => 4, 
        'threads' => 3
    ];
    return $supports;
}
add_filter( 'wp_hash_password_options', 'my_password_options');
```
## Changelog
See [CHANGELOG.md](https://github.com/timnashcouk/wp-php-password/blob/main/CHANGELOG.md) for notable changes per version.
