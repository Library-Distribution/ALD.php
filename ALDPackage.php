<?php
require_once dirname(__FILE__) . '/lib/exceptions.php';
require_once dirname(__FILE__) . '/ALDPackageDefinition.php';

class ALDPackage {
	private $archive;
	public $definition;

	public function __construct($path) {
		if (!file_exists($path)) {
			throw new FileNotFoundException();
		}

		$this->archive = new ZipArchive();
		if (@$this->archive->open($path) !== true) {
			@$this->archive->close();
			throw new ArchiveOpenException();
		}

		$this->definition = new ALDPackageDefinition($this->archive->getFromName(ALDPackageDefinition::DefinitionFile));

		$this->validate();
	}

	public function __destruct() {
		$this->archive->close();
	}

	private function validate() {
		$def_files = $this->definition->GetFiles();
		$files = array_flip($this->GetFiles());

		foreach ($def_files AS $def_file) {
			if (!isset($files[$def_file])) {
				throw new FileNotFoundException($def_file); # defined but missing
			}
			unset($files[$def_file]);
		}

		foreach ($files AS $file => $i) { # undefined files left
			if ($file !== ALDPackageDefinition::DefinitionFile) {
				throw new UndefinedFileException($file);
			}
		}
	}

	public function GetFiles() {
		$files = array();

		for ($i = 0; $i < $this->archive->numFiles; $i++) {
			$stat = $this->archive->statIndex($i);
			if ($stat['name'][strlen($stat['name'])-1] !== '/') {
				$files[] = $stat['name'];
			}
		}

		return $files;
	}
}
?>