<?php

namespace XFRM\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildUserResourceCounts extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfrm-user-resource-counts';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds resource related user counters.';
	}

	protected function getRebuildClass()
	{
		return 'XFRM:UserResourceCount';
	}
}