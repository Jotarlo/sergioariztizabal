<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'J}b/:{kJAa]_+O{zL{J{SJtGRRx Q%sAO[,#ZK}#KvS1*!FC^~NMIuZCT;^N@Nva' );
define( 'SECURE_AUTH_KEY',   'f!Tk4ofv-qWltD,x) ;bD4[-ujW23Wg=1rKV;s{,#>]lN1TJUqPqJo*<~k{xG5ub' );
define( 'LOGGED_IN_KEY',     '$JO/**{s/Tqom //oB@l2jDxAglmBXB(}o^U@(1!v>~2L;x!>[ (^nv0q:*IxH,L' );
define( 'NONCE_KEY',         '<l?VrSPlE,RW!L2IP%kk20mI(p-?f1r!>Mh@oX}K[YfQH5v->nP/ /ivTrq9c1^{' );
define( 'AUTH_SALT',         'dtRV^6HS1 BxcUb%4O#G_Yz=yqDM4|.t-_7(L0q2}D)NP.Q+1mBoV~V>+pK )Z%C' );
define( 'SECURE_AUTH_SALT',  '8X<%ftW~q|&fCC.s^$kdsl^4_t0k,CS7[_W /EbY}}tI} ? x%m1G=@~6m:$Bzq3' );
define( 'LOGGED_IN_SALT',    'Xq#;d$pQR4G(=yJ!kS0#hY0c/cEg<k Iw.XpyfBG{a _~6SMaQ;7VW+8)ZdDQzNE' );
define( 'NONCE_SALT',        '7K5^IWb4C+LTg$;[qp8pRXiR XIx|4|~dN6)L#IuK]_C]tqZ_ IU4:isa%2+<p_p' );
define( 'WP_CACHE_KEY_SALT', 'tM|HQEzNU1Zf{??V-s,LG;]~mM4b}wMCnF@pnEHU>8xLdgqAX%`-J3Aj<9k[.a;|' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
