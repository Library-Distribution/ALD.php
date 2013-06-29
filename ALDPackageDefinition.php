<?php
require_once dirname(__FILE__) . '/lib/exceptions.php';

class ALDPackageDefinition {

	const XMLNS = 'ald://package/schema/2012';
	const DefinitionFile = 'definition.ald';

	/************************************************************************************************/

	private static $schema = NULL;

	public static function SetSchemaLocation($path) {
		if (!file_exists($path)) {
			throw new FileNotFoundException();
		}
		self::$schema = file_get_contents($path);
	}

	/************************************************************************************************/

	public function __construct($source) {
		if (self::$schema === NULL) {
			throw new UnknownSchemaException();
		}

		$this->document = new DOMDocument();
		$this->document->loadXML($source);

		$this->validate();

		$this->xpath = new DOMXPath($this->document);
		$this->xpath->registerNamespace('ald', self::XMLNS);
	}

	public function GetFiles() {
		$files = array_merge($this->GetSourceFiles(), $this->GetDocFiles());
		if (!in_array($logo = $this->GetLogo(), $files) && $logo !== NULL) {
			$files[] = $t;
		}
		return $files;
	}

	public function GetSourceFiles() {
		return $this->fileList('src');
	}

	public function GetDocFiles() {
		return $this->fileList('doc');
	}

	public function GetFilesHierarchy() {
		return array('src' => $this->GetSourceFilesHierarchy(), 'doc' => $this->GetDocFilesHierarchy());
	}

	public function GetSourceFilesHierarchy() {
		return $this->fileHierarchy('src');
	}

	public function GetDocFilesHierarchy() {
		return $this->fileHierarchy('doc');
	}

	public function GetID() {
		return $this->readAttribute('id');
	}

	public function GetName() {
		return $this->readAttribute('name');
	}

	public function GetVersion() {
		return $this->readAttribute('version');
	}

	public function GetType() {
		return $this->readAttribute('type');
	}

	public function GetHomepage() {
		return $this->readAttribute('homepage');
	}

	public function GetLogo() {
		return $this->readAttribute('logo-image');
	}

	public function GetDescription() {
		return $this->readTag('description');
	}

	public function GetSummary() {
		return $this->readTag('summary');
	}

	public function GetAuthors() {
		return $this->readArray('ald:authors/ald:author', array('name', 'user-name', 'homepage', 'email'));
	}

	public function GetDependencies() {
		return $this->readArray('ald:dependencies/ald:dependency', array('name'), '.');
	}

	public function GetTargetsHierarchy() {
		return $this->readArrayRecursive('ald:targets', 'ald:target',
				array('id', 'message', 'language-architecture', 'language-encoding', 'system-architecture', 'system-version', 'system-type'),
				'./ald:language-version', 'language-version');
	}

	public function GetTargets() {
		$targets = array();
		foreach ($this->GetTargetsHierarchy() AS $target) {
			$targets = array_merge($targets, $this->flattenTarget($target));
		}
		return $targets;
	}

	public function GetTags() {
		$tags = array();
		foreach ($this->readArray('ald:tags/ald:tag', array('name')) AS $tag) {
			$tags[] = $tag['name'];
		}
		return $tags;
	}

	public function GetLinks() {
		return $this->readArray('ald:links/ald:link', array('name', 'description', 'href'));
	}

	/************************************************************************************************/

	private $document;
	private $xpath;

	private function validate() {
		if (!@$this->document->schemaValidateSource(self::$schema)) {
			throw new InvalidXmlException();
		}
	}

	private function fileList($list) {
		$files = array();
		$list_root = $this->xpath->query('/*/ald:files/ald:' . $list)->item(0);

		foreach ($this->xpath->query('.//ald:file', $list_root) AS $node) {
			$path = $node->getAttribute('ald:path');

			$curr_node = $node;
			while ($curr_node->parentNode->tagName == 'ald:file-set') {
				$curr_node = $curr_node->parentNode;
				$path = $curr_node->getAttribute('ald:src') . '/' . $path;
			}

			$files[] = $path;
		}

		return $files;
	}

	private function fileHierarchy($xpath, $top = true) {
		$files = array('files' => array(), 'sets' => array());
		$list_root = $this->xpath->query(($top ? '/*/ald:files/ald:' : '') . $xpath)->item(0);

		if (!$top) {
			$files['src'] = $list_root->getAttribute('ald:src');

			$files['targets'] = array();
			foreach ($this->xpath->query('./ald:target', $list_root) AS $node) {
				$files['targets'][] = $node->getAttribute('ald:ref');
			}
		}
		foreach ($this->xpath->query('./ald:file', $list_root) AS $node) {
			$files['files'][] = $node->getAttribute('ald:path');
		}
		foreach ($this->xpath->query('./ald:file-set', $list_root) AS $node) {
			$files['sets'][] = $this->fileHierarchy($node->getNodePath(), false);
		}

		return $files;
	}

	private function flattenTarget($obj, $parent = array()) {
		$list = array();

		unset($parent['id']); # so it doesn't overwrite
		$target = array_merge($obj, $parent); # parent values overwrite child values
		unset($target[0]); # so we don't have it in the output
		$list[] = $target;

		if (isset($obj[0]) && is_array($obj[0])) {
			foreach ($obj[0] AS $child) {
				$list = array_merge($list, $this->flattenTarget($child, $target));
			}
		}

		return $list;
	}

	private function readAttribute($attr, $context = NULL) {
		return $this->readNode('@ald:' . $attr, $context);
	}

	private function readTag($tag, $context = NULL) {
		return $this->readNode('ald:' . $tag, $context);
	}

	private function readNode($xpath, $context = NULL) {
		$list = $this->xpath->query($xpath, $context);
		if ($list->length > 0) {
			return $list->item(0)->nodeValue;
		}
		return NULL;
	}

	private function readAttributeList($node, $list) {
		$data = array();
		foreach ($list AS $attr) {
			if (($t = $this->readAttribute($attr, $node)) !== NULL) {
				$data[$attr] = $t;
			}
		}
		return $data;
	}

	private function readArray($fragment, $attributes, $version_tag = NULL) {
		$arr = array();

		foreach ($this->xpath->query('/*/' . $fragment) AS $node) {
			$item = $this->readAttributeList($node, $attributes);

			if ($version_tag !== NULL) {
				$item = array_merge($item, $this->readVersionTag($this->xpath->query($version_tag, $node)->item(0)));
			}

			$arr[] = $item;
		}

		return $arr;
	}

	private function readArrayRecursive($root, $path, $attributes, $version_tag = NULL, $version_tag_key = NULL) {
		$arr = array();

		foreach ($this->xpath->query($root . '/' . $path) AS $node) {
			$item = $this->readAttributeList($node, $attributes);

			$children = $this->readArrayRecursive($node->getNodePath(), $path, $attributes, $version_tag, $version_tag_key);
			if (count($children) > 0) {
				$item[] = $children;
			}

			if ($version_tag !== NULL) {
				$list = $this->xpath->query($version_tag, $node);
				if ($list->length > 0) {
					$item[$version_tag_key] = $this->readVersionTag($list->item(0));
				}
			}

			$arr[] = $item;
		}

		return $arr;
	}

	private function readVersionTag($node) {
		$tag = array();

		if ($list = $this->xpath->query('ald:version-list', $node)->item(0)) {
			$tag['version-list'] = array();
			foreach ($this->xpath->query('ald:version/@ald:value', $list) AS $version) {
				$tag['version-list'][] = $version->nodeValue;
			}

		} else if ($range = $this->xpath->query('ald:version-range', $node)->item(0)) {
			$tag['version-range'] = array('min' => $this->readAttribute('min-version', $range), 'max' => $this->readAttribute('max-version', $range));

		} else {
			$tag['version'] = $this->xpath->query('ald:version/@ald:value', $node)->item(0)->nodeValue;
		}

		return $tag;
	}
}
?>