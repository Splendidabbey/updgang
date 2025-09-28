<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class MediaItem extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media');
	}

	public function actionGet(ParameterBag $params)
	{
		$media = $this->assertViewableMedia($params->media_id);

		if ($this->filter('with_comments', 'bool'))
		{
			$commentData = $this->getCommentsOnMediaPaginated($media, $this->filterPage());
		}
		else
		{
			$commentData = [];
		}

		$result = [
			'media' => $media->toApiResult(Entity::VERBOSITY_VERBOSE, ['with_container' => true])
		];
		$result += $commentData;

		return $this->apiResult($result);
	}

	public function actionGetComments(ParameterBag $params)
	{
		$media = $this->assertViewableMedia($params->media_id);

		$commentData = $this->getCommentsOnMediaPaginated($media, $this->filterPage());

		return $this->apiResult($commentData);
	}

	protected function getCommentsOnMediaPaginated(\XFMG\Entity\MediaItem $media, $page = 1, $perPage = null)
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->xfmgCommentsPerPage;
		}

		$finder = $this->setupCommentFinder($media);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$comments = $finder->limitByPage($page, $perPage)->fetch();
		$commentResults = $comments->toApiResults();

		return [
			'comments' => $commentResults,
			'pagination' => $this->getPaginationData($commentResults, $page, $perPage, $total)
		];
	}

	/**
	 * @param \XFMG\Entity\MediaItem $media
	 * @return \XFMG\Finder\Comment
	 */
	protected function setupCommentFinder(\XFMG\Entity\MediaItem $media)
	{
		return $this->repository('XFMG:Comment')->findCommentsForApi($media);
	}

	public function actionGetData(ParameterBag $params)
	{
		$media = $this->assertViewableMedia($params->media_id);

		$attachment = $media->Attachment;
		if (!$attachment)
		{
			return $this->notFound();
		}

		/** @var \XF\ControllerPlugin\Attachment $attachPlugin */
		$attachPlugin = $this->plugin('XF:Attachment');

		return $attachPlugin->displayAttachment($attachment);
	}

	public function actionPost(ParameterBag $params)
	{
		$media = $this->assertViewableMedia($params->media_id);

		if (\XF::isApiCheckingPermissions() && !$media->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupMediaEdit($media);

		if (\XF::isApiCheckingPermissions())
		{
			$editor->checkForSpam();
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		return $this->apiSuccess([
			'media' => $media->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XFMG\Entity\MediaItem $media
	 *
	 * @return \XFMG\Service\Media\Editor
	 */
	protected function setupMediaEdit(\XFMG\Entity\MediaItem $media)
	{
		$input = $this->filter([
			'title' => '?str',
			'description' => '?str',
			'custom_fields' => 'array',
			//'add_tags' => 'array-str',
			//'remove_tags' => 'array-str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
		]);

		/** @var \XFMG\Service\Media\Editor $editor */
		$editor = $this->service('XFMG:Media\Editor', $media);

		if ($input['title'] !== null)
		{
			$editor->setTitle($input['title']);
		}
		if ($input['description'] !== null)
		{
			$editor->setDescription($input['description']);
		}
		if ($input['custom_fields'])
		{
			$editor->setCustomFields($input['custom_fields'], true);
		}

		// TODO: tags

		if ($input['author_alert'] && $media->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	public function actionPostReact(ParameterBag $params)
	{
		$media = $this->assertViewableMedia($params->media_id);

		/** @var \XF\Api\ControllerPlugin\Reaction $reactPlugin */
		$reactPlugin = $this->plugin('XF:Api:Reaction');
		return $reactPlugin->actionReact($media);
	}

	public function actionDelete(ParameterBag $params)
	{
		$media = $this->assertViewableMedia($params->media_id);

		if (\XF::isApiCheckingPermissions() && !$media->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('media:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$media->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var \XFMG\Service\Media\Deleter $deleter */
		$deleter = $this->service('XFMG:Media\Deleter', $media);

		if ($this->filter('author_alert', 'bool') && $media->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFMG\Entity\MediaItem
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableMedia($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFMG:MediaItem', $id, $with);
	}
}