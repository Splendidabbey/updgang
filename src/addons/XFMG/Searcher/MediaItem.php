<?php

namespace XFMG\Searcher;

use XF\Mvc\Entity\Finder;
use XF\Searcher\AbstractSearcher;

/**
 * @method \XFMG\Finder\MediaItem getFinder()
 */
class MediaItem extends AbstractSearcher
{
	protected $allowedRelations = ['Category'];

	protected $formats = [
		'title' => 'like',
		'description' => 'like',
		'username' => 'like',
		'media_date' => 'date'
	];

	protected $order = [['media_date', 'desc']];

	protected function getEntityType()
	{
		return 'XFMG:MediaItem';
	}

	protected function getDefaultOrderOptions()
	{
		return [
			'media_date' => \XF::phrase('date'),
			'title' => \XF::phrase('title'),
			'comment_count' => \XF::phrase('comments'),
			'view_count' => \XF::phrase('views'),
			'reaction_score' => \XF::phrase('reaction_score')
		];
	}

	protected function applySpecialCriteriaValue(Finder $finder, $key, $value, $column, $format, $relation)
	{
		if ($key == 'category_id' && $value == 0)
		{
			// any category so skip condition
			return true;
		}

		return false;
	}

	public function getFormData()
	{
		/** @var \XFMG\Repository\Category $categoryRepo */
		$categoryRepo = $this->em->getRepository('XFMG:Category');
		$categories = $categoryRepo->getCategoryOptionsData(false);

		return [
			'categories' => $categories
		];
	}

	public function getFormDefaults()
	{
		return [
			'category_id' => 0,

			'comment_count' => ['end' => -1],
			'view_count' => ['end' => -1],

			'media_state' => ['visible', 'moderated', 'deleted'],

			'watermarked' => [0, 1],

			'media_type' => ['image', 'audio', 'video', 'embed']
		];
	}
}