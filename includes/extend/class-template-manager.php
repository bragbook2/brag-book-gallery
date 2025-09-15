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

		// Register taxonomy-procedures template
		register_block_template( $plugin_uri . '//taxonomy-procedures', [
			'title'       => __( 'Procedures', 'brag-book-gallery' ),
			'description' => __( 'Template for procedure taxonomy pages', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'taxonomy-procedures' ),
		] );

		// Register single-brag_book_cases template
		register_block_template( $plugin_uri . '//single-brag_book_cases', [
			'title'       => __( 'Single Case', 'brag-book-gallery' ),
			'description' => __( 'Template for individual case posts', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'single-brag_book_cases' ),
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
		// Register taxonomy-procedures template
		$taxonomy_template = [
			'slug'        => 'taxonomy-procedures',
			'title'       => __( 'Procedures', 'brag-book-gallery' ),
			'description' => __( 'Template for procedure taxonomy pages', 'brag-book-gallery' ),
			'content'     => $this->get_block_template_content( 'taxonomy-procedures' ),
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

		// Register templates with WordPress using legacy method
		add_filter( 'get_block_templates', function( $templates, $query ) use ( $taxonomy_template, $single_case_template ) {
			// Add our templates to the available templates
			if ( empty( $query['slug'] ) || in_array( $query['slug'], [ 'taxonomy-procedures', 'single-brag_book_cases' ], true ) ) {
				$templates[] = new \WP_Block_Template( (object) $taxonomy_template );
				$templates[] = new \WP_Block_Template( (object) $single_case_template );
			}
			return $templates;
		}, 10, 2 );

		// Handle template resolution
		add_filter( 'get_block_template', function( $template, $id, $template_type ) use ( $taxonomy_template, $single_case_template ) {
			if ( 'wp_template' !== $template_type ) {
				return $template;
			}

			$theme_slug = get_template();

			if ( $id === $theme_slug . '//taxonomy-procedures' ) {
				return new \WP_Block_Template( (object) $taxonomy_template );
			}

			if ( $id === $theme_slug . '//single-brag_book_cases' ) {
				return new \WP_Block_Template( (object) $single_case_template );
			}

			return $template;
		}, 10, 3 );
	}

	/**
	 * Get block template content from file
	 *
	 * Loads the HTML content for block templates from the plugin's templates directory.
	 * Creates dynamic templates based on the current theme's structure.
	 *
	 * @since 3.0.0
	 * @param string $template_name Template name without extension.
	 * @return string Template content or empty string if not found.
	 */
	private function get_block_template_content( string $template_name ): string {
		// Try to get theme-specific template parts
		$template_parts = $this->get_theme_template_parts();

		// Create dynamic template based on detected template parts
		if ( 'taxonomy-procedures' === $template_name ) {
			return $this->build_taxonomy_template( $template_parts );
		}

		if ( 'single-brag_book_cases' === $template_name ) {
			return $this->build_single_case_template( $template_parts );
		}

		// Fallback to file-based template
		$template_path = dirname( __DIR__, 2 ) . '/templates/block-templates/' . $template_name . '.html';
		if ( file_exists( $template_path ) ) {
			return file_get_contents( $template_path );
		}

		return '';
	}

	/**
	 * Get theme template parts
	 *
	 * Detects the current theme's header and footer template part names.
	 *
	 * @since 3.0.0
	 * @return array Template part information.
	 */
	private function get_theme_template_parts(): array {
		$theme = get_template();
		$template_parts = [];

		// Common template part names to check
		$header_names = [ 'header', 'site-header', 'masthead', 'head' ];
		$footer_names = [ 'footer', 'site-footer', 'foot' ];

		// Check for existing template parts
		foreach ( $header_names as $name ) {
			if ( file_exists( get_template_directory() . '/parts/' . $name . '.html' ) ||
			     file_exists( get_template_directory() . '/templates/parts/' . $name . '.html' ) ) {
				$template_parts['header'] = $name;
				break;
			}
		}

		foreach ( $footer_names as $name ) {
			if ( file_exists( get_template_directory() . '/parts/' . $name . '.html' ) ||
			     file_exists( get_template_directory() . '/templates/parts/' . $name . '.html' ) ) {
				$template_parts['footer'] = $name;
				break;
			}
		}

		// Default fallbacks
		if ( empty( $template_parts['header'] ) ) {
			$template_parts['header'] = 'header';
		}
		if ( empty( $template_parts['footer'] ) ) {
			$template_parts['footer'] = 'footer';
		}

		$template_parts['theme'] = $theme;

		return $template_parts;
	}

	/**
	 * Build taxonomy template dynamically
	 *
	 * Creates a taxonomy template using detected theme template parts.
	 *
	 * @since 3.0.0
	 * @param array $parts Template part information.
	 * @return string Template content.
	 */
	private function build_taxonomy_template( array $parts ): string {
		return sprintf(
			'<!-- wp:template-part {"slug":"%s","theme":"%s"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">

	<!-- wp:query-title {"type":"archive","level":1} /-->

	<!-- wp:term-description /-->

	<!-- wp:spacer {"height":"2rem"} -->
	<div style="height:2rem" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:shortcode -->
	[brag_book_gallery]
	<!-- /wp:shortcode -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"%s","theme":"%s"} /-->',
			$parts['header'],
			$parts['theme'],
			$parts['footer'],
			$parts['theme']
		);
	}

	/**
	 * Build single case template dynamically
	 *
	 * Creates a single case template using detected theme template parts.
	 *
	 * @since 3.0.0
	 * @param array $parts Template part information.
	 * @return string Template content.
	 */
	private function build_single_case_template( array $parts ): string {
		return sprintf(
			'<!-- wp:template-part {"slug":"%s","theme":"%s"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">

	<!-- wp:post-title {"level":1,"textAlign":"center"} /-->

	<!-- wp:post-featured-image {"align":"wide"} /-->

	<!-- wp:spacer {"height":"2rem"} -->
	<div style="height:2rem" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:post-content /-->

	<!-- wp:spacer {"height":"2rem"} -->
	<div style="height:2rem" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:shortcode -->
	[brag_book_gallery_case]
	<!-- /wp:shortcode -->

	<!-- wp:spacer {"height":"2rem"} -->
	<div style="height:2rem" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:post-terms {"term":"procedures","textAlign":"center"} /-->

	<!-- wp:post-navigation-link {"type":"previous","showTitle":true} /-->

	<!-- wp:post-navigation-link {"type":"next","showTitle":true} /-->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"%s","theme":"%s"} /-->',
			$parts['header'],
			$parts['theme'],
			$parts['footer'],
			$parts['theme']
		);
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
		if ( is_tax( 'procedures' ) ) {
			$term = get_queried_object();

			// Check for specific term template first
			$specific_template = $plugin_template_dir . 'taxonomy-procedures-' . $term->slug . '.php';
			if ( file_exists( $specific_template ) ) {
				return $specific_template;
			}

			// Check for general taxonomy template
			$general_template = $plugin_template_dir . 'taxonomy-procedures.php';
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

			if ( file_exists( $block_template_dir . 'taxonomy-procedures.html' ) ) {
				$templates['taxonomy-procedures'] = __( 'Procedures (Block)', 'brag-book-gallery' );
			}

			if ( file_exists( $block_template_dir . 'single-brag_book_cases.html' ) ) {
				$templates['single-brag_book_cases'] = __( 'Single Case (Block)', 'brag-book-gallery' );
			}
		} else {
			// Classic theme templates
			if ( file_exists( $plugin_template_dir . 'taxonomy-procedures.php' ) ) {
				$templates['taxonomy-procedures'] = __( 'Procedures (PHP)', 'brag-book-gallery' );
			}

			if ( file_exists( $plugin_template_dir . 'single-brag_book_cases.php' ) ) {
				$templates['single-brag_book_cases'] = __( 'Single Case (PHP)', 'brag-book-gallery' );
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