<?php

namespace XFMG\PreRegAction;

use XF\Entity\PreRegAction;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

abstract class AbstractCommentHandler extends \XF\PreRegAction\AbstractHandler
{
	public function getDefaultActionData(): array
	{
		return [
			'message' => ''
		];
	}

	protected function canCompleteAction(PreRegAction $action, Entity $containerContent, User $newUser): bool
	{
		/** @var \XFMG\Entity\MediaItem|\XFMG\Entity\Album $containerContent */
		return $containerContent->canAddComment();
	}

	protected function executeAction(PreRegAction $action, Entity $containerContent, User $newUser)
	{
		/** @var \XFMG\Entity\MediaItem|\XFMG\Entity\Album $containerContent */

		$creator = $this->setupCommentCreate($action, $containerContent);
		$creator->checkForSpam();

		if (!$creator->validate())
		{
			return null;
		}

		$comment = $creator->save();

		if ($containerContent->content_type == 'xfmg_media')
		{
			\XF::repository('XFMG:MediaWatch')->autoWatchMediaItem($containerContent, $newUser, false);
		}
		else
		{
			\XF::repository('XFMG:AlbumWatch')->autoWatchAlbum($containerContent, $newUser, false);
		}

		$creator->sendNotifications();

		return $comment;
	}

	protected function setupCommentCreate(
		PreRegAction $action,
		\XF\Mvc\Entity\Entity $containerContent
	): \XFMG\Service\Comment\Creator
	{
		$creator = \XF::app()->service('XFMG:Comment\Creator', $containerContent);
		$creator->setMessage($action->action_data['message']);
		$creator->logIp($action->ip_address);

		return $creator;
	}

	protected function sendSuccessAlert(
		PreRegAction $action,
		Entity $containerContent,
		User $newUser,
		Entity $executeContent
	)
	{
		if (!($executeContent instanceof \XFMG\Entity\Comment))
		{
			return;
		}

		/** @var \XFMG\Entity\Comment $comment */
		$comment = $executeContent;

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = \XF::repository('XF:UserAlert');

		$alertRepo->alertFromUser(
			$newUser, null,
			'xfmg_comment', $comment->comment_id,
			'pre_reg',
			['welcome' => $action->isForNewUser()],
			['autoRead' => false]
		);
	}

	protected function getStructuredContentData(PreRegAction $preRegAction, Entity $containerContent): array
	{
		/** @var \XFMG\Entity\MediaItem|\XFMG\Entity\Album $containerContent */

		$phrase = $containerContent->content_type == 'xfmg_media' ?
			'xfmg_comment_on_media_x' : 'xfmg_comment_on_album_x';

		return [
			'title' => \XF::phrase($phrase, [
				'title' => $containerContent->title
			]),
			'title_link' => $containerContent->getContentUrl(),
			'bb_code' => $preRegAction->action_data['message']
		];
	}
}