<?php

namespace XFMG\Alert;

use XF\Alert\AbstractHandler;

class MediaNote extends AbstractHandler
{
	public function getEntityWith()
	{
		return ['MediaItem'];
	}

	// Not currently adding any code to prevent opt out of note alerts.
	// Technically can't opt out of approval alerts anyway so it's probably
	// just best to just let these go through regardless.
}