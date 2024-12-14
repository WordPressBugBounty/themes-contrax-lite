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

use WP_Block_Pattern_Categories_Registry;

/**
 * Init Class
 *
 * @package contrax-lite
 */
class Block_Patterns {

	/**
	 * Instance variable
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Class instance.
	 *
	 * @return BlockPatterns
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->register_block_patterns();
		$this->register_synced_patterns();
	}

	/**
	 * Register Block Patterns
	 */
	private function register_block_patterns() {
		$block_pattern_categories = array(
			'contrax-lite-core' => array( 'label' => __( 'Contrax Lite Core Patterns', 'contrax-lite' ) ),
		);

		if ( defined( 'GUTENVERSE' ) ) {
			$block_pattern_categories['contrax-lite-gutenverse'] = array( 'label' => __( 'Contrax Lite Gutenverse Patterns', 'contrax-lite' ) );
			$block_pattern_categories['contrax-lite-pro'] = array( 'label' => __( 'Contrax Lite Gutenverse PRO Patterns', 'contrax-lite' ) );
		}

		$block_pattern_categories = apply_filters( 'contrax-lite_block_pattern_categories', $block_pattern_categories );

		foreach ( $block_pattern_categories as $name => $properties ) {
			if ( ! WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( $name ) ) {
				register_block_pattern_category( $name, $properties );
			}
		}

		$block_patterns = array(
            'contrax-home-core-hero',			'contrax-home-core-about',			'contrax-home-core-services',			'contrax-home-core-funfact',			'contrax-home-core-gallery',			'contrax-home-core-process',			'contrax-home-core-testimonials',			'contrax-home-core-blog',			'contrax-header-core-header',			'contrax-footer-core-cta',			'contrax-footer-core-footer',			'contrax-index-core-hero',			'contrax-page-core-hero',			'contrax-single-core-hero',
		);

		if ( defined( 'GUTENVERSE' ) ) {
            $block_patterns[] = 'contrax-home-gutenverse-hero';			$block_patterns[] = 'contrax-home-gutenverse-about';			$block_patterns[] = 'contrax-home-gutenverse-services';			$block_patterns[] = 'contrax-gutenverse-funfact';			$block_patterns[] = 'contrax-home-gutenverse-gallery';			$block_patterns[] = 'contrax-home-gutenverse-process';			$block_patterns[] = 'contrax-home-gutenverse-testimonials';			$block_patterns[] = 'contrax-home-gutenverse-blog';			$block_patterns[] = 'contrax-header-gutenverse-header';			$block_patterns[] = 'contrax-footer-gutenverse-cta';			$block_patterns[] = 'contrax-footer-gutenverse-footer';			$block_patterns[] = 'contrax-blog-gutenverse-hero';			$block_patterns[] = 'contrax-about-gutenverse-hero';			$block_patterns[] = 'contrax-gutenverse-funfact';			$block_patterns[] = 'contrax-about-gutenverse-mission-vision';			$block_patterns[] = 'contrax-about-gutenverse-about';			$block_patterns[] = 'contrax-about-gutenverse-team';			$block_patterns[] = 'contrax-single-gutenverse-hero';			$block_patterns[] = 'contrax-index-gutenverse-hero';			$block_patterns[] = 'contrax-page-gutenverse-hero';			$block_patterns[] = 'contrax-archive-gutenverse-hero';			$block_patterns[] = 'contrax-search-gutenverse-hero';
            
		}

		$block_patterns = apply_filters( 'contrax-lite_block_patterns', $block_patterns );
		$pattern_list   = get_option( 'contrax-lite_synced_pattern_imported', false );
		if ( ! $pattern_list ) {
			$pattern_list = array();
		}

		if ( function_exists( 'register_block_pattern' ) ) {
			foreach ( $block_patterns as $block_pattern ) {
				$pattern_file = get_theme_file_path( '/inc/patterns/' . $block_pattern . '.php' );
				$pattern_data = require $pattern_file;

				if ( (bool) $pattern_data['is_sync'] ) {
					$post = get_page_by_path( $block_pattern . '-synced', OBJECT, 'wp_block' );
					/**Download Image */
					$content = wp_slash( $pattern_data['content'] );
					if ( $pattern_data['images'] ) {
						$images = json_decode( $pattern_data['images'] );
						foreach ( $images as $key => $image ) {
							$url  = $image->image_url;
							$data = Helper::check_image_exist( $url );
							if ( ! $data ) {
								$data = Helper::handle_file( $url );
							}
							$content = str_replace( $url, $data['url'], $content );
						}
					}
					if ( empty( $post ) ) {
						$post_id = wp_insert_post(
							array(
								'post_name'    => $block_pattern . '-synced',
								'post_title'   => $pattern_data['title'],
								'post_content' => $content,
								'post_status'  => 'publish',
								'post_author'  => 1,
								'post_type'    => 'wp_block',
							)
						);
						if ( ! is_wp_error( $post_id ) ) {
							$pattern_category = $pattern_data['categories'];
							foreach( $pattern_category as $category ){
								wp_set_object_terms( $post_id, $category, 'wp_pattern_category' );
							}
						}
						$pattern_data['content']  = '<!-- wp:block {"ref":' . $post_id . '} /-->';
						$pattern_data['inserter'] = false;
						$pattern_data['slug']     = $block_pattern;

						$pattern_list[] = $pattern_data;
					}
				} else {
					register_block_pattern(
						'contrax-lite/' . $block_pattern,
						require $pattern_file
					);
				}
			}
			update_option( 'contrax-lite_synced_pattern_imported', $pattern_list );
		}
	}

	/**
	 * Register Synced Patterns
	 */
	 private function register_synced_patterns() {
		$patterns = get_option( 'contrax-lite_synced_pattern_imported' );

		 foreach ( $patterns as $block_pattern ) {
			 register_block_pattern(
				'contrax-lite/' . $block_pattern['slug'],
				$block_pattern
			);
		 }
	 }
}
