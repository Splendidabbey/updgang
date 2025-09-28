<?php

namespace XFMG\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;

abstract class AbstractList extends AbstractPlugin
{
	public function getCategoryListData(\XFMG\Entity\Category $category = null)
	{
		$categoryRepo = $this->getCategoryRepo();

		$categories = $categoryRepo->getViewableCategories();

		$viewableCategories = $categories->filter(function($category)
		{
			return ($category->category_type == 'media' || $category->category_type == 'album');
		});

		$albumCategories = $categories->filter(function($category)
		{
			return ($category->category_type == 'album');
		});
		$hasAlbumCategories = $albumCategories->count();

		$categoryTree = $categoryRepo->createCategoryTree($categories);
		$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);

		if ($category)
		{
			$descendents = $categories->filter(function($item) use ($category)
			{
				return ($item->lft > $category->lft && $item->rgt < $category->rgt);
			});
			$descendentTree = $categoryRepo->createCategoryTree($descendents, $category->category_id);
			$descendentExtras = $categoryRepo->getCategoryListExtras($descendentTree);

			$types = $descendents->pluckNamed('category_type');
			$counts = array_count_values($types);

			$primaryType = 'media';
			if (isset($counts['album']) && !isset($counts['media']))
			{
				$primaryType = 'album'; // where applicable, a list of albums will be shown
			}
		}
		else
		{
			$descendents = [];
			$descendentTree = null;
			$descendentExtras = [];
			$primaryType = 'media';
		}

		return [
			'category' => $category,
			'categories' => $categories,
			'descendents' => $descendents,
			'viewableCategories' => $viewableCategories,
			'albumCategories' => $albumCategories,
			'hasAlbumCategories' => $hasAlbumCategories,

			'categoryTree' => $categoryTree,
			'categoryExtras' => $categoryExtras,

			'descendentTree' => $descendentTree,
			'descendentExtras' => $descendentExtras,
			'primaryType' => $primaryType
		];
	}

	public function applyFilters(\XF\Mvc\Entity\Finder $finder, array $filters)
	{
		if (!empty($filters['owner_id']))
		{
			$finder->where('user_id', intval($filters['owner_id']));
		}

		$sorts = $this->getAvailableSorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$finder->order($sorts[$filters['order']], $filters['direction']);
		}
		// else the default order has already been applied
	}

	protected $defaultSort = '';

	public function getAvailableSorts()
	{
		// maps [name of sort] => field in/relative to MediaItem entity
		return [
			'comment_count' => 'comment_count',
			'rating_weighted' => 'rating_weighted',
			'reaction_score' => 'reaction_score',
			'view_count' => 'view_count'
		];
	}

	public function getFilterInput()
	{
		$filters = [];

		$input = $this->filter([
			'owner' => 'str',
			'owner_id' => 'uint',
			'order' => 'str',
			'direction' => 'str'
		]);

		if ($input['owner_id'])
		{
			$filters['owner_id'] = $input['owner_id'];
		}
		else if ($input['owner'])
		{
			$user = $this->em()->findOne('XF:User', ['username' => $input['owner']]);
			if ($user)
			{
				$filters['owner_id'] = $user->user_id;
			}
		}

		$sorts = $this->getAvailableSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			$defaultOrder = $this->defaultSort;
			if ($input['order'] != $defaultOrder || $input['direction'] != 'desc')
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}

		return $filters;
	}

	protected function apply(array $filters, \XFMG\Entity\Category $category = null, \XF\Entity\User $user = null) {}

	public function actionFilters(\XFMG\Entity\Category $category = null, \XF\Entity\User $user = null)
	{
		$filters = $this->getFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->apply($filters, $category, $user);
		}

		if (!empty($filters['owner_id']) && !$user)
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		$applicableCategories = $this->getCategoryRepo()->getViewableCategories($category);
		$applicableCategoryIds = $applicableCategories->keys();
		if ($category)
		{
			$applicableCategoryIds[] = $category->category_id;
		}

		$viewParams = [
			'category' => $category,
			'user' => $user,
			'filters' => $filters,
			'ownerFilter' => $ownerFilter
		];
		return $this->view('XFMG:Media\Filters', 'xfmg_media_filters', $viewParams);
	}

	/**
	 * @return \XFMG\Repository\Media
	 */
	protected function getMediaRepo()
	{
		return $this->repository('XFMG:Media');
	}

	/**
	 * @return \XFMG\Repository\Album
	 */
	protected function getAlbumRepo()
	{
		return $this->repository('XFMG:Album');
	}

	/**
	 * @return \XFMG\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFMG:Category');
	}
}