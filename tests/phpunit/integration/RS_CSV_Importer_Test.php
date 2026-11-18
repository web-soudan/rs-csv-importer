<?php
/**
 * Tests for RS_CSV_Importer.
 *
 * @package Really_Simple_CSV_Importer
 */

class RS_CSV_Importer_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// The upload form always sends this option ("Disable" is the default).
		$_POST['replace-by-title'] = '0';

		register_taxonomy( 'actors', 'post' );
	}

	public function tear_down() {
		unset( $_POST['replace-by-title'] );
		unregister_taxonomy( 'actors' );
		$this->remove_added_uploads();

		parent::tear_down();
	}

	public function test_importer_is_registered() {
		global $wp_importers;

		$this->assertArrayHasKey( 'csv', (array) $wp_importers );
	}

	public function test_import_posts() {
		$author        = self::factory()->user->create( array( 'user_login' => 'csv_author' ) );
		$author_by_id  = self::factory()->user->create();
		$author_login  = self::factory()->user->create( array( 'user_login' => 'csv_author_login' ) );
		$parent        = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$output = $this->import(
			array(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'post_title'     => 'Full Post',
					'post_name'      => 'full-post',
					'post_content'   => "Line 1\nLine 2",
					'post_excerpt'   => 'Excerpt',
					'post_password'  => 'secret',
					'comment_status' => 'closed',
					'post_author'    => 'csv_author',
					'post_date'      => '2015/7/4 12:34',
					'post_date_gmt'  => '2015/7/4 1:23',
					'post_tags'      => 'apple,banana, cherry',
					'custom_field'   => 'custom value',
					'empty_field'    => '',
				),
				array(
					'post_type'   => 'post',
					'post_title'  => 'Author ID',
					'post_author' => $author_by_id,
				),
				array(
					'post_type'         => 'post',
					'post_title'        => 'Author Login Column',
					'post_author_login' => 'csv_author_login',
				),
				array(
					'post_type'   => 'post',
					'post_title'  => 'Unknown Author',
					'post_author' => 'no_such_user',
				),
				array(
					'post_type'   => 'post',
					'post_status' => 'foo',
					'post_title'  => 'Invalid Status',
				),
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => 'Child Page',
					'post_parent' => $parent,
					'menu_order'  => 3,
				),
			)
		);

		$post = $this->get_post_by_title( 'Full Post' );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'full-post', $post->post_name );
		$this->assertSame( "Line 1\nLine 2", $post->post_content );
		$this->assertSame( 'Excerpt', $post->post_excerpt );
		$this->assertSame( 'secret', $post->post_password );
		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( (string) $author, $post->post_author );
		$this->assertSame( '2015-07-04 12:34:00', $post->post_date );
		$this->assertSame( '2015-07-04 01:23:00', $post->post_date_gmt );
		$this->assertSame( array( 'apple', 'banana', 'cherry' ), $this->get_term_names( $post, 'post_tag' ) );
		// Unknown columns are saved as post meta, even when empty.
		$this->assertSame( 'custom value', get_post_meta( $post->ID, 'custom_field', true ) );
		$this->assertTrue( metadata_exists( 'post', $post->ID, 'empty_field' ) );
		$this->assertFalse( metadata_exists( 'post', $post->ID, 'post_title' ) );

		$this->assertSame( (string) $author_by_id, $this->get_post_by_title( 'Author ID' )->post_author );
		$this->assertSame( (string) $author_login, $this->get_post_by_title( 'Author Login Column' )->post_author );
		$this->assertSame( '0', $this->get_post_by_title( 'Unknown Author' )->post_author );

		$this->assertSame( 'draft', $this->get_post_by_title( 'Invalid Status' )->post_status );

		$page = $this->get_post_by_title( 'Child Page', 'page' );
		$this->assertSame( $parent, $page->post_parent );
		$this->assertSame( 3, $page->menu_order );

		$this->assertStringContainsString( 'Processing &quot;Full Post&quot; done.', $output );
		$this->assertStringContainsString( 'All Done.', $output );
	}

	public function test_import_categories_and_custom_taxonomy_from_sample() {
		$this->import_file( dirname( __DIR__, 3 ) . '/sample/movies.csv' );

		$post = $this->get_post_by_title( 'Parker' );
		$this->assertSame( array( 'action', 'crime', 'thriller' ), $this->get_term_names( $post, 'category' ) );
		$this->assertSame( array( 'Jason Statham', 'Jennifer Lopez', 'Michael Chiklis' ), $this->get_term_names( $post, 'actors' ) );

		// Spaces around the comma separated values are trimmed.
		$post = $this->get_post_by_title( '告白(kokuhaku)' );
		$this->assertSame( array( 'drama', 'mystery' ), $this->get_term_names( $post, 'category' ) );
		$this->assertSame( array( '岡田 将生', '木村佳乃', '松たか子' ), $this->get_term_names( $post, 'actors' ) );
	}

	public function test_update_posts() {
		$by_id      = self::factory()->post->create(
			array(
				'post_title'   => 'Original',
				'post_content' => 'Original content',
			)
		);
		$by_post_id = self::factory()->post->create( array( 'post_title' => 'Original' ) );
		$mismatch   = self::factory()->post->create( array( 'post_title' => 'Original' ) );
		$new_id     = $mismatch + 100;

		$output = $this->import(
			array(
				array(
					'ID'         => $by_id,
					'post_type'  => 'post',
					'post_title' => 'Updated By ID',
				),
				// The post_id column is used when the ID column is empty.
				array(
					'post_id'    => $by_post_id,
					'post_type'  => 'post',
					'post_title' => 'Updated By post_id',
				),
				// A post ID that does not exist is used for the new post.
				array(
					'post_id'    => $new_id,
					'post_type'  => 'post',
					'post_title' => 'New ID',
				),
				array(
					'post_id'    => $mismatch,
					'post_type'  => 'page',
					'post_title' => 'Mismatch',
				),
			)
		);

		$post = get_post( $by_id );
		$this->assertSame( 'Updated By ID', $post->post_title );
		// Columns not in the CSV are kept.
		$this->assertSame( 'Original content', $post->post_content );

		$this->assertSame( 'Updated By post_id', get_post( $by_post_id )->post_title );
		$this->assertSame( 'New ID', get_post( $new_id )->post_title );

		$this->assertSame( 'Original', get_post( $mismatch )->post_title );
		$this->assertStringContainsString( 'The post type value from your csv file does not match the existing data in your database.', $output );
	}

	public function test_invalid_post_type_is_rejected() {
		$output = $this->import(
			array(
				array(
					'post_type'  => 'not_exists',
					'post_title' => 'Invalid Post Type',
				),
			)
		);

		$this->assertNull( $this->get_post_by_title( 'Invalid Post Type' ) );
		$this->assertStringContainsString( 'Invalid post type &quot;not_exists&quot;.', $output );
	}

	public function test_replace_by_title() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Same Title',
				'post_content' => 'Original content',
			)
		);

		$_POST['replace-by-title'] = '1';

		$this->import(
			array(
				array(
					'post_type'    => 'post',
					'post_title'   => 'Same Title',
					'post_content' => 'Replaced content',
				),
			)
		);

		$this->assertSame( 'Replaced content', get_post( $post_id )->post_content );
		$this->assertCount( 1, get_posts( array( 'title' => 'Same Title' ) ) );
	}

	public function test_import_thumbnails() {
		$local = $this->copy_test_image( 'canola.jpg' );

		add_filter(
			'pre_http_request',
			function ( $response, $args ) {
				copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );

				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => $args['filename'],
				);
			},
			10,
			2
		);

		$this->import(
			array(
				array(
					'post_type'      => 'post',
					'post_title'     => 'Local Thumbnail',
					'post_thumbnail' => $local,
				),
				array(
					'post_type'      => 'post',
					'post_title'     => 'Remote Thumbnail',
					'post_thumbnail' => 'https://example.com/images/remote.jpg',
				),
			)
		);

		$post         = $this->get_post_by_title( 'Local Thumbnail', 'post' );
		$thumbnail_id = get_post_thumbnail_id( $post );
		$this->assertSame( $local, get_attached_file( $thumbnail_id ) );
		$this->assertSame( $post->ID, get_post( $thumbnail_id )->post_parent );

		$thumbnail_id = get_post_thumbnail_id( $this->get_post_by_title( 'Remote Thumbnail', 'post' ) );
		$this->assertSame( 'remote.jpg', wp_basename( get_attached_file( $thumbnail_id ) ) );
	}

	public function test_import_attachment() {
		$file = $this->copy_test_image( 'canola.jpg' );

		$this->import(
			array(
				array(
					'post_type'                => 'attachment',
					'post_title'               => 'Attachment',
					'post_content'             => 'Description',
					'post_excerpt'             => 'Caption',
					'post_thumbnail'           => $file,
					'_wp_attachment_image_alt' => 'ALT text',
				),
			)
		);

		$attachment = $this->get_post_by_title( 'Attachment', 'attachment' );
		$this->assertSame( 'Description', $attachment->post_content );
		$this->assertSame( 'Caption', $attachment->post_excerpt );
		$this->assertSame( 'image/jpeg', $attachment->post_mime_type );
		$this->assertSame( $file, get_attached_file( $attachment->ID ) );
		$this->assertSame( 'ALT text', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
	}

	public function test_invalid_media_is_rejected() {
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );
		$text = $upload_dir['path'] . '/not-image.txt';
		$php  = $upload_dir['path'] . '/evil.php';
		file_put_contents( $text, 'text' );
		file_put_contents( $php, '<?php echo "evil";' );

		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'Download failed.' );
			}
		);

		$output = $this->import(
			array(
				array(
					'post_type'      => 'post',
					'post_title'     => 'Download Failure',
					'post_thumbnail' => 'https://example.com/images/remote.jpg',
				),
				array(
					'post_type'      => 'post',
					'post_title'     => 'Not Image',
					'post_thumbnail' => $text,
				),
				array(
					'post_type'      => 'attachment',
					'post_title'     => 'Disallowed File Type',
					'post_thumbnail' => $php,
				),
			)
		);

		// The posts are created without thumbnails.
		foreach ( array( 'Download Failure', 'Not Image' ) as $title ) {
			$post = $this->get_post_by_title( $title, 'post' );
			$this->assertInstanceOf( WP_Post::class, $post );
			$this->assertFalse( has_post_thumbnail( $post ) );
		}
		$this->assertNull( $this->get_post_by_title( 'Disallowed File Type', 'attachment' ) );

		$this->assertStringContainsString( 'Download failed.', $output );
		$this->assertStringContainsString( 'is not an image and cannot be used as a thumbnail.', $output );
		$this->assertStringContainsString( 'has a file type that is not permitted for security reasons.', $output );
	}

	public function test_hooks() {
		add_filter(
			'really_simple_csv_importer_save_post',
			function ( $post ) {
				$post['post_content'] = 'Filtered content';
				return $post;
			}
		);
		add_filter(
			'really_simple_csv_importer_save_meta',
			function ( $meta ) {
				$meta['added_meta'] = 'added';
				return $meta;
			}
		);
		add_filter(
			'really_simple_csv_importer_save_tax',
			function ( $tax ) {
				$tax['actors'] = array( 'Filtered Actor' );
				return $tax;
			}
		);

		$saved = array();
		add_action(
			'really_simple_csv_importer_post_saved',
			function ( $post ) use ( &$saved ) {
				$saved[] = $post->post_title;
			}
		);

		$this->import(
			array(
				array(
					'post_type'  => 'post',
					'post_title' => 'Hooks',
				),
			)
		);

		$post = $this->get_post_by_title( 'Hooks' );
		$this->assertSame( 'Filtered content', $post->post_content );
		$this->assertSame( 'added', get_post_meta( $post->ID, 'added_meta', true ) );
		$this->assertSame( array( 'Filtered Actor' ), $this->get_term_names( $post, 'actors' ) );
		$this->assertSame( array( 'Hooks' ), $saved );
	}

	public function test_dry_run_does_not_save_anything() {
		add_filter( 'really_simple_csv_importer_dry_run', '__return_true' );

		$this->import(
			array(
				array(
					'post_type'  => 'post',
					'post_title' => 'Dry Run',
				),
			)
		);

		$this->assertNull( $this->get_post_by_title( 'Dry Run' ) );
	}

	public function test_importer_class_filter() {
		add_filter(
			'really_simple_csv_importer_class',
			function () {
				return 'RSCSV_Test_Custom_Importer';
			}
		);

		$this->import(
			array(
				array(
					'post_type'  => 'post',
					'post_title' => 'Custom Importer',
				),
			)
		);

		$this->assertSame( 'Imported by custom class', $this->get_post_by_title( 'Custom Importer' )->post_content );
	}

	/**
	 * Build a CSV file from rows and import it.
	 *
	 * @param array[] $rows Rows keyed by column name. Missing columns are left empty.
	 * @return string The output of the importer.
	 */
	private function import( array $rows ) {
		$columns = array();
		foreach ( $rows as $row ) {
			$columns = array_merge( $columns, array_diff( array_keys( $row ), $columns ) );
		}

		$file   = wp_tempnam( 'rscsv-test.csv' );
		$handle = fopen( $file, 'w' );
		fputcsv( $handle, $columns, ',', '"', '\\' );
		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $columns as $column ) {
				$values[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
			}
			fputcsv( $handle, $values, ',', '"', '\\' );
		}
		fclose( $handle );

		$output = $this->import_file( $file );

		unlink( $file );

		return $output;
	}

	/**
	 * Run the importer against a CSV file.
	 *
	 * @param string $file Path to the CSV file.
	 * @return string The output of the importer.
	 */
	private function import_file( $file ) {
		$importer       = new RS_CSV_Importer();
		$importer->file = $file;
		$importer->id   = 0;

		ob_start();
		try {
			$importer->process_posts();
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * @param string $title     Post title.
	 * @param string $post_type Post type.
	 * @return WP_Post|null
	 */
	private function get_post_by_title( $title, $post_type = 'any' ) {
		$posts = get_posts(
			array(
				'title'       => $title,
				'post_type'   => $post_type,
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * @param WP_Post $post     Post.
	 * @param string  $taxonomy Taxonomy.
	 * @return string[] Term names, sorted.
	 */
	private function get_term_names( $post, $taxonomy ) {
		$names = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
		sort( $names );

		return $names;
	}

	/**
	 * Copy an image from the WordPress test data into the uploads directory.
	 *
	 * @param string $name File name in the test data images directory.
	 * @return string Path to the copied file.
	 */
	private function copy_test_image( $name ) {
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );

		$file = $upload_dir['path'] . '/' . $name;
		copy( DIR_TESTDATA . '/images/' . $name, $file );

		return $file;
	}
}

/**
 * Importer class used for the really_simple_csv_importer_class filter.
 */
class RSCSV_Test_Custom_Importer {

	public function save_post( $post, $meta, $terms, $thumbnail, $is_update ) {
		$post['post_content'] = 'Imported by custom class';

		return RSCSV_Import_Post_Helper::add( $post );
	}
}
