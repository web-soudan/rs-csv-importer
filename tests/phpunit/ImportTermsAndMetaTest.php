<?php
/**
 * Characterization tests for importing terms and custom fields.
 *
 * @package Really_Simple_CSV_Importer
 */

class ImportTermsAndMetaTest extends Importer_TestCase {

	public function set_up() {
		parent::set_up();

		register_taxonomy( 'actors', 'post' );
	}

	public function tear_down() {
		unregister_taxonomy( 'actors' );

		parent::tear_down();
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

	public function test_categories_and_custom_taxonomy_from_sample() {
		$this->import_sample( 'movies.csv' );

		$post = $this->get_post_by_title( 'Parker' );
		$this->assertSame( array( 'action', 'crime', 'thriller' ), $this->get_term_names( $post, 'category' ) );
		$this->assertSame( array( 'Jason Statham', 'Jennifer Lopez', 'Michael Chiklis' ), $this->get_term_names( $post, 'actors' ) );

		// Spaces around the comma separated values are trimmed.
		$post = $this->get_post_by_title( '告白(kokuhaku)' );
		$this->assertSame( array( 'drama', 'mystery' ), $this->get_term_names( $post, 'category' ) );
		$this->assertSame( array( '岡田 将生', '木村佳乃', '松たか子' ), $this->get_term_names( $post, 'actors' ) );

		// Existing categories are reused.
		$this->assertCount( 6, get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'exclude' => array( get_option( 'default_category' ) ) ) ) );
	}

	public function test_post_tags() {
		$csv = <<<CSV
"post_type","post_title","post_tags"
"post","Tags","apple,banana, cherry"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( array( 'apple', 'banana', 'cherry' ), $this->get_term_names( $this->get_post_by_title( 'Tags' ), 'post_tag' ) );
	}

	public function test_unknown_columns_are_saved_as_post_meta() {
		$csv = <<<CSV
"post_type","post_title","custom_field","empty_field"
"post","Custom Field","this is custom field value.",""
CSV;

		$this->import_csv( $csv );

		$post = $this->get_post_by_title( 'Custom Field' );
		$this->assertSame( 'this is custom field value.', get_post_meta( $post->ID, 'custom_field', true ) );

		// Empty values are saved as empty meta, too.
		$this->assertTrue( metadata_exists( 'post', $post->ID, 'empty_field' ) );
		$this->assertSame( '', get_post_meta( $post->ID, 'empty_field', true ) );

		// Known columns are not saved as post meta.
		$this->assertFalse( metadata_exists( 'post', $post->ID, 'post_title' ) );
		$this->assertFalse( metadata_exists( 'post', $post->ID, 'post_type' ) );
	}

	public function test_dry_run_does_not_save_anything() {
		add_filter( 'really_simple_csv_importer_dry_run', '__return_true' );

		$csv = <<<CSV
"post_type","post_title"
"post","Dry Run"
CSV;

		$output = $this->import_csv( $csv );

		$this->assertNull( $this->get_post_by_title( 'Dry Run' ) );
		$this->assertStringContainsString( 'All Done.', $output );
	}

	public function test_filters_can_change_the_data() {
		add_filter(
			'really_simple_csv_importer_save_post',
			function ( $post, $is_update ) {
				$post['post_content'] = 'Filtered content';
				return $post;
			},
			10,
			2
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

		$csv = <<<CSV
"post_type","post_title"
"post","Filters"
CSV;

		$this->import_csv( $csv );

		$post = $this->get_post_by_title( 'Filters' );
		$this->assertSame( 'Filtered content', $post->post_content );
		$this->assertSame( 'added', get_post_meta( $post->ID, 'added_meta', true ) );
		$this->assertSame( array( 'Filtered Actor' ), $this->get_term_names( $post, 'actors' ) );
	}

	public function test_post_saved_action() {
		$saved = array();
		add_action(
			'really_simple_csv_importer_post_saved',
			function ( $post ) use ( &$saved ) {
				$saved[] = $post->post_title;
			}
		);

		$csv = <<<CSV
"post_type","post_title"
"post","Saved 1"
"post","Saved 2"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( array( 'Saved 1', 'Saved 2' ), $saved );
	}

	public function test_importer_class_filter() {
		add_filter(
			'really_simple_csv_importer_class',
			function () {
				return 'RSCSV_Test_Custom_Importer';
			}
		);

		$csv = <<<CSV
"post_type","post_title"
"post","Custom Importer"
CSV;

		$this->import_csv( $csv );

		$this->assertSame( 'Imported by custom class', $this->get_post_by_title( 'Custom Importer' )->post_content );
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
