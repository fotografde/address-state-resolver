<?php

/**
 * Class StateResolverTest
 * @property \GetPhoto\L10n\StateResolver $StateResolver
 */
class StateResolverTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
		parent::setUp();
		$this->StateResolver = new \GetPhoto\L10n\StateResolver();
	}

	public function test_validateState_US_false() {
		$actual = $this->StateResolver->validateState('US', 'US-QQ');
		$this->assertFalse($actual);
	}

	public function test_validateState_US_true() {
		$actual = $this->StateResolver->validateState('US', 'US-OK');
		$this->assertTrue($actual);
	}

	public function test_validateState_ES_false() {
		$actual = $this->StateResolver->validateState('ES', 'ES-QQ');
		$this->assertFalse($actual);
	}

	public function test_validateState_ES_true() {
		$actual = $this->StateResolver->validateState('ES', 'ES-M');
		$this->assertTrue($actual);
	}

	public function test_getCountriesWithRequiredState() {
		$countries = $this->StateResolver->getCountriesWithRequiredState();
		$this->assertContains('US', $countries);
		$this->assertContains('CA', $countries);
		$this->assertContains('ES', $countries);
	}

	public function test_getState_US() {
		if (getenv('CI')) {
			$this->markTestSkipped('Can only run this test locally');
		}
		$actual = $this->StateResolver->getState('73505', 'Lawton', 'US');
		$this->assertEquals('US-OK', $actual);
	}

	public function test_getState_CA() {
		if (getenv('CI')) {
			$this->markTestSkipped('Can only run this test locally');
		}
		$actual = $this->StateResolver->getState('J8V 3E1', 'Cantley', 'CA');
		$this->assertEquals('CA-QC', $actual);
	}

	public function test_getState_ES() {
		if (getenv('CI')) {
			$this->markTestSkipped('Can only run this test locally');
		}
		$actual = $this->StateResolver->getState('28660', 'Boadilla del Monte', 'ES');
		$this->assertEquals('ES-M', $actual);
	}

	public function test_getState_AU() {
		if (getenv('CI')) {
			$this->markTestSkipped('Can only run this test locally');
		}
		$actual = $this->StateResolver->getState('2136', 'Strathfield South', 'AU');
		$this->assertEquals('AU-NSW', $actual);
	}
}