<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Util\Arr;

class ResourceVersion extends Repository
{
	public function findVersionsInResource(\XFRM\Entity\ResourceItem $resource, array $limits = [])
	{
		/** @var \XFRM\Finder\ResourceVersion $finder */
		$finder = $this->finder('XFRM:ResourceVersion');
		$finder->inResource($resource, $limits)
			->setDefaultOrder('release_date', 'desc');

		if ($resource->canViewTeamMembers())
		{
			$finder->with('TeamUser');
		}

		return $finder;
	}

	public function logDownload(\XFRM\Entity\ResourceVersion $version)
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			$updateResource = true;
			$updateVersion = true;
		}
		else
		{
			$hasDownloaded = $this->db()->fetchOne("
				SELECT 1
				FROM xf_rm_resource_download
				WHERE user_id = ?
					AND resource_id = ?
				LIMIT 1
			", [$visitor->user_id, $version->resource_id]);

			$updateResource = !$hasDownloaded;

			$result = $this->db()->insert('xf_rm_resource_download', [
				'resource_version_id' => $version->resource_version_id,
				'user_id' => $visitor->user_id,
				'resource_id' => $version->resource_id,
				'last_download_date' => \XF::$time
			], false, 'last_download_date = VALUES(last_download_date)');

			$updateVersion = ($result == 1);
		}

		if ($updateResource)
		{
			$this->db()->query("
				UPDATE xf_rm_resource
				SET download_count = download_count + 1
				WHERE resource_id = ?
			", $version->resource_id);
		}

		if ($updateVersion)
		{
			$this->db()->query("
				UPDATE xf_rm_resource_version
				SET download_count = download_count + 1
				WHERE resource_version_id = ?
			", $version->resource_version_id);
		}
	}

	public function getVersionAttachmentConstraints()
	{
		$options = $this->options();

		return [
			'extensions' => Arr::stringToArray($options->xfrmResourceExtensions),
			'size' => $options->xfrmResourceMaxFileSize * 1024,
			'width' => $options->attachmentMaxDimensions['width'],
			'height' => $options->attachmentMaxDimensions['height'],
			'count' =>$options->xfrmResourceMaxFiles
		];
	}
}