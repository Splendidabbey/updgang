<?php

namespace XFMG\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildUserCounts extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfmg-user-counts';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds media related user counters.';
	}

	protected function getRebuildClass()
	{
		return 'XFMG:UserCount';
	}
}