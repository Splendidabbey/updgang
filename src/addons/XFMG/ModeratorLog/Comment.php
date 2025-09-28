<?php

namespace XFMG\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Comment extends AbstractHandler
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
			case 'message':
				return 'edit';

			case 'comment_state':
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
		}

		return false;
	}

	protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
	{
		/** @var \XFMG\Entity\Comment $content */
		$log->content_user_id = $content->user_id;
		$log->content_username = $content->username;
		$log->content_title = $content->Content->title ?: '';
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:media/comments', $content);
		$log->discussion_content_type = $content->content_type;
		$log->discussion_content_id = $content->content_id;
	}

	public function getContentTitle(ModeratorLog $log)
	{
		if ($log->discussion_content_type == 'xfmg_media')
		{
			return \XF::phrase('xfmg_comment_by_x_in_media_y', [
				'user' => $log->content_username,
				'title' => \XF::app()->stringFormatter()->censorText($log->content_title_)
			]);
		}
		else if ($log->discussion_content_type == 'xfmg_album')
		{
			return \XF::phrase('xfmg_comment_by_x_in_album_y', [
				'user' => $log->content_username,
				'title' => \XF::app()->stringFormatter()->censorText($log->content_title_)
			]);
		}
		else
		{
			return \XF::phrase('xfmg_comment_by_x', [
				'user' => $log->content_username
			]);
		}
	}
}