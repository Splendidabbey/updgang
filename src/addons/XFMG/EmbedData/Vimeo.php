<?php

namespace XFMG\EmbedData;

class Vimeo extends BaseData
{
	public function getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$reader = $this->app->http()->reader();

		$apiUrl = 'https://vimeo.com/api/oembed.json?url=' . urlencode($url);

		$response = $reader->getUntrusted($apiUrl);
		if (!$response || $response->getStatusCode() != 200)
		{
			return parent::getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId);
		}

		$apiResponse = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
		if (!isset($apiResponse['thumbnail_url']))
		{
			return parent::getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId);
		}

		$response = $reader->getUntrusted($apiResponse['thumbnail_url']);
		if (!$response || $response->getStatusCode() != 200)
		{
			return parent::getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId);
		}

		$body = $response->getBody();

		return $this->createTempThumbnailFromBody($body);
	}
}