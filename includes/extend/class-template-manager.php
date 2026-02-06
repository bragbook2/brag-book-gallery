<?php
/**
 * Template Manager for BRAGBook Gallery Plugin
 *
 * Handles registration and loading of block templates and PHP templates
 * for procedure taxonomy pages in both classic and block themes.
 *
 * @package BRAGBookGallery
 * @subpackage Extend
 * @since 3.0.0
 */

namespace BRAGBookGallery\Includes\Extend;

/**
 * Template Manager class
 *
 * Manages template registration, loading, and customization for procedure
 * taxonomy pages. Supports both classic PHP templates and Gutenberg block templates.
 *
 * @since 3.0.0
 */
class Template_Manager {

	/**
	 * Initialize template functionality
	 *
	 * Sets up hooks for template registration and loading.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __construct() {
		// Register block templates for block themes
		add_action( 'init', [ $this, 'register_block_templates' ] );

		// Handle template loading for classic themes
		add_filter( 'template_include', [ $this, 'load_taxonomy_templates' ] );

		// Add theme support for block templates
		add_action( 'after_setup_theme', [ $this, 'add_theme_support' ] );

		// Add filter to make block templates discoverable
		add_filter( 'get_block_file_template', [ $this, 'get_block_file_template' ], 10, 3 );

		// Remove duplicate h1.page-title on procedure taxonomy pages
		add_action( 'template_redirect', [ $this, 'buffer_procedure_output' ] );
	}

	/**
	 * Start output buffering on procedure taxonomy pages to remove duplicate h1
	 *
	 * Themes often render their own <h1 class="page-title"> on taxonomy archives,
	 * causing a duplicate heading alongside the plugin's own procedure title.
	 * This buffers the output and strips the theme's h1.page-title via regex.
	 *
	 * @since 4.3.3
	 * @return void
	 */
	public function buffer_procedure_output(): void {
		if ( ! is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			return;
		}

		ob_start( [ $this, 'remove_duplicate_page_title' ] );
	}

	/**
	 * Remove the h1.page-title element from buffered output
	 *
	 * @since 4.3.3
	 * @param string $html The buffered HTML output.
	 * @return string Modified HTML with the duplicate h1 removed.
	 */
	public function remove_duplicate_page_title( string $html ): string {
		return preg_replace(
			'/<h1\b[^>]*\bclass="[^"]*\bpage-title\b[^"]*"[^>]*>.*?<\/h1>/is',
			'',
			$html,
			1
		);
	}

	/**
	 * Register block templates with WordPress
	 *
	 * Uses the new WordPress 6.7+ register_block_template() function for proper
	 * template registration that integrates seamlessly with block themes.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_block_templates(): void {
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'Template Manager: Registering block templates, wp_is_block_theme() = ' . ( wp_is_block_theme() ? 'true' : 'false' ) );
		}

		// Only register for block themes
		if ( ! wp_is_block_theme() ) {
			return;
		}

		// Check if the new registration function exists (WordPress 6.7+)
		if ( function_exists( 'register_block_template' ) ) {
			$this->register_templates_modern();
		} else {
			$this->register_templates_legacy();
		}
	}

	/**
	 * Register templates using WordPress 6.7+ method
	 *
	 * Uses the new register_block_template() function for better integration.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function register_templates_modern(): void {
		$plugin_uri = 'brag-book-gallery';

		// Register taxonomy-brag_book_procedures template
		register_block_template( $plugin_uri . '//taxonomy-brag_book_procedures', [
			'title'       => __( 'Procedures', 'brag-book-gallery' ),
			'description' => __( 'Template for procedure taxonomy pages', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'taxonomy-brag_book_procedures' ),
		] );

		// Register single-brag_book_cases template
		register_block_template( $plugin_uri . '//single-brag_book_cases', [
			'title'       => __( 'Single Case', 'brag-book-gallery' ),
			'description' => __( 'Template for individual case posts', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'single-brag_book_cases' ),
		] );

		// Register page-myfavorites template
		register_block_template( $plugin_uri . '//page-myfavorites', [
			'title'       => __( 'My Favorites Page', 'brag-book-gallery' ),
			'description' => __( 'Template for the My Favorites page', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'page-myfavorites' ),
		] );
	}

	/**
	 * Register templates using legacy method for older WordPress versions
	 *
	 * Fallback for WordPress versions before 6.7.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function register_templates_legacy(): void {
		// Register taxonomy-brag_book_procedures template
		$taxonomy_template = [
			'slug'        => 'taxonomy-brag_book_procedures',
			'title'       => __( 'Procedures', 'brag-book-gallery' ),
			'description' => __( 'Template for procedure taxonomy pages', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'taxonomy-brag_book_procedures' ),
			'source'      => 'plugin',
			'type'        => 'wp_template',
			'theme'       => get_template(),
			'has_theme_file' => false,
		];

		// Register single-brag_book_cases template
		$single_case_template = [
			'slug'        => 'single-brag_book_cases',
			'title'       => __( 'Single Case', 'brag-book-gallery' ),
			'description' => __( 'Template for individual case posts', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'single-brag_book_cases' ),
			'source'      => 'plugin',
			'type'        => 'wp_template',
			'theme'       => get_template(),
			'has_theme_file' => false,
		];

		// Register page-myfavorites template
		$page_myfavorites_template = [
			'slug'        => 'page-myfavorites',
			'title'       => __( 'My Favorites Page', 'brag-book-gallery' ),
			'description' => __( 'Template for the My Favorites page', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'page-myfavorites' ),
			'source'      => 'plugin',
			'type'        => 'wp_template',
			'theme'       => get_template(),
			'has_theme_file' => false,
		];

		// Register templates with WordPress using legacy method
		add_filter( 'get_block_templates', function( $templates, $query ) use ( $taxonomy_template, $single_case_template, $page_myfavorites_template ) {
			// Add our templates to the available templates
			if ( empty( $query['slug'] ) || in_array( $query['slug'], [ 'taxonomy-brag_book_procedures', 'single-brag_book_cases', 'page-myfavorites' ], true ) ) {
				$templates[] = new \WP_Block_Template( (object) $taxonomy_template );
				$templates[] = new \WP_Block_Template( (object) $single_case_template );
				$templates[] = new \WP_Block_Template( (object) $page_myfavorites_template );
			}
			return $templates;
		}, 10, 2 );

		// Handle template resolution
		add_filter( 'get_block_template', function( $template, $id, $template_type ) use ( $taxonomy_template, $single_case_template, $page_myfavorites_template ) {
			if ( 'wp_template' !== $template_type ) {
				return $template;
			}

			$theme_slug = get_template();

			if ( $id === $theme_slug . '//taxonomy-brag_book_procedures' ) {
				return new \WP_Block_Template( (object) $taxonomy_template );
			}

			if ( $id === $theme_slug . '//single-brag_book_cases' ) {
				return new \WP_Block_Template( (object) $single_case_template );
			}

			if ( $id === $theme_slug . '//page-myfavorites' ) {
				return new \WP_Block_Template( (object) $page_myfavorites_template );
			}

			return $template;
		}, 10, 3 );
	}

	/**
	 * Get block file template from plugin directory
	 *
	 * Makes plugin block templates discoverable by WordPress.
	 *
	 * @since 3.3.0
	 * @param \WP_Block_Template|null $template Block template object.
	 * @param string $id Template ID.
	 * @param string $template_type Template type (wp_template).
	 * @return \WP_Block_Template|null Template object or null.
	 */
	public function get_block_file_template( $template, string $id, string $template_type ) {
		if ( 'wp_template' !== $template_type ) {
			return $template;
		}

		// Check if this is one of our templates
		$plugin_templates = [
			'taxonomy-brag_book_procedures',
			'single-brag_book_cases',
			'page-myfavorites',
		];

		// Extract template slug from ID (format: theme//template-slug)
		$id_parts = explode( '//', $id );
		$template_slug = end( $id_parts );

		if ( ! in_array( $template_slug, $plugin_templates, true ) ) {
			return $template;
		}

		// Build path to our template file
		$template_file = dirname( __DIR__, 2 ) . '/templates/block-templates/' . $template_slug . '.html';

		if ( ! file_exists( $template_file ) ) {
			return $template;
		}

		// Create template object
		$template_obj = new \WP_Block_Template();
		$template_obj->id = $id;
		$template_obj->theme = get_template();
		$template_obj->content = file_get_contents( $template_file );
		$template_obj->slug = $template_slug;
		$template_obj->source = 'plugin';
		$template_obj->type = 'wp_template';
		$template_obj->title = $this->get_template_title( $template_slug );
		$template_obj->status = 'publish';
		$template_obj->has_theme_file = true;
		$template_obj->is_custom = false;
		$template_obj->post_types = [];

		return $template_obj;
	}

	/**
	 * Get template title by slug
	 *
	 * @since 3.3.0
	 * @param string $slug Template slug.
	 * @return string Template title.
	 */
	private function get_template_title( string $slug ): string {
		$titles = [
			'taxonomy-brag_book_procedures' => __( 'Procedures', 'brag-book-gallery' ),
			'single-brag_book_cases' => __( 'Single Case', 'brag-book-gallery' ),
			'page-myfavorites' => __( 'My Favorites', 'brag-book-gallery' ),
		];

		return $titles[ $slug ] ?? $slug;
	}

	/**
	 * Get block template content from file
	 *
	 * Loads the HTML content for block templates from the plugin's templates directory.
	 *
	 * @since 3.0.0
	 * @param string $template_name Template name without extension.
	 * @return string Template content or empty string if not found.
	 */
	private function get_block_template_content( string $template_name ): string {
		// Load template from file
		$template_path = dirname( __DIR__, 2 ) . '/templates/block-templates/' . $template_name . '.html';

		if ( file_exists( $template_path ) ) {
			return file_get_contents( $template_path );
		}

		return '';
	}

	/**
	 * Load custom templates for classic themes
	 *
	 * Handles template loading for classic themes by checking for our custom
	 * templates and loading them when appropriate.
	 *
	 * @since 3.0.0
	 * @param string $template Current template path.
	 * @return string Modified template path.
	 */
	public function load_taxonomy_templates( string $template ): string {
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'Template Manager: wp_is_block_theme() = ' . ( wp_is_block_theme() ? 'true' : 'false' ) );
			error_log( 'Template Manager: Current template = ' . $template );
			error_log( 'Template Manager: is_tax(procedures) = ' . ( is_tax( 'procedures' ) ? 'true' : 'false' ) );
		}

		// Only handle classic themes (block themes use registered block templates)
		if ( wp_is_block_theme() ) {
			return $template;
		}

		$plugin_template_dir = dirname( __DIR__, 2 ) . '/templates/';

		// Handle procedures taxonomy
		if ( is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			$term = get_queried_object();

			// Check for specific term template first
			$specific_template = $plugin_template_dir . 'taxonomy-brag_book_procedures-' . $term->slug . '.php';
			if ( file_exists( $specific_template ) ) {
				return $specific_template;
			}

			// Check for general taxonomy template
			$general_template = $plugin_template_dir . 'taxonomy-brag_book_procedures.php';
			if ( file_exists( $general_template ) ) {
				return $general_template;
			}
		}

		// Handle single cases posts
		if ( is_singular( 'brag_book_cases' ) ) {
			$single_case_template = $plugin_template_dir . 'single-brag_book_cases.php';
			if ( file_exists( $single_case_template ) ) {
				return $single_case_template;
			}
		}

		// Handle My Favorites page
		if ( is_page() ) {
			$page = get_queried_object();
			if ( $page && 'myfavorites' === $page->post_name ) {
				$favorites_template = $plugin_template_dir . 'page-myfavorites.php';
				if ( file_exists( $favorites_template ) ) {
					return $favorites_template;
				}
			}
		}

		return $template;
	}

	/**
	 * Add theme support for block templates
	 *
	 * Ensures the current theme supports block templates and custom templates.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_theme_support(): void {
		// Add support for block templates
		add_theme_support( 'block-templates' );
		add_theme_support( 'block-template-parts' );
	}

	/**
	 * Get available templates
	 *
	 * Returns a list of available templates for the current theme type.
	 *
	 * @since 3.0.0
	 * @return array List of available templates.
	 */
	public function get_available_templates(): array {
		$templates = [];
		$plugin_template_dir = dirname( __DIR__, 2 ) . '/templates/';

		if ( wp_is_block_theme() ) {
			// Block theme templates
			$block_template_dir = $plugin_template_dir . 'block-templates/';

			if ( file_exists( $block_template_dir . 'taxonomy-brag_book_procedures.html' ) ) {
				$templates['taxonomy-brag_book_procedures'] = __( 'Procedures (Block)', 'brag-book-gallery' );
			}

			if ( file_exists( $block_template_dir . 'single-brag_book_cases.html' ) ) {
				$templates['single-brag_book_cases'] = __( 'Single Case (Block)', 'brag-book-gallery' );
			}

			if ( file_exists( $block_template_dir . 'page-myfavorites.html' ) ) {
				$templates['page-myfavorites'] = __( 'My Favorites Page (Block)', 'brag-book-gallery' );
			}
		} else {
			// Classic theme templates
			if ( file_exists( $plugin_template_dir . 'taxonomy-brag_book_procedures.php' ) ) {
				$templates['taxonomy-brag_book_procedures'] = __( 'Procedures (PHP)', 'brag-book-gallery' );
			}

			if ( file_exists( $plugin_template_dir . 'single-brag_book_cases.php' ) ) {
				$templates['single-brag_book_cases'] = __( 'Single Case (PHP)', 'brag-book-gallery' );
			}

			if ( file_exists( $plugin_template_dir . 'page-myfavorites.php' ) ) {
				$templates['page-myfavorites'] = __( 'My Favorites Page (PHP)', 'brag-book-gallery' );
			}
		}

		return $templates;
	}

	/**
	 * Check if templates are properly loaded
	 *
	 * Diagnostic method to check if templates are available and working.
	 *
	 * @since 3.0.0
	 * @return array Status information about template loading.
	 */
	public function get_template_status(): array {
		$status = [
			'theme_type' => wp_is_block_theme() ? 'block' : 'classic',
			'templates_available' => $this->get_available_templates(),
			'template_directory' => dirname( __DIR__, 2 ) . '/templates/',
			'current_template' => null,
		];

		// Check current template if on a procedures page
		if ( is_tax( 'procedures' ) || is_post_type_archive() ) {
			global $template;
			$status['current_template'] = $template;
		}

		return $status;
	}
}
