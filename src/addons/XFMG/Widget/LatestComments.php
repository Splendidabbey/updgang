<?php

namespace XFMG\Widget;

use XF\Widget\AbstractWidget;

class LatestComments extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5
	];

	public function render()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewMedia') || !$visitor->canViewMedia())
		{
			return '';
		}

		/** @var \XFMG\Repository\Comment $commentRepo */
		$commentRepo = $this->repository('XFMG:Comment');
		$finder = $commentRepo->findLatestCommentsForWidget();
		$comments = $finder->fetch($this->options['limit'] * 10);

		foreach ($comments AS $id => $comment)
		{
			/** @var \XFMG\Entity\Comment $comment */
			if (!$comment->canView() || $comment->isIgnored() || $comment->Content->isIgnored())
			{
				unset($comments[$id]);
			}
		}

		$comments = $comments->slice(0, $this->options['limit']);

		$router = $this->app->router('public');
		$link = $router->buildLink('whats-new/media-comments', null, ['skip' => 1]);

		$viewParams = [
			'comments' => $comments,
			'link' => $link
		];
		return $this->renderer('xfmg_widget_latest_comments', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint'
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}