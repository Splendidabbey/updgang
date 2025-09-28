<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

abstract class AbstractComment extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XFMG\Entity\MediaItem | \XFMG\Entity\Album
	 */
	abstract protected function assertViewableAndCommentableContent(ParameterBag $params);

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XFMG\Entity\MediaItem | \XFMG\Entity\Album
	 */
	abstract protected function assertViewableContent(ParameterBag $params);

	abstract protected function getLinkPrefix();

	public function actionUnread(ParameterBag $params)
	{
		$content = $this->assertViewableContent($params);

		if ($content->content_type == 'xfmg_media')
		{
			$link = 'media';
		}
		else
		{
			$link = 'media/albums';
		}

		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return $this->redirect($this->buildLink($link, $content));
		}

		$firstUnreadDate = $content->getVisitorReadDate();

		if ($firstUnreadDate <= (\XF::$time - $this->options()->readMarkingDataLifetime * 86400))
		{
			// We have no read marking data for this person, so we don't know whether they've read this before.
			// More than likely, they haven't so we have to take them to the beginning.
			return $this->redirect($this->buildLink($link, $content) . '#comments');
		}

		$commentRepo = $this->getCommentRepo();

		$findFirstUnread = $commentRepo->findNextCommentsInContent($content, $firstUnreadDate);

		if ($visitor->Profile->ignored)
		{
			$findFirstUnread->where('user_id', '<>', array_keys($visitor->Profile->ignored));
		}

		$firstUnread = $findFirstUnread->fetchOne();

		if (!$firstUnread)
		{
			$firstUnread = $content->LastComment;
		}

		if (!$firstUnread)
		{
			// sanity check, probably shouldn't happen
			return $this->redirect($this->buildLink($link, $content) . '#comments');
		}

		return $this->redirect($this->buildLink('media/comments', $firstUnread));
	}

	public function actionPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$content = $this->assertViewableAndCommentableContent($params);

		$creator = $this->setupCommentCreate($content);
		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$comment = $creator->getComment();

		return $this->plugin('XF:BbCodePreview')->actionPreview($comment->message, 'xfmg_comment', $comment->User);
	}

	public function actionDraft(ParameterBag $params)
	{
		$this->assertDraftsEnabled();

		$content = $this->assertViewableAndCommentableContent($params);

		/** @var \XF\ControllerPlugin\Draft $draftPlugin */
		$draftPlugin = $this->plugin('XF:Draft');
		return $draftPlugin->actionDraftMessage($content->draft_comment);
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $content
	 *
	 * @return \XFMG\Service\Comment\Creator
	 */
	protected function setupCommentCreate(\XF\Mvc\Entity\Entity $content)
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var \XFMG\Service\Comment\Creator $creator */
		$creator = $this->service('XFMG:Comment\Creator', $content);
		$creator->setMessage($message);

		if ($content->canAddCommentPreReg())
		{
			// only returns true when pre-reg commenting is the only option
			$creator->setIsPreRegAction(true);
		}

		return $creator;
	}

	protected function finalizeCommentCreate(\XFMG\Service\Comment\Creator $creator)
	{
		$creator->sendNotifications();

		$content = $creator->getContent();
		$content->draft_comment->delete();

		$visitor = \XF::visitor();

		if ($visitor->user_id != $content->user_id)
		{
			if ($content->content_type == 'xfmg_media')
			{
				/** @var \XFMG\Repository\MediaWatch $watchRepo */
				$watchRepo = $this->repository('XFMG:MediaWatch');
				$watchRepo->autoWatchMediaItem($content, $visitor);
			}
			else
			{
				/** @var \XFMG\Repository\AlbumWatch $watchRepo */
				$watchRepo = $this->repository('XFMG:AlbumWatch');
				$watchRepo->autoWatchAlbum($content, $visitor);
			}
		}

		if ($visitor->user_id)
		{
			if ($content->content_type == 'xfmg_media')
			{
				/** @var \XFMG\Repository\Media $mediaRepo */
				$mediaRepo = $this->repository('XFMG:Media');
				$mediaRepo->markMediaCommentsReadByVisitor($content);
			}
			else
			{
				/** @var \XFMG\Repository\Album $albumRepo */
				$albumRepo = $this->repository('XFMG:Album');
				$albumRepo->markAlbumCommentsReadByVisitor($content);
			}
		}
	}

	public function actionComment(ParameterBag $params)
	{
		$content = $this->assertViewableAndCommentableContent($params);

		$defaultMessage = '';

		$quote = $this->filter('quote', 'uint');
		if ($quote)
		{
			/** @var \XFMG\Entity\Comment $comment */
			$comment = $this->em()->find('XFMG:Comment', $quote, 'User');
			if ($comment->content_id == $content->getEntityId() && $comment->canView())
			{
				$defaultMessage = $comment->getQuoteWrapper(
					$this->app->stringFormatter()->getBbCodeForQuote($comment->message, 'xfmg_comment')
				);
			}
		}
		else if ($this->request->exists('requires_captcha'))
		{
			$defaultMessage = $this->plugin('XF:Editor')->fromInput('message');
		}
		else
		{
			$defaultMessage = $content->draft_comment->message;
		}

		$viewParams = [
			'content' => $content,
			'defaultMessage' => $defaultMessage,
			'linkPrefix' => $this->getLinkPrefix()
		];
		return $this->view('XFMG:Comment\Comment', 'xfmg_comment', $viewParams);
	}

	public function actionAddComment(ParameterBag $params)
	{
		$this->assertPostOnly();

		$content = $this->assertViewableAndCommentableContent($params);

		$isPreRegComment = $content->canReplyToCommentPreReg();

		if (!$content->canReplyToComment($error) && !$isPreRegComment)
		{
			return $this->noPermission($error);
		}

		if (!$isPreRegComment)
		{
			if ($this->filter('no_captcha', 'bool')) // JS is disabled so user hasn't seen Captcha.
			{
				$this->request->set('requires_captcha', true);
				return $this->rerouteController(
					$content->content_type == 'xfmg_media' ? 'XFMG:MediaComment' : 'XFMG:AlbumComment', 'comment', $params
				);
			}
			else if (!$this->captchaIsValid())
			{
				return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
			}
		}

		$creator = $this->setupCommentCreate($content);
		$creator->checkForSpam();

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}
		$this->assertNotFlooding('post');

		if ($isPreRegComment)
		{
			$preRegPlugin = $this->plugin('XF:PreRegAction');
			return $preRegPlugin->actionPreRegAction(
				($content->content_type == 'xfmg_media') ? 'XFMG:Media\Comment' : 'XFMG:Album\Comment',
				$content,
				$this->getPreRegCommentActionData($creator)
			);
		}

		$comment = $creator->save();

		$this->finalizeCommentCreate($creator);

		if ($this->filter('_xfWithData', 'bool') && $this->request->exists('last_date') && $comment->canView())
		{
			$commentRepo = $this->getCommentRepo();

			$limit = 3;
			$lastDate = $this->filter('last_date', 'uint');

			/** @var \XF\Mvc\Entity\Finder $commentList */
			$commentList = $commentRepo->findLatestCommentsForContent($content, $lastDate)->limit($limit + 1);
			$comments = $commentList->fetch();

			$lightbox = $this->filter('lightbox', 'bool');

			// We fetched one more comment than needed, if more than $limit comments were returned,
			// we can show the 'there are more comments' notice
			if ($comments->count() > $limit)
			{
				$firstUnshownComment = $comments->last();

				// Remove the extra post
				$comments = $comments->pop();
			}
			else
			{
				$firstUnshownComment = null;
			}

			// put the comments into oldest-first order
			$comments = $comments->reverse(true);
			$last = $comments->last();
			if ($last)
			{
				$lastDate = $last->comment_date;
			}

			$viewParams = [
				'content' => $content,
				'comments' => $comments,
				'linkPrefix' => $this->getLinkPrefix(),
				'firstUnshownComment' => $firstUnshownComment,
				'lightbox' => $lightbox
			];
			$view = $this->view('XFMG:Comment\NewComments', 'xfmg_comment_new_comments', $viewParams);
			$view->setJsonParam('lastDate', $lastDate);
			return $view;
		}
		else
		{
			return $this->redirect($this->buildLink('media/comments', $comment), \XF::phrase('xfmg_your_comment_has_been_added'));
		}
	}

	protected function getPreRegCommentActionData(\XFMG\Service\Comment\Creator $creator)
	{
		$comment = $creator->getComment();

		// note: attachments aren't supported

		return [
			'message' => $comment->message
		];
	}

	public function actionMultiQuote(ParameterBag $params)
	{
		$this->assertPostOnly();

		$content = $this->assertViewableAndCommentableContent($params);

		/** @var \XF\ControllerPlugin\Quote $quotePlugin */
		$quotePlugin = $this->plugin('XF:Quote');

		$quotes = $this->filter('quotes', 'json-array');
		if (!$quotes)
		{
			return $this->error(\XF::phrase('no_messages_selected'));
		}
		$quotes = $quotePlugin->prepareQuotes($quotes);

		$commentFinder = $this->finder('XFMG:Comment');

		$comments = $commentFinder
			->where('comment_id', array_keys($quotes))
			->order('comment_date', 'DESC')
			->fetch();

		if ($this->request->exists('insert'))
		{
			$insertOrder = $this->filter('insert', 'array');
			return $quotePlugin->actionMultiQuote($comments, $insertOrder, $quotes, 'xfmg_comment');
		}
		else
		{
			$viewParams = [
				'quotes' => $quotes,
				'comments' => $comments
			];
			return $this->view('XFMG:Comment\MultiQuote', 'xfmg_comment_multi_quote', $viewParams);
		}
	}
}