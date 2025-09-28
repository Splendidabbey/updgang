<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Category extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media_category', ['delete' => 'delete']);
	}

	public function actionGet(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->category_id);

		if ($this->filter('with_content', 'bool'))
		{
			$this->assertApiScope('media:read');
			$contentData = $this->getContentInCategoryPaginated($category, $this->filterPage());
		}
		else
		{
			$contentData = [];
		}

		$result = [
			'category' => $category->toApiResult(Entity::VERBOSITY_VERBOSE)
		];
		$result += $contentData;

		return $this->apiResult($result);
	}

	/**
	 * @api-desc Gets a page of content from the specified category.
	 *
	 * @api-see self::getContentInCategoryPaginated()
	 */
	public function actionGetContent(ParameterBag $params)
	{
		$this->assertApiScope('media:read');

		$category = $this->assertViewableCategory($params->category_id);

		$contentData = $this->getContentInCategoryPaginated($category, $this->filterPage());

		return $this->apiResult($contentData);
	}

	/**
	 * @api-out XFMG_Album[] $albums If an album category, albums on this page
	 * @api-out XFMG_Media[] $media If a media category, media on this page
	 * @api-out pagination $pagination Pagination information
	 */
	protected function getContentInCategoryPaginated(\XFMG\Entity\Category $category, $page = 1, $perPage = null)
	{
		switch ($category->category_type)
		{
			case 'album':
				return $this->getAlbumsInCategoryPaginated($category, $page, $perPage);

			case 'media':
				return $this->getMediaInCategoryPaginated($category, $page, $perPage);

			default: // container
				$containerType = $this->filter('container_type', 'str');
				if ($containerType == 'album')
				{
					return $this->getAlbumsInCategoryPaginated($category, $page, $perPage);
				}
				else
				{
					return $this->getMediaInCategoryPaginated($category, $page, $perPage);
				}
		}
	}

	protected function getAlbumsInCategoryPaginated(\XFMG\Entity\Category $category, $page = 1, $perPage = null)
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->xfmgAlbumsPerPage;
		}

		$finder = $this->setupAlbumFinder($category, $filters, $sort);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		/** @var \XFMG\Entity\Album[]|\XF\Mvc\Entity\AbstractCollection $resources */
		$albums = $finder->fetch();
		if (\XF::isApiCheckingPermissions())
		{
			$albums = $albums->filterViewable();
		}

		$albumResults = $albums->toApiResults();
		$this->adjustAlbumListApiResults($category, $albumResults);

		return [
			'albums' => $albumResults,
			'pagination' => $this->getPaginationData($albumResults, $page, $perPage, $total)
		];
	}

	/**
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return \XFMG\Finder\Album
	 */
	protected function setupAlbumFinder(\XFMG\Entity\Category $category, &$filters = [], &$sort = null)
	{
		$albumFinder = $this->repository('XFMG:Album')->findAlbumsForApi(null, $category);

		/** @var \XFMG\Api\ControllerPlugin\Album $albumPlugin */
		$albumPlugin = $this->plugin('XFMG:Api:Album');

		$filters = $albumPlugin->applyAlbumListFilters($albumFinder, $category);
		$sort = $albumPlugin->applyAlbumListSort($albumFinder, $category);

		return $albumFinder;
	}

	protected function adjustAlbumListApiResults(\XFMG\Entity\Category $category, \XF\Api\Result\EntityResultInterface $result)
	{
		$result->skipRelation('Category');
	}

	protected function getMediaInCategoryPaginated(\XFMG\Entity\Category $category, $page = 1, $perPage = null)
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->xfmgMediaPerPage;
		}

		$finder = $this->setupMediaFinder($category, $filters, $sort);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		/** @var \XFMG\Entity\MediaItem[]|\XF\Mvc\Entity\AbstractCollection $resources */
		$media = $finder->fetch();
		if (\XF::isApiCheckingPermissions())
		{
			$media = $media->filterViewable();
		}

		$mediaResults = $media->toApiResults();
		$this->adjustMediaListApiResults($category, $mediaResults);

		return [
			'media' => $mediaResults,
			'pagination' => $this->getPaginationData($mediaResults, $page, $perPage, $total)
		];
	}

	/**
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return \XFMG\Finder\MediaItem
	 */
	protected function setupMediaFinder(\XFMG\Entity\Category $category, &$filters = [], &$sort = null)
	{
		$mediaFinder = $this->repository('XFMG:Media')->findMediaForApi(null, $category);

		/** @var \XFMG\Api\ControllerPlugin\MediaItem $mediaPlugin */
		$mediaPlugin = $this->plugin('XFMG:Api:MediaItem');

		$filters = $mediaPlugin->applyMediaListFilters($mediaFinder, $category);
		$sort = $mediaPlugin->applyMediaListSort($mediaFinder, $category);

		return $mediaFinder;
	}

	protected function adjustMediaListApiResults(\XFMG\Entity\Category $category, \XF\Api\Result\EntityResultInterface $result)
	{
		$result->skipRelation('Category');
	}

	public function actionPost(ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');

		$category = $this->assertViewableCategory($params->category_id);

		/** @var \XFMG\Api\ControllerPlugin\Category $plugin */
		$plugin = $this->plugin('XFMG:Api:Category');

		$form = $plugin->setupCategorySave($category);
		$form->run();

		return $this->apiSuccess([
			'category' => $category->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @api-desc Deletes the specified category
	 *
	 * @api-see XF\Api\ControllerPlugin\CategoryTree::actionDelete
	 */
	public function actionDelete(ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');

		$category = $this->assertViewableCategory($params->category_id);

		/** @var \XF\Api\ControllerPlugin\CategoryTree $plugin */
		$plugin = $this->plugin('XF:Api:CategoryTree');

		return $plugin->actionDelete($category);
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFMG\Entity\Category
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableCategory($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFMG:Category', $id, $with);
	}
}