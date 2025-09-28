<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @property \XF\Draft $draft_comment
 */
trait CommentableTrait
{
	abstract public function hasPermission($permission);

	public function canViewComments(&$error = null)
	{
		return $this->hasPermission('viewComments');
	}

	public function canAddComment(&$error = null)
	{
		if (!$this->isVisible())
		{
			return false;
		}

		return $this->hasPermission('addComment');
	}

	public function canAddCommentPreReg()
	{
		if (\XF::visitor()->user_id || $this->canAddComment())
		{
			// quick bypass with the user ID check, then ensure that this can only return true if the visitor
			// can't take the "normal" action
			return false;
		}

		return \XF::canPerformPreRegAction(
			function () { return $this->canAddComment(); }
		);
	}

	public function canReplyToComment(&$error = null)
	{
		return $this->canAddComment($error);
	}

	public function canReplyToCommentPreReg()
	{
		return $this->canAddCommentPreReg();
	}

	public function canViewDeletedComments()
	{
		return $this->hasPermission('viewDeletedComments');
	}

	public function canViewModeratedComments()
	{
		return $this->hasPermission('viewModeratedComments');
	}

	public function canViewCommentModeratorLogs(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && ($this->hasPermission('editAnyComment') || $this->hasPermission('deleteAnyComment'));
	}

	/**
	 * @return \XF\Draft
	 */
	public function getDraftComment()
	{
		return \XF\Draft::createFromEntity($this, 'DraftComments');
	}

	public function getNewCommentState()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->user_id && $this->hasPermission('approveUnapproveComment'))
		{
			return 'visible';
		}

		if (!$visitor->hasPermission('general', 'submitWithoutApproval'))
		{
			return 'moderated';
		}

		return 'visible';
	}

	/**
	 * @return \XFMG\Entity\Comment
	 */
	public function getNewComment()
	{
		$comment = $this->em()->create('XFMG:Comment');
		$comment->content_type = $this->content_type;
		$comment->content_id = $this->getEntityId();

		return $comment;
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->structure()->contentType;
	}

	public function getVisitorReadDate()
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return null;
		}

		$commentRead = $this->CommentRead[$visitor->user_id];
		$contentViewed = isset($this->Viewed) ? $this->Viewed[$visitor->user_id] : null;

		$dates = [\XF::$time - $this->app()->options()->readMarkingDataLifetime * 86400];
		if ($commentRead)
		{
			$dates[] = $commentRead->comment_read_date;
		}
		if ($contentViewed)
		{
			if ($this->content_type == 'xfmg_media')
			{
				$dates[] = $contentViewed->media_view_date;
			}
			else
			{
				$dates[] = $contentViewed->album_view_date;
			}
		}

		return max($dates);
	}

	public function getCommentIds()
	{
		return $this->db()->fetchAllColumn("
			SELECT comment_id
			FROM xf_mg_comment
			WHERE content_type = ?
				AND content_id = ?
			ORDER BY comment_date
		", [$this->content_type, $this->getEntityId()]);
	}

	public function rebuildCommentCount()
	{
		$this->comment_count = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_mg_comment
			WHERE content_type = ?
				AND content_id = ?
				AND comment_state = 'visible'
		", [$this->content_type, $this->getEntityId()]);

		return $this->comment_count;
	}

	public function rebuildLastCommentInfo()
	{
		$lastComment = $this->db()->fetchRow("
			SELECT comment_id, comment_date, user_id, username
			FROM xf_mg_comment
			WHERE content_id = ?
				AND content_type = ?
				AND comment_state = 'visible'
			ORDER BY comment_date DESC
			LIMIT 1
		", [$this->getEntityId(), $this->content_type]);

		if (!$lastComment)
		{
			$lastComment = [
				'comment_id' => 0,
				'comment_date' => 0,
				'user_id' => 0,
				'username' => ''
			];
		}

		$this->last_comment_id = $lastComment['comment_id'];
		$this->last_comment_date = $lastComment['comment_date'];
		$this->last_comment_user_id = $lastComment['user_id'];
		$this->last_comment_username = $lastComment['username'] ?: '-';

		return true;
	}

	public function commentAdded(Comment $comment)
	{
		$this->comment_count++;

		if ($comment->comment_date >= $this->last_comment_date)
		{
			$this->last_comment_date = $comment->comment_date;
			$this->last_comment_id = $comment->comment_id;
			$this->last_comment_user_id = $comment->user_id;
			$this->last_comment_username = $comment->username;
		}
	}

	public function commentRemoved(Comment $comment)
	{
		$this->comment_count--;

		if ($comment->comment_id == $this->last_comment_id)
		{
			$this->rebuildLastCommentInfo();
		}
	}

	protected function _postDeleteComments()
	{
		$commentIds = $this->comment_ids;
		if ($commentIds)
		{
			$db = $this->db();

			/** @var \XF\Repository\Reaction $reactionRepo */
			$reactionRepo = $this->repository('XF:Reaction');
			$reactionRepo->fastDeleteReactions('xfmg_comment', $commentIds);

			$db->delete('xf_mg_comment', 'comment_id IN (' . $db->quote($commentIds) . ')');

			$db->delete('xf_approval_queue', 'content_id IN (' . $db->quote($commentIds) . ') AND content_type = ?', 'xfmg_comment');
			$db->delete('xf_deletion_log', 'content_id IN (' . $db->quote($commentIds) . ') AND content_type = ?', 'xfmg_comment');
			$db->delete('xf_edit_history', 'content_id IN (' . $db->quote($commentIds) . ') AND content_type = ?', 'xfmg_comment');
		}
	}

	protected static function addCommentableStructureElements(Structure $structure)
	{
		$structure->columns['last_comment_date'] = ['type' => self::UINT, 'default' => 0, 'api' => true];
		$structure->columns['last_comment_id'] = ['type' => self::UINT, 'default' => 0, 'api' => true];
		$structure->columns['last_comment_user_id'] = ['type' => self::UINT, 'default' => 0, 'api' => true];
		$structure->columns['last_comment_username'] = ['type' => self::STR, 'maxLength' => 50, 'default' => '', 'api' => true];
		$structure->columns['comment_count'] = ['type' => self::UINT, 'forced' => true, 'api' => true];

		$structure->getters['draft_comment'] = true;
		$structure->getters['content_type'] = true;
		$structure->getters['comment_ids'] = true;

		$structure->relations['DraftComments'] = [
			'entity' => 'XF:Draft',
			'type' => self::TO_MANY,
			'conditions' => [
				['draft_key', '=', str_replace('_', '-', $structure->contentType) . '-comment-', '$' . $structure->primaryKey]
			],
			'key' => 'user_id'
		];

		$structure->relations['LastComment'] = [
			'entity' => 'XFMG:Comment',
			'type' => self::TO_ONE,
			'conditions' => [['comment_id', '=', '$last_comment_id']],
			'primary' => true
		];

		$structure->relations['LastCommenter'] = [
			'entity' => 'XF:User',
			'type' => self::TO_ONE,
			'conditions' => [['user_id', '=', '$last_comment_user_id']],
			'primary' => true
		];

		$structure->relations['CommentRead'] = [
			'entity' => (
				$structure->contentType == 'xfmg_media'
					? 'XFMG:MediaCommentRead'
					: 'XFMG:AlbumCommentRead'
			),
			'type' => self::TO_MANY,
			'conditions' => $structure->primaryKey,
			'key' => 'user_id'
		];
	}
}