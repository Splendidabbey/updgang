<?php

namespace XFMG\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Media extends AbstractHandler
{
	public function isLoggable(Entity $content, $action, \XF\Entity\User $actor)
	{
		switch ($action)
		{
			case 'edit':
				if ($actor->user_id == $content->user_id)
				{
					return false;
				}
		}

		return parent::isLoggable($content, $action, $actor);
	}

	protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
	{
		switch ($field)
		{
			case 'title':
			case 'description':
				return 'edit_' . $field;

			case 'media_state':
				if ($newValue == 'visible' && $oldValue == 'moderated')
				{
					return 'approve';
				}
				else if ($newValue == 'visible' && $oldValue == 'deleted')
				{
					return 'undelete';
				}
				else if ($newValue == 'deleted')
				{
					$reason = $content->DeletionLog ? $content->DeletionLog->delete_reason : '';
					return ['delete_soft', ['reason' => $reason]];
				}
				else if ($newValue == 'moderated')
				{
					return 'unapprove';
				}
				break;

			case 'category_id':
				if ($oldValue)
				{
					/** @var \XFMG\Entity\Category $category */
					$category = \XF::em()->find('XFMG:Category', $oldValue);
					$oldCategory = $category ? $category->title : '';
					return ['move', ['from' => $oldCategory]];
				}
				break;
		}

		return false;
	}

	protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
	{
		/** @var \XFMG\Entity\MediaItem $content */
		$log->content_user_id = $content->user_id;
		$log->content_username = $content->username;
		$log->content_title = $content->title;
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:media', $content);
		$log->discussion_content_type = 'xfmg_media';
		$log->discussion_content_id = $content->media_id;
	}

	public function getContentTitle(ModeratorLog $log)
	{
		return \XF::phrase('xfmg_media_x', [
			'media' => \XF::app()->stringFormatter()->censorText($log->content_title_)
		]);
	}
}