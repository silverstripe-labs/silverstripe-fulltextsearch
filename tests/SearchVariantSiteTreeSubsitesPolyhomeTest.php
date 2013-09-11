<?php

class SearchVariantSiteTreeSubsitesPolyhomeTest_Item extends SiteTree {
	// TODO: Currently theres a failure if you addClass a non-table class
	private static $db = array(
		'TestText' => 'Varchar'
	);
}

class SearchVariantSiteTreeSubsitesPolyhomeTest_Index extends SearchIndex_Recording {
	function init() {
		$this->addClass('SearchVariantSiteTreeSubsitesPolyhomeTest_Item');
		$this->addFilterField('TestText');
	}
}

class SearchVariantSiteTreeSubsitesPolyhomeTest extends SapphireTest {

	private static $index = null;

	private static $subsite_a = null;
	private static $subsite_b = null;

	function setUp() {
		parent::setUp();

		// Check subsites installed
		if(!class_exists('Subsite') || !class_exists('SubsitePolyhome')) {
			return $this->markTestSkipped('The subsites polyhome module is not installed');
		}

		if (self::$index === null) self::$index = singleton('SearchVariantSiteTreeSubsitesPolyhomeTest_Index');

		if (self::$subsite_a === null) {
			self::$subsite_a = new Subsite(); self::$subsite_a->write();
			self::$subsite_b = new Subsite(); self::$subsite_b->write();
		}

		FullTextSearch::force_index_list(self::$index);
		SearchUpdater::clear_dirty_indexes();
	}

	function testSavingDirect() {
		// Initial add

		$item = new SearchVariantSiteTreeSubsitesPolyhomeTest_Item();
		$item->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID', '_subsite')), array(
			array('ID' => $item->ID, '_subsite' => 0)
		));

		// Check that adding to subsites works

		self::$index->reset();

		$item->setField('AddToSubsite[0]', 1);
		$item->setField('AddToSubsite['.(self::$subsite_a->ID).']', 1);

		$item->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID', '_subsite')), array(
			array('ID' => $item->ID, '_subsite' => 0),
			array('ID' => $item->ID, '_subsite' => self::$subsite_a->ID)
		));
		$this->assertEquals(self::$index->deleted, array(
			array('base' => 'SiteTree', 'id' => $item->ID, 'state' => array(
				'SearchVariantVersioned' => 'Stage', 'SearchVariantSiteTreeSubsitesPolyhome' => self::$subsite_b->ID
			))
		));

		
	}
}