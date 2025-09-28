<?php

namespace XFRM\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildResourceItems extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfrm-resource-items';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds resource item counters.';
	}

	protected function getRebuildClass()
	{
		return 'XFRM:ResourceItem';
	}
}