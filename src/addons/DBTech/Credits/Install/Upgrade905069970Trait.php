<?php

namespace DBTech\Credits\Install;

use XF\Db\Schema\Alter;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade905069970Trait
{
	/**
	 *
	 */
	public function upgrade905060032Step1()
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905060033Step1()
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$currencies = $db->fetchAll('
			SELECT `column`
			FROM xf_dbtech_credits_currency
		');

		$sm->alterTable('xf_user', function (Alter $table) use ($currencies)
		{
			foreach ($currencies as $currency)
			{
				if ($table->getColumnDefinition($currency['column']))
				{
					$table->changeColumn($currency['column'], 'decimal')
						->length('65,8')
						->unsigned(false)
						->setDefault(0)
					;
				}
			}
		});
	}

	/**
	 *
	 */
	public function upgrade905060033Step2()
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905060034Step1()
	{
		$this->db()->delete('xf_user_alert', 'content_type = ? AND action = ?', [
			'dbtech_credits_txn',
			'payment'
		]);
	}
}