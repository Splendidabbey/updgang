<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\HasDeletionLogTrait;

class MediaNote extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'xfmg_media_note';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:MediaNote';
	}
}