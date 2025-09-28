<?php

namespace XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class ResourceUpdate extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewResources($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	public function actionIndex(ParameterBag $params)
	{
		$updateId = $params->resource_update_id;
		if (!$updateId)
		{
			$updateId = $this->filter('update', 'uint');
		}

		$update = $this->assertViewableUpdate($updateId);

		return $this->redirectToUpdate($update);
	}

	/**
	 * @param \XFRM\Entity\ResourceUpdate $update
	 *
	 * @return \XFRM\Service\ResourceUpdate\Edit
	 */
	protected function setupUpdateEdit(\XFRM\Entity\ResourceUpdate $update)
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var \XFRM\Service\ResourceUpdate\Edit $editor */
		$editor = $this->service('XFRM:ResourceUpdate\Edit', $update);
		$editor->setMessage($message);
		$editor->setTitle($this->filter('title', 'str'));

		/** @var \XFRM\Entity\Category $category */
		$category = $update->Resource->Category;
		if ($category->canUploadAndManageUpdateImages())
		{
			$editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		if ($this->filter('author_alert', 'bool') && $update->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	protected function finalizeUpdateEdit(\XFRM\Service\ResourceUpdate\Edit $editor)
	{

	}

	public function actionEdit(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);
		if (!$update->canEdit($error))
		{
			return $this->noPermission($error);
		}

		if ($update->isDescription())
		{
			return $this->redirectPermanently(
				$this->buildLink('resources/edit', $update->Resource)
			);
		}

		if ($this->isPost())
		{
			$editor = $this->setupUpdateEdit($update);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();

			$this->finalizeUpdateEdit($editor);

			if ($this->filter('_xfWithData', 'bool'))
			{
				$viewParams = [
					'update' => $update,
					'resource' => $update->Resource
				];
				$reply = $this->view('XFRM:ResourceUpdate\EditNewValue', 'xfrm_resource_update_edit_new_value', $viewParams);
				$reply->setJsonParams([
					'message' => \XF::phrase('your_changes_have_been_saved')
				]);
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('resources/update', $update));
			}
		}
		else
		{
			$category = $update->Resource->Category;
			if ($category && $category->canUploadAndManageUpdateImages())
			{
				/** @var \XF\Repository\Attachment $attachmentRepo */
				$attachmentRepo = $this->repository('XF:Attachment');
				$attachmentData = $attachmentRepo->getEditorData('resource_update', $update);
			}
			else
			{
				$attachmentData = null;
			}

			$viewParams = [
				'update' => $update,
				'resource' => $update->Resource,
				'attachmentData' => $attachmentData,
				'quickEdit' => $this->filter('_xfWithData', 'bool')
			];
			return $this->view('XFRM:ResourceUpdate\Edit', 'xfrm_resource_update_edit', $viewParams);
		}
	}

	public function actionPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$update = $this->assertViewableUpdate($params->resource_update_id);
		if (!$update->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupUpdateEdit($update);

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$attachments = [];
		$tempHash = $this->filter('attachment_hash', 'str');

		$category = $update->Resource->Category;
		if ($category && $category->canUploadAndManageUpdateImages())
		{
			/** @var \XF\Repository\Attachment $attachmentRepo */
			$attachmentRepo = $this->repository('XF:Attachment');
			$attachmentData = $attachmentRepo->getEditorData('resource_update', $update, $tempHash);
			$attachments = $attachmentData['attachments'];
		}

		$resource = $update->Resource;

		return $this->plugin('XF:BbCodePreview')->actionPreview(
			$update->message, 'resource_update', $resource->User, $attachments, $resource->canViewUpdateImages()
		);
	}

	public function actionDelete(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);
		if (!$update->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$update->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \XFRM\Service\ResourceUpdate\Delete $deleter */
			$deleter = $this->service('XFRM:ResourceUpdate\Delete', $update);

			if ($this->filter('author_alert', 'bool') && $update->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('resources', $update->Resource), false)
			);
		}
		else
		{
			$viewParams = [
				'update' => $update,
				'resource' => $update->Resource
			];
			return $this->view('XFRM:ResourceUpdate\Delete', 'xfrm_resource_update_delete', $viewParams);
		}
	}

	public function actionUndelete(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		/** @var \XF\ControllerPlugin\Undelete $plugin */
		$plugin = $this->plugin('XF:Undelete');
		return $plugin->actionUndelete(
			$update,
			$this->buildLink('resources/update/undelete', $update),
			$this->buildLink('resources/update', $update),
			$update->title,
			'message_state'
		);
	}

	public function actionReport(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);
		if (!$update->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'resource_update', $update,
			$this->buildLink('resources/update/report', $update),
			$this->buildLink('resources/update', $update)
		);
	}

	public function actionHistory(ParameterBag $params)
	{
		return $this->rerouteController('XF:EditHistory', 'index', [
			'content_type' => 'resource_update',
			'content_id' => $params->resource_update_id
		]);
	}

	public function actionBookmark(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		/** @var \XF\ControllerPlugin\Bookmark $bookmarkPlugin */
		$bookmarkPlugin = $this->plugin('XF:Bookmark');

		return $bookmarkPlugin->actionBookmark(
			$update, $this->buildLink('resources/update/bookmark', $update)
		);
	}

	public function actionReact(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($update, 'resources/update');
	}

	public function actionReactions(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);
		$resource = $update->Resource;

		$breadcrumbs = $resource->Category->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactions(
			$update,
			'resources/update/reactions',
			null, $breadcrumbs
		);
	}

	public function actionIp(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);
		$breadcrumbs = $update->Resource->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($update, $breadcrumbs);
	}

	public function actionWarn(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		if (!$update->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$resource = $update->Resource;
		$breadcrumbs = $resource->Category->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'resource_update', $update,
			$this->buildLink('resources/update/warn', $update),
			$breadcrumbs
		);
	}

	public function actionApprove(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$update = $this->assertViewableUpdate($params->resource_update_id);
		if (!$update->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XFRM\Service\ResourceUpdate\Approve $approver */
		$approver = \XF::service('XFRM:ResourceUpdate\Approve', $update);
		$approver->approve();

		return $this->redirect($this->buildLink('resources/update', $update));
	}

	public function actionUnapprove(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$update = $this->assertViewableUpdate($params->resource_update_id);
		if (!$update->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		$update->message_state = 'moderated';
		$update->save();

		return $this->redirect($this->buildLink('resources/update', $update));
	}

	protected function redirectToUpdate(\XFRM\Entity\ResourceUpdate $update)
	{
		$resource = $update->Resource;

		if ($update->isDescription())
		{
			return $this->redirectPermanently($this->buildLink('resources', $resource));
		}

		$newerFinder = $this->getUpdateRepo()->findUpdatesInResource($resource);
		$newerFinder->where('post_date', '>', $update->post_date);
		$totalNewer = $newerFinder->total();

		$perPage = $this->options()->xfrmUpdatesPerPage;
		$page = ceil(($totalNewer + 1) / $perPage);

		if ($page > 1)
		{
			$params = ['page' => $page];
		}
		else
		{
			$params = [];
		}

		return $this->redirect(
			$this->buildLink('resources/updates', $resource, $params)
			. '#resource-update-' . $update->resource_update_id
		);
	}

	/**
	 * @param $resourceUpdateId
	 * @param array $extraWith
	 *
	 * @return \XFRM\Entity\ResourceUpdate
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableUpdate($resourceUpdateId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Resource';
		$extraWith[] = 'Resource.User';
		$extraWith[] = 'Resource.Category';
		$extraWith[] = 'Resource.Category.Permissions|' . $visitor->permission_combination_id;

		/** @var \XFRM\Entity\ResourceUpdate $update */
		$update = $this->em()->find('XFRM:ResourceUpdate', $resourceUpdateId, $extraWith);
		if (!$update)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfrm_requested_update_not_found')));
		}

		if (!$update->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin('XFRM:Category')->applyCategoryContext($update->Resource->Category);

		return $update;
	}

	/**
	 * @return \XFRM\Repository\ResourceUpdate
	 */
	protected function getUpdateRepo()
	{
		return $this->repository('XFRM:ResourceUpdate');
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('xfrm_viewing_resources');
	}
}