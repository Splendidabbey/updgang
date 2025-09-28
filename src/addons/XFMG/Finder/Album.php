<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\Finder;

class Album extends Finder
{
	public function inCategory($categoryId)
	{
		$this->where('category_id', $categoryId);

		return $this;
	}

	public function byUser(\XF\Entity\User $user)
	{
		$this->where('user_id', $user->user_id);

		return $this;
	}

	/**
	 * Potentially find albums from specified categories and include personal albums.
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

	public function includePersonalAlbums($alsoCategoryIds = null)
	{
		$visitor = \XF::visitor();
		$userId = $visitor->user_id;

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
			$this->with('SharedMapView|' . $userId);

			$conditions[] = [
				'category_id' => 0,
				'view_privacy' => ['public', 'members']
			];

			$conditions[] = [
				'category_id' => 0,
				'view_privacy' => ['private', 'shared'],
				'user_id' => $userId
			];

			$conditions[] = [
				'category_id' => 0,
				['view_privacy', '=', 'shared'],
				['SharedMapView|' . $userId . '.user_id', '!=', NULL]
			];
		}
		else
		{
			$conditions[] = [
				'category_id' => 0,
				'view_privacy' => 'public'
			];
		}

		$this->whereOr($conditions);

		return $this;
	}

	public function applyVisibilityLimit($allowOwnPending = false)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('xfmg', 'viewDeletedAlbums'))
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		if ($visitor->user_id)
		{
			if ($visitor->hasPermission('xfmg', 'viewModeratedAlbums'))
			{
				$viewableStates[] = 'moderated';
			}
			else if ($allowOwnPending)
			{
				$conditions[] = [
					'album_state' => 'moderated',
					'user_id' => $visitor->user_id
				];
			}
		}

		$conditions[] = ['album_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	public function applyAddMediaLimit()
	{
		$visitor = \XF::visitor();
		$userId = $visitor->user_id;

		$conditions = [];

		if ($userId)
		{
			$this->with('SharedMapAdd|' . $userId);

			$conditions[] = [
				'add_privacy' => ['public', 'members']
			];

			$conditions[] = [
				'add_privacy' => ['private', 'shared'],
				'user_id' => $userId
			];

			$conditions[] = [
				'add_privacy' => 'shared',
				['SharedMapAdd|' . $userId . '.user_id', '!=', 'NULL']
			];
		}
		else
		{
			$conditions[] = ['add_privacy', 'public'];
		}

		$this->whereOr($conditions);
		$this->where('album_state', 'visible');
		// visibility checked elsewhere

		return $this;
	}

	public function orderByDate($order = 'create_date', $direction = 'DESC')
	{
		$this->setDefaultOrder([
			[$order, $direction],
			['album_id', $direction]
		]);

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

		$albumCommentReadExpression = $this->expression(
			'%s > COALESCE(%s, 0)',
			'last_comment_date',
			'CommentRead|' . $userId . '.comment_read_date'
		);

		$this
			->where('last_comment_date', '>', (
				\XF::$time - $this->app()->options()->readMarkingDataLifetime * 86400)
			)
			->where($albumCommentReadExpression);

		return $this;
	}
}