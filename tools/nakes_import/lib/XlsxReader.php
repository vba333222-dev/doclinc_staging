<?php

require_once __DIR__ . '/NakesImportException.php';

class DoclincXlsxReader
{
	private const MAX_ENTRY_BYTES = 8388608;
	private const MAX_TOTAL_BYTES = 33554432;
	private $data;
	private $entries = array();

	public function read($path)
	{
		$data = file_get_contents($path);
		if (!is_string($data)) {
			throw new NakesImportException('source_read_failed');
		}
		return $this->readData($data);
	}

	public function readData($data)
	{
		if (!is_string($data) || $data === '') {
			throw new NakesImportException('source_read_failed');
		}
		$this->data = $data;
		$this->entries = $this->readCentralDirectory($data);
		$this->validatePackageStructure();
		$workbook = $this->xml('xl/workbook.xml');
		$workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$sheet_nodes = $workbook->xpath('/x:workbook/x:sheets/x:sheet');
		$relationships = $this->workbookRelationships();
		$sheets = array();
		foreach ($sheet_nodes ?: array() as $sheet) {
			$attributes = $sheet->attributes();
			$state = strtolower(trim((string) $attributes['state']));
			if ($state !== '' && $state !== 'visible') throw new NakesImportException('hidden_sheet_rejected');
			$relationship_attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
			$relationship_id = (string) $relationship_attributes['id'];
			if ($relationship_id === '' || !isset($relationships[$relationship_id])) throw new NakesImportException('worksheet_relationship_invalid');
			$worksheet = $this->readWorksheet($relationships[$relationship_id]);
			$sheets[] = array(
				'name' => (string) $attributes['name'],
				'rows' => $worksheet['rows'],
				'merged_ranges' => $worksheet['merged_ranges'],
			);
		}
		return $sheets;
	}

	private function readWorksheet($entry)
	{
		$shared = $this->sharedStrings();
		$xml = $this->xml($entry);
		$xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$rows = array();
		foreach ($xml->xpath('/x:worksheet/x:sheetData/x:row') ?: array() as $row) {
			$row_attributes = $row->attributes();
			$row_number = (string) $row_attributes['r'];
			if (preg_match('/\A[1-9][0-9]*\z/', $row_number) !== 1 || isset($rows[(int) $row_number])) throw new NakesImportException('invalid_row_reference');
			$values = array();
			foreach ($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $cell) {
				$attributes = $cell->attributes();
				$reference = (string) $attributes['r'];
				if (preg_match('/\A([A-Z]+)([1-9][0-9]*)\z/', $reference, $match) !== 1 || $match[2] !== $row_number) {
					throw new NakesImportException('invalid_cell_reference');
				}
				$column = $this->columnNumber($match[1]);
				if ($column < 1 || $column > 16384 || array_key_exists($column, $values)) throw new NakesImportException('duplicate_or_invalid_cell');
				$type = (string) $attributes['t'];
				$children = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
				if (isset($children->f)) throw new NakesImportException('formula_cell_rejected');
				if ($type === 'e') throw new NakesImportException('error_cell_rejected');
				if (!in_array($type, array('', 'n', 's', 'inlineStr', 'b'), true)) throw new NakesImportException('unsupported_cell_type');
				$value = '';
				if ($type === 's') {
					$shared_index = trim((string) $children->v);
					if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $shared_index) !== 1) throw new NakesImportException('invalid_shared_string_reference');
					$index = (int) $shared_index;
					if (!array_key_exists($index, $shared)) {
						throw new NakesImportException('invalid_shared_string_reference');
					}
					$value = $shared[$index];
				} elseif ($type === 'inlineStr') {
					$value = $this->richText($children->is);
				} else {
					$value = trim((string) $children->v);
					if ($value !== '' && (($type === 'b' && !in_array($value, array('0','1'), true))
						|| ($type !== 'b' && preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/', $value) !== 1))) {
						throw new NakesImportException('numeric_cell_format_rejected');
					}
				}
				if ($value !== '') {
					$values[$column] = $value;
				}
			}
			if (!empty($values)) {
				$rows[(int) $row_number] = $values;
			}
		}
		$merged = array();
		foreach ($xml->xpath('/x:worksheet/x:mergeCells/x:mergeCell') ?: array() as $merge) {
			$reference = (string) $merge->attributes()['ref'];
			if (preg_match('/\A([A-Z]+)([1-9][0-9]*):([A-Z]+)([1-9][0-9]*)\z/', $reference, $match) !== 1) throw new NakesImportException('merged_range_invalid');
			$merged[] = array('start_column'=>$this->columnNumber($match[1]),'start_row'=>(int)$match[2],'end_column'=>$this->columnNumber($match[3]),'end_row'=>(int)$match[4]);
		}
		return array('rows'=>$rows,'merged_ranges'=>$merged);
	}

	private function validatePackageStructure()
	{
		$required=array('[Content_Types].xml','_rels/.rels','xl/workbook.xml','xl/_rels/workbook.xml.rels');
		foreach($required as $entry)if(!isset($this->entries[$entry]))throw new NakesImportException('xlsx_entry_missing');
		foreach(array_keys($this->entries)as $name){
			$allowed=in_array($name,$required,true)
				||preg_match('#\AdocProps/(?:app|core)\.xml\z#',$name)===1
				||preg_match('#\Axl/(?:sharedStrings|styles)\.xml\z#',$name)===1
				||preg_match('#\Axl/theme/theme[1-9][0-9]*\.xml\z#',$name)===1
				||preg_match('#\Axl/worksheets/sheet[1-9]\.xml\z#',$name)===1
				||preg_match('#\Axl/worksheets/_rels/sheet[1-9]\.xml\.rels\z#',$name)===1
				||preg_match('#\Axl/printerSettings/printerSettings[1-9][0-9]*\.bin\z#',$name)===1;
			if(!$allowed)throw new NakesImportException('xlsx_part_not_allowed');
		}
		$content_types=$this->extract('[Content_Types].xml');
		if(preg_match('/(?:macroEnabled|vbaProject|oleObject|activeX|externalLink|embeddedPackage)/i',$content_types)===1)throw new NakesImportException('active_content_rejected');
		foreach(array_keys($this->entries)as $name)if(substr($name,-5)==='.rels')$this->validateRelationships($name);
	}

	private function validateRelationships($entry)
	{
		$xml=$this->xml($entry);$xml->registerXPathNamespace('r','http://schemas.openxmlformats.org/package/2006/relationships');
		$allowed_types=array('officeDocument','core-properties','extended-properties','worksheet','styles','theme','sharedStrings','printerSettings');
		foreach($xml->xpath('/r:Relationships/r:Relationship')?:array()as $relationship){
			$attributes=$relationship->attributes();$target=(string)$attributes['Target'];$type=(string)$attributes['Type'];
			if(strcasecmp((string)$attributes['TargetMode'],'External')===0||$target===''||strpos($target,"\0")!==false||strpos($target,'\\')!==false||preg_match('#^[a-z][a-z0-9+.-]*:#i',$target)===1)throw new NakesImportException('external_relationship_rejected');
			$suffix=substr($type,strrpos($type,'/')+1);if(!in_array($suffix,$allowed_types,true))throw new NakesImportException('relationship_type_rejected');
			$resolved=$this->relationshipTarget($entry,$target);if(!isset($this->entries[$resolved]))throw new NakesImportException('relationship_target_missing');
		}
	}

	private function workbookRelationships()
	{
		$xml=$this->xml('xl/_rels/workbook.xml.rels');$xml->registerXPathNamespace('r','http://schemas.openxmlformats.org/package/2006/relationships');$result=array();$targets=array();
		foreach($xml->xpath('/r:Relationships/r:Relationship')?:array()as $relationship){$attributes=$relationship->attributes();$type=(string)$attributes['Type'];if(substr($type,-10)!=='/worksheet')continue;$id=(string)$attributes['Id'];$target=$this->relationshipTarget('xl/_rels/workbook.xml.rels',(string)$attributes['Target']);if($id===''||preg_match('#\Axl/worksheets/sheet[1-9]\.xml\z#',$target)!==1||isset($result[$id])||isset($targets[$target])||!isset($this->entries[$target]))throw new NakesImportException('worksheet_relationship_invalid');$result[$id]=$target;$targets[$target]=true;}
		if(count($result)!==9)throw new NakesImportException('worksheet_relationship_invalid');
		return $result;
	}

	private function relationshipTarget($relationship_entry,$target)
	{
		if(strpos($relationship_entry,'/_rels/')!==false){$source=str_replace('/_rels/','/',$relationship_entry);$source=substr($source,0,-5);$base=dirname($source);}else{$base='';}
		$parts=array();$combined=strpos($target,'/')===0?ltrim($target,'/'):(($base===''?'':$base.'/').$target);
		foreach(explode('/',$combined)as $part){if($part===''||$part==='.')continue;if($part==='..'){if(!$parts)throw new NakesImportException('external_relationship_rejected');array_pop($parts);continue;}if(preg_match('/\A[A-Za-z0-9_.-]+\z/',$part)!==1)throw new NakesImportException('external_relationship_rejected');$parts[]=$part;}
		if(!$parts)throw new NakesImportException('external_relationship_rejected');return implode('/',$parts);
	}

	private function sharedStrings()
	{
		if (!isset($this->entries['xl/sharedStrings.xml'])) {
			return array();
		}
		$xml = $this->xml('xl/sharedStrings.xml');
		$xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$result = array();
		foreach ($xml->xpath('/x:sst/x:si') ?: array() as $item) {
			$result[] = $this->richText($item);
		}
		return $result;
	}

	private function richText($node)
	{
		if (!$node) {
			return '';
		}
		$node->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$parts = array();
		foreach ($node->xpath('.//x:t') ?: array() as $text) {
			$parts[] = (string) $text;
		}
		return implode('', $parts);
	}

	private function xml($entry)
	{
		$contents = $this->extract($entry);
		if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) throw new NakesImportException('xml_entity_rejected');
		$previous = libxml_use_internal_errors(true);
		$xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$xml) {
			throw new NakesImportException('invalid_xlsx_xml');
		}
		return $xml;
	}

	private function extract($name)
	{
		if (!isset($this->entries[$name])) {
			throw new NakesImportException('xlsx_entry_missing');
		}
		$entry = $this->entries[$name];
		$offset = $entry['offset'];
		if (substr($this->data, $offset, 4) !== "PK\x03\x04") {
			throw new NakesImportException('invalid_local_file_header');
		}
		$name_length = $this->u16($this->data, $offset + 26);
		$extra_length = $this->u16($this->data, $offset + 28);
		$local_name = substr($this->data, $offset + 30, $name_length);
		if ($local_name !== $name || $this->u16($this->data, $offset + 8) !== $entry['method'] || ($this->u16($this->data, $offset + 6) & 1) !== 0) throw new NakesImportException('local_header_mismatch');
		$start = $offset + 30 + $name_length + $extra_length;
		$compressed = substr($this->data, $start, $entry['compressed_size']);
		if (strlen($compressed) !== $entry['compressed_size']) {
			throw new NakesImportException('truncated_xlsx_entry');
		}
		if ($entry['method'] === 0) {
			$output = $compressed;
		} elseif ($entry['method'] === 8) {
			$output = gzinflate($compressed);
		} else {
			throw new NakesImportException('unsupported_xlsx_compression');
		}
		if (!is_string($output) || strlen($output) !== $entry['size'] || strtolower(hash('crc32b', $output)) !== $entry['crc']) {
			throw new NakesImportException('xlsx_entry_integrity_failed');
		}
		return $output;
	}

	private function readCentralDirectory($data)
	{
		$search_start = max(0, strlen($data) - 65557);
		$eocd = strrpos(substr($data, $search_start), "PK\x05\x06");
		if ($eocd === false) {
			throw new NakesImportException('invalid_xlsx_container');
		}
		$eocd += $search_start;
		if ($this->u16($data, $eocd + 4) !== 0 || $this->u16($data, $eocd + 6) !== 0) {
			throw new NakesImportException('multi_disk_xlsx_rejected');
		}
		$count = $this->u16($data, $eocd + 10);
		$count_on_disk = $this->u16($data, $eocd + 8);
		$central_size = $this->u32($data, $eocd + 12);
		$offset = $this->u32($data, $eocd + 16);
		if ($count < 1 || $count > 256 || $count_on_disk !== $count || $offset + $central_size !== $eocd) {
			throw new NakesImportException('xlsx_entry_count_rejected');
		}
		$entries = array();
		$total = 0;
		for ($i = 0; $i < $count; $i++) {
			if (substr($data, $offset, 4) !== "PK\x01\x02") {
				throw new NakesImportException('invalid_central_directory');
			}
			$flags = $this->u16($data, $offset + 8);
			$method = $this->u16($data, $offset + 10);
			$crc = bin2hex(strrev(substr($data, $offset + 16, 4)));
			$compressed_size = $this->u32($data, $offset + 20);
			$size = $this->u32($data, $offset + 24);
			$name_length = $this->u16($data, $offset + 28);
			$extra_length = $this->u16($data, $offset + 30);
			$comment_length = $this->u16($data, $offset + 32);
			$local_offset = $this->u32($data, $offset + 42);
			$name = substr($data, $offset + 46, $name_length);
			if (($flags & 1) !== 0 || !in_array($method,array(0,8),true) || $name === '' || strpos($name, "\0") !== false
				|| preg_match('#(?:^|/)\.\.?(/|$)|^/|\\\\|:#', $name) === 1 || isset($entries[$name])) {
				throw new NakesImportException('unsafe_xlsx_entry');
			}
			if ($size > self::MAX_ENTRY_BYTES || $compressed_size > self::MAX_ENTRY_BYTES) {
				throw new NakesImportException('xlsx_entry_too_large');
			}
			$total += $size;
			if ($total > self::MAX_TOTAL_BYTES) {
				throw new NakesImportException('xlsx_expansion_too_large');
			}
			$entries[$name] = array('name'=>$name,'method' => $method, 'crc' => $crc, 'compressed_size' => $compressed_size, 'size' => $size, 'offset' => $local_offset);
			$offset += 46 + $name_length + $extra_length + $comment_length;
		}
		if($offset!==$eocd)throw new NakesImportException('invalid_central_directory');
		return $entries;
	}

	private function columnNumber($letters)
	{
		$number = 0;
		foreach (str_split($letters) as $letter) {
			$number = ($number * 26) + (ord($letter) - 64);
		}
		return $number;
	}

	private function u16($data, $offset)
	{
		$value = unpack('vvalue', substr($data, $offset, 2));
		return (int) $value['value'];
	}

	private function u32($data, $offset)
	{
		$value = unpack('Vvalue', substr($data, $offset, 4));
		return (int) $value['value'];
	}
}
