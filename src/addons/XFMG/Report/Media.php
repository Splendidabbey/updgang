<?php

namespace XFMG\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

class Media extends AbstractHandler
{
	protected function canViewContent(Report $report)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$contentInfo = $report->content_info;

		if (!empty($contentInfo['category_id']))
		{
			if (!method_exists($visitor, 'hasGalleryCategoryPermission'))
			{
				return false;
			}

			return $visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'view');
		}
		else if (!empty($contentInfo['album_id']))
		{
			return $visitor->hasPermission('xfmg', 'view');
		}
		else
		{
			return false;
		}
	}

	protected function canActionContent(Report $report)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$contentInfo = $report->content_info;

		if (!empty($contentInfo['category_id']))
		{
			if (!method_exists($visitor, 'hasGalleryCategoryPermission'))
			{
				return false;
			}

			return (
				$visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'editAny')
				|| $visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'deleteAny')
			);
		}
		else if (!empty($contentInfo['album_id']))
		{
			return (
				$visitor->hasPermission('xfmg', 'editAny')
				|| $visitor->hasPermission('xfmg', 'deleteAny')
			);
		}
		else
		{
			return false;
		}
	}

	public function setupReportEntityContent(Report $report, Entity $content)
	{
		/** @var \XFMG\Entity\MediaItem $content */
		if ($content->Category)
		{
			$report->content_user_id = $content->user_id;
			$report->content_info = [
				'category_id' => $content->category_id,
				'category_title' => $content->Category->title,
				'media_id' => $content->media_id,
				'title' => $content->title,
				'description' => $content->description,
				'user_id' => $content->user_id,
				'username' => $content->username
			];
		}
		else if ($content->Album)
		{
			$report->content_user_id = $content->user_id;
			$report->content_info = [
				'album_id' => $content->album_id,
				'album_title' => $content->Album->title,
				'media_id' => $content->media_id,
				'title' => $content->title,
				'description' => $content->description,
				'user_id' => $content->user_id,
				'username' => $content->username
			];
		}
	}

	public function getContentTitle(Report $report)
	{
		$contentInfo = $report->content_info;

		$params = [
			'title' => $contentInfo['title']
		];

		// for display purposes, so check if part of an album first
		if (!empty($contentInfo['album_id']))
		{
			return \XF::phrase('xfmg_media_x_in_album_y', $params + [
				'album' => $contentInfo['album_title']
			]);
		}
		else if (!empty($contentInfo['category_id']))
		{
			return \XF::phrase('xfmg_media_x_in_category_y', $params + [
				'category' => $contentInfo['category_title']
			]);
		}

		return '';
	}

	public function getContentMessage(Report $report)
	{
		return $report->content_info['description'];
	}

	public function getContentLink(Report $report)
	{
		if (!empty($report->content_info['media_id']))
		{
			$linkData = $report->content_info;
		}
		else
		{
			$linkData = ['album_id' => $report->content_id];
		}

		return \XF::app()->router()->buildLink('canonical:media', $linkData);
	}
}