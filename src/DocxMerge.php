<?php

namespace Jupitern\Docx;

use Jupitern\Docx\Lib as DocxLib;

class DocxMerge
{

	public const BREAK_NONE = 'none';
	public const BREAK_PAGE = 'page'; // new page + new section, keep header and footer from each docx

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
		$this->files = array_merge($this->files, $filesPathArr);

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
		foreach (array_slice($this->files, 1) as $filePath) {
			$docx->addFile($filePath, $addPageBreak);
		}

		$docx->flush();
		
		return true;
	}

}