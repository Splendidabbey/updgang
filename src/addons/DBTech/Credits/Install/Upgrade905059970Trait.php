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
trait Upgrade905059970Trait
{
	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905050031Step1()
	{
		$this->query("
			REPLACE INTO `xf_payment_provider`
				(`provider_id`, `provider_class`, `addon_id`)
			VALUES
				('dbtech_credits', 'DBTech\\\\Credits:Credits', X'4442546563682F43726564697473')
		");
	}
}