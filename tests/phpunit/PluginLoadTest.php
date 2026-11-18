<?php
/**
 * Tests for loading the plugin.
 *
 * @package Really_Simple_CSV_Importer
 */

class PluginLoadTest extends WP_UnitTestCase {

	public function test_importer_is_registered() {
		global $wp_importers;

		$this->assertArrayHasKey( 'csv', (array) $wp_importers );
	}
}
