<?php

namespace XFRM\EditHistory;

use XF\EditHistory\AbstractHandler;
use XF\Entity\EditHistory;
use XF\Mvc\Entity\Entity;

class ResourceUpdate extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function canViewHistory(Entity $content)
	{
		/** @var \XFRM\Entity\ResourceUpdate $content */
		return $content->canView() && $content->canViewHistory();
	}

	/**
	 * @return bool
	 */
	public function canRevertContent(Entity $content)
	{
		/** @var \XFRM\Entity\ResourceUpdate $content */
		return $content->canEdit();
	}

	/**
	 * @return string
	 */
	public function getContentText(Entity $content)
	{
		/** @var \XFRM\Entity\ResourceUpdate $content */
		return $content->message;
	}

	/**
	 * @return array
	 */
	public function getBreadcrumbs(Entity $content)
	{
		/** @var \XFRM\Entity\ResourceUpdate $content */
		return $content->Resource ? $content->Resource->getBreadcrumbs() : [];
	}

	/**
	 * @return \XFRM\Entity\ResourceUpdate
	 */
	public function revertToVersion(
		Entity $content,
		EditHistory $history,
		EditHistory $previous = null
	)
	{
		/** @var \XFRM\Entity\ResourceUpdate $content */

		/** @var \XFRM\Service\ResourceUpdate\Edit $editor */
		$editor = \XF::service('XFRM:ResourceUpdate\Edit', $content);
		$editor->logEdit(false);
		$editor->setIsAutomated();
		$editor->setMessage($history->old_text);

		$contentUserId = $content->team_user_id ?: $content->Resource->user_id;

		if (!$previous || $previous->edit_user_id != $contentUserId)
		{
			$content->last_edit_date = 0;
		}
		else if ($previous && $previous->edit_user_id == $contentUserId)
		{
			$content->last_edit_date = $previous->edit_date;
			$content->last_edit_user_id = $previous->edit_user_id;
		}

		return $editor->save();
	}

	/**
	 * @param string $text
	 *
	 * @return string
	 */
	public function getHtmlFormattedContent($text, Entity $content = null)
	{
		return \XF::app()->templater()->func(
			'bb_code',
			[$text, 'resource_update', $content]
		);
	}

	/**
	 * @return string
	 */
	public function getSectionContext()
	{
		return 'xfrm';
	}

	/**
	 * @return array
	 */
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return [
			'Resource',
			'Resource.Category',
			'Resource.Category.Permissions|' . $visitor->permission_combination_id
		];
	}
}
