<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class ResourceItem extends Repository
{
	public function findResourcesForOverviewList(array $viewableCategoryIds = null, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false
		], $limits);

		/** @var \XFRM\Finder\ResourceItem $resourceFinder */
		$resourceFinder = $this->finder('XFRM:ResourceItem');

		if (is_array($viewableCategoryIds))
		{
			$resourceFinder->where('resource_category_id', $viewableCategoryIds);
		}
		else
		{
			$resourceFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$resourceFinder
			->with(['full', 'fullCategory'])
			->useDefaultOrder();

		if ($limits['visibility'])
		{
			$resourceFinder->applyGlobalVisibilityChecks($limits['allowOwnPending']);
		}

		return $resourceFinder;
	}

	public function findTopResources(array $viewableCategoryIds = null)
	{
		/** @var \XFRM\Finder\ResourceItem $resourceFinder */
		$resourceFinder = $this->finder('XFRM:ResourceItem');

		if (is_array($viewableCategoryIds))
		{
			$resourceFinder->where('resource_category_id', $viewableCategoryIds);
		}
		else
		{
			$resourceFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$resourceFinder
			->where('resource_state', 'visible')
			->with(['User'])
			->setDefaultOrder('rating_weighted', 'desc');

		return $resourceFinder;
	}

	/**
	 * @param int[]|null $viewableCategoryIds
	 * @param bool $withFullCategory
	 *
	 * @return \XFRM\Finder\ResourceItem
	 */
	public function findFeaturedResources(array $viewableCategoryIds = null)
	{
		/** @var \XFRM\Finder\ResourceItem $resourceFinder */
		$resourceFinder = $this->finder('XFRM:ResourceItem');

		if (is_array($viewableCategoryIds))
		{
			$resourceFinder->where('resource_category_id', $viewableCategoryIds);
		}
		else
		{
			$resourceFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$resourceFinder
			->with('Featured', true)
			->where('resource_state', 'visible')
			->with('full')
			->setDefaultOrder($resourceFinder->expression('RAND()'));

		return $resourceFinder;
	}

	public function findResourcesForWatchedList($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		$userId = intval($userId);

		/** @var \XFRM\Finder\ResourceItem $finder */
		$finder = $this->finder('XFRM:ResourceItem');

		$finder
			->with(['full', 'fullCategory'])
			->with('Watch|' . $userId, true)
			->where('resource_state', 'visible')
			->setDefaultOrder('last_update', 'DESC');

		return $finder;
	}

	/**
	 * @param \XFRM\Entity\Category|null $withinCategory If provided, applies category-specific limits
	 *
	 * @return \XFRM\Finder\ResourceItem
	 */
	public function findResourcesForApi(\XFRM\Entity\Category $withinCategory = null)
	{
		/** @var \XFRM\Finder\ResourceItem $finder */
		$finder = $this->finder('XFRM:ResourceItem')
			->with('api');

		if (\XF::isApiCheckingPermissions())
		{
			$categoryIds = $this->repository('XFRM:Category')->getViewableCategoryIds($withinCategory);
			$finder->where('resource_category_id', $categoryIds);
			$finder->applyGlobalVisibilityChecks();
		}
		else if ($withinCategory)
		{
			$categoryIds = $this->repository('XFRM:Category')->getCategoryIds($withinCategory);
			$finder->where('resource_category_id', $categoryIds);
		}

		$finder->useDefaultOrder();

		return $finder;
	}

	public function findOtherResourcesByAuthor(\XFRM\Entity\ResourceItem $thisResource)
	{
		/** @var \XFRM\Finder\ResourceItem $resourceFinder */
		$resourceFinder = $this->finder('XFRM:ResourceItem');

		$resourceFinder
			->with(['User', 'CurrentVersion', 'Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id])
			->where('resource_state', 'visible')
			->where('user_id', $thisResource->user_id)
			->where('resource_id', '<>', $thisResource->resource_id)
			->setDefaultOrder('last_update', 'desc');

		return $resourceFinder;
	}

	public function findResourcesByUser($userId, array $viewableCategoryIds = null, array $limits = [])
	{
		/** @var \XFRM\Finder\ResourceItem $resourceFinder */
		$resourceFinder = $this->finder('XFRM:ResourceItem');

		$resourceFinder->where('user_id', $userId)
			->with(['full', 'fullCategory'])
			->setDefaultOrder('last_update', 'desc');

		if (is_array($viewableCategoryIds))
		{
			// if we have viewable category IDs, we likely have those permissions
			$resourceFinder->where('resource_category_id', $viewableCategoryIds);
		}
		else
		{
			$resourceFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => $userId == \XF::visitor()->user_id
		], $limits);

		if ($limits['visibility'])
		{
			$resourceFinder->applyGlobalVisibilityChecks($limits['allowOwnPending']);
		}

		return $resourceFinder;
	}

	public function findResourcesByTeamMember(
		int $userId,
		array $viewableCategoryIds = null,
		array $limits = []
	)
	{
		/** @var \XFRM\Finder\ResourceItem $resourceFinder */
		$resourceFinder = $this->finder('XFRM:ResourceItem');

		$resourceFinder->with("TeamMembers|{$userId}", true)
			->with(['full', 'fullCategory'])
			->setDefaultOrder('last_update', 'desc');

		if (is_array($viewableCategoryIds))
		{
			// if we have viewable category IDs, we likely have those permissions
			$resourceFinder->where('resource_category_id', $viewableCategoryIds);
		}
		else
		{
			$resourceFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$limits = array_replace(['visibility' => true], $limits);
		if ($limits['visibility'])
		{
			$resourceFinder->applyGlobalVisibilityChecks();
		}

		return $resourceFinder;
	}

	public function findResourceForThread(\XF\Entity\Thread $thread)
	{
		/** @var \XFRM\Finder\ResourceItem $finder */
		$finder = $this->finder('XFRM:ResourceItem');

		$finder->where('discussion_thread_id', $thread->thread_id)
			->with('full')
			->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);

		return $finder;
	}

	public function sendModeratorActionAlert(
		\XFRM\Entity\ResourceItem $resource, $action, $reason = '', array $extra = [], \XF\Entity\User $forceUser = null
	)
	{
		if (!$forceUser)
		{
			if (!$resource->user_id || !$resource->User)
			{
				return false;
			}

			$forceUser = $resource->User;
		}

		$extra = array_merge([
			'title' => $resource->title,
			'prefix_id' => $resource->prefix_id,
			'link' => $this->app()->router('public')->buildLink('nopath:resources', $resource),
			'reason' => $reason
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$forceUser,
			0, '',
			'user', $forceUser->user_id,
			"resource_{$action}", $extra,
			['dependsOnAddOnId' => 'XFRM']
		);

		return true;
	}

	public function getAvailableCurrencies(\XFRM\Entity\ResourceItem $resource = null)
	{
		$resourceCurrencies = preg_split('/\s/', $this->options()->xfrmResourceCurrencies, -1, PREG_SPLIT_NO_EMPTY);
		$currencyData = $this->app()->data('XF:Currency')->getCurrencyData();
		$output = [];

		foreach ($resourceCurrencies AS $currency)
		{
			$currency = utf8_strtoupper(utf8_substr($currency, 0, 3));
			if (isset($currencyData[$currency]))
			{
				$output[$currency] = $currencyData[$currency];
			}
			else
			{
				$output[$currency] = [
					'code' => $currency,
					'symbol' => $currency,
					'precision' => 2,
					'phrase' => 'currency.n_a'
				];
			}
		}

		if ($resource && $resource->currency)
		{
			$currency = utf8_strtoupper(utf8_substr($resource->currency, 0, 3));
			if (isset($currencyData[$currency]))
			{
				$output[$currency] = $currencyData[$currency];
			}
			else
			{
				$output[$currency] = [
					'code' => $currency,
					'symbol' => $currency,
					'precision' => 2,
					'phrase' => 'currency.n_a'
				];
			}
		}

		return $output;
	}

	public function getUserResourceCount($userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_rm_resource
			WHERE user_id = ?
				AND resource_state = 'visible'
		", $userId);
	}

	public function logResourceView(\XFRM\Entity\ResourceItem $resource)
	{
		$this->db()->query("
			INSERT INTO xf_rm_resource_view
				(resource_id, total)
			VALUES
				(? , 1)
			ON DUPLICATE KEY UPDATE
				total = total + 1
		", $resource->resource_id);
	}

	public function batchUpdateResourceViews()
	{
		$db = $this->db();
		$db->query("
			UPDATE xf_rm_resource AS r
			INNER JOIN xf_rm_resource_view AS rv ON (r.resource_id = rv.resource_id)
			SET r.view_count = r.view_count + rv.total
		");
		$db->emptyTable('xf_rm_resource_view');
	}

	/**
	 * @return int[]
	 */
	public function getResourceTeamMemberCache(
		\XFRM\Entity\ResourceItem $resource
	): array
	{
		return $this->db()->fetchAllColumn(
			'SELECT user_id
				FROM xf_rm_resource_team_member
				WHERE resource_id = ?',
			$resource->resource_id
		);
	}

	/**
	 * @return int[]
	 */
	public function rebuildResourceTeamMemberCache(
		\XFRM\Entity\ResourceItem $resource
	): array
	{
		$cache = $this->getResourceTeamMemberCache($resource);

		$resource->fastUpdate('team_member_user_ids', $cache);
		$resource->clearCache('TeamMembers');

		return $cache;
	}
}