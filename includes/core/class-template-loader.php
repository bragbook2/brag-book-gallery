<?php
/**
 * Template Loader
 *
 * Manages template loading for both JavaScript and Local modes.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\Mode\Mode_Manager;
use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;
use BRAGBookGallery\Includes\Traits\Trait_Tools;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Loader Class
 *
 * Handles template loading and routing for different operational modes.
 *
 * @since 3.0.0
 */
class Template_Loader {
	use Trait_Tools;

	/**
	 * Mode manager instance
	 *
	 * @since 3.0.0
	 * @var Mode_Manager
	 */
	private $mode_manager;

	/**
	 * Template directory path
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private $template_path;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->mode_manager = Mode_Manager::get_instance();
		$this->template_path = self::get_plugin_path() . 'templates/';
		
		$this->init();
	}

	/**
	 * Initialize template loader
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Template loading hooks
		add_filter( 'template_include', array( $this, 'template_chooser' ), 99 );
		add_filter( 'single_template_hierarchy', array( $this, 'add_single_templates' ) );
		add_filter( 'archive_template_hierarchy', array( $this, 'add_archive_templates' ) );
		add_filter( 'taxonomy_template_hierarchy', array( $this, 'add_taxonomy_templates' ) );
		
		// Template redirect for JavaScript mode
		add_action( 'template_redirect', array( $this, 'handle_javascript_mode_requests' ), 5 );
		
		// Add template variables
		add_action( 'wp', array( $this, 'setup_template_vars' ) );
	}

	/**
	 * Choose the appropriate template based on current mode
	 *
	 * @since 3.0.0
	 * @param string $template Current template path.
	 * @return string Modified template path.
	 */
	public function template_chooser( string $template ): string {
		if ( $this->mode_manager->is_local_mode() ) {
			return $this->load_local_mode_template( $template );
		} else {
			return $this->load_javascript_mode_template( $template );
		}
	}

	/**
	 * Load template for Local mode
	 *
	 * @since 3.0.0
	 * @param string $template Current template path.
	 * @return string Template path.
	 */
	private function load_local_mode_template( string $template ): string {
		global $wp_query;

		// Single gallery post
		if ( is_singular( Gallery_Post_Type::POST_TYPE ) ) {
			$custom_template = $this->locate_template( 'local-mode/single-brag_gallery.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		// Gallery archive
		if ( is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ) {
			$custom_template = $this->locate_template( 'local-mode/archive-brag_gallery.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		// Category taxonomy
		if ( is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) ) {
			$term = get_queried_object();
			$templates = array(
				"local-mode/taxonomy-{$term->taxonomy}-{$term->slug}.php",
				"local-mode/taxonomy-{$term->taxonomy}.php",
				'local-mode/taxonomy-brag_category.php'
			);
			
			$custom_template = $this->locate_template( $templates );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		// Procedure taxonomy
		if ( is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) ) {
			$term = get_queried_object();
			$templates = array(
				"local-mode/taxonomy-{$term->taxonomy}-{$term->slug}.php",
				"local-mode/taxonomy-{$term->taxonomy}.php",
				'local-mode/taxonomy-brag_procedure.php'
			);
			
			$custom_template = $this->locate_template( $templates );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Load template for JavaScript mode
	 *
	 * @since 3.0.0
	 * @param string $template Current template path.
	 * @return string Template path.
	 */
	private function load_javascript_mode_template( string $template ): string {
		// Check if this is a gallery-related request
		if ( $this->is_gallery_request() ) {
			$custom_template = $this->locate_template( 'javascript-mode/gallery-page.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Handle JavaScript mode requests and routing
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_javascript_mode_requests(): void {
		if ( ! $this->mode_manager->is_javascript_mode() ) {
			return;
		}

		global $wp_query;
		
		// Check if this is a gallery-related virtual URL
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$gallery_base = $this->get_gallery_base_url();

		if ( strpos( $request_uri, $gallery_base ) === 0 ) {
			// Parse the request
			$path = str_replace( $gallery_base, '', $request_uri );
			$path_parts = array_filter( explode( '/', trim( $path, '/' ) ) );

			// Set up query vars for JavaScript mode
			$wp_query->set( 'brag_gallery_mode', 'javascript' );
			$wp_query->set( 'brag_gallery_path', $path_parts );
			
			// Determine the type of request
			if ( empty( $path_parts ) ) {
				// Gallery index
				$wp_query->set( 'brag_gallery_view', 'index' );
			} elseif ( count( $path_parts ) === 1 ) {
				// Category or procedure listing
				$wp_query->set( 'brag_gallery_view', 'category' );
				$wp_query->set( 'brag_gallery_slug', $path_parts[0] );
			} elseif ( count( $path_parts ) === 2 ) {
				// Individual case
				$wp_query->set( 'brag_gallery_view', 'single' );
				$wp_query->set( 'brag_gallery_category', $path_parts[0] );
				$wp_query->set( 'brag_gallery_case', $path_parts[1] );
			}

			$wp_query->is_home = false;
			$wp_query->is_page = false;
			$wp_query->is_single = false;
			$wp_query->is_singular = false;
			$wp_query->is_archive = true;
		}
	}

	/**
	 * Get template by name and mode
	 *
	 * @since 3.0.0
	 * @param string $template_name Template name.
	 * @param string $mode Optional mode ('local', 'javascript', or auto-detect).
	 * @return string Template path.
	 */
	public function get_template( string $template_name, string $mode = '' ): string {
		if ( empty( $mode ) ) {
			$mode = $this->mode_manager->is_local_mode() ? 'local' : 'javascript';
		}

		$template_file = "{$mode}-mode/{$template_name}";
		
		return $this->locate_template( $template_file ) ?: '';
	}

	/**
	 * Load template by mode
	 *
	 * @since 3.0.0
	 * @param string $template_base Base template name.
	 * @param array  $variables Variables to pass to template.
	 * @return void
	 */
	public function load_by_mode( string $template_base, array $variables = array() ): void {
		$mode = $this->mode_manager->is_local_mode() ? 'local' : 'javascript';
		$template = $this->get_template( $template_base, $mode );

		if ( $template ) {
			// Extract variables for template
			if ( ! empty( $variables ) ) {
				extract( $variables, EXTR_SKIP );
			}

			include $template;
		}
	}

	/**
	 * Locate template file
	 *
	 * @since 3.0.0
	 * @param string|array $template_names Template name(s) to search for.
	 * @return string|null Template path or null if not found.
	 */
	private function locate_template( $template_names ): ?string {
		if ( ! is_array( $template_names ) ) {
			$template_names = array( $template_names );
		}

		$located = null;

		foreach ( $template_names as $template_name ) {
			if ( ! $template_name ) {
				continue;
			}

			// Check child theme first
			$child_template = get_stylesheet_directory() . '/brag-book-gallery/' . $template_name;
			if ( file_exists( $child_template ) ) {
				$located = $child_template;
				break;
			}

			// Check parent theme
			$parent_template = get_template_directory() . '/brag-book-gallery/' . $template_name;
			if ( file_exists( $parent_template ) ) {
				$located = $parent_template;
				break;
			}

			// Check plugin templates
			$plugin_template = $this->template_path . $template_name;
			if ( file_exists( $plugin_template ) ) {
				$located = $plugin_template;
				break;
			}
		}

		return $located;
	}

	/**
	 * Add single post templates to hierarchy
	 *
	 * @since 3.0.0
	 * @param array $templates Template hierarchy.
	 * @return array Modified template hierarchy.
	 */
	public function add_single_templates( array $templates ): array {
		if ( ! is_singular( Gallery_Post_Type::POST_TYPE ) ) {
			return $templates;
		}

		if ( $this->mode_manager->is_local_mode() ) {
			$new_templates = array(
				'single-' . Gallery_Post_Type::POST_TYPE . '.php',
				'brag-book-gallery/local-mode/single-brag_gallery.php',
			);
		} else {
			$new_templates = array(
				'brag-book-gallery/javascript-mode/single-gallery.php',
			);
		}

		return array_merge( $new_templates, $templates );
	}

	/**
	 * Add archive templates to hierarchy
	 *
	 * @since 3.0.0
	 * @param array $templates Template hierarchy.
	 * @return array Modified template hierarchy.
	 */
	public function add_archive_templates( array $templates ): array {
		if ( ! is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ) {
			return $templates;
		}

		if ( $this->mode_manager->is_local_mode() ) {
			$new_templates = array(
				'archive-' . Gallery_Post_Type::POST_TYPE . '.php',
				'brag-book-gallery/local-mode/archive-brag_gallery.php',
			);
		} else {
			$new_templates = array(
				'brag-book-gallery/javascript-mode/archive-gallery.php',
			);
		}

		return array_merge( $new_templates, $templates );
	}

	/**
	 * Add taxonomy templates to hierarchy
	 *
	 * @since 3.0.0
	 * @param array $templates Template hierarchy.
	 * @return array Modified template hierarchy.
	 */
	public function add_taxonomy_templates( array $templates ): array {
		$taxonomy = get_query_var( 'taxonomy' );
		
		if ( ! in_array( $taxonomy, array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY ), true ) ) {
			return $templates;
		}

		if ( $this->mode_manager->is_local_mode() ) {
			$new_templates = array(
				"taxonomy-{$taxonomy}.php",
				"brag-book-gallery/local-mode/taxonomy-{$taxonomy}.php",
			);
		} else {
			$new_templates = array(
				"brag-book-gallery/javascript-mode/taxonomy-{$taxonomy}.php",
			);
		}

		return array_merge( $new_templates, $templates );
	}

	/**
	 * Setup template variables
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function setup_template_vars(): void {
		global $brag_gallery_mode, $brag_gallery_data;

		$brag_gallery_mode = $this->mode_manager->get_current_mode();
		$brag_gallery_data = array(
			'mode' => $brag_gallery_mode,
			'is_local_mode' => $this->mode_manager->is_local_mode(),
			'is_javascript_mode' => $this->mode_manager->is_javascript_mode(),
		);

		// Add mode-specific data
		if ( $this->mode_manager->is_javascript_mode() ) {
			$brag_gallery_data = array_merge( $brag_gallery_data, array(
				'api_base' => get_option( 'brag_book_gallery_api_url', '' ),
				'api_token' => get_option( 'brag_book_gallery_api_token', '' ),
				'property_id' => get_option( 'brag_book_gallery_property_id', 0 ),
			) );
		}
	}

	/**
	 * Check if current request is gallery-related
	 *
	 * @since 3.0.0
	 * @return bool True if gallery request.
	 */
	private function is_gallery_request(): bool {
		// Check if we're on a gallery post type page
		if ( is_singular( Gallery_Post_Type::POST_TYPE ) || 
			 is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ||
			 is_tax( array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY ) ) ) {
			return true;
		}

		// Check for virtual gallery URLs in JavaScript mode
		if ( $this->mode_manager->is_javascript_mode() ) {
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			$gallery_base = $this->get_gallery_base_url();
			
			if ( strpos( $request_uri, $gallery_base ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get gallery base URL for virtual routing
	 *
	 * @since 3.0.0
	 * @return string Gallery base URL.
	 */
	private function get_gallery_base_url(): string {
		// Get from settings or use default
		$base = get_option( 'brag_book_gallery_base_url', '/gallery/' );
		
		// Ensure leading and trailing slashes
		$base = '/' . trim( $base, '/' ) . '/';
		
		return $base;
	}

	/**
	 * Render template part
	 *
	 * @since 3.0.0
	 * @param string $slug Template slug.
	 * @param string $name Optional template name.
	 * @param array  $variables Variables to pass to template.
	 * @return void
	 */
	public function get_template_part( string $slug, string $name = '', array $variables = array() ): void {
		$mode = $this->mode_manager->is_local_mode() ? 'local' : 'javascript';
		
		$templates = array();
		
		if ( $name ) {
			$templates[] = "{$mode}-mode/{$slug}-{$name}.php";
		}
		
		$templates[] = "{$mode}-mode/{$slug}.php";
		
		$template = $this->locate_template( $templates );
		
		if ( $template ) {
			// Extract variables for template
			if ( ! empty( $variables ) ) {
				extract( $variables, EXTR_SKIP );
			}

			include $template;
		}
	}

	/**
	 * Include template with variables
	 *
	 * @since 3.0.0
	 * @param string $template_name Template name.
	 * @param array  $variables Variables to pass to template.
	 * @return string Template output.
	 */
	public function render_template( string $template_name, array $variables = array() ): string {
		$template = $this->get_template( $template_name );
		
		if ( ! $template ) {
			return '';
		}

		// Extract variables
		if ( ! empty( $variables ) ) {
			extract( $variables, EXTR_SKIP );
		}

		// Capture output
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Get available templates for current mode
	 *
	 * @since 3.0.0
	 * @return array Available templates.
	 */
	public function get_available_templates(): array {
		$mode = $this->mode_manager->is_local_mode() ? 'local' : 'javascript';
		$template_dir = $this->template_path . $mode . '-mode/';
		
		$templates = array();
		
		if ( is_dir( $template_dir ) ) {
			$files = scandir( $template_dir );
			
			foreach ( $files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'php' ) {
					$templates[] = basename( $file, '.php' );
				}
			}
		}

		return $templates;
	}

	/**
	 * Add body classes for gallery pages
	 *
	 * @since 3.0.0
	 * @param array $classes Current body classes.
	 * @return array Modified body classes.
	 */
	public function add_body_classes( array $classes ): array {
		if ( $this->is_gallery_request() ) {
			$classes[] = 'brag-book-gallery';
			$classes[] = 'brag-gallery-' . $this->mode_manager->get_current_mode();
			
			if ( is_singular( Gallery_Post_Type::POST_TYPE ) ) {
				$classes[] = 'brag-gallery-single';
			}
			
			if ( is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ) {
				$classes[] = 'brag-gallery-archive';
			}
			
			if ( is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) ) {
				$classes[] = 'brag-gallery-category';
			}
			
			if ( is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) ) {
				$classes[] = 'brag-gallery-procedure';
			}
		}

		return $classes;
	}
}