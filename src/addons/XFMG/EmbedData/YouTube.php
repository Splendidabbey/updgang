<?php

namespace XFMG\EmbedData;

class YouTube extends BaseData
{
	public function getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		if (strpos($siteMediaId, ':') !== false)
		{
			$siteMediaId = preg_replace('/(.*)(:\d+)/', '\\1', $siteMediaId);
		}

		$siteMediaId = rawurlencode($siteMediaId);

		$preferredThumbnail = "https://i.ytimg.com/vi/{$siteMediaId}/maxresdefault.jpg";
		$fallbackThumbnail = "https://i.ytimg.com/vi/{$siteMediaId}/hqdefault.jpg";

		$reader = $this->app->http()->reader();

		$response = $reader->getUntrusted($preferredThumbnail);
		if (!$response || $response->getStatusCode() != 200)
		{
			$response = $reader->getUntrusted($fallbackThumbnail);
			if (!$response || $response->getStatusCode() != 200)
			{
				return null;
			}
			$body = $response->getBody();
		}
		else
		{
			$body = $response->getBody();
		}

		return $this->createTempThumbnailFromBody($body);
	}
}