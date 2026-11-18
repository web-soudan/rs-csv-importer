<?php
/**
 * Characterization tests for importing post fields.
 *
 * @package Really_Simple_CSV_Importer
 */

class ImportPostTest extends Importer_TestCase {

	public function test_import_post_fields() {
		$csv = <<<CSV
"post_name","post_type","post_status","post_title","post_content","post_excerpt","post_password","comment_status"
"import-test","post","publish","CSV Import Test","Line 1
Line 2","Excerpt","secret","closed"
CSV;

		$output = $this->import_csv( $csv );

		$post = $this->get_post_by_title( 'CSV Import Test' );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'import-test', $post->post_name );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( "Line 1\nLine 2", $post->post_content );
		$this->assertSame( 'Excerpt', $post->post_excerpt );
		$this->assertSame( 'secret', $post->post_password );
		$this->assertSame( 'closed', $post->comment_status );

		$this->assertStringContainsString( 'Processing &quot;CSV Import Test&quot; done.', $output );
		$this->assertStringContainsString( 'All Done.', $output );
	}

	public function test_import_multiple_rows() {
		$csv = <<<CSV
"post_type","post_title"
"post","Row 1"
"post","Row 2"
"page","Row 3"
CSV;

		$this->import_csv( $csv );

		$this->assertInstanceOf( WP_Post::class, $this->get_post_by_title( 'Row 1', 'post' ) );
		$this->assertInstanceOf( WP_Post::class, $this->get_post_by_title( 'Row 2', 'post' ) );
		$this->assertInstanceOf( WP_Post::class, $this->get_post_by_title( 'Row 3', 'page' ) );
	}

	public function test_invalid_post_type_is_rejected() {
		$csv = <<<CSV
"post_type","post_title"
"not_exists","Invalid Post Type"
CSV;

		$output = $this->import_csv( $csv );

		$this->assertNull( $this->get_post_by_title( 'Invalid Post Type' ) );
		$this->assertStringContainsString( 'Invalid post type &quot;not_exists&quot;.', $output );
	}

	public function test_invalid_post_status_falls_back_to_draft() {
		$this->import_sample( 'post_status_test.csv' );

		$this->assertSame( 'publish', $this->get_post_by_title( 'Valid status' )->post_status );
		$this->assertSame( 'draft', $this->get_post_by_title( 'Invalid status' )->post_status );
	}

	public function test_post_author_by_login() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'csv_author' ) );

		$csv = <<<CSV
"post_type","post_title","post_author"
"post","Author Login","csv_author"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( (string) $user_id, $this->get_post_by_title( 'Author Login' )->post_author );
	}

	public function test_post_author_by_id() {
		$user_id = self::factory()->user->create();

		$csv = <<<CSV
"post_type","post_title","post_author"
"post","Author ID","{$user_id}"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( (string) $user_id, $this->get_post_by_title( 'Author ID' )->post_author );
	}

	public function test_post_author_login_column() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'csv_author_login' ) );

		$csv = <<<CSV
"post_type","post_title","post_author_login"
"post","Author Login Column","csv_author_login"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( (string) $user_id, $this->get_post_by_title( 'Author Login Column' )->post_author );
	}

	public function test_unknown_post_author_is_ignored() {
		$csv = <<<CSV
"post_type","post_title","post_author"
"post","Unknown Author","no_such_user"
CSV;

		$output = $this->import_csv( $csv );

		$this->assertSame( '0', $this->get_post_by_title( 'Unknown Author' )->post_author );
		$this->assertStringContainsString( 'done.', $output );
	}

	public function test_post_dates() {
		// Same rows as sample/post_dates.csv, plus the post_type column.
		$csv = <<<CSV
"post_date","post_date_gmt","post_title","post_status","post_type"
2015/7/4 12:34,2015/7/4 1:23,"Post Date Test 1","draft","post"
2015/7/4 12:34,2015/7/4 1:23,"Post Date Test 2","publish","post"
2019/7/4 12:34,2015/7/4 1:23,"Post Date Test 3","future","post"
2015/7/4 12:34,2015/7/4 1:23,"Post Date Test 4","pending","post"
2015/7/4 12:34,,"Post Date Test 5","draft","post"
2015/7/4 12:34,,"Post Date Test 6","publish","post"
CSV;

		$this->import_csv( $csv );

		$post = $this->get_post_by_title( 'Post Date Test 1' );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( '2015-07-04 12:34:00', $post->post_date );
		$this->assertSame( '2015-07-04 01:23:00', $post->post_date_gmt );

		$post = $this->get_post_by_title( 'Post Date Test 2' );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( '2015-07-04 12:34:00', $post->post_date );
		$this->assertSame( '2015-07-04 01:23:00', $post->post_date_gmt );

		// A "future" post with a date in the past is published by WordPress.
		$post = $this->get_post_by_title( 'Post Date Test 3' );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( '2019-07-04 12:34:00', $post->post_date );

		$post = $this->get_post_by_title( 'Post Date Test 4' );
		$this->assertSame( 'pending', $post->post_status );

		// Without post_date_gmt, WordPress leaves the GMT date empty for drafts.
		$post = $this->get_post_by_title( 'Post Date Test 5' );
		$this->assertSame( '2015-07-04 12:34:00', $post->post_date );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		// Without post_date_gmt, WordPress computes the GMT date for published posts.
		$post = $this->get_post_by_title( 'Post Date Test 6' );
		$this->assertSame( '2015-07-04 12:34:00', $post->post_date );
		$this->assertSame( '2015-07-04 12:34:00', $post->post_date_gmt );
	}

	public function test_page_parent_and_menu_order() {
		$parent_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$csv = <<<CSV
"post_parent","post_type","post_status","post_title","menu_order"
{$parent_id},"page","publish","Child Page",3
CSV;

		$this->import_csv( $csv );

		$page = $this->get_post_by_title( 'Child Page', 'page' );
		$this->assertSame( $parent_id, $page->post_parent );
		$this->assertSame( 3, $page->menu_order );
	}

	public function test_update_existing_post_by_id() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Original Title',
				'post_content' => 'Original content',
			)
		);

		$csv = <<<CSV
"ID","post_type","post_title"
{$post_id},"post","Updated Title"
CSV;

		$this->import_csv( $csv );

		$post = get_post( $post_id );
		$this->assertSame( 'Updated Title', $post->post_title );
		$this->assertSame( 'Original content', $post->post_content );
	}

	public function test_update_existing_post_by_post_id_column() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Original Title' ) );

		$csv = <<<CSV
"post_id","post_type","post_title"
{$post_id},"post","Updated By post_id"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( 'Updated By post_id', get_post( $post_id )->post_title );
	}

	public function test_post_id_that_does_not_exist_is_used_as_import_id() {
		$post_id = self::factory()->post->create();
		$new_id  = $post_id + 100;

		$csv = <<<CSV
"post_id","post_type","post_title"
{$new_id},"post","Import ID"
CSV;

		$this->import_csv( $csv );

		$post = get_post( $new_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'Import ID', $post->post_title );
	}

	public function test_post_type_mismatch_is_rejected() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Original Title' ) );

		$csv = <<<CSV
"post_id","post_type","post_title"
{$post_id},"page","Mismatch"
CSV;

		$output = $this->import_csv( $csv );

		$this->assertSame( 'Original Title', get_post( $post_id )->post_title );
		$this->assertStringContainsString( 'The post type value from your csv file does not match the existing data in your database.', $output );
	}

	public function test_replace_by_title() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Same Title',
				'post_content' => 'Original content',
			)
		);

		$_POST['replace-by-title'] = '1';

		$csv = <<<CSV
"post_type","post_title","post_content"
"post","Same Title","Replaced content"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( 'Replaced content', get_post( $post_id )->post_content );
		$this->assertCount( 1, get_posts( array( 'title' => 'Same Title' ) ) );

		// Ignore the deprecation of get_page_by_title() (WordPress 6.2+); only the import result matters here.
		$this->caught_deprecated = array();
	}

	public function test_same_title_creates_new_post_when_replace_by_title_is_disabled() {
		self::factory()->post->create( array( 'post_title' => 'Same Title' ) );

		$csv = <<<CSV
"post_type","post_status","post_title"
"post","publish","Same Title"
CSV;

		$this->import_csv( $csv );

		$this->assertCount( 2, get_posts( array( 'title' => 'Same Title' ) ) );
	}

	public function test_header_with_bom_and_unquoted_first_column() {
		$csv = "\xEF\xBB\xBF" . <<<CSV
post_title,"post_type"
"BOM Test","page"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( 'page', $this->get_post_by_title( 'BOM Test' )->post_type );
	}
}
