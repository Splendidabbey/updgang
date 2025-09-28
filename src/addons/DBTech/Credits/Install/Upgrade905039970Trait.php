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
trait Upgrade905039970Trait
{
	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030031Step1()
	{
		$db = $this->db();

		$db->beginTransaction();

		$this->renamePermission('dbtech_credits', 'canview', 'dbtechCredits', 'view');
		$this->renamePermission('dbtech_credits', 'triggerEvents', 'dbtechCredits', 'triggerEvents');
		$this->renamePermission('dbtech_credits', 'charge', 'dbtechCredits', 'charge');

		$this->renamePermission('dbtech_credits', 'adjust', 'dbtechCredits', 'adjust');
		$this->renamePermission('dbtech_credits', 'viewlog', 'dbtechCredits', 'viewAnyLog');
		$this->renamePermission('dbtech_credits', 'special', 'dbtechCredits', 'bypassCurrencyPrivacy');
		$this->renamePermission('dbtech_credits', 'bypassChargeTag', 'dbtechCredits', 'bypassChargeTag');
	}

	/**
	 *
	 */
	public function upgrade905030031Step2()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addKey('active');
		});

		$sm->alterTable('xf_dbtech_credits_event', function (Alter $table)
		{
			$table->addKey(['active', 'display'], 'transaction_display');
		});

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->renameColumn('to_user_id', 'user_id');
			$table->addColumn('transaction_date', 'int', 10)->setDefault(0)->after('user_id');
			$table->addKey(['transaction_date', 'user_id'], 'transaction_date');
		});

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->addKey(['dateline', 'transaction_id'], 'transaction_date');
		});
	}

	/**
	 *
	 */
	public function upgrade905030031Step3()
	{
		$sm = $this->schemaManager();

		$tables = $this->getTables();

		$key = 'xf_dbtech_credits_adjust_log';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_credits_donation_log';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_credits_redeem_log';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_credits_transfer_log';
		$sm->createTable($key, $tables[$key]);
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030031Step4()
	{
		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_adjust_log
				(user_id, adjust_date, adjust_user_id, event_id, currency_id, amount, message)
			SELECT user_id, dateline, source_user_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'adjust'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_donation_log
				(user_id, donation_date, donation_user_id, event_id, currency_id, amount, message)
			SELECT user_id, dateline, source_user_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'donate'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_purchase_transaction
				(user_id, transaction_date, from_user_id, event_id, currency_id, amount, message)
			SELECT user_id, dateline, source_user_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'purchase'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_redeem_log
				(user_id, redeem_date, redeem_code, event_id, currency_id, amount, message)
			SELECT user_id, dateline, reference_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'redeem'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_transfer_log
				(user_id, transfer_date, event_id, currency_id, amount, message)
			SELECT user_id, dateline, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'transfer'
				AND status = 1
		");
	}

	/**
	 *
	 */
	public function upgrade905030032Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->renameColumn('to_user_id', 'user_id');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030033Step1()
	{
		$defaultValue = [
			'enabled' => 1,
			'right_position' => false,
			'right_text' => true
		];

		$this->query("
			UPDATE xf_option
			SET default_value = ?
			WHERE option_id = 'dbtech_credits_navbar'
		", json_encode($defaultValue));

		$navbarDefaults = json_decode($this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'dbtech_credits_navbar'
		"), true);

		$update = false;
		foreach (array_keys($defaultValue) AS $key)
		{
			if (!isset($navbarDefaults[$key]))
			{
				$update = true;
				$navbarDefaults[$key] = $defaultValue[$key];
			}
		}

		if ($update)
		{
			$this->query("
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = 'dbtech_credits_navbar'
			", json_encode($navbarDefaults));
		}
	}

	/**
	 *
	 */
	public function upgrade905030035Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->addColumn('transaction_state', 'enum')
				->values(['visible', 'moderated', 'skipped', 'skipped_maximum'])
				->setDefault('visible')
				->after('amount')
			;
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030035Step2()
	{
		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_transaction`
			SET `transaction_state` = CASE `status`
				WHEN 1 THEN 'visible'
				WHEN 2 THEN 'moderated'
				WHEN 3 THEN 'skipped'
				WHEN 4 THEN 'skipped_maximum'
				ELSE 'visible' END
		");
	}

	/**
	 *
	 */
	public function upgrade905030035Step3()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->dropIndexes([
				'dateline',
				'user_id_stats'
			]);
			$table->addKey(['dateline', 'user_id', 'transaction_state'], 'dateline');
			$table->addKey(['user_id', 'event_id', 'transaction_state', 'negate', 'dateline'], 'user_id_stats');

			$table->dropColumns([
				'status',
			]);
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030035Step4()
	{
		$this->executeUpgradeQuery("
			INSERT INTO xf_approval_queue
				(content_type, content_id, content_date)
			SELECT 'dbtech_credits_txn', transaction_id, UNIX_TIMESTAMP()
			FROM xf_dbtech_credits_transaction
			WHERE transaction_state = 'moderated'
		");
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030035Step5()
	{
		$this->executeUpgradeQuery("
			UPDATE `xf_user_alert`
			SET `content_type` = 'dbtech_credits_txn'
			WHERE `content_type` = 'dbtech_credits'
		");

		$this->executeUpgradeQuery("
			UPDATE xf_user_alert_optout
			SET alert = REPLACE(`alert`, 'dbtech_credits_', 'dbtech_credits_txn_')
		");
	}

	/**
	 *
	 */
	public function upgrade905030370Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_charge', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('post_id');
			$table->addColumn('content_id', 'int')->after('content_type');
		});

		$sm->alterTable('xf_dbtech_credits_charge_purchase', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('post_id');
			$table->addColumn('content_id', 'int')->after('content_type');
		});
	}

	/**
	 *
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 * @throws \XF\Db\Exception
	 */
	public function upgrade905030370Step2()
	{
		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge`
			SET `content_type` = 'post'
		");

		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge`
			SET `content_id` = `post_id`
		");

		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge_purchase`
			SET `content_type` = 'post'
		");

		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge_purchase`
			SET `content_id` = `post_id`
		");
	}

	/**
	 *
	 */
	public function upgrade905030370Step3()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_charge', function (Alter $table)
		{
			$table->dropPrimaryKey();
			$table->dropColumns(['post_id']);
			$table->addPrimaryKey(['content_type', 'content_id', 'content_hash']);
		});

		$sm->alterTable('xf_dbtech_credits_charge_purchase', function (Alter $table)
		{
			$table->dropPrimaryKey();
			$table->dropColumns(['post_id']);
			$table->addPrimaryKey(['content_type', 'content_id', 'content_hash', 'user_id']);
		});
	}

	/**
	 * @param bool $applied
	 * @param int|null $previousVersion
	 *
	 * @return bool
	 */
	protected function applyPermissionsUpgrade905039970(bool &$applied, ?int $previousVersion = null): bool
	{
		if (!$previousVersion || $previousVersion < 905030031)
		{
			$this->applyGlobalPermission('dbtechCredits', 'viewModerated', 'forum', 'viewModerated');

			$applied = true;
		}

		if ($previousVersion && $previousVersion < 905030035)
		{
			$this->applyGlobalPermission('dbtechCredits', 'approveUnapprove', 'dbtechCredits', 'viewModerated');

			$applied = true;
		}
		elseif (!$previousVersion)
		{
			$this->applyGlobalPermission('dbtechCredits', 'approveUnapprove', 'forum', 'viewModerated');

			$applied = true;
		}

		return $applied;
	}
}