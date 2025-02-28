<?php

namespace Jupitern\Docx;
use Jupitern\Docx\Lib as DocxLib;

class Docx
{

	private string $templateFilePath;
	private array $data = [];

	/**
	 * @return static
	 */
	public static function instance(): Docx
    {
		return new static();
	}


    /**
     * @param string $templateFilePath
     * @return $this
     */
	public function setTemplate(string $templateFilePath): Docx
    {
		$this->templateFilePath = $templateFilePath;
		return $this;
	}

    /**
     * @param array $data
     * @return $this
     */
	public function setData(array $data): Docx
    {
		$this->data = $data;
		return $this;
	}


    /**
     * @param string|null $outputFilePath
     * @return bool
     * @throws \Exception
     */
	public function save(?string $outputFilePath = null): bool
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