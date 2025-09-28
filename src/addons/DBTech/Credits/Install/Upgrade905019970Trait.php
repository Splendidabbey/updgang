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
trait Upgrade905019970Trait
{
	/**
	 *
	 */
	public function upgrade905010031Step1()
	{
		$sm = $this->schemaManager();

		$sm->renameTable('xf_dbtech_credits_eventtrigger', 'xf_dbtech_credits_event_trigger');
	}

	/**
	 *
	 */
	public function upgrade905010031Step2()
	{
		$sm = $this->schemaManager();

		$sm->dropTable('xf_dbtech_credits_field');
		$sm->dropTable('xf_dbtech_credits_purchase_log');
		$sm->dropTable('xf_dbtech_credits_transaction_pending');

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('referenceid');
			$table->addColumn('content_id', 'int')->after('content_type');
		});

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->addColumn('ip_id', 'int', 10)->setDefault(0)->after('touserid');
			$table->dropColumns(['ipaddress']);
		});
	}

	/**
	 *
	 */
	public function upgrade905010031Step3()
	{
		$sm = $this->schemaManager();

		$columns = $sm->getTableColumnDefinitions('xf_user');

		if (array_key_exists('dbtech_credits_credits', $columns))
		{
			$sm->alterTable('xf_user', function (Alter $table)
			{
				// Column was changed but not blacklisted so rename the column
				$table->changeColumn('dbtech_credits_credits', 'double')->unsigned(false)->setDefault('0');
			});
		}
	}

	/**
	 *
	 */
	public function upgrade905010031Step4()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_charge', function (Alter $table)
		{
			$table->renameColumn('postid', 'post_id');
			$table->renameColumn('contenthash', 'content_hash');
		});

		$sm->alterTable('xf_dbtech_credits_charge_purchase', function (Alter $table)
		{
			$table->renameColumn('postid', 'post_id');
			$table->renameColumn('contenthash', 'content_hash');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('displayorder', 'display_order');
			$table->renameColumn('useprefix', 'use_table_prefix');
			$table->renameColumn('userid', 'use_user_id');
			$table->renameColumn('usercol', 'user_id_column');
			$table->renameColumn('displaycurrency', 'is_display_currency');
		});
	}

	/**
	 *
	 */
	public function upgrade905010031Step5()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_event', function (Alter $table)
		{
			$table->renameColumn('eventid', 'event_id');
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('eventtriggerid', 'event_trigger_id');
			$table->renameColumn('usergroups', 'user_group_ids');
			$table->renameColumn('forums', 'node_ids');
		});

		$sm->alterTable('xf_dbtech_credits_event_trigger', function (Alter $table)
		{
			$table->renameColumn('eventtriggerid', 'event_trigger_id');
		});

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->renameColumn('eventid', 'event_id');
			$table->renameColumn('fromuserid', 'from_user_id');
			$table->renameColumn('touserid', 'to_user_id');
			$table->renameColumn('currencyid', 'currency_id');
		});
	}

	/**
	 *
	 */
	public function upgrade905010031Step6()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->renameColumn('transactionid', 'transaction_id');
			$table->renameColumn('eventid', 'event_id');
			$table->renameColumn('eventtriggerid', 'event_trigger_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('sourceuserid', 'source_user_id');
			$table->renameColumn('referenceid', 'reference_id');
			$table->renameColumn('forumid', 'node_id');
			$table->renameColumn('ownerid', 'owner_id');
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('isdisputed', 'is_disputed');
		});
	}

	/**
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905010031Step7()
	{
		$this->query("
			REPLACE INTO `xf_purchasable`
				(`purchasable_type_id`, `purchasable_class`, `addon_id`)
			VALUES
				('dbtech_credits_currency', 'DBTech\\\\Credits:Currency', X'4442546563682F43726564697473')
		");

		$this->query("
			UPDATE `xf_dbtech_credits_event`
			SET `user_group_ids` = '[-1]'
			WHERE `user_group_ids` = '[]'
				OR `user_group_ids` = ''
		");

		$this->query("
			UPDATE `xf_dbtech_credits_event`
			SET `node_ids` = '[-1]'
			WHERE `node_ids` = '[]'
				OR `node_ids` = ''
		");
	}

	/**
	 *
	 */
	public function upgrade905010033Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addColumn('member_dropdown', 'tinyint')->setDefault(0);
		});

		\XF::registry()->delete([
			'dbtCreditsCurrencies',
		]);
	}

	/**
	 * @param bool $applied
	 * @param int|null $previousVersion
	 *
	 * @return bool
	 */
	protected function applyPermissionsUpgrade905019970(bool &$applied, ?int $previousVersion = null): bool
	{
		if (!$previousVersion || $previousVersion < 905010031)
		{
			$this->applyGlobalPermission('dbtechCredits', 'bypassChargeTag', 'general', 'bypassUserPrivacy');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 905010037)
		{
			$this->applyGlobalPermission('dbtechCredits', 'triggerEvents', 'general', 'viewNode');

			$applied = true;
		}

		return $applied;
	}

	/**
	 * @param $previousVersion
	 * @param array $stateChanges
	 */
	protected function postUpgrade905010031($previousVersion, array &$stateChanges)
	{
		if ($previousVersion && $previousVersion < 905010031)
		{
			$options = $this->db()->fetchPairs("
				SELECT option_id, option_value
				FROM xf_option
				WHERE option_id LIKE 'dbtech_credits_eventtrigger_%'
			");
			if (!count($options))
			{
				$stateChanges['redirect'] = \XF::app()->router('admin')
					->buildLink('dbtech-credits/upgrade-error')
				;
			}
			else
			{
				$newSettings = [];

				$eventTriggers = $this->db()->fetchPairs("
					SELECT event_trigger_id, settings
					FROM xf_dbtech_credits_event_trigger
					WHERE event_trigger_id IN(
						'content', 'donate', 'interest', 'message',
						'paycheck', 'purchase', 'revival', 'taxation'
					)
				");
				foreach ($eventTriggers as $eventTriggerId => $settings)
				{
					$settings = json_decode($settings, true);
					if (empty($settings))
					{
						continue;
					}

					foreach ($settings as $key => $val)
					{
						if (strpos($eventTriggerId, $key) === false)
						{
							// Work around an issue where every setting was saved for every event trigger
							// even if it didn't apply
							continue;
						}

						if (array_key_exists('dbtech_credits_eventtrigger_' . $key, $options))
						{
							$newSettings['dbtech_credits_eventtrigger_' . $key] = $val;
						}
					}
				}

				if (count($newSettings))
				{
					/** @var \XF\Repository\Option $optionRepo */
					$optionRepo = \XF::repository('XF:Option');
					$optionRepo->updateOptions($newSettings);
				}
			}
		}
	}
}