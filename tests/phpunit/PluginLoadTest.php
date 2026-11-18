<?php
/**
 * Smoke tests to make sure the plugin is loaded in the test environment.
 *
 * @package Really_Simple_CSV_Importer
 */

class PluginLoadTest extends WP_UnitTestCase {

	public function test_classes_are_loaded() {
		$this->assertTrue( class_exists( 'RS_CSV_Importer' ) );
		$this->assertTrue( class_exists( 'RS_CSV_Helper' ) );
		$this->assertTrue( class_exists( 'RSCSV_Import_Post_Helper' ) );
	}

	public function test_importer_is_registered() {
		global $wp_importers;

		$this->assertArrayHasKey( 'csv', (array) $wp_importers );
	}
}
