<?php

namespace XFMG\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

class Album extends AbstractHandler
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
		else
		{
			return $visitor->hasPermission('xfmg', 'view');
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

			return ($visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'editAnyAlbum')
				|| $visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'deleteAnyAlbum')
			);
		}
		else
		{
			return ($visitor->hasPermission('xfmg', 'editAnyAlbum')
				|| $visitor->hasPermission('xfmg', 'deleteAnyAlbum')
			);
		}
	}

	public function setupReportEntityContent(Report $report, Entity $content)
	{
		$report->content_user_id = $content->user_id;

		/** @var \XFMG\Entity\Album $content */
		if ($content->Category)
		{
			$report->content_info = [
				'album_id' => $content->album_id,
				'title' => $content->title,
				'description' => $content->description,
				'category_id' => $content->Category->category_id,
				'category_title' => $content->Category->title,
				'user_id' => $content->user_id,
				'username' => $content->username
			];
		}
		else
		{
			$report->content_info = [
				'album_id' => $content->album_id,
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

		return \XF::phrase('xfmg_album_x', [
			'album' => $contentInfo['title']
		]);
	}

	public function getContentMessage(Report $report)
	{
		return $report->content_info['description'];
	}

	public function getContentLink(Report $report)
	{
		if (!empty($report->content_info['album_id']))
		{
			$linkData = $report->content_info;
		}
		else
		{
			$linkData = ['album_id' => $report->content_id];
		}

		return \XF::app()->router()->buildLink('canonical:media/albums', $linkData);
	}
}