<?php

namespace Jupitern\Docx;

use Jupitern\Docx\Lib as DocxLib;

class DocxMerge
{

	public const BREAK_NONE = 'none';
	public const BREAK_PAGE = 'page';           // new page, keep header and footer from base docx
	public const BREAK_SECTION = 'section';     // new page + new section, keep header and footer from orignal docs

	private array $files = [];

	/**
	 * @return self
	 */
	public static function instance(): DocxMerge
    {
		return new self();
	}


    /**
     * @param string $filePath
     * @return self
     */
	public function addFile(string $filePath): DocxMerge
    {
		$this->files[] = $filePath;
		return $this;
	}

	/**
	 * @param array $filesPathArr
	 * @return self
	 */
	public function addFiles(array $filesPathArr): DocxMerge
    {
		foreach ($filesPathArr as $filePath) {
			$this->files[] = $filePath;
		}

		return $this;
	}


    /**
     * @param string $outDocxFilePath
     * @param string $addPageBreak
     * @return bool
     * @throws \Exception
     */
	public function save(string $outDocxFilePath, string $addPageBreak = self::BREAK_NONE): bool
    {
		if (!count($this->files)) {
			// No files to merge
			return false;
		}

		if (!copy($this->files[0], $outDocxFilePath)) {
			// Cannot create file
			throw new \Exception("error saving output file {$outDocxFilePath}");
		}

		$docx = new DocxLib\Docx($outDocxFilePath);
		for ($i = 1; $i < count($this->files); $i++) {
			$docx->addFile($this->files[$i], "part" . $i . ".docx", "rId10" . $i, $addPageBreak);
		}

		$docx->flush();
		return true;
	}

}