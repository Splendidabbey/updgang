<?php

namespace XFMG\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildAlbums extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfmg-albums';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds album counters.';
	}

	protected function getRebuildClass()
	{
		return 'XFMG:Album';
	}
}