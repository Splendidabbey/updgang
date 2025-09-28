<?php

namespace XFMG\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildMediaItems extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfmg-media-items';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds media item counters.';
	}

	protected function getRebuildClass()
	{
		return 'XFMG:MediaItem';
	}
}