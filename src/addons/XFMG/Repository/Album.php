<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\Repository;

class Album extends Repository
{
	/**
	 * @param array $limits
	 *
	 * @return \XFMG\Finder\Album
	 */
	public function findAlbumsForMixedList(array $limits = [])
	{
		$limits = array_replace([
			'categoryIds' => null,
			'includePersonalAlbums' => true,
			'visibility' => true,
			'allowOwnPending' => true
		], $limits);

		/** @var \XFMG\Finder\Album $finder */
		$finder = $this->finder('XFMG:Album')
			->with('Category');

		$finder->inCategoriesIncludePersonalAlbums($limits['categoryIds'], $limits['includePersonalAlbums']);

		if ($limits['visibility'])
		{
			$finder->applyVisibilityLimit($limits['allowOwnPending']);
		}

		$finder->orderByDate();

		return $finder;
	}

	/**
	 * @param mixed $categoryIds
	 * @param array $limits
	 *
	 * @return \XFMG\Finder\Album
	 */
	public function findAlbumsForIndex($categoryIds = null, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;

		return $this->findAlbumsForMixedList($limits);
	}

	public function findAlbumsForCategory($categoryId, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true
		], $limits);

		/** @var \XFMG\Finder\Album $finder */
		$finder = $this->finder('XFMG:Album');

		$finder->inCategory($categoryId);

		if ($limits['visibility'])
		{
			$finder->applyVisibilityLimit($limits['allowOwnPending']);
		}

		$finder->orderByDate();

		return $finder;
	}

	public function findAlbumsForUser(\XF\Entity\User $user, $categoryIds = null, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;

		$finder = $this->findAlbumsForMixedList($limits);

		$finder->byUser($user);

		return $finder;
	}

	public function findAlbumsForApi($typeLimit = null, \XFMG\Entity\Category $category = null)
	{
		/** @var \XFMG\Finder\Album $finder */
		$finder = $this->finder('XFMG:Album');
		$finder->with('api')->orderByDate();

		$categoryRepo = $this->repository('XFMG:Category');

		if ($category)
		{
			if ($category->category_type == 'container')
			{
				if (\XF::isApiCheckingPermissions())
				{
					$categoryIds = $categoryRepo->getViewableCategoryIds($category, false);
				}
				else
				{
					$categoryIds = $categoryRepo->getCategoryIds($category, false);
				}
			}
			else
			{
				$categoryIds = [$category->category_id];
			}

			$finder->inCategory($categoryIds);
		}
		else
		{
			$findViewableCategories = true;
			$includePersonalAlbums = true;

			switch ($typeLimit)
			{
				case 'category':
					$finder->where('category_id', '>', 0);
					$includePersonalAlbums = false;
					break;

				case 'personal':
					$finder->where('category_id', 0);
					$findViewableCategories = false;
					break;

			}

			if (\XF::isApiCheckingPermissions())
			{
				if ($findViewableCategories)
				{
					$viewableCategoryIds = $categoryRepo->getViewableCategoryIds();
				}
				else
				{
					$viewableCategoryIds = null;
				}

				$finder->inCategoriesIncludePersonalAlbums($viewableCategoryIds, $includePersonalAlbums);
			}
		}

		if (\XF::isApiCheckingPermissions())
		{
			$finder->applyVisibilityLimit();
		}

		return $finder;
	}

	/**
	 * @param mixed $categoryIds
	 * @param bool $includePersonalAlbums
	 * @param array $limits
	 *
	 * @return \XFMG\Finder\Album
	 */
	public function findAlbumsUserCanAddTo($categoryIds = null, $includePersonalAlbums = true, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;
		$limits['includePersonalAlbums'] = $includePersonalAlbums;
		$limits['allowOwnPending'] = true; // always show this

		$finder = $this->findAlbumsForMixedList($limits);
		$finder->applyAddMediaLimit();

		return $finder;
	}

	public function findAlbumsForWatchedList($categoryIds = null, $userId = null, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;
		$limits['visibility'] = false;

		$finder = $this->findAlbumsForMixedList($limits);

		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		$userId = intval($userId);

		$finder
			->with('Watch|' . $userId, true)
			->with('CommentRead|' . $userId)
			->with('LastComment')
			->with('LastCommenter')
			->where('album_state', 'visible')
			->setDefaultOrder('create_date', 'DESC');

		return $finder;
	}

	public function findAlbumsForWidget($categoryIds = null, $includePersonalAlbums = false)
	{
		/** @var \XFMG\Finder\Album $finder */
		$finder = $this->finder('XFMG:Album');

		if ($includePersonalAlbums)
		{
			$finder->includePersonalAlbums($categoryIds);
		}
		else
		{
			$finder->inCategory($categoryIds);
		}

		$finder->where('album_state', 'visible');

		return $finder;
	}

	/**
	 * @return string
	 */
	public function generateAlbumHash()
	{
		$mediaFinder = $this->finder('XFMG:Album');

		do
		{
			$albumHash = md5(microtime(true) . \XF::generateRandomString(8, true));

			$albumFound = $mediaFinder->resetWhere()
				->where('album_hash', $albumHash)
				->fetchOne();

			if (!$albumFound)
			{
				break;
			}
		}
		while (true);

		return $albumHash;
	}

	public function logAlbumView(\XFMG\Entity\Album $album)
	{
		$this->db()->query("
			INSERT INTO xf_mg_album_view
				(album_id, total)
			VALUES
				(? , 1)
			ON DUPLICATE KEY UPDATE
				total = total + 1
		", $album->album_id);
	}

	public function sendModeratorActionAlert(\XFMG\Entity\Album $album, $action, $reason = '', array $extra = [])
	{
		if (!$album->user_id || !$album->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $album->title,
			'link' => $this->app()->router('public')->buildLink('nopath:media/albums', $album),
			'reason' => $reason
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$album->User,
			0, '',
			'user', $album->user_id,
			"xfmg_album_{$action}", $extra,
			['dependsOnAddOnId' => 'XFMG']
		);

		return true;
	}

	public function batchUpdateAlbumViews()
	{
		$db = $this->db();
		$db->query("
			UPDATE xf_mg_album AS a
			INNER JOIN xf_mg_album_view AS av ON (a.album_id = av.album_id)
			SET a.view_count = a.view_count + av.total
		");
		$db->emptyTable('xf_mg_album_view');
	}

	public function markAllAlbumCommentsReadByVisitor($categoryIds = null, $newRead = null)
	{
		$finder = $this->findAlbumsForIndex($categoryIds)
			->withUnreadCommentsOnly();

		$albums = $finder->fetch();

		foreach ($albums AS $album)
		{
			$this->markAlbumCommentsReadByVisitor($album, $newRead);
		}
	}

	public function markAlbumCommentsReadByVisitor(\XFMG\Entity\Album $album, $newRead = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($newRead === null)
		{
			$newRead = \XF::$time;
		}

		$cutOff = $this->getViewMarkingCutOff();
		if ($newRead <= $cutOff)
		{
			return false;
		}

		$viewed = $album->CommentRead[$visitor->user_id];
		if ($viewed && $newRead <= $viewed->comment_read_date)
		{
			return false;
		}

		$this->db()->insert('xf_mg_album_comment_read', [
			'album_id' => $album->album_id,
			'user_id' => $visitor->user_id,
			'comment_read_date' => $newRead
		], false, 'comment_read_date = VALUES(comment_read_date)');

		return true;
	}

	public function getViewMarkingCutOff()
	{
		return \XF::$time - $this->options()->readMarkingDataLifetime * 86400;
	}

	public function generateRandomAlbumCache()
	{
		$limit = 5;
		$iterations = 100;

		$maxId = (int)$this->db()->fetchOne('SELECT MAX(album_id) FROM xf_mg_album');

		$albumIds = [];
		while ($iterations > 0)
		{
			$iterations--;

			$gt = mt_rand(0, max(0, $maxId - $limit));

			$albumIds = array_merge($albumIds, $this->db()->fetchAllColumn('
				SELECT album_id
				FROM xf_mg_album
				WHERE album_id > ?
				LIMIT ?
			', [$gt, $limit]));
		}

		return array_unique($albumIds);
	}

	public function getUserAlbumCount($userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_mg_album
			WHERE user_id = ?
				AND album_state = 'visible'
		", $userId);
	}

	/**
	 * @param $url
	 * @param null $error
	 *
	 * @return null|\XFMG\Entity\Album
	 */
	public function getAlbumFromUrl($url, &$error = null)
	{
		$routePath = $this->app()->request()->getRoutePathFromUrl($url);
		$routeMatch = $this->app()->router('public')->routeToController($routePath);
		$params = $routeMatch->getParameterBag();

		if (!$params->album_id)
		{
			$error = \XF::phrase('xfmg_no_album_id_could_be_found_from_that_url');
			return null;
		}

		$album = $this->app()->find('XFMG:Album', $params->album_id);
		if (!$album)
		{
			$error = \XF::phrase('xfmg_no_album_could_be_found_with_id_x', ['album_id' => $params->album_id]);
			return null;
		}

		return $album;
	}
}