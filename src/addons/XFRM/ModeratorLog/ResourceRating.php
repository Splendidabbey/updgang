<?php

namespace XFRM\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceRating extends AbstractHandler
{
	public function isLoggable(Entity $content, $action, \XF\Entity\User $actor)
	{
		switch ($action)
		{
			case 'edit':
				/** @var \XFRM\Entity\ResourceRating $content */
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
			case 'rating_state':
				if ($newValue == 'visible' && $oldValue == 'deleted')
				{
					return 'undelete';
				}
				else if ($newValue == 'deleted')
				{
					$reason = $content->DeletionLog ? $content->DeletionLog->delete_reason : '';
					return ['delete_soft', ['reason' => $reason]];
				}
				break;
		}

		return false;
	}

	protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
	{
		/** @var \XFRM\Entity\ResourceRating $content */
		$resource = $content->Resource;

		$log->content_user_id = $content->user_id;
		$log->content_username = $content->User ? $content->User->username : '';
		$log->content_title = $resource->title ?: '';
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:resources/review', $content);
		$log->discussion_content_type = 'resource';
		$log->discussion_content_id = $content->resource_id;
	}

	public function getContentTitle(ModeratorLog $log)
	{
		return \XF::phrase('xfrm_resource_review_in_x', [
			'title' => \XF::app()->stringFormatter()->censorText($log->content_title_)
		]);
	}
}