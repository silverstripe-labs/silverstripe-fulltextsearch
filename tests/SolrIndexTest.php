<?php
class SolrIndexTest extends SapphireTest {

	function setUpOnce() {
		parent::setUpOnce();

		Phockito::include_hamcrest();
	}
	
	function testBoost() {
		$serviceMock = $this->getServiceMock();
		$index = new SolrIndexTest_FakeIndex();
		$index->setService($serviceMock);

		$query = new SearchQuery();
		$query->search(
			'term', 
			null, 
			array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
		);
		$index->search($query);

		Phockito::verify($serviceMock)->search(
			'+(Field1:term^1.5 OR HasOneObject_Field1:term^3)',
			anything(), anything(), anything(), anything()
		);
	}

	function testIndexExcludesNullValues() {
		$serviceMock = $this->getServiceMock();
		$index = new SolrIndexTest_FakeIndex();
		$index->setService($serviceMock);		
		$obj = new SearchUpdaterTest_Container();

		$obj->Field1 = 'Field1 val';
		$obj->Field2 = null;
		$obj->MyDate = null;
		$docs = $index->add($obj);
		$value = $docs[0]->getField('SearchUpdaterTest_Container_Field1');
		$this->assertEquals('Field1 val', $value['value'], 'Writes non-NULL string fields');
		$value = $docs[0]->getField('SearchUpdaterTest_Container_Field2');
		$this->assertFalse($value, 'Ignores string fields if they are NULL');
		$value = $docs[0]->getField('SearchUpdaterTest_Container_MyDate');
		$this->assertFalse($value, 'Ignores date fields if they are NULL');

		$obj->MyDate = '2010-12-30';
		$docs = $index->add($obj);
		$value = $docs[0]->getField('SearchUpdaterTest_Container_MyDate');
		$this->assertEquals('2010-12-30T00:00:00Z', $value['value'], 'Writes non-NULL dates');
	}

	protected function getServiceMock() {
		$serviceMock = Phockito::mock('SolrService');
		$fakeResponse = new Apache_Solr_Response(new Apache_Solr_HttpTransport_Response(null, null, null));

		Phockito::when($serviceMock)
			->_sendRawPost(anything(), anything(), anything(), anything())
			->return($fakeResponse);

		return $serviceMock;
	}

}

class SolrIndexTest_FakeIndex extends SolrIndex {
	function init() {
		$this->addClass('SearchUpdaterTest_Container');

		$this->addFilterField('Field1');
		$this->addFilterField('MyDate', 'Date');
		$this->addFilterField('HasOneObject.Field1');
		$this->addFilterField('HasManyObjects.Field1');
	}
}