<?php
/**
 * Characterization tests for importing thumbnails and attachments.
 *
 * @package Really_Simple_CSV_Importer
 */

class ImportMediaTest extends Importer_TestCase {

	public function test_thumbnail_from_local_file() {
		$file = $this->copy_test_image();

		$csv = <<<CSV
"post_type","post_title","post_thumbnail"
"post","Local Thumbnail","{$file}"
CSV;

		$this->import_csv( $csv );

		$post         = $this->get_post_by_title( 'Local Thumbnail', 'post' );
		$thumbnail_id = get_post_thumbnail_id( $post );
		$this->assertGreaterThan( 0, $thumbnail_id );
		$this->assertSame( $post->ID, get_post( $thumbnail_id )->post_parent );
		$this->assertSame( 'image/jpeg', get_post_mime_type( $thumbnail_id ) );
		$this->assertSame( $file, get_attached_file( $thumbnail_id ) );
	}

	public function test_thumbnail_from_remote_url() {
		$requested = array();
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$requested ) {
				$requested[] = $url;
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
			3
		);

		$csv = <<<CSV
"post_type","post_title","post_thumbnail"
"post","Remote Thumbnail","https://example.com/images/remote.jpg"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( array( 'https://example.com/images/remote.jpg' ), $requested );

		$thumbnail_id = get_post_thumbnail_id( $this->get_post_by_title( 'Remote Thumbnail', 'post' ) );
		$this->assertGreaterThan( 0, $thumbnail_id );
		$this->assertSame( 'remote.jpg', wp_basename( get_attached_file( $thumbnail_id ) ) );
	}

	public function test_thumbnail_download_failure() {
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'Download failed.' );
			}
		);

		$csv = <<<CSV
"post_type","post_title","post_thumbnail"
"post","Failed Thumbnail","https://example.com/images/remote.jpg"
CSV;

		$output = $this->import_csv( $csv );

		$post = $this->get_post_by_title( 'Failed Thumbnail', 'post' );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertFalse( has_post_thumbnail( $post ) );
		$this->assertStringContainsString( 'Download failed.', $output );
		$this->assertStringNotContainsString( 'done.', $output );
	}

	public function test_non_image_thumbnail_is_rejected() {
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );
		$file = $upload_dir['path'] . '/not-image.txt';
		file_put_contents( $file, 'text' );

		$csv = <<<CSV
"post_type","post_title","post_thumbnail"
"post","Not Image Thumbnail","{$file}"
CSV;

		$output = $this->import_csv( $csv );

		// The post itself is created, but the thumbnail is not set.
		$post = $this->get_post_by_title( 'Not Image Thumbnail', 'post' );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertFalse( has_post_thumbnail( $post ) );
		$this->assertStringContainsString( 'The file &quot;not-image.txt&quot; is not an image and cannot be used as a thumbnail.', $output );
	}

	public function test_import_attachment() {
		$file = $this->copy_test_image();

		$csv = <<<CSV
"post_title","post_type","post_content","post_excerpt","post_thumbnail","_wp_attachment_image_alt"
"Attachment test","attachment","Image presentation","Image caption","{$file}","ALT text"
CSV;

		$this->import_csv( $csv );

		$attachment = $this->get_post_by_title( 'Attachment test', 'attachment' );
		$this->assertInstanceOf( WP_Post::class, $attachment );
		$this->assertSame( 'inherit', $attachment->post_status );
		$this->assertSame( 'Image presentation', $attachment->post_content );
		$this->assertSame( 'Image caption', $attachment->post_excerpt );
		$this->assertSame( 'image/jpeg', $attachment->post_mime_type );
		$this->assertSame( $file, get_attached_file( $attachment->ID ) );
		$this->assertSame( 'ALT text', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
	}

	public function test_disallowed_attachment_file_type_is_rejected() {
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );
		$file = $upload_dir['path'] . '/evil.php';
		file_put_contents( $file, '<?php echo "evil";' );

		$csv = <<<CSV
"post_title","post_type","post_thumbnail"
"Evil attachment","attachment","{$file}"
CSV;

		$output = $this->import_csv( $csv );

		$this->assertNull( $this->get_post_by_title( 'Evil attachment', 'attachment' ) );
		$this->assertStringContainsString( 'has a file type that is not permitted for security reasons.', $output );
	}
}
