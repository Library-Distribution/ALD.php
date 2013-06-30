<?php
require_once dirname(__FILE__) . '/lib/exceptions.php';

class ALDVersionSwitch {
	private $data;

	public function __construct($data) {
		$this->validate($data);
		$this->data = $data;
	}

	public function matches($value) {
		if (isset($this->data['version']) && $this->compare($this->data['version'], $value) == 0) {
			return true;
		} else if (isset($this->data['version-range'])
			&& $this->compare($this->data['version-range']['min'], $value) <= 0
			&& $this->compare($this->data['version-range']['max'], $value) >= 0) {
			return true;
		} else if (isset($this->data['version-list'])) {
			foreach ($this->data['version-list'] AS $version) {
				if ($this->compare($version, $value) == 0) {
					return true;
				}
			}
		}
		return false;
	}

	protected function compare($a, $b) {
		return strnatcasecmp($a, $b);
	}

	protected function validate($data) {
		if (!is_array($data) || count($data) != 1) {
			throw new InvalidVersionSwitchException('Invalid switch data');
		}

		if (isset($data['version-range'])) {
			if (!is_array($data['version-range'])
				|| count($data['version-range']) != 2
				|| !isset($data['version-range']['min'])
				|| !isset($data['version-range']['max'])) {
				throw new InvalidVersionSwitchException('Invalid version range: incorrect data');
			}
			if ($this->compare($data['version-range']['min'], $data['version-range']['max']) > 0) {
				throw new InvalidVersionSwitchException('Invalid version range: min > max');
			}

		}
		else if (isset($data['version-list'])) {
			if (!is_array($data['version-list'])) {
				throw new InvalidVersionSwitchException('Invalid version list: not an array');
			}
			if (array_keys($data['version-list']) !== array_keys(array_keys($data['version-list']))) { # not only continous numeric keys
				throw new InvalidVersionSwitchException('Invalid version list: not a continous zero-based array');
			}

		} else if (!isset($data['version'])) {
			throw new InvalidVersionSwitchException('Invalid switch data: unsupported fields');
		}
	}
}
?>