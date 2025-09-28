<?php

namespace XFMG\Exif;

class Formatter implements \ArrayAccess
{
	/**
	 * @var \XFMG\Entity\MediaItem
	 */
	protected $mediaItem;

	protected $exifData;

	public function __construct(\XFMG\Entity\MediaItem $mediaItem, array $exifData)
	{
		$this->prepareExifData($exifData);
		$this->mediaItem = $mediaItem;
	}

	protected function prepareExifData(array $exifData)
	{
		$prepared = [];
		foreach ($exifData AS $data)
		{
			$prepared = array_merge($prepared, $data);
		}
		$this->exifData = $prepared;
	}

	public function offsetExists($offset)
	{
		return (isset($this->exifData[$offset]));
	}

	public function offsetGet($offset)
	{
		switch ($offset)
		{
			case 'device':
				if (!$this->Make || !$this->Model)
				{
					return null;
				}
				return $this->Make . ' ' . $this->Model;

			case 'aperture':
				$f = $this->divideValue($this->FNumber);
				if (!$f)
				{
					return null;
				}
				return \XF::escapeString('ƒ/' . $f);

			case 'focal':
				$fLength = $this->divideValue($this->FocalLength);
				if (!$fLength)
				{
					return null;
				}
				return \XF::escapeString(\XF::language()->numberFormat($fLength,1) . ' mm');

			case 'exposure':
				if (!$this->ExposureTime)
				{
					return null;
				}

				$exposure = $this->divideValue($this->ExposureTime);

				if ($exposure < 1)
				{

					return $this->simplifyValue($this->ExposureTime);
				}

				return \XF::phrase('xfmg_x_seconds', ['count' => $exposure]);

			case 'iso':
				return intval($this->ISOSpeedRatings);

			case 'flash':
				$flash = $this->Flash;
				switch ($flash)
				{
					case 8:
					case 9:
					case 16:
					case 24:
					case 25:
						return \XF::phrase('xfmg_exif.flash_' . strval($flash));
					default:
						return \XF::phrase('xfmg_exif.flash_' . strval($flash))->render('html', ['nameOnInvalid' => false]);
				}

			case 'date_taken':
				$dateTime = $this->DateTimeOriginal;
				if (!$dateTime)
				{
					$dateTime = $this->DateTime;
				}

				if (!$dateTime)
				{
					return null;
				}

				try
				{
					$date = new \DateTime($dateTime);
				}
				catch (\Exception $e)
				{
					return null;
				}
				return \XF::language()->date($date, 'D, d F Y g:i A');

			case 'file_size':
				if (!$this->FileSize)
				{
					return null;
				}
				return \XF::language()->fileSizeFormat($this->FileSize);

			case 'dimensions':
				if ($this->Width && $this->Height)
				{
					return $this->Width . 'px x ' . $this->Height . 'px';
				}
				break;
		}

		return $this->offsetExists($offset) ? \XF::escapeString($this->exifData[$offset]) : null;
	}

	protected function divideValue($value)
	{
		$value = explode('/', $value, 2);
		if (!$value || $value[0] == 0 || $value[1] == 0)
		{
			return null;
		}
		return strval($value[0] / $value[1]);
	}

	protected function simplifyValue($value)
	{
		$value = explode('/', $value, 2);
		if (!$value || $value[0] == 0 || $value[1] == 0)
		{
			return null;
		}
		return implode('/', [1, round($value[1] / $value[0])]);
	}

	public function offsetSet($offset, $value)
	{
		$this->exifData[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->exifData[$offset]);
	}

	public function __get($name)
	{
		return $this->offsetGet($name);
	}

	public function __set($name, $value)
	{
		$this->offsetSet($name, $value);
	}

	public function toArray()
	{
		return $this->exifData;
	}
}