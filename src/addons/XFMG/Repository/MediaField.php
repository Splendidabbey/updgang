<?php

namespace XFMG\Repository;

use XF\Repository\AbstractField;

class MediaField extends AbstractField
{
	protected function getRegistryKey()
	{
		return 'xfmgMediaFields';
	}

	protected function getClassIdentifier()
	{
		return 'XFMG:MediaField';
	}

	public function getDisplayGroups()
	{
		return [
			'below_media' => \XF::phrase('xfmg_below_media_item'),
			'below_info' => \XF::phrase('xfmg_bottom_of_media_info_block'),
			'extra_info_sidebar_block' => \XF::phrase('xfmg_extra_info_block'),
			'new_sidebar_block' => \XF::phrase('xfmg_new_sidebar_block')
		];
	}
}