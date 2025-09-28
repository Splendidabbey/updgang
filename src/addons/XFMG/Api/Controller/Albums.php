<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Albums extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media');
	}

	public function actionGet()
	{
		$page = $this->filterPage();
		$perPage = $this->options()->xfmgAlbumsPerPage;

		$albumFinder = $this->setupAlbumFinder()->limitByPage($page, $perPage);
		$total = $albumFinder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$albums = $albumFinder->fetch();

		if (\XF::isApiCheckingPermissions())
		{
			$albums = $albums->filterViewable();
		}

		return $this->apiResult([
			'albums' => $albums->toApiResults(),
			'pagination' => $this->getPaginationData($albums, $page, $perPage, $total)
		]);
	}

	/**
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return \XFMG\Finder\Album
	 */
	protected function setupAlbumFinder(&$filters = [], &$sort = null)
	{
		$albumFinder = $this->repository('XFMG:Album')->findAlbumsForApi();

		/** @var \XFMG\Api\ControllerPlugin\Album $albumPlugin */
		$albumPlugin = $this->plugin('XFMG:Api:Album');

		$filters = $albumPlugin->applyAlbumListFilters($albumFinder);
		$sort = $albumPlugin->applyAlbumListSort($albumFinder);

		return $albumFinder;
	}

	public function actionPost()
	{
		$this->assertRequiredApiInput(['title']);

		$categoryId = $this->filter('category_id', 'uint');
		if ($categoryId)
		{
			/** @var \XFMG\Entity\Category $category */
			$category = $this->assertViewableApiRecord('XFMG:Category', $categoryId);

			if (\XF::isApiCheckingPermissions() && !$category->canAddMedia($error))
			{
				return $this->noPermission($error);
			}
		}
		else
		{
			$category = null;

			/** @var \XFMG\XF\Entity\User $visitor */
			$visitor = \XF::visitor();

			if (\XF::isApiCheckingPermissions() && !$visitor->canCreateAlbum())
			{
				return $this->noPermission();
			}
		}

		$creator = $this->setupAlbumCreate($category);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var \XFMG\Entity\Album $album */
		$album = $creator->save();

		$this->finalizeAlbumCreate($creator);

		return $this->apiSuccess([
			'album' => $album->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	protected function setupAlbumCreate(\XFMG\Entity\Category $category = null)
	{
		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'view_privacy' => '?str',
			'view_user_ids' => 'array-uint',
			'add_privacy' => '?str',
			'add_user_ids' => 'array-uint'
		]);

		/** @var \XFMG\Service\Album\Creator $creator */
		$creator = $this->service('XFMG:Album\Creator');

		$creator->setTitle($input['title'], $input['description']);

		if ($category)
		{
			$creator->setCategory($category);
		}
		else
		{
			if (isset($input['view_privacy']))
			{
				$creator->setViewPrivacy($input['view_privacy'], $input['view_user_ids'], true);
			}
			if (isset($input['add_privacy']))
			{
				$creator->setAddPrivacy($input['add_privacy'], $input['add_user_ids'], true);
			}
		}

		return $creator;
	}

	protected function finalizeAlbumCreate(\XFMG\Service\Album\Creator $creator)
	{
		$creator->sendNotifications();
	}
}