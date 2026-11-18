<?php
/**
 * Tests for RS_CSV_Importer::find_post_by_title().
 *
 * @package Really_Simple_CSV_Importer
 */

class FindPostByTitleTest extends WP_UnitTestCase {

	/**
	 * @var RS_CSV_Importer
	 */
	private $importer;

	public function set_up() {
		parent::set_up();

		$this->importer = new RS_CSV_Importer();
	}

	public function test_returns_the_oldest_post_with_the_same_title() {
		$oldest = self::factory()->post->create( array( 'post_title' => 'Same Title' ) );
		self::factory()->post->create( array( 'post_title' => 'Same Title' ) );

		$this->assertSame( $oldest, $this->importer->find_post_by_title( 'Same Title', 'post' )->ID );
	}

	public function test_does_not_match_other_post_types() {
		self::factory()->post->create(
			array(
				'post_title' => 'Same Title',
				'post_type'  => 'page',
			)
		);

		$this->assertNull( $this->importer->find_post_by_title( 'Same Title', 'post' ) );
	}

	public function test_matches_any_post_status() {
		$draft = self::factory()->post->create(
			array(
				'post_title'  => 'Draft',
				'post_status' => 'draft',
			)
		);
		$trash = self::factory()->post->create(
			array(
				'post_title'  => 'Trash',
				'post_status' => 'trash',
			)
		);

		$this->assertSame( $draft, $this->importer->find_post_by_title( 'Draft', 'post' )->ID );
		$this->assertSame( $trash, $this->importer->find_post_by_title( 'Trash', 'post' )->ID );
	}

	public function test_returns_null_when_not_found() {
		$this->assertNull( $this->importer->find_post_by_title( 'Not Found', 'post' ) );
	}
}
