<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once dirname(__FILE__) . '/../ALDVersionSwitch.php';

class ALDVersionSwitchTest extends PHPUnit_Framework_TestCase {

	/**
	* @dataProvider empty_switches
	* @expectedException InvalidVersionSwitchException
	* @expectedExceptionMessage Invalid switch data
	*/
	public function test_validate_empty($data) {
		new ALDVersionSwitch($data);
	}

	public function empty_switches() {
		$data = array(NULL,
			      array(),
			      'abc',
			      1);
		return array_map(create_function('$a', 'return array($a);'), $data);
	}

	/**
	* @expectedException InvalidVersionSwitchException
	* @expectedExceptionMessage Invalid switch data: unsupported fields
	*/
	public function test_validate_unknown() {
		new ALDVersionSwitch(array('dummy' => 'error'));
	}

	/**
	* @dataProvider invalid_ranges
	* @expectedException InvalidVersionSwitchException
	* @expectedExceptionMessage Invalid version range
	*/
	public function test_validate_range($data) {
		new ALDVersionSwitch($data);
	}

	public function invalid_ranges() {
		$data = array(array('min' => 1),
			      array('max' => 2),
			      array('min' => 1, 'max' => 2, 'dummy' => 'error'),
			      array('error', 'min' => 1, 'max' => 2),
			      array('min' => 2, 'max' => 1));
		return array_map(create_function('$a', 'return array(array("version-range" => $a));'), $data);
	}

	/**
	* @dataProvider invalid_lists
	* @expectedException InvalidVersionSwitchException
	* @expectedExceptionMessage Invalid version list
	*/
	public function test_validate_list($data) {
		new ALDVersionSwitch($data);
	}

	public function invalid_lists() {
		$data = array(NULL,
			      array('a' => '2', '1', 2),
			      array(1 => 'a', 'b', 'c'));
		return array_map(create_function('$a', 'return array(array("version-list" => $a));'), $data);
	}

	/**
	* @expectedException InvalidVersionSwitchException
	* @expectedExceptionMessage Invalid version: must not be NULL
	*/
	public function test_validate_version() {
		new ALDVersionSwitch(array('version' => NULL));
	}
}
?>