<?php

namespace XFMG\Widget;

use XF\Widget\AbstractWidget;

class AlbumSlider extends AbstractWidget
{
	protected $defaultOptions = [
		'category_ids' => [0],
		'include_personal_albums' => false,
		'order' => 'latest',
		'limit' => 12,
		'slider' => [
			'item' => 6,
			'itemWide' => 4,
			'itemMedium' => 3,
			'itemNarrow' => 2,
			'auto' => false,
			'loop' => false,
			'pager' => false
		]
	];

	public function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$categoryRepo = $this->repository('XFMG:Category');
			$categoryList = $categoryRepo->findCategoryList()->fetch();
			$params['categoryTree'] = $categoryRepo->createCategoryTree($categoryList);
		}
		return $params;
	}

	public function render()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewMedia') || !$visitor->canViewMedia())
		{
			return '';
		}

		$categoryRepo = $this->repository('XFMG:Category');

		$categoryIds = array_unique($this->options['category_ids']);
		if ($categoryIds && !in_array(0, $categoryIds))
		{
			$categoryList = $categoryRepo
				->findCategoryList(null, 'Permissions|' . \XF::visitor()->permission_combination_id)
				->where('category_id', $categoryIds)
				->fetch()
				->filterViewable();
		}
		else
		{
			$categoryList = $categoryRepo->getViewableCategories();
		}

		$viewableCategories = $categoryList->filter(function($category)
		{
			return ($category->category_type == 'album');
		});
		$categoryIds = $viewableCategories->keys();

		$albumRepo = $this->repository('XFMG:Album');
		$albumList = $albumRepo->findAlbumsForWidget($categoryIds, $this->options['include_personal_albums'])
			->limit($this->options['limit'] * 10);

		$title = \XF::phrase('xfmg_latest_albums');

		if ($this->options['order'] == 'random')
		{
			$albumIds = $this->app->simpleCache()->XFMG->randomAlbumCache;
			if ($albumIds)
			{
				$title = \XF::phrase('xfmg_random_albums');
				shuffle($albumIds);
				$albumIds = array_slice($albumIds, 0, $this->options['limit'] * 10);
				$albumList->where('album_id', $albumIds);
			}
			else
			{
				// Treat as latest albums if there are no album IDs.
				$albumList->orderByDate();
				$this->options['order'] = 'latest';
			}
		}
		else
		{
			$albumList->orderByDate();
		}

		$albums = $albumList->fetch()->filterViewable();

		if ($this->options['order'] == 'random')
		{
			$albums = $albums->shuffle();
		}

		$router = $this->app->router('public');
		$link = $router->buildLink('media/albums');

		$viewParams = [
			'albums' => $albums->slice(0,$this->options['limit']),
			'title' => $this->getTitle() ?: $title,
			'link' => $link
		];
		return $this->renderer('xfmg_widget_album_slider', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'category_ids' => 'array-uint',
			'include_personal_albums' => 'bool',
			'order' => 'str',
			'limit' => 'uint',
			'slider' => [
				'item' => 'uint',
				'itemWide' => 'uint',
				'itemMedium' => 'uint',
				'itemNarrow' => 'uint',
				'auto' => 'bool',
				'loop' => 'bool',
				'pager' => 'bool'
			]
		]);
		if (in_array(0, $options['category_ids']))
		{
			$options['category_ids'] = [0];
		}
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}