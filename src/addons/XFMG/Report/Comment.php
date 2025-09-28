<?php

namespace XFMG\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

class Comment extends AbstractHandler
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

			return $visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'viewComments');
		}
		else
		{
			return $visitor->hasPermission('xfmg', 'viewComments');
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
				$visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'editAnyComment')
				|| $visitor->hasGalleryCategoryPermission($contentInfo['category_id'], 'deleteAnyComment')
			);
		}
		else
		{
			return (
				$visitor->hasPermission('xfmg', 'editAnyComment')
				|| $visitor->hasPermission('xfmg', 'deleteAnyComment')
			);
		}
	}

	public function setupReportEntityContent(Report $report, Entity $content)
	{
		/** @var \XFMG\Entity\Comment $content */

		$contentInfo = [
			// these are only populated in the context of being containers of the comment content
			'category_id' => 0,
			'category_title' => '',
			'album_id' => 0,
			'album_title' => '',

			// actually related to the comment content
			'content_type' => $content->content_type,
			'content_id' => $content->content_id,
			'content_title' => $content->Content->title,
			'content_description' => $content->Content->description,
			'user_id' => $content->user_id,
			'username' => $content->username,
			'comment' => [
				'comment_id' => $content->comment_id,
				'username' => $content->username,
				'message' => $content->message
			]
		];

		$parentContent = $content->Content;

		if ($parentContent->Category)
		{
			$contentInfo['category_id'] = $parentContent->Category->category_id;
			$contentInfo['category_title'] = $parentContent->Category->title;
		}
		if ($parentContent instanceof \XFMG\Entity\MediaItem && $parentContent->Album)
		{
			$contentInfo['album_id'] = $parentContent->Album->album_id;
			$contentInfo['album_title'] = $parentContent->Album->title;
		}

		$report->content_user_id = $content->user_id;
		$report->content_info = $contentInfo;
	}

	public function getContentTitle(Report $report)
	{
		$contentInfo = $report->content_info;

		if (!empty($contentInfo['content_type']) && $contentInfo['content_type'] == 'xfmg_media')
		{
			return \XF::phrase('xfmg_comment_by_x_in_media_y', [
				'user' => $contentInfo['username'],
				'title' => \XF::app()->stringFormatter()->censorText($contentInfo['content_title'])
			]);
		}
		else if (!empty($contentInfo['content_type']) && $contentInfo['content_type'] == 'xfmg_album')
		{
			return \XF::phrase('xfmg_comment_by_x_in_album_y', [
				'user' => $contentInfo['username'],
				'title' => \XF::app()->stringFormatter()->censorText($contentInfo['content_title'])
			]);
		}
		else
		{
			return \XF::phrase('xfmg_comment_by_x', [
				'user' => $contentInfo['username']
			]);
		}
	}

	public function getContentMessage(Report $report)
	{
		return $report->content_info['comment']['message'];
	}

	public function getContentLink(Report $report)
	{
		return \XF::app()->router()->buildLink('canonical:media/comments', $report->content_info['comment']);
	}
}