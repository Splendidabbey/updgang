<?php

namespace XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class ResourceVersion extends AbstractController
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
		$version = $this->assertViewableVersion($params->resource_version_id);

		return $this->redirect($this->buildLink(
			$version->isCurrentVersion() ? 'resources' : 'resources/history',
			$version->Resource
		));
	}

	public function actionDownload(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);
		if (!$version->isDownloadable())
		{
			return $this->redirect($this->buildLink('resources', $version->Resource));
		}

		if (!$version->canDownload($error))
		{
			return $this->noPermission($error);
		}

		$resource = $version->Resource;

		/** @var \XF\Entity\Attachment|null $attachment */
		$attachment = null;

		if (!$version->download_url)
		{
			$attachments = $version->getRelationFinder('Attachments')->fetch();

			$file = $this->filter('file', 'uint');
			if ($attachments->count() == 0)
			{
				return $this->error(\XF::phrase('attachment_cannot_be_shown_at_this_time'));
			}
			else if ($attachments->count() == 1)
			{
				$attachment = $attachments->first();
			}
			else if ($file && isset($attachments[$file]))
			{
				$attachment = $attachments[$file];
			}

			if (!$attachment)
			{
				$viewParams = [
					'resource' => $resource,
					'version' => $version,
					'files' => $attachments
				];
				return $this->view('XFRM:ResourceVersion\DownloadChooser', 'xfrm_resource_download_chooser', $viewParams);

			}
		}

		$visitor = \XF::visitor();

		if ($visitor->user_id != $resource->user_id)
		{
			$this->repository('XFRM:ResourceWatch')->autoWatchResource($version->Resource, \XF::visitor());
		}

		$this->repository('XFRM:ResourceVersion')->logDownload($version);

		if ($version->download_url)
		{
			return $this->redirectPermanently($version->download_url);
		}
		else
		{
			/** @var \XF\ControllerPlugin\Attachment $attachPlugin */
			$attachPlugin = $this->plugin('XF:Attachment');

			return $attachPlugin->displayAttachment($attachment);
		}
	}

	public function actionDelete(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);
		if (!$version->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$version->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			if ($type == 'soft')
			{
				$version->softDelete($reason);
			}
			else
			{
				$version->delete();
			}

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('resources/history', $version->Resource), false)
			);
		}
		else
		{
			$viewParams = [
				'version' => $version,
				'resource' => $version->Resource
			];
			return $this->view('XFRM:ResourceVersion\Delete', 'xfrm_resource_version_delete', $viewParams);
		}
	}

	/**
	 * @param $resourceVersionId
	 * @param array $extraWith
	 *
	 * @return \XFRM\Entity\ResourceVersion
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableVersion($resourceVersionId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Resource';
		$extraWith[] = 'Resource.User';
		$extraWith[] = 'Resource.Category';
		$extraWith[] = 'Resource.Category.Permissions|' . $visitor->permission_combination_id;

		/** @var \XFRM\Entity\ResourceVersion $version */
		$version = $this->em()->find('XFRM:ResourceVersion', $resourceVersionId, $extraWith);
		if (!$version)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfrm_requested_version_not_found')));
		}

		if (!$version->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin('XFRM:Category')->applyCategoryContext($version->Resource->Category);

		return $version;
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('xfrm_viewing_resources');
	}
}