<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class ResourceItems extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	public function actionGet()
	{
		$page = $this->filterPage();
		$perPage = $this->options()->xfrmResourcesPerPage;

		$finder = $this->setupResourceFinder()->limitByPage($page, $perPage);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$resources = $finder->fetch();

		if (\XF::isApiCheckingPermissions())
		{
			$resources = $resources->filterViewable();
		}

		return $this->apiResult([
			'resources' => $resources->toApiResults(),
			'pagination' => $this->getPaginationData($resources, $page, $perPage, $total)
		]);
	}

	/**
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return \XFRM\Finder\ResourceItem
	 */
	protected function setupResourceFinder(&$filters = [], &$sort = null)
	{
		$repo = $this->repository('XFRM:ResourceItem');
		$finder = $repo->findResourcesForApi();

		/** @var \XFRM\Api\ControllerPlugin\ResourceItem $plugin */
		$plugin = $this->plugin('XFRM:Api:ResourceItem');

		$filters = $plugin->applyResourceListFilters($finder);
		$sort = $plugin->applyResourceListSort($finder);

		return $finder;
	}

	public function actionPost()
	{
		$this->assertRequiredApiInput(['resource_category_id', 'title', 'tag_line', 'description', 'resource_type']);

		$categoryId = $this->filter('resource_category_id', 'uint');

		/** @var \XFRM\Entity\Category $category */
		$category = $this->assertViewableApiRecord('XFRM:Category', $categoryId);

		if (\XF::isApiCheckingPermissions() && !$category->canAddResource($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupResourceCreate($category);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $creator->save();
		$this->finalizeResourceCreate($creator);

		return $this->apiSuccess([
			'resource' => $resource->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XFRM\Entity\Category $category
	 *
	 * @return \XFRM\Service\ResourceItem\Create
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupResourceCreate(\XFRM\Entity\Category $category)
	{
		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'prefix_id' => 'uint',
			'version_string' => 'str',
			'resource_type' => 'str',
			'custom_fields' => 'array',
			'tags' => 'array-str',
			'description_attachment_key' => 'str',
			'version_attachment_key' => 'str'
		]);

		$bulkInput = $this->filter([
			'tag_line' => 'str',
			'external_url' => 'str',
			'alt_support_url' => 'str'
		]);

		$isBypassingPermissions = \XF::isApiBypassingPermissions();

		/** @var \XFRM\Service\ResourceItem\Create $creator */
		$creator = $this->service('XFRM:ResourceItem\Create', $category);

		$creator->setContent($input['title'], $input['description']);
		$creator->setVersionString($input['version_string'], true);
		$creator->setCustomFields($input['custom_fields']);
		$creator->getResource()->bulkSet($bulkInput);

		if ($input['prefix_id'] && ($isBypassingPermissions || $category->isPrefixUsable($input['prefix_id'])))
		{
			$creator->setPrefix($input['prefix_id']);
		}

		if ($category->canEditTags())
		{
			$creator->setTags($input['tags']);
		}

		if ($isBypassingPermissions || $category->canUploadAndManageUpdateImages())
		{
			$hash = $this->getAttachmentTempHashFromKey(
				$input['description_attachment_key'],
				'resource_update',
				['resource_category_id' => $category->resource_category_id]
			);

			$creator->setDescriptionAttachmentHash($hash);
		}

		switch ($input['resource_type'])
		{
			case 'download_local':
				if ($category->allow_local)
				{
					$this->assertRequiredApiInput('version_attachment_key');

					$hash = $this->getAttachmentTempHashFromKey(
						$input['version_attachment_key'],
						'resource_version',
						['resource_category_id' => $category->resource_category_id]
					);

					$creator->setLocalDownload($hash);
				}
				break;

			case 'download_external':
				if ($category->allow_external)
				{
					$this->assertRequiredApiInput('external_download_url');

					$creator->setExternalDownload($this->filter('external_download_url', 'str'));
				}
				break;

			case 'external_purchase':
				if ($category->allow_commercial_external)
				{
					$this->assertRequiredApiInput(['price', 'currency', 'external_purchase_url']);

					$purchaseInput = $this->filter([
						'price' => 'num',
						'currency' => 'str',
						'external_purchase_url' => 'str'
					]);

					$creator->setExternalPurchasable(
						$purchaseInput['price'], $purchaseInput['currency'], $purchaseInput['external_purchase_url']
					);
				}
				break;

			case 'fileless':
				if ($category->allow_fileless)
				{
					$creator->setFileless();
				}
				break;
		}

		return $creator;
	}

	protected function finalizeResourceCreate(\XFRM\Service\ResourceItem\Create $creator)
	{
		$creator->sendNotifications();
	}
}