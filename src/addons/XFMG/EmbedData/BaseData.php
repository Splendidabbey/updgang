<?php

namespace XFMG\EmbedData;

use Symfony\Component\DomCrawler\Crawler;

class BaseData
{
	/**
	 * @var \XF\App
	 */
	protected $app;

	public function __construct(\XF\App $app)
	{
		$this->app = $app;
	}

	/**
	 * Given a valid usable media site URL provided by a user, and the site ID and media ID from our match
	 * attempt to fetch and return a temp path pointing to whichever thumbnail we want to use or null if fetching
	 * fails for some reason or otherwise not supported.
	 *
	 * The default code here is sufficient in most cases, but could be overridden if, for example, an API could get the info.
	 *
	 * @param $url
	 * @param $bbCodeMediaSiteId
	 * @param $siteMediaId
	 *
	 * @return null|string
	 */
	public function getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$metadata = $this->app->http()->metadataFetcher()->fetch($url, $error);
		if (!$metadata)
		{
			return null;
		}

		$imageUrl = $metadata->getImage();

		if (!$imageUrl)
		{
			return null;
		}

		$reader = $this->app->http()->reader();

		$response = $reader->getUntrusted($imageUrl);
		if (!$response || $response->getStatusCode() != 200)
		{
			return null;
		}

		return $this->createTempThumbnailFromBody($response->getBody());
	}

	protected function createTempThumbnailFromBody($body)
	{
		$tempFile = \XF\Util\File::getTempFile();
		$fp = @fopen($tempFile, 'w');

		if (!$fp)
		{
			return null;
		}

		fwrite($fp, $body);
		fclose($fp);

		return $tempFile;
	}


	/**
	 * Given a valid usable media site URL provided by a user, and the site ID and media ID from our match
	 * attempt tp fetch and return an array containing the default title and description or an empty array if fetching
	 * fails for some reason or otherwise not supported.
	 *
	 * The default code here is sufficient in most cases, but could be overridden if, for example, an API could get the info.
	 *
	 * @param $url
	 * @param $bbCodeMediaSiteId
	 * @param $siteMediaId
	 *
	 * @return array
	 */
	public function getTitleAndDescription($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$metadata = $this->app->http()->metadataFetcher()->fetch($url, $error);

		if (!$metadata)
		{
			return [];
		}

		return [
			'title' => $metadata->getTitle(),
			'description' => $metadata->getDescription()
		];
	}
}