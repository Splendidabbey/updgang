<?php

namespace XFMG\Alert;

use XF\Alert\AbstractHandler;

class Rating extends AbstractHandler
{
	public function getEntityWith()
	{
		return ['Album', 'Media', 'Comment', 'User'];
	}

	public function getOptOutActions()
	{
		return [
			'insert'
		];
	}

	public function getOptOutDisplayOrder()
	{
		return 215;
	}
}