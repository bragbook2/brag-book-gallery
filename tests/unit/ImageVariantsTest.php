<?php
/**
 * Test the small/medium/full image-variant helpers.
 *
 * @package BRAGBookGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BRAGBookGallery\Includes\Shortcodes\Cases_Handler;

/**
 * Image variants test case.
 *
 * The helpers are protected statics on Trait_Image_Variants, so they are driven
 * here through Cases_Handler — a real consumer of the trait — rather than a
 * throwaway fixture class.
 */
class ImageVariantsTest extends WP_UnitTestCase {

	private const FULL   = 'https://cdn.example.com/pp-full.jpg';
	private const MEDIUM = 'https://cdn.example.com/pp-med.jpg';
	private const SMALL  = 'https://cdn.example.com/pp-small.jpg';
	private const HR     = 'https://cdn.example.com/hr-full.jpg';
	private const HR_SM  = 'https://cdn.example.com/hr-small.jpg';

	/**
	 * A case post carrying a complete variant set plus a medium-less one.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * A case post whose variants only ever had the full URL (legacy sync).
	 *
	 * @var int
	 */
	private int $legacy_post_id;

	/**
	 * Seed the variant meta.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->post_id = $this->factory->post->create();
		update_post_meta(
			$this->post_id,
			'brag_book_gallery_case_image_variants',
			array(
				array(
					'post_processed' => array(
						'full'   => self::FULL,
						'medium' => self::MEDIUM,
						'small'  => self::SMALL,
						'alt'    => '',
					),
					'high_res'       => array(
						'full'   => self::HR,
						'medium' => '',
						'small'  => self::HR_SM,
						'alt'    => '',
					),
				),
			)
		);

		$this->legacy_post_id = $this->factory->post->create();
		update_post_meta(
			$this->legacy_post_id,
			'brag_book_gallery_case_image_variants',
			array(
				array(
					'post_processed' => array(
						'full'   => self::FULL,
						'medium' => '',
						'small'  => '',
						'alt'    => '',
					),
				),
			)
		);
	}

	/**
	 * A complete set offers all three widths, smallest first.
	 */
	public function test_complete_set_offers_all_three_widths(): void {
		$sizes = $this->grid_sizes();
		$attrs = $this->attrs( $this->post_id, self::FULL, $sizes );

		$this->assertStringStartsWith( ' srcset="', $attrs );
		$this->assertStringContainsString( self::SMALL . ' 400w', $attrs );
		$this->assertStringContainsString( self::MEDIUM . ' 800w', $attrs );
		$this->assertStringContainsString( self::FULL . ' 1600w', $attrs );
		$this->assertStringContainsString( 'sizes="' . $sizes . '"', $attrs );
	}

	/**
	 * A size the API never generated is skipped rather than back-filled with a
	 * larger file, which would offer a big image under a small descriptor.
	 */
	public function test_absent_size_is_skipped_not_backfilled(): void {
		$attrs = $this->attrs( $this->post_id, self::HR, '100vw' );

		$this->assertStringContainsString( self::HR_SM . ' 400w', $attrs );
		$this->assertStringContainsString( self::HR . ' 1600w', $attrs );
		$this->assertStringNotContainsString( '800w', $attrs );
	}

	/**
	 * With one unique URL there is nothing to choose between, so `src` alone is
	 * the correct markup and no lone `sizes` is emitted.
	 */
	public function test_single_url_emits_no_attributes(): void {
		$this->assertSame( '', $this->attrs( $this->legacy_post_id, self::FULL, '100vw' ) );
	}

	/**
	 * Callers that pass a URL belonging to no stored node get nothing back.
	 */
	public function test_unknown_url_emits_no_attributes(): void {
		$this->assertSame( '', $this->attrs( $this->post_id, 'https://cdn.example.com/not-stored.jpg', '100vw' ) );
	}

	/**
	 * API-sourced cards have no local post; they fall back to a plain `src`.
	 */
	public function test_missing_post_emits_no_attributes(): void {
		$this->assertSame( '', $this->attrs( 0, self::FULL, '100vw' ) );
		$this->assertSame( '', $this->attrs( $this->post_id, '', '100vw' ) );
	}

	/**
	 * A post with no variant meta at all (synced before the feature existed).
	 */
	public function test_post_without_variant_meta_emits_no_attributes(): void {
		$bare = $this->factory->post->create();

		$this->assertSame( '', $this->attrs( $bare, self::FULL, '100vw' ) );
	}

	/**
	 * Requesting a size the node does not carry falls back to the full URL, so
	 * thumbnails still render when only the full image exists.
	 */
	public function test_variant_lookup_falls_back_to_full(): void {
		$this->assertSame( self::SMALL, $this->variant( $this->post_id, self::FULL, 'small' ) );
		$this->assertSame( self::FULL, $this->variant( $this->legacy_post_id, self::FULL, 'small' ) );
		$this->assertSame( self::HR, $this->variant( $this->post_id, self::HR, 'medium' ) );
	}

	/**
	 * Signed URLs carry query strings; they must survive into the attribute
	 * intact once entity decoding is undone.
	 */
	public function test_query_strings_survive_attribute_escaping(): void {
		$signed = 'https://cdn.example.com/s.jpg?token=abc&exp=123';
		$medium = 'https://cdn.example.com/s-med.jpg?token=abc&exp=123';
		$post   = $this->factory->post->create();

		update_post_meta(
			$post,
			'brag_book_gallery_case_image_variants',
			array(
				array(
					'before' => array(
						'full'   => $signed,
						'medium' => $medium,
						'small'  => '',
						'alt'    => '',
					),
				),
			)
		);

		$decoded = html_entity_decode( $this->attrs( $post, $signed, '100vw' ), ENT_QUOTES, 'UTF-8' );

		$this->assertStringContainsString( $medium . ' 800w', $decoded );
		$this->assertStringContainsString( $signed . ' 1600w', $decoded );
	}

	/**
	 * Drive Trait_Image_Variants::build_responsive_attrs() through its consumer.
	 *
	 * @param int    $post_id Case post ID.
	 * @param string $url     Full-size URL.
	 * @param string $sizes   A `sizes` value.
	 * @return string
	 */
	private function attrs( int $post_id, string $url, string $sizes ): string {
		return $this->call( 'build_responsive_attrs', array( $post_id, $url, $sizes ) );
	}

	/**
	 * Drive Trait_Image_Variants::get_variant_url_for_url() through its consumer.
	 *
	 * @param int    $post_id Case post ID.
	 * @param string $url     Full-size URL.
	 * @param string $size    One of 'small', 'medium', 'full'.
	 * @return string
	 */
	private function variant( int $post_id, string $url, string $size ): string {
		return $this->call( 'get_variant_url_for_url', array( $post_id, $url, $size ) );
	}

	/**
	 * The cases-grid `sizes` value built by the trait.
	 *
	 * Was a constant until the value had to account for the saved column count,
	 * at which point it became a method.
	 *
	 * @return string
	 */
	private function grid_sizes(): string {
		return $this->call( 'sizes_case_grid', array() );
	}

	/**
	 * Call a protected static helper on the trait via its consumer.
	 *
	 * @param string            $method Method name on Trait_Image_Variants.
	 * @param array<int, mixed> $args   Positional arguments.
	 * @return string
	 */
	private function call( string $method, array $args ): string {
		$reflection = new ReflectionMethod( Cases_Handler::class, $method );
		$reflection->setAccessible( true );

		return (string) $reflection->invokeArgs( null, $args );
	}
}
