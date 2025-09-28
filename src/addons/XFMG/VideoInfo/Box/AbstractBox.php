<?php

namespace XFMG\VideoInfo\Box;

use XFMG\VideoInfo\Preparer;

abstract class AbstractBox
{
	/**
	 * Total box size, including box header (8 bytes)
	 *
	 * @var int
	 */
	protected $totalSize;
	
	/**
	 * Box type, numeric
	 *
	 * @var int
	 */
	protected $boxType;
	
	/**
	 * Box type, string
	 *
	 * @var string
	 */
	protected $boxTypeStr;
	
	/**
	 * Box data
	 *
	 * @var string(binary)
	 */
	protected $data;

	/**
	 * Preparer object
	 *
	 * @var Preparer
	 */
	protected $preparer;
	
	/**
	 * Parent box
	 *
	 * @var AbstractBox|false
	 */
	protected $parent;
	
	/**
	 * Children
	 *
	 * @var AbstractBox[]
	 */
	protected $children = [];

	public function __construct($totalSize, $boxType, $file, Preparer $preparer, &$parent = false)
	{
		if (!$this->isCompatible($boxType))
		{
			return;
		}

		$this->totalSize = $totalSize;
		$this->boxType = $boxType;
		$this->boxTypeStr = pack('N', $boxType);
		$this->data = $this->getDataFromFileOrString($file, $totalSize);
		$this->preparer = $preparer;

		$this->parent = $parent;
		if ($parent != false)
		{
			$parent->addChild($this);
		}
	}

	/**
	 * Check if the box type is compatible with this box.
	 *
	 * @param $boxType
	 *
	 * @return bool
	 */
	abstract protected function isCompatible($boxType);

	/**
	 * Add a child box to this box
	 *
	 * @param AbstractBox $child
	 */
	public function addChild(AbstractBox &$child)
	{
		$this->children[] = &$child;
	}

	/**
	 * Checks if this box has any children
	 *
	 * @return bool
	 */
	public function hasChildren()
	{
		return count($this->children) > 0;
	}

	/**
	 * Gets this box's children.
	 *
	 * @return AbstractBox[]
	 */
	public function children()
	{
		return $this->children;
	}

	/**
	 * Gets the data from the file. File could be an actual file pointer or string.
	 *
	 * @param $file
	 * @param $totalSize
	 *
	 * @return string
	 */
	public function getDataFromFileOrString($file, $totalSize)
	{
		if ($file === false)
		{
			return '';
		}
		else if (is_string($file))
		{
			$data = substr($file, 0, $totalSize - 8);
		}
		else
		{
			$data = fread($file, $totalSize - 8);
		}		
		
		return $data;
	}

	/**
	 * Instantiates the specified box object based on type
	 *
	 * @param $totalSize
	 * @param $boxType
	 * @param $f
	 * @param Preparer $preparer
	 * @param bool $parent
	 *
	 * @return AbstractBox
	 */
	public static function create($totalSize, $boxType, $f, Preparer $preparer, $parent = false)
	{
		switch (pack('N', $boxType))
		{
			case 'stsd':

				$box = new Stsd($totalSize, $boxType, $f, $preparer, $parent);
				break;

			default:

				$box = new Container($totalSize, $boxType, $f, $preparer, $parent);
				break;
		}

		return $box;
	}

	/**
	 * Gets the total size of this box.
	 *
	 * @return int
	 */
	public function getTotalSize()
	{
		return $this->totalSize;
	}

	/**
	 * Get type of this box as an integer
	 *
	 * @return int
	 */
	public function getBoxType()
	{
		return $this->boxType;
	}

	/**
	 * Get type of this box as a readable string (pack('N', $boxType))
	 *
	 * @return string
	 */
	public function getBoxTypeStr()
	{
		return $this->boxTypeStr;
	}
}
