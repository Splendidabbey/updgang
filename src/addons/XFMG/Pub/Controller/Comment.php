<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

class Comment extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		$content = $comment->Content;

		$commentRepo = $this->getCommentRepo();

		$commentList = $commentRepo->findCommentsForContent($content)
			->applyVisibilityChecksInContent($content)
			->where('comment_id', '<', $comment->comment_id)
			->fetch();
		$commentList = $commentList->filterViewable();
		$commentsBefore = $commentList->count();

		$perPage = $this->options()->xfmgCommentsPerPage;
		$page = floor($commentsBefore / $perPage) + 1;
		$params = [];

		if ($comment->content_type == 'xfmg_media')
		{
			$link = 'media';

			if ($page > 1)
			{
				$params['page'] = $page;
			}
		}
		else
		{
			$link = 'media/albums';

			if ($page > 1)
			{
				$params['comment_page'] = $page;
			}
		}

		return $this->redirectPermanently(
			$this->buildLink($link, $content, $params) . '#xfmg-comment-' . $comment->comment_id
		);
	}

	public function actionShow(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		$content = $comment->Content;

		$viewParams = [
			'comment' => $comment,
			'content' => $content,
			'canInlineMod' => $comment->canUseInlineModeration(),
			'lightbox' => $this->filter('lightbox', 'bool')
		];
		return $this->view('XFMG:Comment\Show', 'xfmg_comment_show', $viewParams);
	}

	/**
	 * @param \XFMG\Entity\Comment $comment
	 *
	 * @return \XFMG\Service\Comment\Editor
	 */
	protected function setupCommentEdit(\XFMG\Entity\Comment $comment)
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var \XFMG\Service\Comment\Editor $editor */
		$editor = $this->service('XFMG:Comment\Editor', $comment);
		if ($comment->canEditSilently())
		{
			$silentEdit = $this->filter('silent', 'bool');
			if ($silentEdit)
			{
				$editor->logEdit(false);
				if ($this->filter('clear_edit', 'bool'))
				{
					$comment->last_edit_date = 0;
				}
			}
		}
		$editor->setMessage($message);

		if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	protected function finalizeCommentEdit(\XFMG\Service\Comment\Editor $editor) {}

	public function actionEdit(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		if (!$comment->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$lightbox = $this->filter('lightbox', 'bool');

		if ($this->isPost())
		{
			$editor = $this->setupCommentEdit($comment);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			$this->finalizeCommentEdit($editor);

			if ($this->filter('_xfWithData', 'bool'))
			{
				$content = $comment->Content;

				$viewParams = [
					'comment' => $comment,
					'content' => $content,
					'lightbox' => $lightbox
				];
				$reply = $this->view('XFMG:Comment\EditNewComment', 'xfmg_comment_edit_new_comment', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('redirect_changes_saved_successfully'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('media/comments', $comment));
			}
		}
		else
		{
			$viewParams = [
				'comment' => $comment,
				'content' => $comment->Content,
				'quickEdit' => $this->filter('_xfWithData', 'bool'),
				'lightbox' => $lightbox
			];
			return $this->view('XFMG:Comment\Edit', 'xfmg_comment_edit', $viewParams);
		}
	}

	public function actionDelete(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		if (!$comment->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$comment->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \XFMG\Service\Comment\Deleter $deleter */
			$deleter = $this->service('XFMG:Comment\Deleter', $comment);

			if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin('XF:InlineMod')->clearIdFromCookie('xfmg_comment', $comment->comment_id);

			return $this->redirect(
				$this->buildLink('media' . ($comment->content_type == 'xfmg_album' ? '/albums' : ''), $comment->Content)
			);
		}
		else
		{
			$viewParams = [
				'comment' => $comment,
				'content' => $comment->Content
			];
			return $this->view('XFMG:Comment\Delete', 'xfmg_comment_delete', $viewParams);
		}
	}

	public function actionIp(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		$breadcrumbs = $comment->Content->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($comment, $breadcrumbs);
	}

	public function actionReport(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		if (!$comment->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'xfmg_comment', $comment,
			$this->buildLink('media/comments/report', $comment),
			$this->buildLink('media/comments', $comment)
		);
	}

	public function actionQuote(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);
		return $this->plugin('XF:Quote')->actionQuote($comment, 'xfmg_comment');
	}

	public function actionHistory(ParameterBag $params)
	{
		return $this->rerouteController('XF:EditHistory', 'index', [
			'content_type' => 'xfmg_comment',
			'content_id' => $params->comment_id
		]);
	}

	public function actionReact(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($comment, 'media/comments');
	}

	public function actionReactions(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		$breadcrumbs = $comment->Content->getBreadcrumbs();
		$title = \XF::phrase('xfmg_members_who_have_reacted_to_comment_by_x', ['user' => $comment->username]);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactions(
			$comment,
			'media/comments/reactions',
			$title, $breadcrumbs
		);
	}

	public function actionWarn(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->comment_id);

		if (!$comment->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $comment->Content->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'xfmg_comment', $comment,
			$this->buildLink('media/comments/warn', $comment),
			$breadcrumbs
		);
	}
}