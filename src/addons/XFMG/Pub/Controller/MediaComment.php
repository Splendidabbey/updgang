<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

class MediaComment extends AbstractComment
{
	protected function assertViewableAndCommentableContent(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if ($mediaItem->canViewComments() && ($mediaItem->canAddComment() || $mediaItem->canAddCommentPreReg()))
		{
			return $mediaItem;
		}
		else
		{
			throw $this->exception($this->noPermission());
		}
	}

	protected function assertViewableContent(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if ($mediaItem->canViewComments())
		{
			return $mediaItem;
		}
		else
		{
			throw $this->exception($this->noPermission());
		}
	}

	protected function getLinkPrefix()
	{
		return 'media/media-comments';
	}
}