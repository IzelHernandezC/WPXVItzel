<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wpxvitzel' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '^U~XV:J~S1mHBzO{;s%aNF)n[5KdQD|8GeW~Ev?5[38dz?MCMxaB$34(Q@j$Ky/x' );
define( 'SECURE_AUTH_KEY',  't}UoN{<i WFeB/R94=sAiX5q^ rlE9el?wHR:]n(*6Ei>?dh`Qk?]H%BAZ|h? ko' );
define( 'LOGGED_IN_KEY',    'VV^,g@g~VrEIJz-ZlC>|w:e=V@p?s+:/Je dG}i4JtR,Flq/9.:Nf&XUSq;m;KW@' );
define( 'NONCE_KEY',        'lF4KWQk,g[?gCJ1sAH[xX>[/pT};3`OadtGeSmwE.Y{T6p[Lz,sw@}p xo0JT8DS' );
define( 'AUTH_SALT',        '3fH$;U$JtV|Jyn=!;(a}{Kspwn4Bs$ds#AFHgn)Uq./`,GUZMr~g3HvV$0up?TaG' );
define( 'SECURE_AUTH_SALT', 'dFxm_9+M>Ch=(cG/s}=;r.L)T-EJw[?4mht|ED}X 35c~=n2i|26FfEUwuMtjn0m' );
define( 'LOGGED_IN_SALT',   'g93@tt>cbuJ>hA}cfZw4lJ8ur}T;2pW2aNgbnMt]`&8bI,dofgvg(<Tu6NT{|obh' );
define( 'NONCE_SALT',       '}L1Nfe0:KvL:ZgM6>I8`}hXQXU)a-F:zN;4+h1^}6 tGU<rSO(L5}lZq~!sT34cV' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

define( 'FS_METHOD', 'direct' );



/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

