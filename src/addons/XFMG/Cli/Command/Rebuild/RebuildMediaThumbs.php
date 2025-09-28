<?php

namespace XFMG\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildMediaThumbs extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfmg-media-thumbs';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds media item thumbnails.';
	}

	protected function getRebuildClass()
	{
		return 'XFMG:MediaThumb';
	}
}