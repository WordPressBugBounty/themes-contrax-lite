<?php
/**
 * Init Configuration
 *
 * @author Jegstudio
 * @package contrax-lite
 */

namespace Contrax_Lite;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init Class
 *
 * @package contrax-lite
 */
class Init {

	/**
	 * Instance variable
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Class instance.
	 *
	 * @return Init
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
	private function __construct() {
		$this->init_instance();
		$this->load_hooks();
	}

	/**
	 * Load initial hooks.
	 */
	private function load_hooks() {
		add_action( 'init', array( $this, 'register_block_patterns' ), 9 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'dashboard_scripts' ) );

		add_action( 'wp_ajax_contrax-lite_set_admin_notice_viewed', array( $this, 'notice_closed' ) );

		add_action( 'after_switch_theme', array( $this, 'update_global_styles_after_theme_switch' ) );
		add_filter( 'gutenverse_template_path', array( $this, 'template_path' ), null, 3 );
		add_filter( 'gutenverse_themes_template', array( $this, 'add_template' ), 10, 2 );
		add_filter( 'gutenverse_block_config', array( $this, 'default_font' ), 10 );
		add_filter( 'gutenverse_font_header', array( $this, 'default_header_font' ) );
		add_filter( 'gutenverse_global_css', array( $this, 'global_header_style' ) );

		add_filter( 'gutenverse_stylesheet_directory', array( $this, 'change_stylesheet_directory' ) );
		add_filter( 'gutenverse_themes_override_mechanism', '__return_true' );

		
	}

	/**
	 * Update Global Styles After Theme Switch
	 */
	public function update_global_styles_after_theme_switch() {
		// Get the path to the current theme's theme.json file
		$theme_json_path = get_template_directory() . '/theme.json';
		$theme_slug      = get_option( 'stylesheet' ); // Get the current theme's slug
		$args            = array(
			'post_type'      => 'wp_global_styles',
			'post_status'    => 'publish',
			'name'           => 'wp-global-styles-' . $theme_slug,
			'posts_per_page' => 1,
		);

		$global_styles_query = new WP_Query( $args );
		// Check if the theme.json file exists
		if ( file_exists( $theme_json_path ) && $global_styles_query->have_posts() ) {
			$global_styles_query->the_post();
			$global_styles_post_id = get_the_ID();
			// Step 2: Get the existing global styles (color palette)
			$global_styles_content = json_decode( get_post_field( 'post_content', $global_styles_post_id ), true );
			if ( isset( $global_styles_content['settings']['color']['palette']['theme'] ) ) {
				$existing_colors = $global_styles_content['settings']['color']['palette']['theme'];
			} else {
				$existing_colors = array();
			}

			// Step 3: Extract slugs from the existing colors
			$existing_slugs = array_column( $existing_colors, 'slug' );
			// Step 4:Read the contents of the theme.json file

			$theme_json_content = file_get_contents( $theme_json_path );
			$theme_json_data    = json_decode( $theme_json_content, true );

			// Access the color palette from the theme.json file
			if ( isset( $theme_json_data['settings']['color']['palette'] ) ) {

				$theme_colors = $theme_json_data['settings']['color']['palette'];

				// Step 5: Loop through theme.json colors and add them if they don't exist
				foreach ( $theme_colors as $theme_color ) {
					if ( ! in_array( $theme_color['slug'], $existing_slugs ) ) {
						$existing_colors[] = $theme_color; // Add new color to the existing palette
					}
				}
				foreach ( $theme_colors as $theme_color ) {
					$theme_slug = $theme_color['slug'];

					// Step 6: Use in_array to check if the slug already exists in the global palette
					if ( ! in_array( $theme_slug, $existing_slugs ) ) {
						// If the slug does not exist, add the theme color to the global palette
						$global_colors[] = $theme_color;
					}
				}
				// Step 6: Update the global styles content with the new colors
				$global_styles_content['settings']['color']['palette']['theme'] = $existing_colors;

				// Step 7: Save the updated global styles back to the post
				wp_update_post(
					array(
						'ID'           => $global_styles_post_id,
						'post_content' => wp_json_encode( $global_styles_content ),
					)
				);

			}
			wp_reset_postdata(); // Reset the query
		}
	}

	/**
	 * Change Stylesheet Directory.
	 *
	 * @return string
	 */
	public function change_stylesheet_directory() {
		return CONTRAX_LITE_DIR . 'gutenverse-files';
	}

	/**
	 * Initialize Instance.
	 */
	public function init_instance() {
		new Asset_Enqueue();
		new Plugin_Notice();
	}

	/**
	 * Notice Closed
	 */
	public function notice_closed() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'contrax-lite_admin_notice' ) ) {
			update_user_meta( get_current_user_id(), 'gutenverse_install_notice', 'true' );
		}
		die;
	}

	/**
	 * Generate Global Font
	 *
	 * @param string $value  Value of the option.
	 *
	 * @return string
	 */
	public function global_header_style( $value ) {
		$theme_name      = get_stylesheet();
		$global_variable = get_option( 'gutenverse-global-variable-font-' . $theme_name );

		if ( empty( $global_variable ) && function_exists( 'gutenverse_global_font_style_generator' ) ) {
			$font_variable = $this->default_font_variable();
			$value        .= \gutenverse_global_font_style_generator( $font_variable );
		}

		return $value;
	}

	/**
	 * Header Font.
	 *
	 * @param mixed $value  Value of the option.
	 *
	 * @return mixed Value of the option.
	 */
	public function default_header_font( $value ) {
		if ( ! $value ) {
			$value = array(
				array(
					'value'  => 'Alfa Slab One',
					'type'   => 'google',
					'weight' => 'bold',
				),
			);
		}

		return $value;
	}

	/**
	 * Alter Default Font.
	 *
	 * @param array $config Array of Config.
	 *
	 * @return array
	 */
	public function default_font( $config ) {
		if ( empty( $config['globalVariable']['fonts'] ) ) {
			$config['globalVariable']['fonts'] = $this->default_font_variable();

			return $config;
		}

		if ( ! empty( $config['globalVariable']['fonts'] ) ) {
			// Handle existing fonts.
			$theme_name   = get_stylesheet();
			$initial_font = get_option( 'gutenverse-font-init-' . $theme_name );

			if ( ! $initial_font ) {
				$result = array();
				$array1 = $config['globalVariable']['fonts'];
				$array2 = $this->default_font_variable();
				foreach ( $array1 as $item ) {
					$result[ $item['id'] ] = $item;
				}
				foreach ( $array2 as $item ) {
					$result[ $item['id'] ] = $item;
				}
				$fonts = array();
				foreach ( $result as $key => $font ) {
					$fonts[] = $font;
				}
				$config['globalVariable']['fonts'] = $fonts;

				update_option( 'gutenverse-font-init-' . $theme_name, true );
			}
		}

		return $config;
	}

	/**
	 * Default Font Variable.
	 *
	 * @return array
	 */
	public function default_font_variable() {
		return array(
            array (
  'id' => '8AwtEC',
  'name' => 'H1',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '75',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '56',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '32',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '500',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'PXEJFk',
  'name' => 'H1 Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '75',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '56',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '32',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'iIJ9h6',
  'name' => 'H2',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '56',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '40',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '26',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.3',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'Tkk5cO',
  'name' => 'H3',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '30',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '30',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '28',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.3',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'HEGRJG',
  'name' => 'H4',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '26',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '22',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => '6Lj3vL',
  'name' => 'H4 Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '24',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '0.8',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'z5lRfr',
  'name' => 'H5',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.6',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'n1CZUD',
  'name' => 'H6',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'mVv5iG',
  'name' => 'Button',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
    'weight' => '500',
    'transform' => 'uppercase',
    'spacing' => 
    array (
      'Desktop' => '0.05',
    ),
  ),
),array (
  'id' => 'k9LI1q',
  'name' => 'Text',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.6',
      ),
    ),
    'weight' => '300',
  ),
),array (
  'id' => 'aRqp9G',
  'name' => 'Funfact',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '48',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '45',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '35',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '700',
  ),
),array (
  'id' => 'P4Hcoa',
  'name' => 'Funfact Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '60',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '54',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '40',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '700',
  ),
),array (
  'id' => 'owa82Y',
  'name' => 'Title Funfact',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => 'saq537',
  'name' => 'Title Funfact Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => '1x8hse',
  'name' => 'Super',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '42',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '42',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '35',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.4',
      ),
    ),
    'weight' => '700',
  ),
),array (
  'id' => 'WXSRLk',
  'name' => 'Super Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '60',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '54',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '40',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '700',
  ),
),array (
  'id' => 'eHu9Ta',
  'name' => 'Testimonials',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '35',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.3',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => 'P6qBfg',
  'name' => 'Designation Testimonials',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => 'pDlWqy',
  'name' => 'Post Meta',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => 'B3B6IX',
  'name' => 'Category',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '12',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '12',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'TMLEFF',
  'name' => 'Text Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.7',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => '6Nlb6w',
  'name' => 'Text Footer',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
    'weight' => '300',
  ),
),array (
  'id' => 'WdNgFq',
  'name' => 'Text Hero',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.4',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => '8byQMN',
  'name' => 'Text Alt 3',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
    'weight' => '300',
  ),
),array (
  'id' => 'oe0Bpc',
  'name' => 'Our Process',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '45',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '45',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '45',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
    'weight' => '700',
  ),
),array (
  'id' => 'rLQXwY',
  'name' => 'H1 Alt 2',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '60',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '50',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '32',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '500',
    'transform' => 'uppercase',
  ),
),array (
  'id' => '7BXnGM',
  'name' => 'H1 Alt 3',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '60',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '50',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '32',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
  ),
),array (
  'id' => 'eOS527',
  'name' => 'Pricing',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '35',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '35',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '35',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '700',
  ),
),array (
  'id' => 'c218Lv',
  'name' => 'Title Pricing',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => '2CZE3a',
  'name' => 'Projects',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Almarai',
      'value' => 'Almarai',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'C9BfwB',
  'name' => '404',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Plus Jakarta Sans',
      'value' => 'Plus Jakarta Sans',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '180',
        'unit' => 'px',
      ),
      'Tablet' => 
      array (
        'point' => '130',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '100',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
    'weight' => '700',
    'transform' => 'uppercase',
  ),
),
		);
	}



	/**
	 * Add Template to Editor.
	 *
	 * @param array $template_files Path to Template File.
	 * @param array $template_type Template Type.
	 *
	 * @return array
	 */
	public function add_template( $template_files, $template_type ) {
		if ( 'wp_template' === $template_type ) {
			$new_templates = array(
				'home',
				'blog',
				'about',
				'single',
				'index',
				'page',
				'archive',
				'search',
				'404'
			);

			foreach ( $new_templates as $template ) {
				$template_files[] = array(
					'slug'  => $template,
					'path'  => $this->change_stylesheet_directory() . "/templates/{$template}.html",
					'theme' => get_template(),
					'type'  => 'wp_template',
				);
			}
		}

		return $template_files;
	}

	/**
	 * Use gutenverse template file instead.
	 *
	 * @param string $template_file Path to Template File.
	 * @param string $theme_slug Theme Slug.
	 * @param string $template_slug Template Slug.
	 *
	 * @return string
	 */
	public function template_path( $template_file, $theme_slug, $template_slug ) {
		switch ( $template_slug ) {
            case 'home':
					return $this->change_stylesheet_directory() . 'templates/home.html';
			case 'header':
					return $this->change_stylesheet_directory() . 'parts/header.html';
			case 'footer':
					return $this->change_stylesheet_directory() . 'parts/footer.html';
			case 'blog':
					return $this->change_stylesheet_directory() . 'templates/blog.html';
			case 'about':
					return $this->change_stylesheet_directory() . 'templates/about.html';
			case 'single':
					return $this->change_stylesheet_directory() . 'templates/single.html';
			case 'index':
					return $this->change_stylesheet_directory() . 'templates/index.html';
			case 'page':
					return $this->change_stylesheet_directory() . 'templates/page.html';
			case 'archive':
					return $this->change_stylesheet_directory() . 'templates/archive.html';
			case 'search':
					return $this->change_stylesheet_directory() . 'templates/search.html';
			case '404':
					return $this->change_stylesheet_directory() . 'templates/404.html';
		}

		return $template_file;
	}

	/**
	 * Register Block Pattern.
	 */
	public function register_block_patterns() {
		new Block_Patterns();
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function dashboard_scripts() {
		$screen = get_current_screen();
		wp_enqueue_script('wp-api-fetch');

		if ( is_admin() ) {
			// enqueue css.
			wp_enqueue_style(
				'contrax-lite-dashboard',
				CONTRAX_LITE_URI . '/assets/css/theme-dashboard.css',
				array(),
				CONTRAX_LITE_VERSION
			);

			// enqueue js.
			wp_enqueue_script(
				'contrax-lite-dashboard',
				CONTRAX_LITE_URI . '/assets/js/theme-dashboard.js',
				array( 'wp-api-fetch' ),
				CONTRAX_LITE_VERSION,
				true
			);

			wp_localize_script( 'contrax-lite-dashboard', 'GutenThemeConfig', $this->theme_config() );
		}
	}

	/**
	 * Check if plugin is installed.
	 *
	 * @param string $plugin_slug plugin slug.
	 * 
	 * @return boolean
	 */
	public function is_installed( $plugin_slug ) {
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$plugin_dir = dirname($plugin_file);

			if ($plugin_dir === $plugin_slug) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register static data to be used in theme's js file
	 */
	public function theme_config() {
		$active_plugins = get_option( 'active_plugins' );
		$plugins = array();
		foreach( $active_plugins as $active ) {
			$plugins[] = explode( '/', $active)[0];
		}

		$config = array(
			'home_url'     => home_url(),
			'version'      => CONTRAX_LITE_VERSION,
			'images'       => CONTRAX_LITE_URI . '/assets/img/',
			'title'        => esc_html__( 'Contrax Lite', 'contrax-lite' ),
			'description'  => esc_html__( 'Contrax Lite is a sleek and fully responsive theme template designed for WordPress full-site editing, fully optimized for the Gutenverse plugin. Contrax Lite is crafted specifically for construction firms, engineering companies, and independent contractors seeking a modern and professional online presence. This template package includes both a core version and a Gutenverse plugin version, offering pre-designed block patterns that make it easy to customize your site without needing to start from scratch. We’ve designed Contrax Lite to streamline your website creation process, making WordPress full-site editing a seamless and intuitive experience.




', 'contrax-lite' ),
			'pluginTitle'  => esc_html__( 'Plugin Requirement', 'contrax-lite' ),
			'pluginDesc'   => esc_html__( 'This theme require some plugins. Please make sure all the plugin below are installed and activated.', 'contrax-lite' ),
			'note'         => esc_html__( '', 'contrax-lite' ),
			'note2'        => esc_html__( '', 'contrax-lite' ),
			'demo'         => esc_html__( '', 'contrax-lite' ),
			'demoUrl'      => esc_url( 'https://gutenverse.com/demo?name=contrax-lite' ),
			'install'      => '',
			'installText'  => esc_html__( 'Install Gutenverse Plugin', 'contrax-lite' ),
			'activateText' => esc_html__( 'Activate Gutenverse Plugin', 'contrax-lite' ),
			'doneText'     => esc_html__( 'Gutenverse Plugin Installed', 'contrax-lite' ),
			'dashboardPage'=> admin_url( 'themes.php?page=contrax-lite-dashboard' ),
			'logo'         => false,
			'slug'         => 'contrax-lite',
			'upgradePro'   => 'https://gutenverse.com/pro',
			'supportLink'  => 'https://support.jegtheme.com/forums/forum/fse-themes/',
			'libraryApi'   => 'https://gutenverse.com//wp-json/gutenverse-server/v1',
			'docsLink'     => 'https://support.jegtheme.com/theme/fse-themes/',
			'pages'        => array(
				'page-0' => CONTRAX_LITE_URI . 'assets/img/ss-full-contrax-home.webp',
				'page-1' => CONTRAX_LITE_URI . 'assets/img/ss-full-contrax-about-us.webp',
				'page-2' => CONTRAX_LITE_URI . 'assets/img/ss-full-contrax-blog.webp'
			),
			'plugins'      => array(
				array(
					'slug'       		=> 'gutenverse',
					'title'      		=> 'Gutenverse',
					'short_desc' 		=> 'GUTENVERSE – GUTENBERG BLOCKS AND WEBSITE BUILDER FOR SITE EDITOR, TEMPLATE LIBRARY, POPUP BUILDER, ADVANCED ANIMATION EFFECTS, 45+ FREE USER-FRIENDLY BLOCKS',
					'active'    		=> in_array( 'gutenverse', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse' ),
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse/assets/icon-128x128.gif?rev=3132408',
  '2x' => 'https://ps.w.org/gutenverse/assets/icon-256x256.gif?rev=3132408',
),
					'download_url'      => '',
				),
				array(
					'slug'       		=> 'gutenverse-form',
					'title'      		=> 'Gutenverse Form',
					'short_desc' 		=> 'GUTENVERSE FORM – FORM BUILDER FOR GUTENBERG BLOCK EDITOR, MULTI-STEP FORMS, CONDITIONAL LOGIC, PAYMENT, CALCULATION, 15+ FREE USER-FRIENDLY FORM BLOCKS',
					'active'    		=> in_array( 'gutenverse-form', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse-form' ),
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse-form/assets/icon-128x128.png?rev=3135966',
),
					'download_url'      => '',
				)
			),
			'assign'       => array(
				
			),
			'dashboardData'=> array(
				'comparison' => array (
  'name_core' => 'Contrax Core',
  'name_lite' => 'Contrax Lite',
  'name_pro' => 'Contrax',
  'description' => 'Here\'s a comparison of Contrax Core, Contrax Lite, and Contrax Pro to help you determine which version best suits your needs.',
  'core_template_count' => 6,
  'lite_theme_template_count' => 11,
  'pro_theme_template_count' => 16,
  'lite_block_count' => 52,
  'lite_template_count' => 200,
  'pro_block_count' => 70,
  'pro_template_count' => 700,
)
			),
			'eventBanner' => array(
				'url' => 'https://gutenverse.com/pro?utm_source=contrax-lite&utm_medium=dashboard&utm_campaign=blackfriday', 
				'expired' => '2024-11-25', 
				'banner' => CONTRAX_LITE_URI . 'assets/img/banner-bfs-dashboard.png',
			)
		);

		if ( isset( $config['assign'] ) && $config['assign'] ) {
			$assign = $config['assign'];
			foreach ( $assign as $key => $value ) {
				$query = new \WP_Query(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'title'          => '' !== $value['page'] ? $value['page'] : $value['title'],
						'posts_per_page' => 1,
					)
				);

				if ( $query->have_posts() ) {
					$post                     = $query->posts[0];
					$page_template            = get_page_template_slug( $post->ID );
					$assign[ $key ]['status'] = array(
						'exists'         => true,
						'using_template' => $page_template === $value['slug'],
					);

				} else {
					$assign[ $key ]['status'] = array(
						'exists'         => false,
						'using_template' => false,
					);
				}

				wp_reset_postdata();
			}
			$config['assign'] = $assign;
		}

		return $config;
	}

	/**
	 * Add Menu
	 */
	public function admin_menu() {
		add_theme_page(
			'Contrax Lite Dashboard',
			'Contrax Lite Dashboard',
			'manage_options',
			'contrax-lite-dashboard',
			array( $this, 'load_dashboard' ),
			1
		);
	}

	/**
	 * Template page
	 */
	public function load_dashboard() {
		?>
			<div id="gutenverse-theme-dashboard">
			</div>
		<?php
	}
}
