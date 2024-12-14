<?php
/**
 * Theme Functions
 *
 * @author Jegstudio
 * @package contrax-lite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined( 'CONTRAX_LITE_VERSION' ) || define( 'CONTRAX_LITE_VERSION', '1.0.5' );
defined( 'CONTRAX_LITE_DIR' ) || define( 'CONTRAX_LITE_DIR', trailingslashit( get_template_directory() ) );
defined( 'CONTRAX_LITE_URI' ) || define( 'CONTRAX_LITE_URI', trailingslashit( get_template_directory_uri() ) );

require get_parent_theme_file_path( 'inc/autoload.php' );

Contrax_Lite\Init::instance();
