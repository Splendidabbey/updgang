<?php

namespace XFMG\ActivitySummary;

use XF\ActivitySummary\AbstractSection;
use XF\ActivitySummary\Instance;
use XF\Mvc\Entity\Finder;

class LatestComments extends AbstractSection
{
	protected $defaultOptions = [
		'limit' => 5,
		'min_reaction_score' => null,
		'order' => 'comment_date',
		'direction' => 'DESC'
	];

	public function getDefaultTitle(\XF\Entity\ActivitySummaryDefinition $definition)
	{
		return \XF::phrase('xfmg_latest_comments');
	}

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$params['sortOrders'] = $this->getDefaultOrderOptions();
		}
		return $params;
	}

	protected function getBaseFinderForFetch(): Finder
	{
		return $this->finder('XFMG:Comment')
			->with(['Album.Category', 'Media.Album', 'Media.Category', 'Rating'])
			->setDefaultOrder($this->options['order'], $this->options['direction']);
	}

	protected function findDataForFetch(Finder $finder): Finder
	{
		$options = $this->options;
		$limit = $options['limit'];

		$finder
			->where('comment_state', 'visible')
			->limit(max($limit * 10, 25));

		$finder->where('comment_date', '>', $this->getActivityCutOff());

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

		/** @var \XF\Mvc\Entity\ArrayCollection|\XFMG\Entity\Comment[] $comments */
		$comments = $this->fetchData();

		$categoryIds = $comments->pluck(
			function (\XFMG\Entity\Comment $comment)
			{
				return $comment->Content ? [$comment->comment_id, $comment->Content->category_id] : null;
			},
			false
		);
		$user->cacheGalleryCategoryPermissions(array_unique($categoryIds));

		foreach ($comments AS $commentId => $comment)
		{
			if (!$comment->canView() || $comment->isIgnored())
			{
				unset($comments[$commentId]);
				continue;
			}

			if ($instance->hasSeen('xfmg_comment', $commentId))
			{
				unset($comments[$commentId]);
				continue;
			}
		}

		if (!$comments->count())
		{
			return '';
		}

		$comments = $comments->slice(0, $this->options['limit']);

		foreach ($comments AS $comment)
		{
			$instance->addSeen('xfmg_comment', $comment->comment_id);
		}

		$viewParams = [
			'comments' => $comments
		];
		return $this->renderSectionTemplate($instance, 'xfmg_activity_summary_latest_comments', $viewParams);
	}

	protected function getDefaultOrderOptions()
	{
		return [
			'comment_date' => \XF::phrase('date'),
			'reaction_score' => \XF::phrase('reaction_score')
		];
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'min_reaction_score' => '?int',
			'order' => 'str',
			'direction' => 'str'
		]);

		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		$orders = $this->getDefaultOrderOptions();
		if (!isset($orders[$options['order']]))
		{
			$options['order'] = 'comment_date';
		}

		$options['direction'] = strtoupper($options['direction']);
		if (!in_array($options['direction'], ['ASC', 'DESC']))
		{
			$options['direction'] = 'DESC';
		}

		return true;
	}
}