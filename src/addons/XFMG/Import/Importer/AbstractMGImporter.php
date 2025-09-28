<?php

namespace XFMG\Import\Importer;

use XF\Import\Importer\AbstractAddOnImporter;
use XF\Util\Arr;

abstract class AbstractMGImporter extends AbstractAddOnImporter
{
	protected function getMediaTypeAndFilePathFromExtension($extension)
	{
		list($imageExtensions, $videoExtensions, $audioExtensions) = $this->getAllowedUploadExtensions();

		$mediaType = null;
		$filePath = null;

		if (in_array($extension, $imageExtensions))
		{
			$mediaType = 'image';
		}
		else if (in_array($extension, $videoExtensions))
		{
			$mediaType = 'video';
			$filePath = 'data://xfmg/video/%FLOOR%/%DATA_ID%-%HASH%.mp4';
		}
		else if (in_array($extension, $audioExtensions))
		{
			$mediaType = 'audio';
			$filePath = 'data://xfmg/audio/%FLOOR%/%DATA_ID%-%HASH%.mp3';
		}

		return [
			$mediaType,
			$filePath
		];
	}

	protected function getAllowedUploadExtensions()
	{
		$options = $this->app->options();

		$imageExtensions = Arr::stringToArray($options->xfmgImageExtensions);
		$videoExtensions = Arr::stringToArray($options->xfmgVideoExtensions);
		$audioExtensions = Arr::stringToArray($options->xfmgAudioExtensions);

		return [
			$imageExtensions,
			$videoExtensions,
			$audioExtensions
		];
	}

	protected function isForumType($importType)
	{
		return (strpos($importType, 'xfmg_') !== 0);
	}

	public function canRetainIds()
	{
		$db = $this->app->db();

		$maxMediaId = $db->fetchOne("SELECT MAX(media_id) FROM xf_mg_media_item");
		if ($maxMediaId)
		{
			return false;
		}

		$maxAlbumId = $db->fetchOne("SELECT MAX(album_id) FROM xf_mg_album");
		if ($maxAlbumId)
		{
			return false;
		}

		$maxCategoryId = $db->fetchOne("SELECT MAX(category_id) FROM xf_mg_category");
		if ($maxCategoryId > 1)
		{
			return false;
		}

		$maxCommentId = $db->fetchOne("SELECT MAX(comment_id) FROM xf_mg_comment");
		if ($maxCommentId)
		{
			return false;
		}

		return true;
	}

	public function resetDataForRetainIds()
	{
		// category 1 is created by default in the installer so we need to remove that if retaining IDs
		$category = $this->em()->find('XFMG:Category', 1);
		if ($category)
		{
			$category->delete();
		}
	}

	public function getFinalizeJobs(array $stepsRun)
	{
		$jobs = [];

		$jobs[] = 'XFMG:Category';
		$jobs[] = 'XFMG:Album';
		$jobs[] = 'XFMG:MediaItem';
		$jobs[] = 'XFMG:AlbumRatingRebuild';
		$jobs[] = 'XFMG:MediaRatingRebuild';
		$jobs[] = 'XFMG:UserCount';
		$jobs[] = 'XFMG:UserMediaQuota';
		$jobs[] = 'XFMG:AlbumThumb';
		$jobs[] = 'XF:PermissionRebuild';

		return $jobs;
	}
}