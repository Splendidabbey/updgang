<?php

namespace XFMG\VideoInfo\Box;

use XFMG\VideoInfo\Preparer;

class Container extends AbstractBox
{
	public function __construct($totalSize, $boxType, $data, Preparer $preparer, $parent)
	{
		parent::__construct($totalSize, $boxType, false, $preparer, $parent);

		if (is_string($data))
		{
			while ($data != '')
			{
				try
				{
					$box = $this->preparer->fromString($data, $this);
					if (!$box instanceof AbstractBox)
					{
						break;
					}
				}
				catch (\Exception $e)
				{
					break;
				}
			}
		}
		else
		{
			do
			{
				try
				{
					$box = $this->preparer->fromStream($data, $this);
					if (!$box instanceof AbstractBox)
					{
						break;
					}
				}
				catch (\Exception $e)
				{
					break;
				}
			}
			while ($box !== false);
		}
	}

	/**
	 * Check if the box type is compatible with this box.
	 *
	 * @param $boxType
	 *
	 * @return bool
	 */
	protected function isCompatible($boxType)
	{
		switch ($boxType)
		{
			case 0x6D6F6F76: // moov
			case 0x7472616B: // trak
			case 0x6d646961: // mdia
			case 0x6D696E66: // minf
			case 0x7374626c: // stbl
			case 0x75647461: // udta
				return true;
			default:
				return false;
		}
	}
}