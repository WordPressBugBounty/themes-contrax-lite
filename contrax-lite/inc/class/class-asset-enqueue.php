<?php
/**
 * Block Pattern Class
 *
 * @author Jegstudio
 * @package contrax-lite
 */
namespace Contrax_Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init Class
 *
 * @package contrax-lite
 */
class Asset_Enqueue {
	/**
	 * Class constructor.
	 */
	public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 20 );
	}

    /**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'contrax-lite-style', get_stylesheet_uri(), array(), CONTRAX_LITE_VERSION );

				wp_enqueue_style( 'contrax-preset-styling', CONTRAX_LITE_URI . '/assets/css/contrax-preset-styling.css', array(), CONTRAX_LITE_VERSION );
		wp_enqueue_style( 'custom-styling', CONTRAX_LITE_URI . '/assets/css/custom-styling.css', array(), CONTRAX_LITE_VERSION );
		wp_enqueue_script( 'animation-script', CONTRAX_LITE_URI . '/assets/js/animation-script.js', array(), CONTRAX_LITE_VERSION, true );


        if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
    }

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function admin_scripts() {
		
    }
}
