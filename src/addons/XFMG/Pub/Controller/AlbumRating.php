<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

class AlbumRating extends AbstractRating
{
	protected function assertViewableAndRateableContent(ParameterBag $params, array $extraWith = [])
	{
		$album = $this->assertViewableContent($params, $extraWith);
		if ($album->canRate())
		{
			return $album;
		}
		else
		{
			throw $this->exception($this->noPermission());
		}
	}

	protected function assertViewableContent(ParameterBag $params, array $extraWith = [])
	{
		return $this->assertViewableAlbum($params->album_id, $extraWith);
	}

	protected function getLinkPrefix()
	{
		return 'media/album-ratings';
	}
}