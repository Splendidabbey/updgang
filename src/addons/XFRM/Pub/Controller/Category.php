<?php

namespace XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Category extends AbstractController
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
		$this->assertNotEmbeddedImageRequest();

		$category = $this->assertViewableCategory($params->resource_category_id, $this->getCategoryViewExtraWith());

		/** @var \XFRM\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('XFRM:Overview');

		$categoryParams = $overviewPlugin->getCategoryListData($category);

		/** @var \XF\Tree $categoryTree */
		$categoryTree = $categoryParams['categoryTree'];
		$descendants = $categoryTree->getDescendants($category->resource_category_id);

		$sourceCategoryIds = array_keys($descendants);
		$sourceCategoryIds[] = $category->resource_category_id;

		// for any contextual widget
		$category->cacheViewableDescendents($descendants);

		$listParams = $overviewPlugin->getCoreListData($sourceCategoryIds);

		$this->assertValidPage(
			$listParams['page'],
			$listParams['perPage'],
			$listParams['total'],
			'resources/categories',
			$category
		);
		$this->assertCanonicalUrl($this->buildPaginatedLink('resources/categories', $category, $listParams['page']));

		$viewParams = [
			'category' => $category,
			'pendingApproval' => $this->filter('pending_approval', 'bool'),
			'iconError' => $this->filter('icon_error', 'bool')
		];
		$viewParams += $categoryParams + $listParams;

		return $this->view('XFRM:Category\View', 'xfrm_category_view', $viewParams);
	}

	protected function getCategoryViewExtraWith()
	{
		$extraWith = [];
		$userId = \XF::visitor()->user_id;
		if ($userId)
		{
			$extraWith[] = 'Watch|' . $userId;
		}

		return $extraWith;
	}

	public function actionFilters(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->resource_category_id);

		/** @var \XFRM\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('XFRM:Overview');

		return $overviewPlugin->actionFilters($category);
	}

	public function actionFeatured(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->resource_category_id);

		/** @var \XFRM\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('XFRM:Overview');

		return $overviewPlugin->actionFeatured($category);
	}

	public function actionWatch(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->resource_category_id);
		if (!$category->canWatch($error))
		{
			return $this->noPermission($error);
		}

		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			if ($this->filter('stop', 'bool'))
			{
				$action = 'delete';
				$config = [];
			}
			else
			{
				$action = 'watch';
				$config = $this->filter([
					'notify_on' => 'str',
					'send_alert' => 'bool',
					'send_email' => 'bool',
					'include_children' => 'bool'
				]);
			}

			/** @var \XFRM\Repository\CategoryWatch $watchRepo */
			$watchRepo = $this->repository('XFRM:CategoryWatch');
			$watchRepo->setWatchState($category, $visitor, $action, $config);

			$redirect = $this->redirect($this->buildLink('resources/categories', $category));
			$redirect->setJsonParam('switchKey', $action == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}
		else
		{
			$viewParams = [
				'category' => $category,
				'isWatched' => !empty($category->Watch[$visitor->user_id])
			];
			return $this->view('XFRM:Category\Watch', 'xfrm_category_watch', $viewParams);
		}
	}

	/**
	 * @param \XFRM\Entity\Category $category
	 *
	 * @return \XFRM\Service\ResourceItem\Create
	 */
	protected function setupResourceCreate(\XFRM\Entity\Category $category)
	{
		$title = $this->filter('title', 'str');

		$description = $this->plugin('XF:Editor')->fromInput('description');

		/** @var \XFRM\Service\ResourceItem\Create $creator */
		$creator = $this->service('XFRM:ResourceItem\Create', $category);

		$creator->setContent($title, $description);

		$prefixId = $this->filter('prefix_id', 'uint');
		if ($prefixId && $category->isPrefixUsable($prefixId))
		{
			$creator->setPrefix($prefixId);
		}

		if ($category->canEditTags())
		{
			$creator->setTags($this->filter('tags', 'str'));
		}

		if ($category->canUploadAndManageUpdateImages())
		{
			$creator->setDescriptionAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		$bulkInput = $this->filter([
			'tag_line' => 'str',
			'external_url' => 'str',
			'alt_support_url' => 'str'
		]);
		$creator->getResource()->bulkSet($bulkInput);

		$versionString = $this->filter('version_string', 'str');
		$creator->setVersionString($versionString, true);

		switch ($this->filter('resource_type', 'str'))
		{
			case 'download_local':
				if ($category->allow_local)
				{
					$creator->setLocalDownload(
						$this->filter('version_attachment_hash', 'str')
					);
				}
				break;

			case 'download_external':
				if ($category->allow_external)
				{
					$creator->setExternalDownload($this->filter('external_download_url', 'str'));
				}
				break;

			case 'external_purchase':
				if ($category->allow_commercial_external)
				{
					$purchaseInput = $this->filter([
						'price' => 'str',
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

		$customFields = $this->filter('custom_fields', 'array');
		$creator->setCustomFields($customFields);

		return $creator;
	}

	protected function finalizeResourceCreate(\XFRM\Service\ResourceItem\Create $creator)
	{
		$creator->sendNotifications();

		$resource = $creator->getResource();

		if (\XF::visitor()->user_id)
		{
			$creator->getCategory()->draft_resource->delete();

			if ($resource->resource_state == 'moderated')
			{
				$this->session()->setHasContentPendingApproval();
			}
		}
	}

	public function actionAdd(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->resource_category_id);
		if (!$category->canAddResource($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$creator = $this->setupResourceCreate($category);
			$creator->checkForSpam();

			if (!$creator->validate($errors))
			{
				return $this->error($errors);
			}
			$this->assertNotFlooding('post');

			/** @var \XFRM\Entity\ResourceItem $resource */
			$resource = $creator->save();
			$this->finalizeResourceCreate($creator);

			$iconError = null;

			if ($resource->canEditIcon())
			{
				try
				{
					/** @var \XFRM\ControllerPlugin\ResourceIcon $plugin */
					$plugin = $this->plugin('XFRM:ResourceIcon');
					$plugin->actionUpload($resource);
				}
				catch (\XF\Mvc\Reply\Exception $e)
				{
					$iconError = true;
				}
			}

			if (!$resource->canView())
			{
				return $this->redirect($this->buildLink('resources/categories', $category, [
					'pending_approval' => 1,
					'icon_error' => $iconError
				]));
			}
			else
			{
				return $this->redirect($this->buildLink('resources', $resource, [
					'icon_error' => $iconError
				]));
			}
		}
		else
		{
			/** @var \XF\Repository\Attachment $attachmentRepo */
			$attachmentRepo = $this->repository('XF:Attachment');

			$draft = $category->draft_resource;

			if ($category->canUploadAndManageUpdateImages())
			{
				$attachmentData = $attachmentRepo->getEditorData('resource_update', $category, $draft->attachment_hash);
			}
			else
			{
				$attachmentData = null;
			}

			$versionAttachData = $attachmentRepo->getEditorData('resource_version', $category, $draft->version_attachment_hash);

			$resource = $category->getNewResource();

			$resource->title = $draft->title ?: '';
			$resource->prefix_id = $draft->prefix_id ?: 0;
			$resource->tag_line = $draft->tag_line ?: '';
			$resource->external_url = $draft->external_url ?: '';
			$resource->alt_support_url = $draft->alt_support_url ?: '';
			$resource->external_purchase_url = $draft->external_purchase_url ?: '';
			$resource->price = $draft->price ?: 0;
			$resource->currency = $draft->currency ?: '';

			if ($draft->custom_fields)
			{
				/** @var \XF\CustomField\Set $customFields */
				$customFields = $resource->custom_fields;
				$customFields->bulkSet($draft->custom_fields, null, 'user', true);
			}

			$viewParams = [
				'category' => $category,
				'resource' => $resource,
				'prefixes' => $category->getUsablePrefixes(),

				'attachmentData' => $attachmentData,
				'versionAttachData' => $versionAttachData
			];
			return $this->view('XFRM:Category\AddResource', 'xfrm_category_add_resource', $viewParams);
		}
	}

	public function actionDraft(ParameterBag $params)
	{
		$this->assertDraftsEnabled();

		$category = $this->assertViewableCategory($params->resource_category_id);
		if (!$category->canAddResource($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupResourceCreate($category);
		$resource = $creator->getResource();

		$fromInput = $this->filter([
			'tags' => 'str',
			'resource_type' => 'str',
			'version_attachment_hash' => 'str',
			'version_string' => 'str',
			'attachment_hash' => 'str',
			'external_download_url' => 'str',
			'external_purchase_url' => 'str',
			'price' => 'str', // note: str because we want this to be blank in many cases
			'currency' => 'str',
			'external_url' => 'str',
			'alt_support_url' =>'str',
		]);

		$extraData = [
			'prefix_id' => $resource->prefix_id,
			'title' => $resource->title,
			'tag_line' => $resource->tag_line,
			'custom_fields' => $resource->custom_fields->getFieldValues()
		] + $fromInput;

		/** @var \XF\ControllerPlugin\Draft $draftPlugin */
		$draftPlugin = $this->plugin('XF:Draft');
		return $draftPlugin->actionDraftMessage($category->draft_resource, $extraData, 'description');
	}

	public function actionPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$category = $this->assertViewableCategory($params->resource_category_id);
		if (!$category->canAddResource($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupResourceCreate($category);

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$resource = $creator->getResource();
		$description = $creator->getDescription();

		$attachments = [];
		$tempHash = $this->filter('attachment_hash', 'str');

		if ($category && $category->canUploadAndManageUpdateImages())
		{
			/** @var \XF\Repository\Attachment $attachmentRepo */
			$attachmentRepo = $this->repository('XF:Attachment');
			$attachmentData = $attachmentRepo->getEditorData('resource_update', $description, $tempHash);
			$attachments = $attachmentData['attachments'];
		}

		return $this->plugin('XF:BbCodePreview')->actionPreview(
			$description->message, 'resource_update', $resource->User, $attachments, $resource->canViewUpdateImages()
		);
	}

	/**
	 * @param integer $categoryId
	 * @param array $extraWith
	 *
	 * @return \XFRM\Entity\Category
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableCategory($categoryId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Permissions|' . $visitor->permission_combination_id;

		/** @var \XFRM\Entity\Category $category */
		$category = $this->em()->find('XFRM:Category', $categoryId, $extraWith);
		if (!$category)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_category_not_found')));
		}

		if (!$category->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin('XFRM:Category')->applyCategoryContext($category);

		return $category;
	}

	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities, \XF::phrase('xfrm_viewing_resource_category'), 'resource_category_id',
			function(array $ids)
			{
				$categories = \XF::em()->findByIds(
					'XFRM:Category',
					$ids,
					['Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($categories->filterViewable() AS $id => $category)
				{
					$data[$id] = [
						'title' => $category->title,
						'url' => $router->buildLink('resources/categories', $category)
					];
				}

				return $data;
			}
		);
	}

	public function actionPrefixHelp(ParameterBag $params)
	{
		$this->assertPostOnly();

		$category = $this->assertViewableCategory($params->resource_category_id);

		$prefixId = $this->filter('prefix_id', 'uint');
		if ($prefixId && $category->isPrefixUsable($prefixId))
		{
			$prefix = $this->em()->find('XFRM:ResourcePrefix', $prefixId);

			return $this->view('XFRM:Category\PrefixHelp', 'prefix_usage_help', [
				'prefix' => $prefix,
				'contentType' => 'resource'
			]);
		}

		return $this->notFound();
	}
}