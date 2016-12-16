<?php

namespace Jupitern\Docx;

use Jupitern\Docx\Lib as DocxLib;

class Docx
{

	private $templateFilePath;
	private $data = [];

	/**
	 * @return static
	 */
	public static function instance()
	{
		return new static();
	}


	/**
	 * @param $templateFilePath
	 * @return $this
	 */
	public function setTemplate($templateFilePath)
	{
		$this->templateFilePath = $templateFilePath;
		return $this;
	}

	/**
	 * @param $data
	 * @return $this
	 */
	public function setData($data)
	{
		$this->data = $data;
		return $this;
	}


	/**
	 * @param null $outputFilePath
	 * @return bool
	 * @throws \Exception
	 */
	public function save($outputFilePath = null)
	{
		if (!file_exists($this->templateFilePath)) {
			throw new \Exception("template file {$this->templateFilePath} not found");
		}

		if ($outputFilePath === null) {
			$outputFilePath = $this->templateFilePath;
		} elseif (!copy($this->templateFilePath, $outputFilePath)) {
			throw new \Exception("error creating output file {$outputFilePath}");
		}

		$docx = new DocxLib\Docx($outputFilePath);
		$docx->loadHeadersAndFooters();
		foreach ($this->data as $key => $value) {
			$docx->findAndReplace($key, $value);
		}

		$docx->flush();
		return true;
	}

}