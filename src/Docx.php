<?php

namespace Jupitern\Docx;
use Jupitern\Lib;

class Docx {

	private $templateFilePath;
	private $data = [];

	/**
	 * @return static
	 */
	public static function instance()
	{
		return new static();
	}


	public function setTemplate($templateFilePath)
	{
		$this->templateFilePath = $templateFilePath;
		return $this;
	}

	public function setData($data)
	{
		$this->data = $data;
		return $this;
	}


	public function save($outputFilePath = null)
	{
		if ( !file_exists( $this->templateFilePath ) ) {
			throw new \Exception("template file {$this->templateFilePath} not found");
		}

		if ($outputFilePath === null) {
			$outputFilePath = $this->templateFilePath;
		}
		elseif (!copy( $this->templateFilePath, $outputFilePath ) ) {
			throw new \Exception("error creating output file {$outputFilePath}");
		}

		$docx = new Lib\Docx( $outputFilePath );
		$docx->loadHeadersAndFooters();
		foreach ($this->data as $key => $value) {
			$docx->findAndReplace( $key, $value );
		}

		$docx->flush();
		return true;
	}

	/**
	 * Merge files in $docxFilesArray order and
	 * create new file $outDocxFilePath
	 * @param $docxFilesArray
	 * @param $outDocxFilePath
	 * @return int
	 */
	public static function merge( $docxFilesArray, $outDocxFilePath, $addPageBreak = false ) {
		if ( count($docxFilesArray) == 0 ) {
			// No files to merge
			return -1;
		}

		if ( substr( $outDocxFilePath, -5 ) != ".docx" ) {
			$outDocxFilePath .= ".docx";
		}

		if ( !copy( $docxFilesArray[0], $outDocxFilePath ) ) {
			// Cannot create file
			return -2;
		}

		$docx = new Lib\Docx( $outDocxFilePath );
		for( $i=1; $i<count( $docxFilesArray ); $i++ ) {
			$docx->addFile( $docxFilesArray[$i], "part".$i.".docx", "rId10".$i, $addPageBreak );
		}

		$docx->flush();

		return 0;
	}

}