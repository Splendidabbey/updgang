<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

class MediaRating extends AbstractRating
{
	protected function assertViewableAndRateableContent(ParameterBag $params, array $extraWith = [])
	{
		$mediaItem = $this->assertViewableContent($params, $extraWith);
		if ($mediaItem->canRate())
		{
			return $mediaItem;
		}
		else
		{
			throw $this->exception($this->noPermission());
		}
	}

	protected function assertViewableContent(ParameterBag $params, array $extraWith = [])
	{
		return $this->assertViewableMediaItem($params->media_id, $extraWith);
	}

	protected function getLinkPrefix()
	{
		return 'media/media-ratings';
	}
}