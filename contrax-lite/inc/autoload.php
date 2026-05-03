<?php
/**
 * Autoload function
 *
 * @author Jegstudio
 * @package contrax-lite
 */

/**
 * Autoloader function for Contrax Lite theme classes
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
function contrax_lite_autoloader( $class ) {
    $prefix   = 'Contrax_Lite';
    $base_dir = CONTRAX_LITE_DIR . 'inc/class/';
    $len      = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $array_path     = explode( '\\', substr( $class, $len ) );
    $relative_class = array_pop( $array_path );
    $class_path     = strtolower( implode( '/', $array_path ) );
    $class_name     = str_replace( '_', '-', 'class-' . $relative_class . '.php' );

    $file = rtrim( $base_dir, '/' ) . '/' . $class_path . '/' . strtolower( $class_name );

    if ( is_link( $file ) ) {
        $file = readlink( $file );
    }

    if ( is_file( $file ) ) {
        require $file;
    }
}

spl_autoload_register( 'contrax_lite_autoloader' );