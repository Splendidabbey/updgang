<?php

namespace DBTech\Credits\Cli\Command\Rebuild;

use Symfony\Component\Console\Input\InputOption;
use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

/**
 * Class Transactions
 *
 * @package DBTech\Credits\Cli\Command\Rebuild
 */
class RebuildTransactions extends AbstractRebuildCommand
{
	/**
	 * @return string
	 */
	protected function getRebuildName(): string
	{
		return 'dbtech-credits-transactions';
	}

	/**
	 * @return string
	 */
	protected function getRebuildDescription(): string
	{
		return 'Rebuilds the transaction records.';
	}

	/**
	 * @return string
	 */
	protected function getRebuildClass(): string
	{
		return 'DBTech\Credits:TransactionRebuild';
	}
	
	protected function configureOptions()
	{
		$this
			->addOption(
				'type',
				null,
				InputOption::VALUE_REQUIRED,
				'Content type to rebuild transaction records for. Default: all'
			)
			->addOption(
				'truncate',
				null,
				InputOption::VALUE_NONE,
				'Delete the existing records before rebuilding. Default: false'
			)
			->addOption(
				'reset',
				null,
				InputOption::VALUE_NONE,
				'Reset all currencies to 0. Requires "truncate" option. Default: false'
			);
	}
}