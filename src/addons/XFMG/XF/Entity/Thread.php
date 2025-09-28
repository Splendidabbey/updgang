<?php

namespace XFMG\XF\Entity;

use XF\Entity\Forum;
use XF\Mvc\Entity\Structure;

class Thread extends XFCP_Thread
{
	protected function threadHidden($hardDelete = false)
	{
		parent::threadHidden($hardDelete);

		if (!$hardDelete)
		{
			// hard delete will delete the attachments which will trigger similar code
			$this->app()->service('XFMG:Media\MirrorManager')->attachmentContainerHidden('post', $this->post_ids);
		}
	}

	protected function threadMoved(Forum $from, Forum $to)
	{
		parent::threadMoved($from, $to);

		$fromMediaCategoryId = $from->xfmg_media_mirror_category_id;
		$toMediaCategoryId = $to->xfmg_media_mirror_category_id;

		if ($fromMediaCategoryId != $toMediaCategoryId)
		{
			$this->app()->service('XFMG:Media\MirrorManager')->syncMirrorStateForContent('post', $this->post_ids);
		}
	}
}