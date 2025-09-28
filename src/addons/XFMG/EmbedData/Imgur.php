<?php

namespace XFMG\EmbedData;

class Imgur extends BaseData
{
	protected function getImgurPageUrl($url)
	{
		if (preg_match('#(https?://i.imgur.com/[A-Z0-9]+)(\.[A-Z]+)#i', $url, $matches))
		{
			$url = $matches[1];
		}
		return $url;
	}

	public function getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$url = $this->getImgurPageUrl($url);
		
		return parent::getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId);
	}

	public function getTitleAndDescription($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$url = $this->getImgurPageUrl($url);

		return parent::getTitleAndDescription($url, $bbCodeMediaSiteId, $siteMediaId);
	}
}