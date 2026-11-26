<?php

namespace Jupitern\Docx\Lib;

class Docx
{

	public const BREAK_NONE = 'none';
	public const BREAK_PAGE = 'page';           // new page + new section, keep header and footer from each docx

	// Path to current docx file
	private string $docxPath;

	// Current _RELS data
	private mixed $docxRels;
	// Current DOCUMENT data
	private mixed $docxDocument;
	// Current CONTENT_TYPES data
	private mixed $docxContentTypes;

	private TbsZip $docxZip;

	private string $RELS_ZIP_PATH = "word/_rels/document.xml.rels";
	private string $DOC_ZIP_PATH = "word/document.xml";
	private string $CONTENT_TYPES_PATH = "[Content_Types].xml";
	private string $ALT_CHUNK_TYPE = "http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk";
	private string $ALT_CHUNK_CONTENT_TYPE = "application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml";

	// Array "zip path" => "content"
	private array $headerAndFootersArray = [];
	private array $addedZipNames = [];

	public function __construct(string $docxPath)
	{
		$this->docxPath = $docxPath;

		$this->docxZip = new TbsZip();
		$this->docxZip->Open($this->docxPath);

		$this->docxRels = $this->readContent($this->RELS_ZIP_PATH);
		$this->docxDocument = $this->readContent($this->DOC_ZIP_PATH);
		$this->docxContentTypes = $this->readContent($this->CONTENT_TYPES_PATH);
	}

	private function readContent(string $zipPath)
	{
		$content = $this->docxZip->FileRead($zipPath);

		return $content;
	}

	private function writeContent(mixed $content, string $zipPath): int
    {
		$this->docxZip->FileReplace($zipPath, $content, TBSZIP_STRING);
		return 0;
	}

	public function addFile(string $filePath, string $addPageBreak = self::BREAK_NONE, ?string $zipName = null, ?string $refID = null): void
	{
		if (!is_file($filePath)) {
			return;
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return;
		}

		$sectionProperties = null;
		if ($addPageBreak === self::BREAK_PAGE) {
			$sectionProperties = $this->readSourceSectionProperties($filePath);
		}
		$content = $this->removeHeaderFooterReferences($filePath, $content);

		$uniqueZipName = $this->resolveUniqueZipName($zipName ?? 'word/afchunk.docx');
		$uniqueRefId = $this->resolveUniqueRefId($refID ?? '');

		$this->docxZip->FileAdd($uniqueZipName, $content);
		$this->addedZipNames[] = $uniqueZipName;

		$this->addReference($uniqueZipName, $uniqueRefId);
		$this->addAltChunk($uniqueRefId, $addPageBreak);
		if ($sectionProperties !== null) {
			$this->replaceFinalSectionProperties($sectionProperties);
		}
		$this->addContentType($uniqueZipName);
	}

	private function removeHeaderFooterReferences(string $filePath, string $content): string
	{
		$sourceZip = new TbsZip();
		if (!$sourceZip->Open($filePath)) {
			throw new \RuntimeException("Unable to open DOCX file {$filePath}");
		}

		$tempFile = null;
		try {
			$documentXml = $sourceZip->FileRead($this->DOC_ZIP_PATH);
			if ($documentXml === false) {
				throw new \RuntimeException("Unable to read {$this->DOC_ZIP_PATH} from {$filePath}");
			}

			$document = new \DOMDocument();
			if (!$document->loadXML($documentXml, LIBXML_NONET)) {
				throw new \RuntimeException("Invalid document XML in {$filePath}");
			}

			$xpath = new \DOMXPath($document);
			$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
			$references = $xpath->query('//w:sectPr/w:headerReference | //w:sectPr/w:footerReference');
			if ($references === false) {
				throw new \RuntimeException("Unable to inspect section properties in {$filePath}");
			}

			if ($references->length === 0) {
				return $content;
			}

			$referenceNodes = [];
			foreach ($references as $reference) {
				$referenceNodes[] = $reference;
			}
			foreach ($referenceNodes as $reference) {
				$reference->parentNode->removeChild($reference);
			}

			$updatedDocumentXml = $document->saveXML();
			if ($updatedDocumentXml === false || $sourceZip->FileReplace($this->DOC_ZIP_PATH, $updatedDocumentXml, TBSZIP_STRING) === false) {
				throw new \RuntimeException("Unable to remove header/footer references from {$filePath}");
			}

			$tempFile = tempnam(sys_get_temp_dir(), 'docx');
			if ($tempFile === false || !$sourceZip->Flush(TBSZIP_FILE, $tempFile)) {
				throw new \RuntimeException("Unable to prepare DOCX chunk from {$filePath}");
			}

			$updatedContent = file_get_contents($tempFile);
			if ($updatedContent === false) {
				throw new \RuntimeException("Unable to read prepared DOCX chunk from {$filePath}");
			}

			return $updatedContent;
		} finally {
			$sourceZip->Close();
			if ($tempFile !== null && is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	private function readSourceSectionProperties(string $filePath): string
	{
		$sourceZip = new TbsZip();
		if (!$sourceZip->Open($filePath)) {
			throw new \RuntimeException("Unable to open DOCX file {$filePath}");
		}

		try {
			$documentXml = $sourceZip->FileRead($this->DOC_ZIP_PATH);
			$relationshipsXml = $sourceZip->FileRead($this->RELS_ZIP_PATH);
			$contentTypesXml = $sourceZip->FileRead($this->CONTENT_TYPES_PATH);
			if ($documentXml === false || $relationshipsXml === false || $contentTypesXml === false) {
				throw new \RuntimeException("Unable to read DOCX metadata from {$filePath}");
			}

			$document = new \DOMDocument();
			$relationships = new \DOMDocument();
			$contentTypes = new \DOMDocument();
			if (
				!$document->loadXML($documentXml, LIBXML_NONET) ||
				!$relationships->loadXML($relationshipsXml, LIBXML_NONET) ||
				!$contentTypes->loadXML($contentTypesXml, LIBXML_NONET)
			) {
				throw new \RuntimeException("Invalid DOCX metadata in {$filePath}");
			}

			$xpath = new \DOMXPath($document);
			$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
			$sectionProperties = $xpath->query('/w:document/w:body/w:sectPr');
			if ($sectionProperties === false || $sectionProperties->length === 0) {
				return '<w:sectPr><w:type w:val="nextPage" /></w:sectPr>';
			}

			$sectionProperties = $sectionProperties->item(0)->cloneNode(true);
			$referenceNodes = $xpath->query('./w:headerReference | ./w:footerReference', $sectionProperties);
			if ($referenceNodes === false) {
				throw new \RuntimeException("Unable to inspect section references in {$filePath}");
			}

			$copiedParts = [];
			$references = [];
			foreach ($referenceNodes as $referenceNode) {
				$references[] = $referenceNode;
			}
			foreach ($references as $referenceNode) {
				$relationId = $referenceNode->getAttributeNS(
					'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
					'id'
				);
				$relation = $this->findRelationship($relationships, $relationId);
				if ($relation === null) {
					throw new \RuntimeException("Unable to find relationship {$relationId} in {$filePath}");
				}

				$sourcePart = $this->resolvePackageTarget('word/document.xml', $relation->getAttribute('Target'));
				$copiedPart = $this->copyRelatedPart($sourceZip, $contentTypes, $sourcePart, $copiedParts);
				$newRelationId = $this->resolveUniqueRefId('');
				$this->addPackageRelationship(
					$newRelationId,
					$relation->getAttribute('Type'),
					$this->relativePackagePath('word/document.xml', $copiedPart)
				);
				$referenceNode->setAttributeNS(
					'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
					'r:id',
					$newRelationId
				);
			}

			$sectionXml = $document->saveXML($sectionProperties);
			if ($sectionXml === false) {
				throw new \RuntimeException("Unable to serialize section properties from {$filePath}");
			}

			return $sectionXml;
		} finally {
			$sourceZip->Close();
		}
	}

	private function findRelationship(\DOMDocument $relationships, string $id): ?\DOMElement
	{
		$xpath = new \DOMXPath($relationships);
		$xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
		$nodes = $xpath->query('/rel:Relationships/rel:Relationship[@Id="' . addslashes($id) . '"]');

		return $nodes !== false && $nodes->length > 0 ? $nodes->item(0) : null;
	}

	private function copyRelatedPart(TbsZip $sourceZip, \DOMDocument $sourceContentTypes, string $sourcePart, array &$copiedParts): string
	{
		if (isset($copiedParts[$sourcePart])) {
			return $copiedParts[$sourcePart];
		}

		$partContent = $sourceZip->FileRead($sourcePart);
		if ($partContent === false) {
			throw new \RuntimeException("Unable to read DOCX part {$sourcePart}");
		}

		$destinationPart = $this->resolveUniqueZipName($sourcePart);
		if ($this->docxZip->FileAdd($destinationPart, $partContent) === false) {
			throw new \RuntimeException("Unable to copy DOCX part {$sourcePart}");
		}
		$this->addedZipNames[] = $destinationPart;
		$copiedParts[$sourcePart] = $destinationPart;
		$this->copyContentType($sourceContentTypes, $sourcePart, $destinationPart);

		$sourceRelationshipsPath = $this->relationshipsPath($sourcePart);
		if (!$sourceZip->FileExists($sourceRelationshipsPath)) {
			return $destinationPart;
		}

		$sourceRelationshipsXml = $sourceZip->FileRead($sourceRelationshipsPath);
		$sourceRelationships = new \DOMDocument();
		if ($sourceRelationshipsXml === false || !$sourceRelationships->loadXML($sourceRelationshipsXml, LIBXML_NONET)) {
			throw new \RuntimeException("Invalid relationships for DOCX part {$sourcePart}");
		}

		$xpath = new \DOMXPath($sourceRelationships);
		$xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
		$relations = $xpath->query('/rel:Relationships/rel:Relationship');
		if ($relations === false) {
			throw new \RuntimeException("Unable to inspect relationships for DOCX part {$sourcePart}");
		}

		foreach ($relations as $relation) {
			if ($relation->getAttribute('TargetMode') === 'External') {
				continue;
			}

			$relatedSourcePart = $this->resolvePackageTarget($sourcePart, $relation->getAttribute('Target'));
			$relatedDestinationPart = $this->copyRelatedPart(
				$sourceZip,
				$sourceContentTypes,
				$relatedSourcePart,
				$copiedParts
			);
			$relation->setAttribute(
				'Target',
				$this->relativePackagePath($destinationPart, $relatedDestinationPart)
			);
		}

		$destinationRelationshipsPath = $this->relationshipsPath($destinationPart);
		$updatedRelationshipsXml = $sourceRelationships->saveXML();
		if ($updatedRelationshipsXml === false || $this->docxZip->FileAdd($destinationRelationshipsPath, $updatedRelationshipsXml) === false) {
			throw new \RuntimeException("Unable to copy relationships for DOCX part {$sourcePart}");
		}
		$this->addedZipNames[] = $destinationRelationshipsPath;

		return $destinationPart;
	}

	private function copyContentType(\DOMDocument $sourceContentTypes, string $sourcePart, string $destinationPart): void
	{
		$sourceXpath = new \DOMXPath($sourceContentTypes);
		$sourceXpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
		$sourceOverrides = $sourceXpath->query('/ct:Types/ct:Override');
		$contentType = null;
		if ($sourceOverrides !== false) {
			foreach ($sourceOverrides as $override) {
				if ($override->getAttribute('PartName') === '/' . $sourcePart) {
					$contentType = $override->getAttribute('ContentType');
					break;
				}
			}
		}

		$destination = new \DOMDocument();
		if (!$destination->loadXML($this->docxContentTypes, LIBXML_NONET)) {
			throw new \RuntimeException('Invalid content types in the output DOCX');
		}
		$destinationXpath = new \DOMXPath($destination);
		$destinationXpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');

		if ($contentType !== null) {
			$destinationOverrides = $destinationXpath->query('/ct:Types/ct:Override');
			if ($destinationOverrides !== false) {
				foreach ($destinationOverrides as $override) {
					if ($override->getAttribute('PartName') === '/' . $destinationPart) {
						return;
					}
				}
			}
			$override = $destination->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override');
			$override->setAttribute('PartName', '/' . $destinationPart);
			$override->setAttribute('ContentType', $contentType);
			$destination->documentElement->appendChild($override);
		} else {
			$extension = strtolower(pathinfo($sourcePart, PATHINFO_EXTENSION));
			$sourceDefaults = $sourceXpath->query('/ct:Types/ct:Default');
			$extensionType = null;
			if ($sourceDefaults !== false) {
				foreach ($sourceDefaults as $default) {
					if (strtolower($default->getAttribute('Extension')) === $extension) {
						$extensionType = $default->getAttribute('ContentType');
						break;
					}
				}
			}
			if ($extensionType !== null) {
				$destinationDefaults = $destinationXpath->query('/ct:Types/ct:Default');
				$hasExtension = false;
				if ($destinationDefaults !== false) {
					foreach ($destinationDefaults as $default) {
						if (strtolower($default->getAttribute('Extension')) === $extension) {
							$hasExtension = true;
							break;
						}
					}
				}
				if (!$hasExtension) {
					$default = $destination->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Default');
					$default->setAttribute('Extension', $extension);
					$default->setAttribute('ContentType', $extensionType);
					$destination->documentElement->appendChild($default);
				}
			}
		}

		$updatedContentTypes = $destination->saveXML();
		if ($updatedContentTypes === false) {
			throw new \RuntimeException('Unable to update content types in the output DOCX');
		}
		$this->docxContentTypes = $updatedContentTypes;
	}

	private function addPackageRelationship(string $id, string $type, string $target): void
	{
		$relationship = '<Relationship Id="' . htmlspecialchars($id, ENT_XML1) . '" Type="' . htmlspecialchars($type, ENT_XML1) . '" Target="' . htmlspecialchars($target, ENT_XML1) . '"/>';
		$position = strpos($this->docxRels, '</Relationships>');
		if ($position !== false) {
			$this->docxRels = substr_replace($this->docxRels, $relationship, $position, 0);
		}
	}

	private function resolvePackageTarget(string $sourcePart, string $target): string
	{
		$target = rawurldecode(str_replace('\\', '/', $target));
		$path = str_starts_with($target, '/')
			? ltrim($target, '/')
			: dirname($sourcePart) . '/' . $target;

		$segments = [];
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}
			if ($segment === '..') {
				array_pop($segments);
				continue;
			}
			$segments[] = $segment;
		}

		return implode('/', $segments);
	}

	private function relativePackagePath(string $sourcePart, string $targetPart): string
	{
		$sourceDirectory = explode('/', dirname($sourcePart));
		$targetSegments = explode('/', $targetPart);
		while ($sourceDirectory && $targetSegments && $sourceDirectory[0] === $targetSegments[0]) {
			array_shift($sourceDirectory);
			array_shift($targetSegments);
		}

		return implode('/', array_merge(array_fill(0, count($sourceDirectory), '..'), $targetSegments));
	}

	private function relationshipsPath(string $partPath): string
	{
		return dirname($partPath) . '/_rels/' . basename($partPath) . '.rels';
	}

	private function addReference(string $zipName, string $refID): void
	{
		$cleanZipName = ltrim($zipName, '/');
		$relXmlString = '<Relationship Target="../' . $cleanZipName . '" Type="' . $this->ALT_CHUNK_TYPE . '" Id="' . $refID . '"/>';

		$p = strpos($this->docxRels, '</Relationships>');
		if ($p !== false) {
			$this->docxRels = substr_replace($this->docxRels, $relXmlString, $p, 0);
		}
	}

	private function addAltChunk(string $refID, string $pageBreak = self::BREAK_NONE): void
	{
		switch ($pageBreak) {
			case self::BREAK_PAGE:
				$sectionProperties = $this->prepareInitialSectionProperties();
				$pagebreak = '<w:p><w:pPr>' . $sectionProperties . '</w:pPr></w:p>';
				break;
			default:
				$pagebreak = '';
				break;
		}

		$xmlItem = $pagebreak . '<w:altChunk r:id="' . $refID . '"/>';

		$p = strrpos($this->docxDocument, '<w:sectPr');
		if ($p === false) {
			$p = strpos($this->docxDocument, '</w:body>');
		}
		if ($p !== false) {
			$this->docxDocument = substr_replace($this->docxDocument, $xmlItem, $p, 0);
		}
	}

	private function prepareInitialSectionProperties(): string
	{
		$document = new \DOMDocument();
		if (!$document->loadXML($this->docxDocument, LIBXML_NONET)) {
			throw new \RuntimeException('Invalid document XML in the output DOCX');
		}

		$xpath = new \DOMXPath($document);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
		$sectionProperties = $xpath->query('/w:document/w:body/w:sectPr');
		if ($sectionProperties === false || $sectionProperties->length === 0) {
			return '<w:sectPr><w:type w:val="nextPage" /></w:sectPr>';
		}

		$finalSectionProperties = $sectionProperties->item(0);
		$sectionBreakProperties = $finalSectionProperties->cloneNode(true);
		$types = $xpath->query('./w:type', $sectionBreakProperties);
		if ($types === false) {
			throw new \RuntimeException('Unable to inspect section properties in the output DOCX');
		}

		if ($types->length > 0) {
			$types->item(0)->setAttributeNS(
				'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
				'w:val',
				'nextPage'
			);
		} else {
			$type = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:type');
			$type->setAttributeNS(
				'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
				'w:val',
				'nextPage'
			);

			$firstPageSetting = null;
			foreach ($sectionBreakProperties->childNodes as $child) {
				if ($child instanceof \DOMElement && !in_array($child->localName, ['headerReference', 'footerReference', 'footnotePr', 'endnotePr'], true)) {
					$firstPageSetting = $child;
					break;
				}
			}
			if ($firstPageSetting === null) {
				$sectionBreakProperties->appendChild($type);
			} else {
				$sectionBreakProperties->insertBefore($type, $firstPageSetting);
			}
		}

		$sectionBreakXml = $document->saveXML($sectionBreakProperties);
		if ($sectionBreakXml === false) {
			throw new \RuntimeException('Unable to serialize section properties in the output DOCX');
		}

		return $sectionBreakXml;
	}

	private function replaceFinalSectionProperties(string $sectionXml): void
	{
		$document = new \DOMDocument();
		$sourceSection = new \DOMDocument();
		if (
			!$document->loadXML($this->docxDocument, LIBXML_NONET) ||
			!$sourceSection->loadXML($sectionXml, LIBXML_NONET)
		) {
			throw new \RuntimeException('Invalid section properties while merging DOCX files');
		}

		$xpath = new \DOMXPath($document);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
		$bodies = $xpath->query('/w:document/w:body');
		$finalSectionProperties = $xpath->query('/w:document/w:body/w:sectPr');
		if ($bodies === false || $bodies->length === 0 || $finalSectionProperties === false) {
			throw new \RuntimeException('Unable to locate the document body while merging DOCX files');
		}

		foreach ($finalSectionProperties as $existingSectionProperties) {
			$existingSectionProperties->parentNode->removeChild($existingSectionProperties);
		}

		$body = $bodies->item(0);
		$body->appendChild($document->importNode($sourceSection->documentElement, true));

		$updatedDocumentXml = $document->saveXML();
		if ($updatedDocumentXml === false) {
			throw new \RuntimeException('Unable to update final section properties in the DOCX');
		}

		$this->docxDocument = $updatedDocumentXml;
	}

	private function resolveUniqueRefId(string $preferredId): string
	{
		preg_match_all('/\bId="(rId\d+)"/', $this->docxRels, $matches);
		$existingIds = $matches[1] ?? [];

		if ($preferredId !== '' && !in_array($preferredId, $existingIds, true)) {
			return $preferredId;
		}

		$maxNum = 0;
		foreach ($existingIds as $id) {
			$num = (int) substr($id, 3);
			if ($num > $maxNum) {
				$maxNum = $num;
			}
		}

		do {
			$maxNum++;
			$candidate = 'rId' . $maxNum;
		} while (in_array($candidate, $existingIds, true));

		return $candidate;
	}

	private function resolveUniqueZipName(string $preferredZipName): string
	{
		$baseName = trim($preferredZipName) !== '' ? ltrim($preferredZipName, '/') : 'word/afchunk.docx';

		if (!$this->docxZip->FileExists($baseName) && !in_array($baseName, $this->addedZipNames, true)) {
			return $baseName;
		}

		$pathInfo = pathinfo($baseName);
		$dir = (!empty($pathInfo['dirname']) && $pathInfo['dirname'] !== '.') ? $pathInfo['dirname'] . '/' : '';
		$filename = $pathInfo['filename'] ?? 'afchunk';
		$ext = !empty($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '.docx';

		$counter = 1;
		do {
			$candidate = $dir . $filename . '_' . $counter . $ext;
			$counter++;
		} while ($this->docxZip->FileExists($candidate) || in_array($candidate, $this->addedZipNames, true));

		return $candidate;
	}

	private function addContentType(string $zipName)
	{
		$xmlItem = '<Override ContentType="' . $this->ALT_CHUNK_CONTENT_TYPE . '" PartName="/' . $zipName . '"/>';

		$p = strpos($this->docxContentTypes, '</Types>');
		$this->docxContentTypes = substr_replace($this->docxContentTypes, $xmlItem, $p, 0);
	}

	public function loadHeadersAndFooters()
	{
		$relsXML = new \SimpleXMLElement($this->docxRels);
		foreach ($relsXML as $rel) {
			if ($rel["Type"] == "http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" ||
				$rel["Type"] == "http://schemas.openxmlformats.org/officeDocument/2006/relationships/header"
			) {
				$path = "word/" . $rel["Target"];
				$this->headerAndFootersArray[$path] = $this->readContent($path);
			}
		}
	}

	public function findAndReplace(array|string $key, array|string $value)
	{
		// Search/Replace in document
		$this->docxDocument = str_replace($key, $value, $this->docxDocument);

		// Search/Replace in footers and headers
		foreach ($this->headerAndFootersArray as $path => $content) {
			$this->headerAndFootersArray[$path] = str_replace($key, $value, $content);
		}
	}

	public function flush()
	{
		// Save RELS data
		$this->writeContent($this->docxRels, $this->RELS_ZIP_PATH);
		// Save DOCUMENT data
		$this->writeContent($this->docxDocument, $this->DOC_ZIP_PATH);
		// Save CONTENT TYPES data
		$this->writeContent($this->docxContentTypes, $this->CONTENT_TYPES_PATH);
		// Save footers and headers
		foreach ($this->headerAndFootersArray as $path => $content) {
			$this->writeContent($content, $path);
		}

		// Save the merge into a third file
		// We cannot save to current file because it damages ZIP file
		$tempFile = tempnam(dirname($this->docxPath), "dm");

		$this->docxZip->Flush(TBSZIP_FILE, $tempFile);

		// Replace current file with tempFile content
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			copy($tempFile, $this->docxPath);
			unlink($tempFile);
		} else {
			rename($tempFile, $this->docxPath);
		}
	}

}
