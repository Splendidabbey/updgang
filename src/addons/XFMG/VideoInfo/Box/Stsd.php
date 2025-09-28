<?php

namespace XFMG\VideoInfo\Box;

use XFMG\VideoInfo\Preparer;

class Stsd extends AbstractBox
{
	protected $values = [];

	public function __construct($totalSize, $boxType, $data, Preparer $preparer, $parent)
	{
		parent::__construct($totalSize, $boxType, '', $preparer, $parent);

		$data = $this->getDataFromFileOrString($data, $totalSize);

		$ar = unpack('Cversion/C3flags/Ncount', $data);

		$desc = substr($data,8);
		for ($i = 0; $i < $ar['count']; $i++)
		{
			$details = unpack('Nlen',$desc);

			$type = substr($desc, 4, 4);
			$info = substr($desc, 8, $details['len'] - 8);
			$desc = substr($desc, $details['len']);

			$this->values[$type] = $info;
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
		return ($boxType == 0x73747364);
	}

	/**
	 * Gets the values for this box.
	 *
	 * @return array
	 */
	public function getValues()
	{
		return $this->values;
	}

	/**
	 * Gets a specific value for this box.
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	public function getValue($key)
	{
		return isset($this->values[$key]) ? $this->values[$key] : false;
	}
}