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
		add_action( 'after_setup_theme', array( $this, 'setup_theme' ) );
		add_action( 'after_setup_theme', array( $this, 'maybe_sync_global_styles_after_version_change' ), 20 );
		add_action( 'init', array( $this, 'register_block_patterns' ), 9 );
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

		add_filter( 'gutenverse_themes_support_section_global_style', '__return_true' );
		add_filter( 'gutenverse_wporg_plus_mechanism', '__return_true' );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Update Global Styles After Theme Switch
	 */
	public function update_global_styles_after_theme_switch() {
		$this->sync_global_styles();
	}

	/**
	 * Sync Global Styles after a version change.
	 */
	public function maybe_sync_global_styles_after_version_change() {
		$synced_version = get_option( 'contrax-lite_global_styles_synced_version' );

		if ( CONTRAX_LITE_VERSION === $synced_version ) {
			return;
		}

		$this->sync_global_styles();
	}

	/**
	 * Sync Global Styles After Theme Update.
	 *
	 * @param WP_Upgrader $upgrader_object Upgrader instance.
	 * @param array       $options         Update options.
	 */
	public function sync_global_styles_after_theme_update( $upgrader_object, $options ) {
		if ( empty( $options['type'] ) || 'theme' !== $options['type'] ) {
			return;
		}

		if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
			return;
		}

		if ( empty( $options['themes'] ) || ! is_array( $options['themes'] ) ) {
			return;
		}

		$current_theme = get_stylesheet();
		$parent_theme  = get_template();

		if ( ! in_array( $current_theme, $options['themes'], true ) && ! in_array( $parent_theme, $options['themes'], true ) ) {
			return;
		}

		$this->sync_global_styles();
	}

	/**
	 * Sync Global Styles.
	 */
	private function sync_global_styles() {
		$this->sync_global_colors();
		$this->sync_global_fonts();
		update_option( 'contrax-lite_global_styles_synced_version', CONTRAX_LITE_VERSION );
	}

	/**
	 * Sync Global Colors.
	 */
	private function sync_global_colors() {
		// Get the path to the current theme's theme.json file.
		$theme_json_path = get_template_directory() . '/theme.json';
		$theme_slug      = get_option( 'stylesheet' ); // Get the current theme's slug.
		$args            = array(
			'post_type'      => 'wp_global_styles',
			'post_status'    => 'publish',
			'name'           => 'wp-global-styles-' . $theme_slug,
			'posts_per_page' => 1,
		);

		$global_styles_query = new WP_Query( $args );
		// Check if the theme.json file exists.
		if ( file_exists( $theme_json_path ) && $global_styles_query->have_posts() ) {
			$global_styles_query->the_post();
			$global_styles_post_id = get_the_ID();
			// Step 2: Get the existing global styles (color palette).
			$global_styles_content = json_decode( get_post_field( 'post_content', $global_styles_post_id ), true );
			if ( isset( $global_styles_content['settings']['color']['palette']['theme'] ) ) {
				$existing_colors = $global_styles_content['settings']['color']['palette']['theme'];
			} else {
				$existing_colors = array();
			}

			// Step 3: Extract slugs from the existing colors.
			$existing_slugs = array_column( $existing_colors, 'slug' );
			// Step 4:Read the contents of the theme.json file.

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$theme_json_content = $wp_filesystem->get_contents( $theme_json_path );
			$theme_json_data    = json_decode( $theme_json_content, true );

			// Access the color palette from the theme.json file.
			if ( isset( $theme_json_data['settings']['color']['palette'] ) ) {
				$theme_colors = $theme_json_data['settings']['color']['palette'];
				$has_changes  = false;

				// Step 5: Loop through theme.json colors and add them if they don't exist.
				foreach ( $theme_colors as $theme_color ) {
					if ( ! empty( $theme_color['slug'] ) && ! in_array( $theme_color['slug'], $existing_slugs, true ) ) {
						$existing_colors[] = $theme_color; // Add new color to the existing palette.
						$existing_slugs[] = $theme_color['slug'];
						$has_changes      = true;
					}
				}

				if ( $has_changes ) {
					// Step 6: Update the global styles content with the new colors.
					$global_styles_content['settings']['color']['palette']['theme'] = $existing_colors;

					// Step 7: Save the updated global styles back to the post.
					wp_update_post(
						array(
							'ID'           => $global_styles_post_id,
							'post_content' => wp_json_encode( $global_styles_content ),
						)
					);
				}
			}
			wp_reset_postdata(); // Reset the query.
		}
	}

	/**
	 * Sync Global Fonts.
	 */
	private function sync_global_fonts() {
		$theme_name    = get_stylesheet();
		$option_name   = 'gutenverse-global-variable-font-' . $theme_name;
		$default_fonts = $this->default_font_variable();
		$global_fonts  = get_option( $option_name );

		if ( ! is_array( $global_fonts ) ) {
			update_option( $option_name, $default_fonts );

			return;
		}

		$existing_keys = array();
		$has_changes   = false;

		foreach ( $global_fonts as $font ) {
			$font_key = $this->get_font_sync_key( $font );

			if ( $font_key ) {
				$existing_keys[] = $font_key;
			}
		}

		foreach ( $default_fonts as $font ) {
			$font_key = $this->get_font_sync_key( $font );

			if ( $font_key && in_array( $font_key, $existing_keys, true ) ) {
				continue;
			}

			$global_fonts[] = $font;
			$has_changes    = true;

			if ( $font_key ) {
				$existing_keys[] = $font_key;
			}
		}

		if ( $has_changes ) {
			update_option( $option_name, $global_fonts );
		}
	}

	/**
	 * Get font sync key.
	 *
	 * @param array $font Font item.
	 *
	 * @return string
	 */
	private function get_font_sync_key( $font ) {
		if ( ! empty( $font['slug'] ) ) {
			return (string) $font['slug'];
		}

		if ( ! empty( $font['id'] ) ) {
			return (string) $font['id'];
		}

		if ( ! empty( $font['name'] ) ) {
			return sanitize_title( $font['name'] );
		}

		return '';
	}

	/**
	 * Setup theme.
	 */
	public function setup_theme() {
		load_theme_textdomain( 'contrax-lite', get_template_directory() . '/languages' );
	}

	/**
	 * Change Stylesheet Directory.
	 *
	 * @return string
	 */
	public function change_stylesheet_directory() {
		return CONTRAX_LITE_DIR . 'gutenverse-files/';
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
				foreach ( $array2 as $item ) { // default font.
					$result[ $item['id'] ] = $item;
				}
				foreach ( $array1 as $item ) { // overwrite fonts.
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
  'name' => 'H1 (Legacy)',
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
  'name' => 'H1 Alt (Legacy)',
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
  'name' => 'H2 (Legacy)',
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
  'name' => 'H3 (Legacy)',
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
  'name' => 'H4 (Legacy)',
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
  'name' => 'H4 Alt (Legacy)',
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
  'name' => 'H5 (Legacy)',
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
  'name' => 'H6 (Legacy)',
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
  'name' => 'Button (Legacy)',
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
  'name' => 'Text (Legacy)',
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
  'name' => 'Funfact (Legacy)',
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
  'name' => 'Funfact Alt (Legacy)',
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
  'name' => 'Title Funfact (Legacy)',
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
  'name' => 'Title Funfact Alt (Legacy)',
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
  'name' => 'Super (Legacy)',
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
  'name' => 'Super Alt (Legacy)',
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
  'name' => 'Testimonials (Legacy)',
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
  'name' => 'Designation Testimonials (Legacy)',
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
  'name' => 'Post Meta (Legacy)',
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
  'name' => 'Category (Legacy)',
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
  'name' => 'Text Alt (Legacy)',
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
  'name' => 'Text Footer (Legacy)',
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
  'name' => 'Text Hero (Legacy)',
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
  'name' => 'Text Alt 3 (Legacy)',
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
  'name' => 'Our Process (Legacy)',
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
  'name' => 'H1 Alt 2 (Legacy)',
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
  'name' => 'H1 Alt 3 (Legacy)',
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
  'name' => 'Pricing (Legacy)',
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
  'name' => 'Title Pricing (Legacy)',
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
  'name' => 'Projects (Legacy)',
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
  'name' => '404 (Legacy)',
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
),array (
  'id' => 'gv-font-primary',
  'name' => 'Primary',
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
  'id' => 'gv-font-secondary',
  'name' => 'Secondary',
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
  'id' => 'gv-font-feature',
  'name' => 'Feature',
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
  'id' => 'gv-font-feature-secondary',
  'name' => 'Feature Secondary',
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
  'id' => 'gv-font-meta',
  'name' => 'Meta',
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
  'id' => 'gv-font-text',
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
  'id' => 'gv-font-text-hero',
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
  'id' => 'gv-font-text-small',
  'name' => 'Text Small',
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
  'id' => 'gv-font-subheading',
  'name' => 'Subheading',
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
  'id' => 'gv-font-button-primary',
  'name' => 'Button Primary ',
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
  'id' => 'gv-font-button-secondary',
  'name' => 'Button Secondary',
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
  'id' => 'gv-font-form-label',
  'name' => 'Form Label',
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
  'id' => 'gv-font-heading-404',
  'name' => 'Heading 404',
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
),array (
  'id' => 'gv-font-primary-accent',
  'name' => 'Primary Accent',
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
  'id' => 'gv-font-feature-alt-primary',
  'name' => 'Feature Alt pimary',
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
  'id' => 'gv-font-funfact',
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
  'id' => 'gv-font-primary-alt-secondary',
  'name' => 'Primary Alt Secondary',
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
  'id' => 'gv-font-funfact-alt',
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
  'id' => 'gv-font-super',
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
  'id' => 'gv-font-super-alt',
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
  'id' => 'gv-font-feature-alt-secondary',
  'name' => 'Feature Alt Secondary',
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
  'id' => 'gv-font-designation-testimonials',
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
  'id' => 'gv-font-post-meta',
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
  'id' => 'gv-font-text-footer',
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
  'id' => 'gv-font-secondary-alt',
  'name' => 'Secondary Alt',
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
    'style' => 'italic',
  ),
),array (
  'id' => 'gv-font-primary-alt-primary',
  'name' => 'Primary Alt Primary',
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
  'id' => 'gv-font-primary-alt-accent',
  'name' => 'Primary Alt Accent',
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
  'id' => 'gv-font-feature-alt-tertiary',
  'name' => 'Feature Alt Tertiary',
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
  'id' => 'gv-font-title-pricing',
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
  'id' => 'gv-font-text-alt',
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
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
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
				'single',
				'index',
				'page',
				'archive',
				'search',
				'404',
				'blank-canvas',
				'full-width'
			);

			foreach ( $new_templates as $template ) {
				$template_files[] = array(
					'slug'  => $template,
					'path'  => $this->change_stylesheet_directory() . "/templates/{$template}.html",
					'theme' => get_template(),
					'type'  => 'wp_template',
					'title' => ucfirst( str_replace( '-', ' ', $template ) ),
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
					return $this->change_stylesheet_directory() . '/templates/home.html';
			case 'header':
					return $this->change_stylesheet_directory() . '/parts/header.html';
			case 'footer':
					return $this->change_stylesheet_directory() . '/parts/footer.html';
			case 'single':
					return $this->change_stylesheet_directory() . '/templates/single.html';
			case 'index':
					return $this->change_stylesheet_directory() . '/templates/index.html';
			case 'page':
					return $this->change_stylesheet_directory() . '/templates/page.html';
			case 'archive':
					return $this->change_stylesheet_directory() . '/templates/archive.html';
			case 'search':
					return $this->change_stylesheet_directory() . '/templates/search.html';
			case '404':
					return $this->change_stylesheet_directory() . '/templates/404.html';
			case 'blank-canvas':
					return $this->change_stylesheet_directory() . '/templates/blank-canvas.html';
			case 'full-width':
					return $this->change_stylesheet_directory() . '/templates/full-width.html';
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
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public function dashboard_scripts( $hook_suffix ) {
		
					if ( 'appearance_page_contrax-lite-dashboard' !== $hook_suffix && 'admin_page_gutenverse-onboarding-wizard' !== $hook_suffix ) {
						return;
					}
		
		if ( is_admin() ) {
			// enqueue css.
			
						wp_enqueue_style(
							'contrax-lite-dashboard',
							get_template_directory_uri() . '/assets/css/theme-dashboard.css',
							array(),
							CONTRAX_LITE_VERSION
						);
					
		$dashboard_includes = include get_template_directory() . '/assets/dependencies/theme-dashboard.asset.php';
		
						wp_enqueue_script(
							'contrax-lite-dashboard',
							get_template_directory_uri() . '/assets/js/theme-dashboard.js',
							$dashboard_includes["dependencies"],
							CONTRAX_LITE_VERSION,
							true
						);
					
		
					wp_enqueue_style(
						'contrax-lite-dashboard-inter-font',
						get_template_directory_uri() . '/assets/fonts/inter/inter.css',
						[],
						null
					);

			wp_enqueue_script('wp-api-fetch');

			wp_localize_script( 'wp-api-fetch', 'GutenThemeConfig', $this->theme_config() );
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
		global $pagenow;
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$active_plugins = get_option( 'active_plugins' );
		$plugins = array();
		$installed_plugins = get_plugins();
		$installed_plugin_versions = array();
		foreach ( $active_plugins as $active ) {
			$plugin_name = explode( '/', $active )[0];
			$plugins[]   = $plugin_name;
			$installed_plugin_versions[ $plugin_name ] = isset( $installed_plugins[ $active ] ) ? $installed_plugins[ $active ]['Version'] : '1.0.0';
		}

		$config = array(
			'home_url'      => home_url(),
			'active_plugins'=> $active_plugins,
			'version'       => CONTRAX_LITE_VERSION,
			'images'        => get_template_directory_uri() . '/assets/img/',
			'title'         => esc_html__( 'Contrax Lite', 'contrax-lite' ),
			'description'   => esc_html__( 'Contrax Lite is a Construction & Building Company WordPress Block Theme designed for businesses that want to create a strong and professional online presence. Built with full site editing and powered by Gutenverse, this theme offers a flexible and modern platform to showcase construction projects, company services, and expertise with a clean and structured layout. Whether you run a construction firm, contractor service, or property development business, Contrax Lite helps you present your work with clarity and credibility. It is ideal for contractors, builders, architects, engineering firms, and renovation specialists looking to expand their reach online. With responsive layouts, customizable block patterns, and well-organized pages, you can easily highlight projects, services, team members, and client testimonials. Optimized for performance and usability, Contrax Lite allows you to build a reliable website that reflects your brand and expertise in the Construction & Building Company sector. If you are looking for a scalable and professional solution, Contrax Lite is a dependable Construction & Building Company WordPress theme to support your business growth.', 'contrax-lite' ),
			'pluginTitle'   => esc_html__( 'Plugin Requirement', 'contrax-lite' ),
			'pluginDesc'    => esc_html__( 'This theme require some plugins. Please make sure all the plugin below are installed and activated.', 'contrax-lite' ),
			'note'          => '',
			'note2'         => '',
			'demo'          => '',
			'demoUrl'       => esc_url( 'https://gutenverse.com/demo?name=contrax-lite' ),
			'install'       => '',
			'installText'   => esc_html__( 'Install Gutenverse Plugin', 'contrax-lite' ),
			'activateText'  => esc_html__( 'Activate Gutenverse Plugin', 'contrax-lite' ),
			'doneText'      => esc_html__( 'Gutenverse Plugin Installed', 'contrax-lite' ),
			'dashboardPage' => admin_url( 'themes.php?page=contrax-lite-dashboard' ),
			'logo'          => trailingslashit( get_template_directory_uri() ) . 'assets/img/logo-contrax-dark.webp',
			'slug'          => 'contrax-lite',
			'upgradePro'    => esc_url( 'https://gutenverse.com/pricing' ),
			'supportLink'   => esc_url( 'https://wordpress.org/support/theme/contrax-lite/' ),
			'libraryApi'    => esc_url( 'https://gutenverse.com//wp-json/gutenverse-server/v1' ),
			'docsLink'      => esc_url( 'https://gutenverse.com/docs/' ),
			'pages'         => array(
				'page-0' => get_template_directory_uri() . '/assets/img/ss-full-contrax-home.webp',
				'page-1' => get_template_directory_uri() . '/assets/img/ss-full-contrax-about-us.webp',
				'page-2' => get_template_directory_uri() . '/assets/img/ss-full-contrax-blog.webp'
			),
			'plugins'       => array(
				array(
					'slug'       		=> 'gutenverse',
					'title'      		=> esc_html__( 'Gutenverse', 'contrax-lite' ),
					'short_desc' 		=> esc_html__( 'GUTENVERSE – GUTENBERG BLOCKS AND WEBSITE BUILDER FOR SITE EDITOR, TEMPLATE LIBRARY, POPUP BUILDER, ADVANCED ANIMATION EFFECTS, COMPLETE FEATURE ECOSYSTEM, 45+ FREE USER-FRIENDLY BLOCKS', 'contrax-lite' ),
					'active'    		=> in_array( 'gutenverse', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse' ),
					'req_version'    	=> '2.1.2',
					'installed_version' => isset( $installed_plugins['gutenverse/gutenverse.php']['Version'] ) ? $installed_plugins['gutenverse/gutenverse.php']['Version'] : '',
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse/assets/icon-128x128.gif?rev=3132408',
  '2x' => 'https://ps.w.org/gutenverse/assets/icon-256x256.gif?rev=3132408',
),
					'download_url'      => '',
				),
				array(
					'slug'       		=> 'gutenverse-form',
					'title'      		=> esc_html__( 'Gutenverse Form', 'contrax-lite' ),
					'short_desc' 		=> esc_html__( 'GUTENVERSE FORM – FORM BUILDER FOR GUTENBERG BLOCK EDITOR, MULTI-STEP FORMS, CONDITIONAL LOGIC, PAYMENT, CALCULATION, 15+ FREE USER-FRIENDLY FORM BLOCKS', 'contrax-lite' ),
					'active'    		=> in_array( 'gutenverse-form', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse-form' ),
					'req_version'    	=> '1.1.2',
					'installed_version' => isset( $installed_plugins['gutenverse-form/gutenverse-form.php']['Version'] ) ? $installed_plugins['gutenverse-form/gutenverse-form.php']['Version'] : '',
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse-form/assets/icon-128x128.png?rev=3135966',
),
					'download_url'      => '',
				),
				array(
					'slug'       		=> 'gutenverse-companion',
					'title'      		=> esc_html__( 'Gutenverse Companion', 'contrax-lite' ),
					'short_desc' 		=> esc_html__( 'A companion plugin designed specifically to enhance and extend the functionality of Gutenverse base themes. This plugin integrates seamlessly with the base themes, providing additional features, customization options, and advanced tools to optimize the overall user experience and streamline the development process.', 'contrax-lite' ),
					'active'    		=> in_array( 'gutenverse-companion', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse-companion' ),
					'req_version'    	=> '2.3.3',
					'installed_version' => isset( $installed_plugins['gutenverse-companion/gutenverse-companion.php']['Version'] ) ? $installed_plugins['gutenverse-companion/gutenverse-companion.php']['Version'] : '',
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse-companion/assets/icon-128x128.png?rev=3162415',
),
					'download_url'      => '',
				)
			),
			'assign'        => array(
				
			),
			'dashboardData' => array(
				'lite_template_count' => 11,
'plus_template_count' => 18,
'lite_block_count' => 40,
'plus_block_count' => 80,
'lite_page_count' => 0,
'plus_page_count' => 8,
'plus_pattern_count' => 43,
'lite_pattern_count' => 16
			),
			'lite_plus_type' => 'wporg',
			'pro_preview' => trailingslashit( get_template_directory_uri() ) . 'assets/img/ss-cover-contrax-pro-home.webp',
			'pro_title' => esc_html__('Contrax Pro', 'contrax-lite'),
			'upgrade_required_license' => array('professional','agency','enterprise','ultimate'),
		);

		if ( 'themes.php' === $pagenow && isset( $_GET['page'] ) && 'contrax-lite-dashboard' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			$admin_config = array(
				'system' => $this->system_status(),
			);
			$config = array_merge( $config, $admin_config );
		}

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
						 * System Status.
						 *
						 * @return array
						 */
						public function system_status() {
							$status      = array();
							$active_demo = get_option( 'gutenverse_companion_template_options' );
							/** Themes */
							$theme                    = wp_get_theme();
							$parent                   = wp_get_theme( get_template() );
							$status['theme_name']     = $theme->get( 'Name' );
							$status['theme_version']  = $theme->get( 'Version' );
							$status['is_child_theme'] = is_child_theme();
							$status['parent_theme']   = $parent->get( 'Name' );
							$status['parent_version'] = $parent->get( 'Version' );

							$status['active_companion_demo'] = $active_demo['active_demo'] ?? esc_html__( 'You don\'t have any demo activated', 'contrax-lite' );

							/** WordPress Environment */
							$wp_upload_dir              = wp_upload_dir();
							$status['home_url']         = home_url( '/' );
							$status['site_url']         = site_url();
							$status['login_url']        = wp_login_url();
							$status['wp_version']       = get_bloginfo( 'version', 'display' );
							$status['is_multisite']     = is_multisite();
							$status['wp_debug']         = defined( 'WP_DEBUG' ) && WP_DEBUG;
							$status['memory_limit']     = ini_get( 'memory_limit' );
							$status['wp_memory_limit']  = WP_MEMORY_LIMIT;
							$status['wp_language']      = get_locale();
							$status['writeable_upload'] = wp_is_writable( $wp_upload_dir['basedir'] );
							$status['count_category']   = wp_count_terms( 'category' );
							$status['count_tag']        = wp_count_terms( 'post_tag' );

							/** Server Environment */
							$remote = get_transient( 'gutenverse_wp_remote_get_status_cache' );
							if ( ! $remote ) {
								$remote = wp_remote_get( home_url() );
								set_transient( 'gutenverse_wp_remote_get_status_cache', $remote, 30 * MINUTE_IN_SECONDS );
							}

							$gd_support = array();
							if ( function_exists( 'gd_info' ) ) {
								foreach ( gd_info() as $key => $value ) {
									$gd_support[ $key ] = $value;
								}
							}

							$status['server_info']        = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
							$status['php_version']        = PHP_VERSION;
							$status['post_max_size']      = ini_get( 'post_max_size' );
							$status['max_input_vars']     = ini_get( 'max_input_vars' );
							$status['max_execution_time'] = ini_get( 'max_execution_time' );
							$status['suhosin']            = extension_loaded( 'suhosin' );
							$status['imagick']            = extension_loaded( 'imagick' );
							$status['gd']                 = extension_loaded( 'gd' ) && function_exists( 'gd_info' );
							$status['gd_webp']            = extension_loaded( 'gd' ) && $gd_support['WebP Support'];
							$status['fileinfo']           = extension_loaded( 'fileinfo' ) && ( function_exists( 'finfo_open' ) || function_exists( 'mime_content_type' ) );
							$status['curl']               = extension_loaded( 'curl' ) && function_exists( 'curl_version' );
							$status['wp_remote_get']      = ! is_wp_error( $remote ) && $remote['response']['code'] >= 200 && $remote['response']['code'] < 300;

							/** Plugins */
							$status['plugins'] = $this->data_active_plugin();

							return $status;
						}
						/**
						 * Data active plugin
						 *
						 * @return array
						 */
						public function data_active_plugin() {
							$active_plugin = array();

							$plugins = array_merge(
								array_flip( (array) get_option( 'active_plugins', array() ) ),
								(array) get_site_option( 'active_sitewide_plugins', array() )
							);

							$plugins = array_intersect_key( get_plugins(), $plugins );

							if ( count( $plugins ) > 0 ) {
								foreach ( $plugins as $plugin ) {
									$item                = array();
									$item['uri']         = isset( $plugin['PluginURI'] ) ? esc_url( $plugin['PluginURI'] ) : '#';
									$item['name']        = isset( $plugin['Name'] ) ? $plugin['Name'] : esc_html__( 'unknown', 'contrax-lite' );
									$item['author_uri']  = isset( $plugin['AuthorURI'] ) ? esc_url( $plugin['AuthorURI'] ) : '#';
									$item['author_name'] = isset( $plugin['Author'] ) ? $plugin['Author'] : esc_html__( 'unknown', 'contrax-lite' );
									$item['version']     = isset( $plugin['Version'] ) ? $plugin['Version'] : esc_html__( 'unknown', 'contrax-lite' );

									$content = esc_html__( 'by', 'contrax-lite' );

									$active_plugin[] = array(
										'type'            => 'status',
										'title'           => $item['name'],
										'content'         => $content,
										'link'            => $item['author_uri'],
										'link_text'       => $item['author_name'],
										'additional_text' => $item['version'],
									);
								}
							}

							return $active_plugin;
						}
					
			
						/**
						 * Add Menu
						 */
						public function admin_menu() {
							add_theme_page(
								esc_html__('Contrax Lite Dashboard', 'contrax-lite'),
								esc_html__('Contrax Lite Dashboard', 'contrax-lite'),
								'edit_theme_options',
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
								<div id='gutenverse-theme-dashboard'>
								</div>
							<?php
						}
					
}
