<?php

namespace Jupitern\Docx;

use Jupitern\Docx\Lib as DocxLib;

class DocxMerge
{

	private $files = [];

	/**
	 * @return static
	 */
	public static function instance()
	{
		return new static();
	}


	/**
	 * @param $filePath
	 * @return $this
	 */
	public function addFile($filePath)
	{
		$this->files[] = $filePath;
		return $this;
	}

	/**
	 * @param array $filesPathArr
	 * @return $this
	 */
	public function addFiles(array $filesPathArr)
	{
		$this->files = array_merge($this->files, $filesPathArr);
		return $this;
	}


	/**
	 * @param $outDocxFilePath
	 * @param bool $addPageBreak
	 * @return bool
	 * @throws \Exception
	 */
	public function save($outDocxFilePath, $addPageBreak = false)
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