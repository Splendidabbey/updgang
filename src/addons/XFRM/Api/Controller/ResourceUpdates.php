<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class ResourceUpdates extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	public function actionPost()
	{
		$this->assertRequiredApiInput(['resource_id', 'title', 'message']);

		$resourceId = $this->filter('resource_id', 'uint');

		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $this->assertViewableApiRecord('XFRM:ResourceItem', $resourceId);

		if (\XF::isApiCheckingPermissions() && !$resource->canReleaseUpdate($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupResourceUpdate($resource);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var \XFRM\Entity\ResourceUpdate $update */
		$update = $creator->save();

		$this->finalizeResourceUpdate($creator);

		return $this->apiSuccess([
			'update' => $update->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XFRM\Entity\ResourceItem $resource
	 *
	 * @return \XFRM\Service\ResourceUpdate\Create
	 */
	protected function setupResourceUpdate(\XFRM\Entity\ResourceItem $resource)
	{
		/** @var \XFRM\Service\ResourceUpdate\Create $creator */
		$creator = $this->service('XFRM:ResourceUpdate\Create', $resource);

		$input = $this->filter([
			'title' => 'str',
			'message' => 'str',
			'attachment_key' => 'str'
		]);

		$creator->setMessage($input['message']);
		$creator->setTitle($input['title']);

		/** @var \XFRM\Entity\Category $category */
		$category = $resource->Category;
		if (\XF::isApiBypassingPermissions() || $category->canUploadAndManageUpdateImages())
		{
			$hash = $this->getAttachmentTempHashFromKey(
				$input['attachment_key'],
				'resource_update',
				['resource_id' => $resource->resource_id]
			);

			$creator->setAttachmentHash($hash);
		}

		return $creator;
	}

	protected function finalizeResourceUpdate(\XFRM\Service\ResourceUpdate\Create $creator)
	{
		$creator->sendNotifications();
	}
}