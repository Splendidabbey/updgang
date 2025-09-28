<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class ResourceRating extends Repository
{
	public function findReviewsInResource(\XFRM\Entity\ResourceItem $resource, array $limits = [])
	{
		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->finder('XFRM:ResourceRating');
		$finder->inResource($resource, $limits)
			->where('is_review', 1)
			->setDefaultOrder('rating_date', 'desc');

		return $finder;
	}

	public function findLatestReviews(array $viewableCategoryIds = null)
	{
		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->finder('XFRM:ResourceRating');

		if (is_array($viewableCategoryIds))
		{
			$finder->where('Resource.resource_category_id', $viewableCategoryIds);
		}
		else
		{
			$finder->with('Resource.Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$finder->where([
				'Resource.resource_state' => 'visible',
				'rating_state' => 'visible',
				'is_review' => 1
			])
			->with('Resource', true)
			->with(['Resource.Category', 'User'])
			->setDefaultOrder('rating_date', 'desc');

		$cutOffDate = \XF::$time - ($this->options()->readMarkingDataLifetime * 86400);
		$finder->where('rating_date', '>', $cutOffDate);

		return $finder;
	}

	public function findLatestReviewsForApi(\XFRM\Entity\Category $withinCategory = null)
	{
		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->finder('XFRM:ResourceRating');

		if (\XF::isApiCheckingPermissions())
		{
			$categoryIds = $this->repository('XFRM:Category')->getViewableCategoryIds($withinCategory);
			$finder->where('Resource.resource_category_id', $categoryIds);
		}

		$finder->where([
				'Resource.resource_state' => 'visible',
				'rating_state' => 'visible',
				'is_review' => 1
			])
			->with('Resource', true)
			->with('api|resource')
			->setDefaultOrder('rating_date', 'desc');

		$cutOffDate = \XF::$time - ($this->options()->readMarkingDataLifetime * 86400);
		$finder->where('rating_date', '>', $cutOffDate);

		return $finder;
	}

	/**
	 * @param int $resourceId
	 * @param int $userId
	 *
	 * @return \XFRM\Entity\ResourceRating|null
	 */
	public function getCountableRating($resourceId, $userId)
	{
		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->finder('XFRM:ResourceRating');
		$finder->where([
			'resource_id' => $resourceId,
			'user_id' => $userId,
			'rating_state' => 'visible'
		])->order('rating_date', 'desc');

		return $finder->fetchOne();
	}

	/**
	 * Returns the ratings that are counted for the the given resource user. This should normally return one.
	 * In general, only a bug would have it return more than one but the code is written so that this can be resolved.
	 *
	 * @param $resourceId
	 * @param $userId
	 *
	 * @return \XF\Mvc\Entity\ArrayCollection
	 */
	public function getCountedRatings($resourceId, $userId)
	{
		/** @var \XFRM\Finder\ResourceRating $finder */
		$finder = $this->finder('XFRM:ResourceRating');
		$finder->where([
			'resource_id' => $resourceId,
			'user_id' => $userId,
			'count_rating' => 1
		])->order('rating_date', 'desc');

		return $finder->fetch();
	}

	public function sendModeratorActionAlert(\XFRM\Entity\ResourceRating $rating, $action, $reason = '', array $extra = [])
	{
		$resource = $rating->Resource;

		if (!$resource || !$resource->user_id || !$resource->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $resource->title,
			'prefix_id' => $resource->prefix_id,
			'link' => $this->app()->router('public')->buildLink('nopath:resources/review', $rating),
			'resourceLink' => $this->app()->router('public')->buildLink('nopath:resources', $resource),
			'reason' => $reason
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$rating->User,
			0, '',
			'user', $rating->user_id,
			"resource_rating_{$action}", $extra,
			['dependsOnAddOnId' => 'XFRM']
		);

		return true;
	}

	public function sendReviewAlertToResourceAuthor(\XFRM\Entity\ResourceRating $rating)
	{
		if (!$rating->isVisible() || !$rating->is_review)
		{
			return false;
		}

		$resource = $rating->Resource;
		$resourceAuthor = $resource->User;

		if (!$resourceAuthor)
		{
			return false;
		}

		if ($rating->is_anonymous)
		{
			$senderId = 0;
			$senderName = \XF::phrase('anonymous')->render('raw');
		}
		else
		{
			$senderId = $rating->user_id;
			$senderName = $rating->User ? $rating->User->username : \XF::phrase('unknown')->render('raw');
		}

		$alertRepo = $this->repository('XF:UserAlert');
		return $alertRepo->alert(
			$resourceAuthor,
			$senderId,
			$senderName,
			'resource_rating',
			$rating->resource_rating_id,
			'review',
			[],
			['autoRead' => false]
		);
	}

	public function sendAuthorReplyAlert(\XFRM\Entity\ResourceRating $rating)
	{
		if (!$rating->isVisible() || !$rating->is_review || !$rating->User)
		{
			return false;
		}

		$resource = $rating->Resource;

		$alertRepo = $this->repository('XF:UserAlert');
		return $alertRepo->alert(
			$rating->User,
			$resource->user_id,
			$resource->username,
			'resource_rating',
			$rating->resource_rating_id,
			'reply',
			[],
			['autoRead' => false]
		);
	}
}