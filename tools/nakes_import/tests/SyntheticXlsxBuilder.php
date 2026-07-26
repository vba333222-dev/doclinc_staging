<?php

class SyntheticXlsxBuilder
{
	public function build(array $options = array())
	{
		$sheet_names = array('Cibeber', 'Citangkil', 'Cilegon', 'Purwakarta', 'Pulomerak', 'Jombang', 'Grogol', 'Ciwandan', 'Citangkil II');
		$relationships = array();
		$sheets = array();
		$entries = array(
			'[Content_Types].xml' => $this->contentTypes(!empty($options['macro'])),
			'_rels/.rels' => $this->relationships(array(array('rId1', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument', 'xl/workbook.xml', null))),
		);
		foreach ($sheet_names as $index => $name) {
			$id = $index + 1;
			$state = !empty($options['hidden']) && $id === 1 ? ' state="hidden"' : '';
			$sheets[] = '<sheet name="' . $name . '" sheetId="' . $id . '" r:id="rId' . $id . '"' . $state . '/>';
			$relationships[] = array('rId' . $id, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet', 'worksheets/sheet' . $id . '.xml', null);
			$entries['xl/worksheets/sheet' . $id . '.xml'] = $this->worksheet($options, $id);
		}
		if (!empty($options['external'])) {
			$relationships[] = array('rIdExternal', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/externalLink', 'https://invalid.example.test/book.xlsx', 'External');
		}
		$entries['xl/workbook.xml'] = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . implode('', $sheets) . '</sheets></workbook>';
		$entries['xl/_rels/workbook.xml.rels'] = $this->relationships($relationships);
		if (!empty($options['invalid_shared'])) {
			$entries['xl/sharedStrings.xml'] = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="1" uniqueCount="1"><si><t>only</t></si></sst>';
		}
		if (!empty($options['macro'])) $entries['xl/vbaProject.bin'] = 'synthetic-active-content';
		if (!empty($options['unsafe_entry'])) $entries['../unsafe.xml'] = '<x/>';
		return $this->zip($entries);
	}

	private function worksheet(array $options, $id)
	{
		$cell = '<c r="A1" t="inlineStr"><is><t>synthetic</t></is></c>';
		if ($id === 1 && !empty($options['formula'])) $cell = '<c r="A1"><f>1+1</f><v>2</v></c>';
		if ($id === 1 && !empty($options['error'])) $cell = '<c r="A1" t="e"><v>#VALUE!</v></c>';
		if ($id === 1 && !empty($options['invalid_shared'])) $cell = '<c r="A1" t="s"><v>7</v></c>';
		if ($id === 1 && !empty($options['scientific'])) $cell = '<c r="A1" t="n"><v>8.1E+10</v></c>';
		$merge = $id === 1 && !empty($options['merge']) ? '<mergeCells count="1"><mergeCell ref="A1:E1"/></mergeCells>' : '';
		return '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">' . $cell . '</row></sheetData>' . $merge . '</worksheet>';
	}

	private function relationships(array $relationships)
	{
		$xml = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		foreach ($relationships as $relationship) {
			$xml .= '<Relationship Id="' . $relationship[0] . '" Type="' . $relationship[1] . '" Target="' . $relationship[2] . '"';
			if ($relationship[3] !== null) $xml .= ' TargetMode="' . $relationship[3] . '"';
			$xml .= '/>';
		}
		return $xml . '</Relationships>';
	}

	private function contentTypes($macro)
	{
		$workbook_type = $macro ? 'application/vnd.ms-excel.sheet.macroEnabled.main+xml' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';
		return '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="' . $workbook_type . '"/></Types>';
	}

	private function zip(array $entries)
	{
		$body = '';
		$central = '';
		foreach ($entries as $name => $data) {
			$offset = strlen($body);
			$length = strlen($data);
			$crc = hexdec(hash('crc32b', $data));
			$body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $length, $length, strlen($name), 0) . $name . $data;
			$central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $length, $length, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
		}
		$count = count($entries);
		return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
	}
}
