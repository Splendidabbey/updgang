<?php

namespace XFMG\XF\Pub\Controller;

class Watched extends XFCP_Watched
{
	public function actionMedia()
	{
		$this->setSectionContext('xfmg');

		$page = $this->filterPage();
		$perPage = $this->options()->xfmgMediaPerPage;

		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');
		$categoryParams = $mediaListPlugin->getCategoryListData();

		$categoryIds = $categoryParams['viewableCategories']->keys();

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');
		$finder = $mediaRepo->findMediaForWatchedList($categoryIds);

		$total = $finder->total();
		$mediaItems = $finder->limitByPage($page, $perPage)->fetch();

		$viewParams = [
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'mediaItems' => $mediaItems->filterViewable()
		];
		return $this->view('XFMG:Watched\MediaItems', 'xfmg_watched_media', $viewParams);
	}

	public function actionMediaManage()
	{
		$this->setSectionContext('xfmg');

		if (!$state = $this->filter('state', 'str'))
		{
			return $this->redirect($this->buildLink('watched/media'));
		}

		if ($this->isPost())
		{
			/** @var \XFMG\Repository\MediaWatch $watchRepo */
			$watchRepo = $this->repository('XFMG:MediaWatch');

			if ($action = $this->getWatchActionConfig($state, $updates))
			{
				$watchRepo->setWatchStateForAll(\XF::visitor(), $action, $updates);
			}

			return $this->redirect($this->buildLink('watched/media'));
		}
		else
		{
			$viewParams = [
				'state' => $state
			];
			return $this->view('XFMG:Watched\MediaManage', 'watched_media_manage', $viewParams);
		}
	}

	public function actionMediaUpdate()
	{
		$this->assertPostOnly();
		$this->setSectionContext('xfmg');

		/** @var \XFMG\Repository\MediaWatch $watchRepo */
		$watchRepo = $this->repository('XFMG:MediaWatch');

		$inputAction = $this->filter('watch_action', 'str');
		$action = $this->getWatchActionConfig($inputAction, $config);

		if ($action)
		{
			$ids = $this->filter('ids', 'array-uint');
			$mediaItems = $this->em()->findByIds('XFMG:MediaItem', $ids);
			$visitor = \XF::visitor();

			/** @var \XFMG\Entity\MediaItem $mediaItem */
			foreach ($mediaItems AS $mediaItem)
			{
				$watchRepo->setWatchState($mediaItem, $visitor, $action, $config);
			}
		}

		return $this->redirect(
			$this->getDynamicRedirect($this->buildLink('watched/media'))
		);
	}

	public function actionMediaAlbums()
	{
		$this->setSectionContext('xfmg');

		$page = $this->filterPage();
		$perPage = $this->options()->xfmgAlbumsPerPage;

		/** @var \XFMG\ControllerPlugin\AlbumList $albumListPlugin */
		$albumListPlugin = $this->plugin('XFMG:AlbumList');
		$categoryParams = $albumListPlugin->getCategoryListData();

		$categoryIds = $categoryParams['albumCategories']->keys();

		/** @var \XFMG\Repository\Album $albumRepo */
		$albumRepo = $this->repository('XFMG:Album');
		$finder = $albumRepo->findAlbumsForWatchedList($categoryIds);

		$total = $finder->total();
		$albums = $finder->limitByPage($page, $perPage)->fetch();

		$viewParams = [
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'albums' => $albums->filterViewable()
		];
		return $this->view('XFMG:Watched\Albums', 'xfmg_watched_albums', $viewParams);
	}

	public function actionMediaAlbumsManage()
	{
		$this->setSectionContext('xfmg');

		if (!$state = $this->filter('state', 'str'))
		{
			return $this->redirect($this->buildLink('watched/media-albums'));
		}

		if ($this->isPost())
		{
			/** @var \XFMG\Repository\AlbumWatch $watchRepo */
			$watchRepo = $this->repository('XFMG:AlbumWatch');

			if ($action = $this->getWatchActionConfig($state, $updates))
			{
				$watchRepo->setWatchStateForAll(\XF::visitor(), $action, $updates);
			}

			return $this->redirect($this->buildLink('watched/media-albums'));
		}
		else
		{
			$viewParams = [
				'state' => $state
			];
			return $this->view('XFMG:Watched\AlbumsManage', 'watched_albums_manage', $viewParams);
		}
	}

	public function actionMediaAlbumsUpdate()
	{
		$this->assertPostOnly();
		$this->setSectionContext('xfmg');

		/** @var \XFMG\Repository\AlbumWatch $watchRepo */
		$watchRepo = $this->repository('XFMG:AlbumWatch');

		$inputAction = $this->filter('watch_action', 'str');
		$action = $this->getWatchActionConfig($inputAction, $config);

		if ($action)
		{
			$ids = $this->filter('ids', 'array-uint');
			$albums = $this->em()->findByIds('XFMG:Album', $ids);
			$visitor = \XF::visitor();

			/** @var \XFMG\Entity\Album $album */
			foreach ($albums AS $album)
			{
				$watchRepo->setWatchState($album, $visitor, $action, $config);
			}
		}

		return $this->redirect(
			$this->getDynamicRedirect($this->buildLink('watched/media-albums'))
		);
	}

	public function actionMediaCategories()
	{
		$this->setSectionContext('xfmg');

		$watchedFinder = $this->finder('XFMG:CategoryWatch');
		$watchedCategories = $watchedFinder->where('user_id', \XF::visitor()->user_id)
			->keyedBy('category_id')
			->fetch();

		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');
		$categoryParams = $mediaListPlugin->getCategoryListData();

		$viewParams = $categoryParams + [
			'watchedCategories' => $watchedCategories
		];
		return $this->view('XFMG:Watched\Categories', 'xfmg_watched_categories', $viewParams);
	}

	public function actionMediaCategoriesUpdate()
	{
		$this->assertPostOnly();
		$this->setSectionContext('xfmg');

		/** @var \XFMG\Repository\CategoryWatch $watchRepo */
		$watchRepo = $this->repository('XFMG:CategoryWatch');

		$inputAction = $this->filter('watch_action', 'str');
		$action = $this->getWatchActionConfig($inputAction, $config);

		if ($action)
		{
			$visitor = \XF::visitor();

			$ids = $this->filter('ids', 'array-uint');
			$categories = $this->em()->findByIds('XFMG:Category', $ids);

			/** @var \XFMG\Entity\Category $category */
			foreach ($categories AS $category)
			{
				$watchRepo->setWatchState($category, $visitor, $action, $config);
			}
		}

		return $this->redirect(
			$this->getDynamicRedirect($this->buildLink('watched/media-categories'))
		);
	}

	protected function getWatchActionConfig($inputAction, array &$config = null)
	{
		$config = [];

		$parts = explode(':', $inputAction, 2);

		$inputAction = $parts[0];
		$boolSwitch = (isset($parts[1]) && $parts[1] == 'on') ? 1 : 0;

		switch ($inputAction)
		{
			case 'send_email':
			case 'send_alert':
			case 'include_children':
				$config = [$inputAction => $boolSwitch];
				return 'update';

			case 'delete':
				return 'delete';

			default:
				return null;
		}
	}
}