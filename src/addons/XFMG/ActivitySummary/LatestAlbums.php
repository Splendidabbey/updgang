<?php

namespace XFMG\ActivitySummary;

use XF\ActivitySummary\AbstractSection;
use XF\ActivitySummary\Instance;
use XF\Mvc\Entity\Finder;

class LatestAlbums extends AbstractSection
{
	protected $defaultOptions = [
		'limit' => 5,
		'category_ids' => [0],
		'include_personal_albums' => false,
		'min_comments' => null,
		'min_reaction_score' => null,
		'order' => 'create_date',
		'direction' => 'DESC'
	];

	public function getDefaultTitle(\XF\Entity\ActivitySummaryDefinition $definition)
	{
		return \XF::phrase('xfmg_latest_albums');
	}

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$categoryRepo = $this->app->repository('XFMG:Category');
			$params['categoryTree'] = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());

			$params['sortOrders'] = $this->getDefaultOrderOptions();
		}
		return $params;
	}

	protected function getBaseFinderForFetch(): Finder
	{
		return $this->finder('XFMG:Album')
			->with(['User', 'Category'])
			->setDefaultOrder($this->options['order'], $this->options['direction']);
	}

	protected function findDataForFetch(Finder $finder): Finder
	{
		/** @var \XFMG\Finder\Album $finder */

		$options = $this->options;
		$limit = $options['limit'];

		$finder
			->where('album_state', 'visible')
			->where('media_count', '>', 0)
			->limit(max($limit * 5, 25));

		$categoryIds = $options['category_ids'];
		if (!$categoryIds || in_array(0, $categoryIds))
		{
			$categoryIds = null;
		}

		if ($options['include_personal_albums'])
		{
			$finder->includePersonalAlbums($categoryIds);
		}
		else if ($categoryIds)
		{
			$finder->inCategory($categoryIds);
		}
		else
		{
			$finder->where('category_id', '>', 0);
		}

		$finder->where('create_date', '>', $this->getActivityCutOff());

		if ($options['min_comments'] !== null)
		{
			$finder->where('comment_count', '>=', $options['min_comments']);
		}

		if ($options['min_reaction_score'] !== null)
		{
			$finder->where('reaction_score', '>=', $options['min_reaction_score']);
		}

		return $finder;
	}

	protected function renderInternal(Instance $instance): string
	{
		$user = $instance->getUser();
		if (!method_exists($user, 'cacheGalleryCategoryPermissions'))
		{
			return '';
		}

		/** @var \XF\Mvc\Entity\ArrayCollection|\XFMG\Entity\Album[] $albums */
		$albums = $this->fetchData();

		$categoryIds = $albums->pluckNamed('category_id');

		$user->cacheGalleryCategoryPermissions(array_unique($categoryIds));

		foreach ($albums AS $albumId => $album)
		{
			if (!$album->canView() || $album->isIgnored())
			{
				unset($albums[$albumId]);
				continue;
			}

			if ($instance->hasSeen('xfmg_album', $albumId))
			{
				unset($albums[$albumId]);
				continue;
			}
		}

		if (!$albums->count())
		{
			return '';
		}

		$albums = $albums->slice(0, $this->options['limit']);

		foreach ($albums AS $album)
		{
			$instance->addSeen('xfmg_album', $album->album_id);
		}

		$viewParams = [
			'albums' => $albums
		];
		return $this->renderSectionTemplate($instance, 'xfmg_activity_summary_latest_albums', $viewParams);
	}

	protected function getDefaultOrderOptions()
	{
		return [
			'create_date' => \XF::phrase('date'),
			'media_count' => \XF::phrase('xfmg_media_count'),
			'comment_count' => \XF::phrase('comments'),
			'rating_weighted' => \XF::phrase('rating'),
			'reaction_score' => \XF::phrase('reaction_score'),
			'view_count' => \XF::phrase('views')
		];
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'category_ids' => 'array-uint',
			'include_personal_albums' => 'bool',
			'min_comments' => '?uint',
			'min_reaction_score' => '?int',
			'order' => 'str',
			'direction' => 'str',
		]);

		if (in_array(0, $options['category_ids']))
		{
			$options['category_ids'] = [0];
		}

		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		$orders = $this->getDefaultOrderOptions();
		if (!isset($orders[$options['order']]))
		{
			$options['order'] = 'create_date';
		}

		$options['direction'] = strtoupper($options['direction']);
		if (!in_array($options['direction'], ['ASC', 'DESC']))
		{
			$options['direction'] = 'DESC';
		}

		return true;
	}
}