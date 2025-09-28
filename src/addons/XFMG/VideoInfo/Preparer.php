<?php

namespace XFMG\VideoInfo;

use XFMG\VideoInfo\Box\AbstractBox;

/**
 * Class which takes a file, and determines if the file contains video, audio (or both)
 * and further ascertains which codec the audio and video is encoded with.
 *
 * Code based on php-mp4info by Tommy Lacroix modified for XenForo Media Gallery.
 *
 * @author		Tommy Lacroix <lacroix.tommy@gmail.com>
 * @copyright	Copyright (c) 2006-2009 Tommy Lacroix
 * @license		LGPL version 3, http://www.gnu.org/licenses/lgpl.html
 */
class Preparer
{
	protected $file;

	protected $result;

	public function __construct($filename)
	{
		$fp = fopen($filename, 'rb');
		if (!$fp)
		{
			throw new \LogicException('Cannot open file: ' . $filename);
		}

		$this->setFile($fp);
	}

	protected function setFile($fp)
	{
		$this->file = $fp;
	}

	/**
	 * Gets information from an MP4 file.
	 *
	 * @return Result
	 */
	public function getInfo()
	{
		$boxes = [];

		$fp = $this->file;
		while ($box = $this->fromStream($fp))
		{
			$boxes[] = $box;
		}
		
		// Close
		fclose($fp);

		return $this->getInfoFromBoxes($boxes);
	}

	/**
	 * Gets information from a subset of MP4 boxes.
	 *
	 * @param AbstractBox[] $boxes
	 * @return Result
	 */
	public function getInfoFromBoxes($boxes)
	{
		if ($this->result === null)
		{
			$this->result = new Result();
		}

		if (!$boxes)
		{
			return $this->result;
		}

		foreach ($boxes AS &$box)
		{
			switch ($box->getBoxTypeStr())
			{
				case 'stsd':

					/** @var \XFMG\VideoInfo\Box\Stsd $box */
					$values = $box->getValues();

					foreach ($values AS $code => $data)
					{
						switch ($code)
						{
							case 'mp3':
								$this->result->audioCodec = 'mp3';
								$this->result->hasAudio = true;
								break;

							case 'mp4a':
							case 'mp4s':
								$this->result->audioCodec = 'aac';
								$this->result->hasAudio = true;
								break;

							case 'amf0':
								break;

							case 'mp4v':
								$this->result->videoCodec = 'mp4v';
								$this->result->hasVideo = true;
								break;

							case 'avc1':
							case 'h264':
							case 'H264':
								$this->result->videoCodec = 'h264';
								$this->result->hasVideo = true;
								break;

							default:
								break;
						}
					}
					break;
			}

			if ($box->hasChildren())
			{
				$this->getInfoFromBoxes($box->children());
			}
		}

		return $this->result;
	}

	/**
	 * Creates a box object from a file stream.
	 *
	 * @param $fp
	 * @param bool $parent
	 *
	 * @return bool|AbstractBox
	 */
	public function fromStream($fp, $parent = false)
	{
		// Get box header
		$buf = fread($fp,8);
		if (strlen($buf) < 8)
		{
			return false;
		}

		$ar = unpack('NtotalSize/NboxType',$buf);

		if ($ar['totalSize'] == 1) // larger than 4GB
		{
			$buf = fread($fp,8);
			$ar2 = unpack('N2extSize',$buf);

			if ($ar2['extSize1'] > 0)
			{
				throw new \Exception('Extended size not supported');
			}
			else
			{
				$ar['totalSize'] = $ar2['extSize2'];
			}
		}

		if ($this->isBoxSkippable($ar['boxType']))
		{
			fseek($fp, $ar['totalSize'] - 8, SEEK_CUR);
			return $this->fromStream($fp, $parent);
		}

		if ($ar['totalSize'] > 0)
		{
			if ($ar['totalSize'] < 256 * 1024)
			{
				if ($ar['totalSize'] - 8 > 0)
				{
					$data = fread($fp, $ar['totalSize'] - 8);
				}
				else
				{
					$data = '';
				}
			}
			else
			{
				$data = $fp;
			}
		}
		else
		{
			$data = '';
		}

		$box = AbstractBox::create($ar['totalSize'], $ar['boxType'], $data, $this, $parent);
		return $box;
	}

	/**
	 * Creates a box object from a string.
	 *
	 * @param $data
	 * @param bool $parent
	 *
	 * @return AbstractBox
	 */
	public function fromString(&$data, $parent = false)
	{
		if (strlen($data) < 8)
		{
			throw new \LogicException('Not enough data, need at least 8 bytes!');
		}

		$ar = unpack('NtotalSize/NboxType',$data);
		if ($ar['totalSize'] == 1) // larger than 4GB
		{
			$ar2 = unpack('N2extSize', substr($data,8));
			if ($ar2['extSize1'] > 0)
			{
				throw new \LogicException('Extended size not supported');
			}
			else
			{
				$ar['totalSize'] = $ar2['extSize2'];
			}
			$skip = 8;
		}
		else
		{
			$skip = 0;
		}

		if ($this->isBoxSkippable($ar['boxType']))
		{
			$data = substr($data, $ar['totalSize']);
			return $this->fromString($data, $parent);
		}

		$box = AbstractBox::create($ar['totalSize'], $ar['boxType'], substr($data, 8 + $skip), $this, $parent);
		if ($box)
		{
			$data = substr($data, $box->getTotalSize());
		}

		return $box;
	}

	/**
	 * Check if we need to skip a box based on type
	 *
	 * @param $boxType
	 * @return bool
	 */
	protected function isBoxSkippable($boxType)
	{
		switch ($boxType)
		{
			case 0x73747364: // stsd
			case 0x7374626c: // stbl
			case 0x6d696e66: // minf
			case 0x6d646961: // mdia
			case 0x7472616b: // trak
			case 0x6d6f6f76: // moov
				return false;
			default: // everything else, skip it.
				return true;
		}
	}
}
