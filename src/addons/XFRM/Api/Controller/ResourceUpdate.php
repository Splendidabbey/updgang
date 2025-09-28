<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class ResourceUpdate extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	public function actionGet(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		$result = [
			'update' => $update->toApiResult(Entity::VERBOSITY_VERBOSE, ['with_resource' => true])
		];
		return $this->apiResult($result);
	}

	public function actionPost(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		if (\XF::isApiCheckingPermissions() && !$update->canEdit($error))
		{
			return $this->noPermission($error);
		}

		if ($update->isDescription())
		{
			return $this->noPermission();
		}

		$editor = $this->setupUpdateEdit($update);

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
			'update' => $update->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XFRM\Entity\ResourceUpdate $update
	 *
	 * @return \XFRM\Service\ResourceUpdate\Edit
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupUpdateEdit(\XFRM\Entity\ResourceUpdate $update)
	{
		$input = $this->filter([
			'title' => '?str',
			'message' => '?str',

			'author_alert' => 'bool',
			'author_alert_reason' => 'str',

			'attachment_key' => 'str'
		]);

		/** @var \XFRM\Service\ResourceUpdate\Edit $editor */
		$editor = $this->service('XFRM:ResourceUpdate\Edit', $update);

		if (isset($input['message']))
		{
			$editor->setMessage($input['message']);
		}
		if (isset($input['title']))
		{
			$editor->setTitle($input['title']);
		}
		if (\XF::isApiBypassingPermissions() || $update->Resource->Category->canUploadAndManageUpdateImages())
		{
			$hash = $this->getAttachmentTempHashFromKey(
				$input['attachment_key'],
				'resource_update',
				['resource_update_id' => $update->resource_update_id]
			);
			$editor->setAttachmentHash($hash);
		}

		if ($this->filter('author_alert', 'bool') && $update->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	public function actionDelete(ParameterBag $params)
	{
		$update = $this->assertViewableUpdate($params->resource_update_id);

		if (\XF::isApiCheckingPermissions() && !$update->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($update->isDescription())
		{
			return $this->noPermission();
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('resource:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$update->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var \XFRM\Service\ResourceUpdate\Delete $deleter */
		$deleter = $this->service('XFRM:ResourceUpdate\Delete', $update);

		if ($this->filter('author_alert', 'bool'))
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param bool $allowDescription
	 * @param string|array $with
	 *
	 * @return \XFRM\Entity\ResourceUpdate
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableUpdate($id, $allowDescription = false, $with = 'api')
	{
		/** @var \XFRM\Entity\ResourceUpdate $update */
		$update = $this->assertViewableApiRecord('XFRM:ResourceUpdate', $id, $with);

		if ($update->isDescription() && !$allowDescription)
		{
			throw $this->exception($this->notFound());
		}

		return $update;
	}
}