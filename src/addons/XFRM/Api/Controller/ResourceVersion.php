<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class ResourceVersion extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	public function actionGet(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);

		$result = [
			'version' => $version->toApiResult(Entity::VERBOSITY_VERBOSE, ['with_resource' => true])
		];
		return $this->apiResult($result);
	}

	public function actionGetDownload(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);

		if (\XF::isApiCheckingPermissions() && !$version->canDownload($error))
		{
			return $this->error($error);
		}

		if ($version->download_url)
		{
			return $this->redirect($version->download_url);
		}

		$this->assertRequiredApiInput('file');

		$fileId = $this->filter('file', 'uint');
		if (!$fileId || !isset($version->Attachments[$fileId]))
		{
			return $this->notFound();
		}

		$file = $version->Attachments[$fileId];

		/** @var \XF\ControllerPlugin\Attachment $attachPlugin */
		$attachPlugin = $this->plugin('XF:Attachment');

		return $attachPlugin->displayAttachment($file);
	}

	public function actionDelete(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);

		if (\XF::isApiCheckingPermissions() && !$version->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('resource:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$version->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$version->delete();
		}
		else
		{
			$reason = $this->filter('reason', 'str');
			$version->softDelete($reason);
		}

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFRM\Entity\ResourceVersion
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableVersion($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFRM:ResourceVersion', $id, $with);
	}
}