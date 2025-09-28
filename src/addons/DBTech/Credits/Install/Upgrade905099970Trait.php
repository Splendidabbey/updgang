<?php

namespace DBTech\Credits\Install;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade905099970Trait
{
	/**
	 *
	 */
	public function upgrade905090031Step1()
	{
		$this->applyTables();
	}

	/**
	 * @return void
	 */
	public function upgrade905090031Step2()
	{
		$this->installStep2();
	}
}