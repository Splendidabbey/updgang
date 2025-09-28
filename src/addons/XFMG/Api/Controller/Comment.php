<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Comment extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media');
	}

	public function actionGet(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		$result = $comment->toApiResult(Entity::VERBOSITY_VERBOSE, [
			'with_container' => true
		]);

		return $this->apiResult(['comment' => $result]);
	}

	public function actionPost(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		if (\XF::isApiCheckingPermissions() && !$comment->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupCommentEdit($comment);

		if (\XF::isApiCheckingPermissions())
		{
			$editor->checkForSpam();
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		return $this->apiSuccess([
			'comment' => $comment->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XFMG\Entity\Comment $comment
	 *
	 * @return \XFMG\Service\Comment\Editor
	 */
	protected function setupCommentEdit(\XFMG\Entity\Comment $comment)
	{
		$input = $this->filter([
			'message' => '?str',
			'silent' => 'bool',
			'clear_edit' => 'bool',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str'
		]);

		/** @var \XFMG\Service\Comment\Editor $editor */
		$editor = $this->service('XFMG:Comment\Editor', $comment);

		if ($input['message'] !== null)
		{
			if ($input['silent'] && (\XF::isApiBypassingPermissions() || $comment->canEditSilently()))
			{
				$editor->logEdit(false);
				if ($input['clear_edit'])
				{
					$comment->last_edit_date = 0;
				}
			}

			$editor->setMessage($input['message']);
		}

		if ($input['author_alert'] && $comment->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	public function actionPostReact(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		/** @var \XF\Api\ControllerPlugin\Reaction $reactPlugin */
		$reactPlugin = $this->plugin('XF:Api:Reaction');
		return $reactPlugin->actionReact($comment);
	}

	public function actionDelete(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		if (\XF::isApiCheckingPermissions() && !$comment->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('media:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$comment->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var \XFMG\Service\Comment\Deleter $deleter */
		$deleter = $this->service('XFMG:Comment\Deleter', $comment);

		if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFMG\Entity\Comment
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableComment($id, $with = 'api|container')
	{
		return $this->assertViewableApiRecord('XFMG:Comment', $id, $with);
	}
}