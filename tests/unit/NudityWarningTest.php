<?php
/**
 * Test the nudity warning presets and copy.
 *
 * @package BRAGBookGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Shortcodes\HTML_Renderer;

/**
 * Nudity warning test case.
 */
class NudityWarningTest extends WP_UnitTestCase {

	/**
	 * Case assigned to a procedure flagged for nudity.
	 *
	 * @var int
	 */
	private int $flagged_procedure_case;

	/**
	 * Case flagged on its own, whose procedure is clean.
	 *
	 * @var int
	 */
	private int $flagged_case;

	/**
	 * Seed procedures, cases and their flags.
	 */
	public function set_up(): void {
		parent::set_up();

		$nude_term  = $this->factory->term->create( array( 'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES ) );
		$clean_term = $this->factory->term->create( array( 'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES ) );
		update_term_meta( $nude_term, 'nudity', 'true' );

		$this->flagged_procedure_case = $this->factory->post->create( array( 'post_type' => 'brag_book_cases' ) );
		wp_set_object_terms( $this->flagged_procedure_case, array( $nude_term ), Taxonomies::TAXONOMY_PROCEDURES );

		$this->flagged_case = $this->factory->post->create( array( 'post_type' => 'brag_book_cases' ) );
		wp_set_object_terms( $this->flagged_case, array( $clean_term ), Taxonomies::TAXONOMY_PROCEDURES );
		update_post_meta( $this->flagged_case, HTML_Renderer::NUDITY_META_KEY, '1' );
	}

	/**
	 * Remove the preset so it cannot leak into other tests.
	 */
	public function tear_down(): void {
		delete_option( 'brag_book_gallery_nudity_mode' );
		delete_option( 'brag_book_gallery_nudity_title' );
		parent::tear_down();
	}

	/**
	 * Default preset follows the procedure flag, not the per-case flag.
	 */
	public function test_default_preset_uses_procedure_flag(): void {
		update_option( 'brag_book_gallery_nudity_mode', HTML_Renderer::NUDITY_MODE_DEFAULT );

		$this->assertTrue( HTML_Renderer::should_warn( $this->flagged_procedure_case ) );
		$this->assertFalse( HTML_Renderer::should_warn( $this->flagged_case ) );
	}

	/**
	 * A caller-supplied procedure flag wins over auto-detection.
	 */
	public function test_default_preset_honours_supplied_flag(): void {
		update_option( 'brag_book_gallery_nudity_mode', HTML_Renderer::NUDITY_MODE_DEFAULT );

		$this->assertTrue( HTML_Renderer::should_warn( $this->flagged_case, true ) );
		$this->assertFalse( HTML_Renderer::should_warn( $this->flagged_procedure_case, false ) );
	}

	/**
	 * Individualized preset reads the per-case flag only.
	 */
	public function test_individual_preset_uses_case_flag(): void {
		update_option( 'brag_book_gallery_nudity_mode', HTML_Renderer::NUDITY_MODE_INDIVIDUAL );

		$this->assertTrue( HTML_Renderer::should_warn( $this->flagged_case ) );
		$this->assertFalse( HTML_Renderer::should_warn( $this->flagged_procedure_case, true ) );
	}

	/**
	 * Global preset emits no per-card overlay, only the queued full-screen one.
	 */
	public function test_global_preset_suppresses_card_overlay(): void {
		update_option( 'brag_book_gallery_nudity_mode', HTML_Renderer::NUDITY_MODE_GLOBAL );

		$this->assertSame( '', HTML_Renderer::maybe_render_nudity_warning( $this->flagged_procedure_case ) );

		ob_start();
		do_action( 'wp_footer' );
		$footer = (string) ob_get_clean();

		$this->assertStringContainsString( 'brag-book-gallery-nudity-warning--global', $footer );
	}

	/**
	 * Configured copy replaces the defaults; blank fields fall back.
	 */
	public function test_configured_copy_is_rendered(): void {
		update_option( 'brag_book_gallery_nudity_mode', HTML_Renderer::NUDITY_MODE_DEFAULT );
		update_option( 'brag_book_gallery_nudity_title', 'Sensitive Content' );

		$html = HTML_Renderer::maybe_render_nudity_warning( $this->flagged_procedure_case );

		$this->assertStringContainsString( 'Sensitive Content', $html );
		$this->assertStringContainsString( 'Proceed', $html );
		$this->assertStringNotContainsString( 'nudity-warning-decline', $html );
	}

	/**
	 * The decline link is rendered on the global preset only.
	 */
	public function test_decline_link_is_global_only(): void {
		update_option( 'brag_book_gallery_nudity_mode', HTML_Renderer::NUDITY_MODE_GLOBAL );
		update_option( 'brag_book_gallery_nudity_decline_url', 'https://example.com/exit' );

		HTML_Renderer::maybe_render_nudity_warning( $this->flagged_procedure_case );

		ob_start();
		do_action( 'wp_footer' );
		$footer = (string) ob_get_clean();

		$this->assertStringContainsString( 'brag-book-gallery-nudity-warning-decline', $footer );
		$this->assertStringContainsString( 'https://example.com/exit', $footer );
		$this->assertStringContainsString( '>Decline<', $footer );
	}
}
