<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\Finder;

class MediaItem extends Finder
{
	public function inCategory($categoryId)
	{
		$this->where('category_id', $categoryId);

		return $this;
	}

	public function inAlbum($albumId)
	{
		$this->where('album_id', $albumId);

		return $this;
	}

	public function byUser(\XF\Entity\User $user)
	{
		$this->where('user_id', $user->user_id);

		return $this;
	}

	/**
	 * Potentially include media from specified categories and personal albums.
	 * If category IDs is null, then all categories will be included.
	 *
	 * @param mixed $categoryIds Can be null, an int or an array
	 * @param bool $includePersonalAlbums If true, include personal albums (otherwise, just the specified categories)
	 *
	 * @return $this
	 */
	public function inCategoriesIncludePersonalAlbums($categoryIds, $includePersonalAlbums = true)
	{
		if ($includePersonalAlbums)
		{
			$this->includePersonalAlbums($categoryIds);
		}
		else if ($categoryIds === null)
		{
			$this->where('category_id', '>', 0);
		}
		else
		{
			$this->where('category_id', $categoryIds);
		}

		return $this;
	}

	/**
	 * Sets media from personal albums to be included, along with any specified categories.
	 * Category IDs can be:
	 *  - null: Includes media from all other categories as well
	 *  - 0 or empty array: Includes only media from personal albums
	 *  - >0 or non-empty array: Includes media from nominated categories as well
	 * Note this function will mostly be skipped if personal albums are disabled.
	 *
	 * @param mixed $alsoCategoryIds
	 *
	 * @return $this
	 */
	public function includePersonalAlbums($alsoCategoryIds = null)
	{
		$visitor = \XF::visitor();
		$userId = $visitor->user_id;

		// never include media in hidden albums here as it will mostly be confusing
		$this->visibleAlbumsOnly();

		if (!$this->app()->options()->xfmgAllowPersonalAlbums)
		{
			// albums are disabled, so just apply category limits
			if ($alsoCategoryIds === null)
			{
				$this->where('category_id', '>', 0);
			}
			else
			{
				$this->where('category_id', $alsoCategoryIds);
			}

			return $this;
		}

		$conditions = [];

		if ($alsoCategoryIds === null)
		{
			// not limiting by category
			$conditions[] = ['category_id', '>', 0];
		}
		else if ($alsoCategoryIds)
		{
			// include nominated categories
			$conditions[] = ['category_id', $alsoCategoryIds];
		}
		else
		{
			// no categories, only from personal albums
			// don't put anything here as conditions are all or'd together so below will catch this
		}

		if ($visitor->hasPermission('xfmg', 'bypassPrivacy'))
		{
			// skip all privacy checks
			$conditions[] = ['category_id' => 0];
		}
		else if ($userId)
		{
			$this->with('Album.SharedMapView|' . $userId);

			$conditions[] = [
				'category_id' => 0,
				'Album.view_privacy' => ['public', 'members']
			];

			$conditions[] = [
				'category_id' => 0,
				'Album.view_privacy' => ['private', 'shared'],
				'Album.user_id' => $userId
			];

			$conditions[] = [
				'category_id' => 0,
				'Album.view_privacy' => 'shared',
				['Album.SharedMapView|' . $userId . '.user_id', '!=', NULL]
			];
		}
		else
		{
			$conditions[] = [
				'category_id' => 0,
				'Album.view_privacy' => 'public'
			];
		}

		$this->whereOr($conditions);

		return $this;
	}

	public function visibleAlbumsOnly()
	{
		$expression = $this->expression(
			'IF(%s > 0, %s = \'visible\', 1=1)',
			'album_id', 'Album.album_state'
		);
		$this->where($expression);

		return $this;
	}

	public function applyVisibilityLimit($allowOwnPending = false)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('xfmg', 'viewDeleted'))
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		if ($visitor->user_id)
		{
			if ($visitor->hasPermission('xfmg', 'viewModerated'))
			{
				$viewableStates[] = 'moderated';
			}
			else if ($allowOwnPending)
			{
				$conditions[] = [
					'media_state' => 'moderated',
					'user_id' => $visitor->user_id
				];
			}
		}

		$conditions[] = ['media_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	/**
	 * @param string $direction
	 *
	 * @return Finder
	 */
	public function orderByDate($order = 'media_date', $direction = 'DESC')
	{
		$this->setDefaultOrder([
			[$order, $direction],
			['media_id', $direction]
		]);

		return $this;
	}

	public function unviewedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, no read tracking
			return $this;
		}

		$mediaItemViewedExpression = $this->expression(
			'%s > COALESCE(%s, 0)',
			'media_date',
			'Viewed|' . $userId . '.media_view_date'
		);

		$this
			->where('media_date', '>', (
				\XF::$time - $this->app()->options()->readMarkingDataLifetime * 86400)
			)
			->where($mediaItemViewedExpression);

		return $this;
	}

	public function watchedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, just ignore
			return $this;
		}

		$this->where('Watch|' . $userId . '.user_id', '!=', null);

		return $this;
	}

	public function withUnreadCommentsOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, no read tracking
			return $this;
		}

		$mediaCommentReadExpression = $this->expression(
			'%s > COALESCE(%s, 0)',
			'last_comment_date',
			'CommentRead|' . $userId . '.comment_read_date'
		);

		$this
			->where('last_comment_date', '>', (
				\XF::$time - $this->app()->options()->readMarkingDataLifetime * 86400)
			)
			->where($mediaCommentReadExpression);

		return $this;
	}

	public function limitByDate($limitDays)
	{
		if ($limitDays)
		{
			$this->where('media_date', '>=', \XF::$time - ($limitDays * 86400));
		}

		return $this;
	}
}