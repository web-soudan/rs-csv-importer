<?php
/**
 * Base test case for running the importer against CSV data.
 *
 * @package Really_Simple_CSV_Importer
 */

abstract class Importer_TestCase extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// The upload form always sends this option ("Disable" is the default).
		$_POST['replace-by-title'] = '0';
	}

	public function tear_down() {
		unset( $_POST['replace-by-title'] );
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Import CSV data given as a string.
	 *
	 * @param string $csv CSV data including the header row.
	 * @return string The output of the importer.
	 */
	protected function import_csv( $csv ) {
		$file = wp_tempnam( 'rscsv-test.csv' );
		file_put_contents( $file, $csv );

		$output = $this->import_file( $file );

		unlink( $file );

		return $output;
	}

	/**
	 * Import one of the CSV files in the sample directory.
	 *
	 * @param string $name File name in the sample directory.
	 * @return string The output of the importer.
	 */
	protected function import_sample( $name ) {
		return $this->import_file( dirname( __DIR__, 3 ) . '/sample/' . $name );
	}

	/**
	 * Run the importer against a CSV file.
	 *
	 * @param string $file Path to the CSV file.
	 * @return string The output of the importer.
	 */
	protected function import_file( $file ) {
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
	 * Run a callback while collecting PHP warnings instead of failing the test,
	 * so the code keeps running as it does in production.
	 *
	 * @param callable $callback Callback to run.
	 * @return string[] Warning messages.
	 */
	protected function collect_warnings( callable $callback ) {
		$warnings = array();

		set_error_handler(
			function ( $errno, $errstr ) use ( &$warnings ) {
				$warnings[] = $errstr;
				return true;
			},
			E_WARNING
		);

		try {
			$callback();
		} finally {
			restore_error_handler();
		}

		return $warnings;
	}

	/**
	 * Find a post by its title.
	 *
	 * @param string $title     Post title.
	 * @param string $post_type Post type.
	 * @return WP_Post|null
	 */
	protected function get_post_by_title( $title, $post_type = 'any' ) {
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
	 * Copy an image from the WordPress test data into the uploads directory.
	 *
	 * @param string $name File name in the test data images directory.
	 * @return string Path to the copied file.
	 */
	protected function copy_test_image( $name = 'canola.jpg' ) {
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );

		$file = $upload_dir['path'] . '/' . $name;
		copy( DIR_TESTDATA . '/images/' . $name, $file );

		return $file;
	}
}
