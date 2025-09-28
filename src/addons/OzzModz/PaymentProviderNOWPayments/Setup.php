<?php

namespace OzzModz\PaymentProviderNOWPayments;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	// ################################## INSTALL ###########################################

	public function installStep1()
	{
		$sm = $this->schemaManager();

		foreach ($this->getTables() as $tableName => $closure)
		{
			$sm->createTable($tableName, $closure);
		}
	}

	public function installStep2()
	{
		/** @var \XF\Entity\PaymentProvider $provider */
		$provider = $this->app->em()->create('XF:PaymentProvider');

		$provider->bulkSet([
			'provider_id' => 'ozzmodz_nowpayments',
			'provider_class' => 'OzzModz\PaymentProviderNOWPayments:NOWPayments',
			'addon_id' => 'OzzModz/PaymentProviderNOWPayments'
		]);

		$provider->save();
	}

	// ################################## UNINSTALL ###########################################

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		foreach (array_keys($this->getTables()) as $tableName)
		{
			$sm->dropTable($tableName);
		}
	}

	public function uninstallStep2()
	{
		/** @var \XF\Entity\PaymentProvider $provider */
		$provider = $this->app->em()->find('XF:PaymentProvider', 'ozzmodz_nowpayments');
		if ($provider)
		{
			$provider->delete();
		}
	}

	// ################################## DATA ###########################################

	protected function getTables(): array
	{
		$tables = [];

		$tables['xf_ozzmodz_nowpayments_plan'] = function (Create $table) {
			$table->addColumn('plan_id', 'int', 10)->primaryKey();
			$table->addColumn('purchasable_type_id', 'varbinary', 50);
			$table->addColumn('purchasable_id', 'varbinary', 50);
			$table->addColumn('title', 'varchar', 255);
			$table->addColumn('interval_day', 'int', 10);
			$table->addColumn('amount', 'int', 10);
			$table->addColumn('currency', 'varchar', 3);

			$table->addKey('purchasable_type_id');
			$table->addKey('purchasable_id');
			$table->addKey(['purchasable_type_id', 'purchasable_id']);
		};

		$tables['xf_ozzmodz_nowpayments_subscription'] = function (Create $table) {
			$table->addColumn('purchase_request_key', 'varbinary', 32)->primaryKey();
			$table->addColumn('subscription_id', 'int', 10);
			$table->addColumn('subscription_plan_id', 'int', 10);
			$table->addColumn('email', 'varchar', 120);
			$table->addColumn('create_date', 'int', 10);
			$table->addKey('subscription_id');
		};

		return $tables;
	}
}