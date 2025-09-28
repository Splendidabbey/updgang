<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;

class MP3Detector extends AbstractService
{
	protected $path;

	public function __construct(\XF\App $app, $filePath)
	{
		parent::__construct($app);
		$this->path = $filePath;
	}

	protected function verifyFile(&$error = null)
	{
		if (!file_exists($this->path) || !is_readable($this->path))
		{
			$error = 'MP3 file does not exist or cannot be read.';
			return false;
		}

		return true;
	}

	public function isValidMP3()
	{
		if (!$this->verifyFile($error))
		{
			throw new \XF\PrintableException($error);
		}

		$fp = @fopen($this->path, 'rb');
		if ($fp)
		{
			// Fetch the first bytes of the file, overfetching and trimming is necessary as
			// some files have padding. Supports MP3s with or without an ID3v2 container.
			$firstBytes = strtoupper(bin2hex(ltrim(fread($fp, 256000))));

			if (strpos($firstBytes, '494433') === 0 // indicates an ID3v2 container
				|| strpos($firstBytes, 'FFF') === 0 // indicates MP3 header without ID3v2
			)
			{
				return true;
			}
		}

		return false;
	}
}