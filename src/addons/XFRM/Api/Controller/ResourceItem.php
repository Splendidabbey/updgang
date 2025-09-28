<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class ResourceItem extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	public function actionGet(ParameterBag $params)
	{
		$resource = $this->assertViewableResource($params->resource_id);

		$result = [
			'resource' => $resource->toApiResult(Entity::VERBOSITY_VERBOSE)
		];
		return $this->apiResult($result);
	}

	public function actionGetUpdates(ParameterBag $params)
	{
		$resource = $this->assertViewableResource($params->resource_id);

		$page = $this->filterPage();
		$perPage = $this->options()->xfrmUpdatesPerPage;

		$finder = $this->setupUpdateFinder($resource);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$updates = $finder->limitByPage($page, $perPage)->fetch();

		/** @var \XF\Repository\Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		$attachmentRepo->addAttachmentsToContent($updates, 'resource_update');

		$updateResults = $updates->toApiResults();

		return $this->apiResult([
			'updates' => $updateResults,
			'pagination' => $this->getPaginationData($updateResults, $page, $perPage, $total)
		]);
	}

	/**
	 * @param \XFRM\Entity\ResourceItem $resource
	 *
	 * @return \XFRM\Finder\ResourceUpdate
	 */
	protected function setupUpdateFinder(\XFRM\Entity\ResourceItem $resource)
	{
		/** @var \XFRM\Finder\ResourceUpdate $finder */
		$finder = $this->finder('XFRM:ResourceUpdate');
		$finder
			->inResource($resource)
			->setDefaultOrder('post_date', 'desc')
			->with('api');

		return $finder;
	}

	public function actionGetReviews(ParameterBag $params)
	{
		$this->assertApiScope('resource_rating:read');

		$resource = $this->assertViewableResource($params->resource_id);

		$page = $this->filterPage();
		$perPage = $this->options()->xfrmReviewsPerPage;

		$finder = $this->setupReviewFinder($resource);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$reviews = $finder->limitByPage($page, $perPage)->fetch();
		$reviewResults = $reviews->toApiResults();

		return $this->apiResult([
			'reviews' => $reviewResults,
			'pagination' => $this->getPaginationData($reviewResults, $page, $perPage, $total)
		]);
	}

	/**
	 * @param \XFRM\Entity\ResourceItem $resource
	 *
	 * @return \XFRM\Finder\ResourceRating
	 */
	protected function setupReviewFinder(\XFRM\Entity\ResourceItem $resource)
	{
		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->finder('XFRM:ResourceRating');
		$finder
			->inResource($resource)
			->where('is_review', 1)
			->setDefaultOrder('rating_date', 'desc')
			->with('api');

		return $finder;
	}

	public function actionGetVersions(ParameterBag $params)
	{
		$resource = $this->assertViewableResource($params->resource_id);

		if (!$resource->isVersioned())
		{
			return $this->error(\XF::phrase('xfrm_this_resource_is_not_versioned'));
		}

		$versions = $this->setupVersionFinder($resource)->fetch();

		return $this->apiResult([
			'versions' => $versions->toApiResults()
		]);
	}

	/**
	 * @param \XFRM\Entity\ResourceItem $resource
	 *
	 * @return \XFRM\Finder\ResourceVersion
	 */
	protected function setupVersionFinder(\XFRM\Entity\ResourceItem $resource)
	{
		/** @var \XFRM\Finder\ResourceVersion $finder */
		$finder = $this->finder('XFRM:ResourceVersion');
		$finder
			->inResource($resource)
			->setDefaultOrder('release_date', 'desc')
			->with('api');

		return $finder;
	}

	public function actionPost(ParameterBag $params)
	{
		$resource = $this->assertViewableResource($params->resource_id);

		if (\XF::isApiCheckingPermissions() && !$resource->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupResourceEdit($resource);

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
			'resource' => $resource->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XFRM\Entity\ResourceItem $resource
	 *
	 * @return \XFRM\Service\ResourceItem\Edit
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupResourceEdit(\XFRM\Entity\ResourceItem $resource)
	{
		$input = $this->filter([
			'prefix_id' => '?uint',
			'title' => '?str',
			'version_string' => '?str',
			'description' => '?str',
			'external_download_url' => '?str',

			'custom_fields' => 'array',

			'add_tags' => 'array-str',
			'remove_tags' => 'array-str',

			'author_alert' => 'bool',
			'author_alert_reason' => 'str',

			'attachment_key' => 'str'
		]);

		/** @var \XFRM\Service\ResourceItem\Edit $editor */
		$editor = $this->service('XFRM:ResourceItem\Edit', $resource);

		$isBypassingPermissions = \XF::isApiBypassingPermissions();
		$isCheckingPermissions = \XF::isApiCheckingPermissions();

		if (isset($input['prefix_id']))
		{
			$prefixId = $input['prefix_id'];
			if ($prefixId != $resource->prefix_id
				&& $isCheckingPermissions
				&& !$resource->Category->isPrefixUsable($input['prefix_id'])
			)
			{
				$prefixId = 0; // not usable, just blank it out
			}
			$editor->setPrefix($prefixId);
		}

		if (isset($input['title']))
		{
			$editor->setTitle($input['title']);
		}

		if ($resource->isVersioned() && isset($input['version_string']))
		{
			$currentVersion = $resource->CurrentVersion;
			if ($currentVersion && ($currentVersion->canEditVersionString() || $isBypassingPermissions))
			{
				$editor->setVersionString($input['version_string']);
			}
		}

		if ($input['custom_fields'])
		{
			$editor->setCustomFields($input['custom_fields'], true);
		}

		if ($isBypassingPermissions || $resource->canEditTags())
		{
			if ($input['add_tags'])
			{
				$editor->addTags($input['add_tags']);
			}
			if ($input['remove_tags'])
			{
				$editor->removeTags($input['remove_tags']);
			}
		}

		$basicFields = $this->filter([
			'tag_line' => '?str',
			'external_url' => '?str',
			'alt_support_url' => '?str',
		]);
		$basicFields = \XF\Util\Arr::filterNull($basicFields);
		$resource->bulkSet($basicFields);

		if ($resource->isExternalPurchasable())
		{
			$purchaseFields = $this->filter([
				'price' => '?num',
				'currency' => '?str',
				'external_purchase_url' => '?str',
			]);
			$editor->setExternalPurchaseData(
				isset($purchaseFields['price']) ? $purchaseFields['price'] : $resource->price,
				isset($purchaseFields['currency']) ? $purchaseFields['currency'] : $resource->currency
			);

			if (isset($purchaseFields['external_purchase_url']))
			{
				$resource->external_purchase_url = $purchaseFields['external_purchase_url'];
			}
		}
		else if ($resource->isExternalDownload() && isset($input['external_download_url']))
		{
			$editor->setExternalDownloadUrl($input['external_download_url']);
		}

		$descriptionEditor = $editor->getDescriptionEditor();

		if (isset($input['description']))
		{
			$descriptionEditor->setMessage($input['description']);
		}

		if ($isBypassingPermissions || $resource->Category->canUploadAndManageUpdateImages())
		{
			$hash = $this->getAttachmentTempHashFromKey(
				$input['attachment_key'],
				'resource_update',
				['resource_id' => $resource->resource_id]
			);
			$descriptionEditor->setAttachmentHash($hash);
		}

		if ($input['author_alert'] && $resource->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	public function actionDelete(ParameterBag $params)
	{
		$resource = $this->assertViewableResource($params->resource_id);

		if (\XF::isApiCheckingPermissions() && !$resource->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('resource:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$resource->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var \XFRM\Service\ResourceItem\Delete $deleter */
		$deleter = $this->service('XFRM:ResourceItem\Delete', $resource);

		if ($this->filter('author_alert', 'bool'))
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
	 * @return \XFRM\Entity\ResourceItem
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableResource($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFRM:ResourceItem', $id, $with);
	}
}