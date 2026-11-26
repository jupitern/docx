<?php
/**
 * Created by PhpStorm.
 * User: krustnic
 * Date: 05.02.14
 * Time: 11:59
 */
namespace Jupitern\Docx\Lib;

class Docx
{

	public const BREAK_NONE = 'none';
	public const BREAK_PAGE = 'page';           // new page, keep header and footer from base docx
	public const BREAK_SECTION = 'section';     // new page + new section, keep header and footer from orignal docs

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

	public function addFile(string $filePath, mixed $zipName, string $refID, string $addPageBreak = self::BREAK_NONE): void
	{
		$content = file_get_contents($filePath);
		$this->docxZip->FileAdd($zipName, $content);

		$this->addReference($zipName, $refID);
		$this->addAltChunk($refID, $addPageBreak);
		$this->addContentType($zipName);
	}

	private function addReference(string $zipName, string $refID)
	{
		$relXmlString = '<Relationship Target="../' . $zipName . '" Type="' . $this->ALT_CHUNK_TYPE . '" Id="' . $refID . '"/>';

		$p = strpos($this->docxRels, '</Relationships>');
		$this->docxRels = substr_replace($this->docxRels, $relXmlString, $p, 0);
	}

	private function addAltChunk(string $refID, string $pageBreak = self::BREAK_NONE)
	{
		switch ($pageBreak) {
			case self::BREAK_PAGE:
				$pagebreak = '<w:p><w:r><w:br w:type="page" /></w:r></w:p>';
				break;
			case self::BREAK_SECTION:
				$pagebreak = '<w:p><w:pPr><w:sectPr><w:type w:val="nextPage" /></w:sectPr></w:pPr></w:p>';
				break;
			default:
				$pagebreak = '';
				break;
		}
		$xmlItem = $pagebreak . '<w:altChunk r:id="' . $refID . '"/>';

		$p = strpos($this->docxDocument, '</w:body>');
		$this->docxDocument = substr_replace($this->docxDocument, $xmlItem, $p, 0);
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
