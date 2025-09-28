<?php

namespace XFRM\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceUpdate extends AbstractHandler
{
	public function isLoggable(Entity $content, $action, \XF\Entity\User $actor)
	{
		switch ($action)
		{
			case 'edit':
			case 'attachment_deleted':
				/** @var \XFRM\Entity\ResourceUpdate $content */
				if (
					$content->Resource &&
					$content->Resource->isTeamMember($actor->user_id)
				)
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
			case 'message':
				return 'edit';

			case 'message_state':
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
		/** @var \XFRM\Entity\ResourceUpdate $content */
		$resource = $content->Resource;

		$log->content_user_id = $resource->user_id;
		$log->content_username = $resource->username;
		$log->content_title = $resource->title ?: '';
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:resources/update', $content);
		$log->discussion_content_type = 'resource';
		$log->discussion_content_id = $content->resource_id;
	}

	public function getContentTitle(ModeratorLog $log)
	{
		return \XF::phrase('xfrm_resource_update_in_x', [
			'title' => \XF::app()->stringFormatter()->censorText($log->content_title_)
		]);
	}
}