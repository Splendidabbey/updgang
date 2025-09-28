<?php

namespace XFMG\Entity;

use XF\Entity\BookmarkTrait;
use XF\Entity\ReactionTrait;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Arr;

/**
 * COLUMNS
 * @property int|null album_id
 * @property int category_id
 * @property string album_hash
 * @property string title
 * @property string description
 * @property int create_date
 * @property int last_update_date
 * @property array media_item_cache
 * @property string|null view_privacy
 * @property array view_users
 * @property string|null add_privacy
 * @property array add_users
 * @property string album_state
 * @property int user_id
 * @property string username
 * @property int ip_id
 * @property int media_count
 * @property int view_count
 * @property int warning_id
 * @property string warning_message
 * @property string default_order
 * @property int thumbnail_date
 * @property int custom_thumbnail_date
 * @property int last_comment_date
 * @property int last_comment_id
 * @property int last_comment_user_id
 * @property string last_comment_username
 * @property int comment_count
 * @property int rating_count
 * @property int rating_sum
 * @property float rating_avg
 * @property float rating_weighted
 * @property int reaction_score
 * @property array reactions_
 * @property array reaction_users_
 *
 * GETTERS
 * @property array allowed_types
 * @property array field_cache
 * @property int min_tags
 * @property string|null] thumbnail_url
 * @property \XF\Mvc\Entity\ArrayCollection MediaCache
 * @property array structured_data
 * @property \XF\Draft draft_comment
 * @property string content_type
 * @property mixed comment_ids
 * @property array rating_ids
 * @property mixed reactions
 * @property mixed reaction_users
 *
 * RELATIONS
 * @property \XFMG\Entity\Category Category
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\SharedMapAdd[] SharedMapAdd
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\SharedMapView[] SharedMapView
 * @property \XF\Entity\ApprovalQueue ApprovalQueue
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\AlbumWatch[] Watch
 * @property \XF\Entity\DeletionLog DeletionLog
 * @property \XF\Entity\User User
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Draft[] DraftComments
 * @property \XFMG\Entity\Comment LastComment
 * @property \XF\Entity\User LastCommenter
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\AlbumCommentRead[] CommentRead
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\Rating[] Ratings
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\ReactionContent[] Reactions
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\BookmarkItem[] Bookmarks
 */
class Album extends Entity implements \XF\Entity\LinkableInterface
{
	use CommentableTrait, RateableTrait, ReactionTrait, BookmarkTrait;

	public function canView(&$error = null)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($this->album_state == 'moderated')
		{
			if (
				!$this->hasPermission('viewModeratedAlbums')
				&& (!$visitor->user_id || $visitor->user_id != $this->user_id)
			)
			{
				$error = \XF::phraseDeferred('xfmg_requested_album_not_found');
				return false;
			}
		}
		else if ($this->album_state == 'deleted')
		{
			if (!$this->hasPermission('viewDeletedAlbums'))
			{
				$error = \XF::phraseDeferred('xfmg_requested_album_not_found');
				return false;
			}
		}

		if ($this->category_id && $this->Category)
		{
			return $this->Category->canView($error);
		}

		if (!$this->hasPermission('view') || !$this->app()->options()->xfmgAllowPersonalAlbums)
		{
			return false;
		}

		if ($visitor->user_id)
		{
			$canView = (
				$this->hasPermission('bypassPrivacy')
					|| $this->view_privacy == 'public'
					|| $this->view_privacy == 'members'
					|| ($this->view_privacy == 'private' && $this->user_id == $visitor->user_id)
					|| ($this->view_privacy == 'shared' && $this->user_id == $visitor->user_id)
					|| ($this->view_privacy == 'shared' && in_array($visitor->user_id, $this->view_users))
			);
		}
		else
		{
			$canView = ($this->view_privacy == 'public');
		}

		if (!$canView)
		{
			return false;
		}

		return true;
	}

	public function canAddMedia(&$error = null)
	{
		if (!$this->user_id)
		{
			// If an album is owned by a deleted user, we shouldn't allow further additions.
			return false;
		}

		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($this->category_id && $this->Category && $this->Category->category_type == 'album')
		{
			return ($visitor->user_id == $this->user_id);
		}
		else if (!$this->app()->options()->xfmgAllowPersonalAlbums)
		{
			return false;
		}

		if ($visitor->user_id)
		{
			$canAdd = (
				$this->add_privacy == 'public'
				|| $this->add_privacy == 'members'
				|| ($this->add_privacy == 'private' && $this->user_id == $visitor->user_id)
				|| ($this->add_privacy == 'shared' && $this->user_id == $visitor->user_id)
				|| ($this->add_privacy == 'shared' && in_array($visitor->user_id, $this->add_users))
			);
		}
		else
		{
			$canAdd = ($this->add_privacy == 'public');
		}

		if (!$canAdd)
		{
			$error = \XF::phraseDeferred('xfmg_you_do_not_have_permission_to_add_media_to_this_album');
			return false;
		}

		return ($this->canUploadMedia($error) || $this->canEmbedMedia($error));
	}

	public function canUploadMedia(&$error = null)
	{
		foreach ($this->allowed_types AS $type)
		{
			if ($type == 'image' || $type == 'video' || $type == 'audio')
			{
				return true;
			}
		}
		return false;
	}

	public function canEmbedMedia(&$error = null)
	{
		return in_array('embed', $this->allowed_types);
	}

	public function canChangePrivacy(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->category_id)
		{
			return false;
		}

		if ($this->hasPermission('changePrivacyAnyAlbum'))
		{
			return true;
		}

		$changePrivacyOwnAlbum = $this->hasPermission('changePrivacyOwnAlbum');

		if ($this->user_id == $visitor->user_id && $changePrivacyOwnAlbum)
		{
			return true;
		}

		if ($this->isInsert() && $changePrivacyOwnAlbum)
		{
			return true;
		}

		return false;
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->hasPermission('editAnyAlbum'))
		{
			return true;
		}

		if ($this->user_id == $visitor->user_id && $this->hasPermission('editOwnAlbum'))
		{
			$editLimit = $this->hasPermission('editOwnAlbumTimeLimit');
			if ($editLimit != -1 && (!$editLimit || $this->create_date < \XF::$time - 60 * $editLimit))
			{
				$error = \XF::phrase('xfmg_time_limit_to_edit_this_album_x_minutes_has_expired', ['editLimit' => $editLimit]);
				return false;
			}

			return true;
		}

		return false;
	}

	public function canMove(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->hasPermission('moveAnyAlbum'))
		{
			return true;
		}

		if ($this->user_id == $visitor->user_id && $this->hasPermission('moveOwnAlbum'))
		{
			return true;
		}

		return false;
	}

	protected function canBookmarkContent(&$error = null)
	{
		return $this->isVisible();
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($type != 'soft' && !$this->hasPermission('hardDeleteAnyAlbum'))
		{
			return false;
		}

		if ($this->hasPermission('deleteAnyAlbum'))
		{
			return true;
		}

		if ($this->user_id == $visitor->user_id && $this->hasPermission('deleteOwnAlbum'))
		{
			$editLimit = $this->hasPermission('editOwnAlbumTimeLimit');
			if ($editLimit != -1 && (!$editLimit || $this->create_date < \XF::$time - 60 * $editLimit))
			{
				$error = \XF::phrase('xfmg_time_limit_to_delete_this_album_x_minutes_has_expired', ['editLimit' => $editLimit]);
				return false;
			}

			return true;
		}

		return false;
	}

	public function canUndelete(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('undeleteAlbum');
	}

	public function canApproveUnapprove(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('approveUnapproveAlbum');
	}

	public function canWarn(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$this->user_id
			|| !$visitor->user_id
			|| $this->user_id == $visitor->user_id
			|| !$this->hasPermission('warnAlbum')
		)
		{
			return false;
		}

		if ($this->warning_id)
		{
			$error = \XF::phraseDeferred('user_has_already_been_warned_for_this_content');
			return false;
		}

		return ($this->User && $this->User->isWarnable());
	}

	public function canReport(&$error = null, \XF\Entity\User $asUser = null)
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}

	public function canReact(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->album_state != 'visible')
		{
			return false;
		}

		if ($this->user_id == $visitor->user_id)
		{
			$error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');
			return false;
		}

		return $this->hasPermission('reactAlbum');
	}

	public function canCleanSpam()
	{
		return (\XF::visitor()->canCleanSpam() && $this->User && $this->User->isPossibleSpammer());
	}

	public function canSendModeratorActionAlert()
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id || $visitor->user_id == $this->user_id)
		{
			return false;
		}

		if ($this->album_state != 'visible')
		{
			return false;
		}

		return true;
	}

	public function canWatch(&$error = null)
	{
		return \XF::visitor()->user_id;
	}

	public function canEditTags(MediaItem $mediaItem = null, &$error = null)
	{
		if (!$this->app()->options()->enableTagging)
		{
			return false;
		}

		$visitor = \XF::visitor();

		// if no media item, assume the media will be owned by this person
		if (!$mediaItem || $mediaItem->user_id == $visitor->user_id)
		{
			if ($this->hasPermission('tagOwnMedia'))
			{
				return true;
			}
		}

		if (
			$this->hasPermission('tagAnyMedia')
			|| $this->hasPermission('manageAnyTag')
		)
		{
			return true;
		}

		return false;
	}

	public function canUseInlineModeration(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return false;
		}

		return ($this->hasPermission('inlineModAlbum') || $visitor->user_id == $this->user_id);
	}

	public function canViewModeratorLogs(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && ($this->hasPermission('editAnyAlbum') || $this->hasPermission('deleteAnyAlbum'));
	}

	public function getNewContentState()
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $this->hasPermission('approveUnapprove'))
		{
			return 'visible';
		}

		if (!$this->hasPermission('addWithoutApproval'))
		{
			return 'moderated';
		}

		return 'visible';
	}

	public function hasPermission($permission)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($this->category_id && $this->Category)
		{
			return $this->Category->hasPermission($permission);
		}
		else
		{
			if ($permission == 'maxAllowedStorage') // special case -- global and not relevant on a per content basis
			{
				return $visitor->hasPermission('xfmgStorage', $permission);
			}
			else
			{
				return $visitor->hasPermission('xfmg', $permission);
			}
		}
	}

	public function isVisible()
	{
		return ($this->album_state == 'visible');
	}

	public function isIgnored()
	{
		return \XF::visitor()->isIgnoring($this->user_id);
	}

	public function getAttachmentConstraints()
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->getAttachmentConstraints();
		}
		else
		{
			$options = $this->app()->options();

			$extensions = [];
			if (in_array('image', $this->allowed_types))
			{
				$extensions = array_merge($extensions, Arr::stringToArray($options->xfmgImageExtensions));
			}
			if (in_array('video', $this->allowed_types))
			{
				$extensions = array_merge($extensions, Arr::stringToArray($options->xfmgVideoExtensions));
			}
			if (in_array('audio', $this->allowed_types))
			{
				$extensions = array_merge($extensions, Arr::stringToArray($options->xfmgAudioExtensions));
			}

			$total = $this->hasPermission('maxAllowedStorage');
			$size = $this->hasPermission('maxFileSize');
			$width = $this->hasPermission('maxImageWidth');
			$height = $this->hasPermission('maxImageHeight');

			// Treat both 0 and -1 as unlimited
			return [
				'extensions' => $extensions,
				'total' => ($total <= 0) ? PHP_INT_MAX : $total * 1024 * 1024,
				'size' => ($size <= 0) ? PHP_INT_MAX : $size * 1024 * 1024,
				'width' => ($width <= 0) ? PHP_INT_MAX : $width,
				'height' => ($height <= 0) ? PHP_INT_MAX : $height,
				'count' => 100
			];
		}
	}

	public function getBreadcrumbs($includeSelf = true)
	{
		/** @var \XF\Mvc\Router $router */
		$router = $this->app()->container('router.public');

		if ($this->category_id && $this->Category)
		{
			$output = $this->Category->getBreadcrumbs();
		}
		else
		{
			$output = [[
				'value' => \XF::phrase('xfmg_browse_albums'),
				'href' => $router->buildLink('media/albums')
			]];
		}

		if ($includeSelf)
		{
			$output[] = [
				'value' => $this->title,
				'href' => $router->buildLink('media/albums', $this)
			];
		}

		return $output;
	}

	/**
	 * @return array
	 */
	public function getAllowedTypes()
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->allowed_types;
		}
		else
		{
			$allowedTypes = [];
			if ($this->hasPermission('addImage'))
			{
				$allowedTypes[] = 'image';
			}
			if ($this->hasPermission('addVideo'))
			{
				$allowedTypes[] = 'video';
			}
			if ($this->hasPermission('addAudio'))
			{
				$allowedTypes[] = 'audio';
			}
			if ($this->hasPermission('addEmbed'))
			{
				$allowedTypes[] = 'embed';
			}
			return $allowedTypes;
		}
	}

	/**
	 * @return array
	 */
	public function getFieldCache()
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->field_cache;
		}

		$fieldsCache = $this->app()->registry()['xfmgMediaFields'] ?: [];
		$filtered = array_filter($fieldsCache, function(array $field)
		{
			return (bool)$field['album_use'];
		});
		return array_keys($filtered);
	}

	/**
	 * @return int
	 */
	public function getMinTags()
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->min_tags;
		}
		else
		{
			return $this->app()->options()->xfmgMinTagsPersonalAlbums;
		}
	}

	/**
	 * @param bool $canonical
	 *
	 * @return string|null]
	 */
	public function getThumbnailUrl($canonical = false)
	{
		if (!$this->thumbnail_date)
		{
			return null;
		}

		$albumId = $this->album_id;

		$path = sprintf("xfmg/album_thumbnail/%d/%d-%s.jpg?{$this->thumbnail_date}",
			floor($albumId / 1000),
			$albumId,
			$this->album_hash
		);
		return $this->app()->applyExternalDataUrl($path, $canonical);
	}

	public function getAbstractedThumbnailPath()
	{
		$albumId = $this->album_id;

		return sprintf('data://xfmg/album_thumbnail/%d/%d-%s.jpg',
			floor($albumId / 1000),
			$albumId,
			$this->album_hash
		);
	}

	public function getPrivacyPhrase($value)
	{
		return \XF::phrase('xfmg_album_privacy.' . $value);
	}

	/**
	 * @return \XF\Mvc\Entity\ArrayCollection
	 */
	public function getMediaCache()
	{
		$finder = $this->finder('XFMG:MediaItem')
			->where('media_id', $this->media_item_cache)
			->where('media_state', 'visible')
			->orderByDate();

		return $finder->fetch();
	}

	public function setMediaCache($mediaItems)
	{
		$this->_getterCache['MediaCache'] = $mediaItems;
	}

	/**
	 * @return array
	 */
	public function getStructuredData()
	{
		$router = $this->app()->router('public');
		$strFormatter = $this->app()->stringFormatter();
		$language = $this->app()->language();

		$structuredData = [
			'@context' => "https://schema.org",
			'@id' => $router->buildLink('canonical:media/albums', $this),
			'@type' => 'CreativeWork',
			'name' => \XF::escapeString($this->title, 'htmljs'),
			'headline' => $this->title,
			'description' => $strFormatter->snippetString($this->description, 250),
			'author' => [
				'@type' => 'Person',
				'name' => $this->User ? $this->User->username : $this->username
			],
			'dateCreated' => $language->date($this->create_date, 'c'),
			'dateModified' => $language->date($this->last_update_date ?: $this->create_date, 'c')
		];

		if ($this->thumbnail_date)
		{
			$structuredData['thumbnailUrl'] = $this->getThumbnailUrl(true);
		}

		if ($this->rating_count)
		{
			$structuredData['aggregateRating'] = [
				'@type' => 'AggregateRating',
				'ratingCount' => $this->rating_count,
				'ratingValue' => $this->rating_avg
			];
		}

		$structuredData['interactionStatistic'] = [
			[
				'@type' => 'InteractionCounter',
				'interactionType' => 'https://schema.org/CommentAction',
				'userInteractionCount' => strval($this->comment_count)
			],
			[
				'@type' => 'InteractionCounter',
				'interactionType' => 'https://schema.org/InsertAction',
				'userInteractionCount' => strval($this->media_count)
			],
			[
				'@type' => 'InteractionCounter',
				'interactionType' => 'https://schema.org/LikeAction',
				'userInteractionCount' => strval($this->reaction_score)
			],
			[
				'@type' => 'InteractionCounter',
				'interactionType' => 'https://schema.org/ViewAction',
				'userInteractionCount' => strval($this->view_count)
			]
		];

		return $structuredData;
	}

	public function mediaItemAdded(MediaItem $mediaItem)
	{
		$this->media_count++;
		$this->rebuildAlbumThumbnail();
		$this->rebuildMediaItemCache();
	}

	public function mediaItemRemoved(MediaItem $mediaItem)
	{
		$this->media_count--;
		$this->rebuildAlbumThumbnail();
		$this->rebuildMediaItemCache();
	}

	public function rebuildAlbumThumbnail()
	{
		\XF::runOnce('xfmgRebuildAlbumThumbnail' . $this->album_id, function()
		{
			/** @var \XFMG\Service\Album\ThumbnailGenerator $generator */
			$generator = $this->app()->service('XFMG:Album\ThumbnailGenerator', $this);
			if ($generator->createAlbumThumbnail())
			{
				$this->fastUpdate('thumbnail_date', time());
			}
			else
			{
				$this->fastUpdate('thumbnail_date', 0);
			}
		});
	}

	public function rebuildCounters()
	{
		$this->rebuildMediaItemCache();
		$this->rebuildMediaCount();
		$this->rebuildCommentCount();
		$this->rebuildLastCommentInfo();
		$this->rebuildRating();

		return true;
	}

	public function rebuildMediaItemCache()
	{
		\XF::runOnce('xfmgRebuildAlbumMediaCache' . $this->album_id, function()
		{
			$finder = $this->finder('XFMG:MediaItem')
				->where('media_state', 'visible')
				->inAlbum($this->album_id)
				->orderByDate()
				->fetch(20);

			$this->fastUpdate('media_item_cache', $finder->pluckNamed('media_id'));
		});
	}

	public function rebuildMediaCount()
	{
		$this->media_count = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_mg_media_item
			WHERE album_id = ?
				AND media_state = 'visible'
		", $this->album_id);

		return $this->media_count;
	}

	public function softDelete($reason = '', \XF\Entity\User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->album_state == 'deleted')
		{
			return false;
		}

		$this->album_state = 'deleted';

		/** @var \XF\Entity\DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	protected function _preSave()
	{
		if ($this->isInsert() && !$this->album_hash)
		{
			$this->album_hash = $this->repository('XFMG:Album')->generateAlbumHash();
		}

		if ($this->category_id && $this->Category->category_type != 'album')
		{
			$this->error(\XF::phrase('xfmg_cannot_move_album_into_non_album_category'));
		}
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('album_state', 'visible');
		$approvalChange = $this->isStateChanged('album_state', 'moderated');
		$deletionChange = $this->isStateChanged('album_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->albumMadeVisible();
			}
			else if ($visibilityChange == 'leave')
			{
				$this->albumHidden();
			}

			if ($this->isChanged('category_id'))
			{
				/** @var Category $oldCategory */
				$oldCategory = $this->getExistingRelation('Category');
				$this->albumMoved($oldCategory, $this->Category);
			}

			if ($deletionChange == 'leave' && $this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}

			if ($approvalChange == 'leave' && $this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}
		else
		{
			// insert
			if ($this->album_state == 'visible')
			{
				$this->albumMadeVisible();
			}
		}

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->create_date;
			$approvalQueue->save();
		}
		else if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorChanges('xfmg_album', $this);
		}

		$this->_postSaveBookmarks();
	}

	protected function _postDelete()
	{
		if ($this->getOption('delete_contents'))
		{
			$this->app()->jobManager()->enqueueUnique('xfmgAlbumDelete' . $this->album_id, 'XFMG:AlbumDelete', [
				'album_id' => $this->album_id
			]);
		}

		if ($this->album_state == 'visible')
		{
			$this->albumHidden(true);


		}
		else if ($this->album_state == 'deleted')
		{
			if ($this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}
		}
		else if ($this->album_state == 'moderated')
		{
			if ($this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}

		$db = $this->db();

		$deleteFrom = [
			'xf_mg_album_comment_read',
			'xf_mg_album_watch'
		];

		foreach ($deleteFrom AS $table)
		{
			$db->delete($table, 'album_id = ?', $this->album_id);
		}

		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('xfmg_album', $this, 'delete_hard');
		}

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('xfmg_album', $this->album_id);

		if ($this->thumbnail_date)
		{
			$thumbPath = $this->getAbstractedThumbnailPath();
			\XF\Util\File::deleteFromAbstractedPath($thumbPath);
		}

		$this->_postDeleteComments();
		$this->_postDeleteRatings();
		$this->_postDeleteBookmarks();
	}

	protected function adjustUserMediaCountIfNeeded($delete = false)
	{
		if ($this->user_id)
		{
			if ($delete)
			{
				$this->db()->query("
					UPDATE xf_user
					SET xfmg_media_count = GREATEST(0, xfmg_media_count - ?)
					WHERE user_id = ?
				", [$this->media_count, $this->user_id]);
			}
			else
			{
				$this->db()->query("
					UPDATE xf_user
					SET xfmg_media_count = xfmg_media_count + ?
					WHERE user_id = ?
				", [$this->media_count, $this->user_id]);
			}
		}
	}

	protected function adjustUserAlbumCountIfNeeded($amount)
	{
		if ($this->user_id)
		{
			$this->db()->query("
				UPDATE xf_user
				SET xfmg_album_count = GREATEST(0, xfmg_album_count + ?)
				WHERE user_id = ?
			", [$amount, $this->user_id]);
		}
	}

	public function albumMadeVisible()
	{
		$this->adjustUserMediaCountIfNeeded();
		$this->adjustUserAlbumCountIfNeeded(1);
		$this->triggerReindexIfNeeded();

		if ($this->category_id && $this->Category)
		{
			$category = $this->Category;
			$category->albumAdded($this);
			$category->save(false);
		}
	}

	public function albumHidden($hardDelete = false)
	{
		$this->adjustUserMediaCountIfNeeded(true);
		$this->adjustUserAlbumCountIfNeeded(-1);
		$this->triggerReindexIfNeeded($hardDelete);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('xfmg_album', $this->album_id);

		if ($this->category_id && $this->Category)
		{
			$category = $this->Category;
			$category->albumRemoved($this);
			$category->save(false);
		}
	}

	public function albumMoved(Category $from = null, Category $to = null)
	{
		if ($from)
		{
			$from->albumRemoved($this);
			$from->save(false);
		}
		if ($to)
		{
			$to->albumAdded($this);
			$to->save(false);
		}

		$this->db()->update('xf_mg_media_item', [
			'category_id' => $to ? $to->category_id : 0
		], 'album_id = ?', $this->album_id);
	}

	protected function triggerReindexIfNeeded($hardDelete = false)
	{
		if ($this->isInsert())
		{
			return;
		}

		if (!$this->isChanged(['category_id', 'album_state']) && !$hardDelete)
		{
			return;
		}

		$db = $this->db();

		$mediaIds = $db->fetchAllColumn('
			SELECT media_id
			FROM xf_mg_media_item
			WHERE album_id = ?
			ORDER BY media_date
		', $this->album_id);

		$mediaCommentIds = [];

		if ($mediaIds)
		{
			if ($hardDelete)
			{
				\XF::runOnce(
					'searchIndexDeleteMediaAlbum-' . $this->album_id,
					function() use ($mediaIds)
					{
						$this->app()->search()->delete('xfmg_media', $mediaIds);
					}
				);
			}
			else
			{
				$this->app()->jobManager()->enqueue('XF:SearchIndex', [
					'content_type' => 'xfmg_media',
					'content_ids' => $mediaIds
				]);
			}

			$mediaCommentIds = $db->fetchAllColumn('
				SELECT comment_id
				FROM xf_mg_comment
				WHERE content_type = \'xfmg_media\'
				AND content_id IN(' . $db->quote($mediaIds) . ')
				ORDER BY comment_date
			');
		}

		$albumCommentIds = $db->fetchAllColumn('
			SELECT comment_id
			FROM xf_mg_comment
			WHERE content_type = \'xfmg_album\'
			AND content_id = ?
			ORDER BY comment_date
		', $this->album_id);

		$commentIds = array_merge($mediaCommentIds, $albumCommentIds);

		if ($commentIds)
		{
			if ($hardDelete)
			{
				\XF::runOnce(
					'searchIndexDeleteCommentAlbum-' . $this->album_id,
					function() use ($commentIds)
					{
						$this->app()->search()->delete('xfmg_comment', $commentIds);
					}
				);
			}
			else
			{
				$this->app()->jobManager()->enqueue('XF:SearchIndex', [
					'content_type' => 'xfmg_comment',
					'content_ids' => $commentIds
				]);
			}
		}
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'media/albums';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'media/albums';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('xfmg_album_x', [
			'album' => $this->title
		]);
	}

	/**
	 * @param \XF\Api\Result\EntityResult $result
	 * @param int $verbosity
	 * @param array $options
	 */
	protected function setupApiResultData(
		\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = []
	)
	{
		$visitor = \XF::visitor();

		$result->username = $this->User ? $this->User->username : $this->username;

		if ($this->category_id)
		{
			$result->includeRelation('Category');
		}

		if ($this->view_privacy == 'shared')
		{
			$result->view_users = $this->view_users;
		}
		if ($this->add_privacy == 'shared')
		{
			$result->add_users = $this->add_users;
		}

		if ($visitor->user_id)
		{
			$result->is_watching = isset($this->Watch[$visitor->user_id]);
		}

		$result->thumbnail_url = $this->getThumbnailUrl(true);
		$result->can_add = $this->canAddMedia();
		$result->can_edit = $this->canEdit();
		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');
		$result->can_react = $this->canReact();

		$this->addReactionStateToApiResult($result);

		$result->view_url = $this->getContentUrl(true);
	}

	public static function getStructure(Structure $structure)
	{
		$options = \XF::options();

		$structure->table = 'xf_mg_album';
		$structure->shortName = 'XFMG:Album';
		$structure->contentType = 'xfmg_album';
		$structure->primaryKey = 'album_id';
		$structure->columns = [
			'album_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'category_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'album_hash' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'title' => ['type' => self::STR, 'required' => true, 'censor' => true, 'api' => true,
				'maxLength' => isset($options->xfmgMaxTitleLength) ? $options->xfmgMaxTitleLength : 150
			],
			'description' => ['type' => self::STR, 'default' => '', 'censor' => true, 'api' => true,
				'maxLength' => isset($options->xfmgMaxDescriptionLength) ? $options->xfmgMaxDescriptionLength : 300
			],
			'create_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'last_update_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'media_item_cache' => ['type' => self::JSON_ARRAY, 'default' => []],
			'view_privacy' => ['type' => self::STR, 'default' => 'private', 'nullable' => true,
				'allowedValues' => ['public', 'members', 'private', 'shared', 'inherit'], 'api' => true
			],
			'view_users' => ['type' => self::JSON_ARRAY, 'default' => []],
			'add_privacy' => ['type' => self::STR, 'default' => 'private', 'nullable' => true,
				'allowedValues' => ['public', 'members', 'private', 'shared', 'inherit'], 'api' => true
			],
			'add_users' => ['type' => self::JSON_ARRAY, 'default' => []],
			'album_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true
			],
			'user_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50],
			'ip_id' => ['type' => self::UINT, 'default' => 0],
			'media_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
			'view_count' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'warning_message' => ['type' => self::STR, 'default' => '', 'maxLength' => 255, 'api' => true],
			'default_order' => ['type' => self::STR, 'default' => '',
				'allowedValues' => ['', 'custom']
			],
			'thumbnail_date' => ['type' => self::UINT, 'default' => 0],
			'custom_thumbnail_date' => ['type' => self::UINT, 'default' => 0]
		];
		$structure->getters = [
			'allowed_types' => true,
			'field_cache' => true,
			'min_tags' => true,
			'thumbnail_url' => true,
			'MediaCache' => true,
			'structured_data' => true
		];
		$structure->behaviors = [
			'XF:Reactable' => ['stateField' => 'album_state'],
			'XF:ReactableContainer' => [
				'childContentType' => 'xfmg_comment',
				'childIds' => function($album) { return $album->comment_ids; },
				'stateField' => 'album_state'
			],
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'create_date'
			],
			'XF:Indexable' => [
				'checkForUpdates' => ['title', 'description', 'user_id', 'category_id', 'create_date', 'album_state']
			]
		];
		$structure->relations = [
			'Category' => [
				'entity' => 'XFMG:Category',
				'type' => self::TO_ONE,
				'conditions' => 'category_id',
				'primary' => true
			],
			'SharedMapAdd' => [
				'entity' => 'XFMG:SharedMapAdd',
				'type' => self::TO_MANY,
				'conditions' => 'album_id',
				'key' => 'user_id',
				'order' => 'user_id'
			],
			'SharedMapView' => [
				'entity' => 'XFMG:SharedMapView',
				'type' => self::TO_MANY,
				'conditions' => 'album_id',
				'key' => 'user_id',
				'order' => 'user_id'
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'xfmg_album'],
					['content_id', '=', '$album_id']
				],
				'primary' => true
			],
			'Watch' => [
				'entity' => 'XFMG:AlbumWatch',
				'type' => self::TO_MANY,
				'conditions' => 'album_id',
				'key' => 'user_id'
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'xfmg_album'],
					['content_id', '=', '$album_id']
				],
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
				'api' => true
			]
		];
		$structure->options = [
			'log_moderator' => true,
			'delete_contents' => true
		];
		$structure->withAliases = [
			'api' => [
				'User.api',
				'Category.api',
				function()
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return [
							'Watch|' . $userId
						];
					}
				}
			]
		];

		static::addCommentableStructureElements($structure);
		static::addRateableStructureElements($structure);
		static::addReactableStructureElements($structure);
		static::addBookmarkableStructureElements($structure);

		return $structure;
	}
}