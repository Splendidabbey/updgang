<?php

namespace XFMG\Searcher;

use XF\Mvc\Entity\Finder;
use XF\Searcher\AbstractSearcher;

/**
 * @method \XFMG\Finder\Album getFinder()
 */
class Album extends AbstractSearcher
{
	protected $allowedRelations = ['Category'];

	protected $formats = [
		'title' => 'like',
		'description' => 'like',
		'username' => 'like',
		'create_date' => 'date'
	];

	protected $order = [['create_date', 'desc']];

	protected function getEntityType()
	{
		return 'XFMG:Album';
	}

	protected function getDefaultOrderOptions()
	{
		return [
			'create_date' => \XF::phrase('date'),
			'title' => \XF::phrase('title'),
			'comment_count' => \XF::phrase('comments'),
			'view_count' => \XF::phrase('views'),
			'reaction_score' => \XF::phrase('reaction_score')
		];
	}

	public function getFormData()
	{
		// TODO: Need to add support for finding albums inside categories, or personal albums only.
		return [];
	}

	public function getFormDefaults()
	{
		return [
			'comment_count' => ['end' => -1],
			'view_count' => ['end' => -1],

			'album_state' => ['visible', 'moderated', 'deleted']
		];
	}
}