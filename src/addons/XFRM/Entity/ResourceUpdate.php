<?php

namespace XFRM\Entity;

use XF\Entity\BookmarkTrait;
use XF\Entity\ReactionTrait;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null resource_update_id
 * @property int resource_id
 * @property int team_user_id
 * @property string team_username
 * @property string title
 * @property string message
 * @property string message_state
 * @property int post_date
 * @property int attach_count
 * @property int ip_id
 * @property int warning_id
 * @property string warning_message
 * @property int last_edit_date
 * @property int last_edit_user_id
 * @property int edit_count
 * @property array|null embed_metadata
 * @property int reaction_score
 * @property array reactions_
 * @property array reaction_users_
 *
 * GETTERS
 * @property string resource_title
 * @property mixed reactions
 * @property mixed reaction_users
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem Resource
 * @property \XF\Entity\User TeamUser
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Attachment[] Attachments
 * @property \XF\Entity\DeletionLog DeletionLog
 * @property \XF\Entity\ApprovalQueue ApprovalQueue
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\ReactionContent[] Reactions
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\BookmarkItem[] Bookmarks
 */
class ResourceUpdate extends Entity implements \XF\BbCode\RenderableContentInterface, \XF\Entity\LinkableInterface
{
	use ReactionTrait, BookmarkTrait;

	public function canView(&$error = null)
	{
		$resource = $this->Resource;

		if (!$resource || !$resource->canView($error))
		{
			return false;
		}

		$visitor = \XF::visitor();

		if ($this->message_state == 'moderated')
		{
			if (
				!$resource->hasPermission('viewModerated')
				&& (!$visitor->user_id || !$resource->isTeamMember())
			)
			{
				return false;
			}
		}
		else if ($this->message_state == 'deleted')
		{
			if (!$resource->hasPermission('viewDeleted'))
			{
				return false;
			}
		}

		return true;
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$visitor->user_id || !$resource)
		{
			return false;
		}

		return $resource->canEdit($error);
	}

	public function canViewHistory(&$error = null): bool
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if (!$this->app()->options()->editHistory['enabled'])
		{
			return false;
		}

		if ($this->Resource && $this->Resource->hasPermission('editAny'))
		{
			return true;
		}

		return false;
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if ($this->isDescription() || !$visitor->user_id || !$resource)
		{
			return false;
		}

		if ($type != 'soft')
		{
			return $resource->hasPermission('hardDeleteAny');
		}

		if ($resource->hasPermission('deleteAny'))
		{
			return true;
		}

		if (
			$resource->isTeamMember() &&
			$this->team_user_id == $visitor->user_id
		)
		{
			return $resource->hasPermission('updateOwn');
		}

		return (
			$resource->user_id == $visitor->user_id
			&& $resource->hasPermission('updateOwn')
		);
	}

	public function canUndelete(&$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$visitor->user_id || !$resource)
		{
			return false;
		}

		return $resource->hasPermission('undelete');
	}

	public function canSendModeratorActionAlert()
	{
		$resource = $this->Resource;

		return (
			$resource
			&& $resource->canSendModeratorActionAlert()
			&& $this->message_state == 'visible'
		);
	}

	protected function canBookmarkContent(&$error = null)
	{
		return ($this->isVisible() && !$this->isDescription());
	}

	public function canReport(&$error = null, \XF\Entity\User $asUser = null)
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}

	public function canWarn(&$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$resource
			|| !$resource->user_id
			|| !$visitor->user_id
			|| $resource->user_id == $visitor->user_id
			|| !$resource->hasPermission('warn')
		)
		{
			return false;
		}

		$user = $this->Resource->User;
		return ($user && $user->isWarnable());
	}

	public function canApproveUnapprove(&$error = null)
	{
		return $this->Resource && $this->Resource->canApproveUnapprove();
	}

	public function canReact(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id || !$this->Resource)
		{
			return false;
		}

		if ($this->message_state != 'visible')
		{
			return false;
		}

		if ($this->Resource->user_id == $visitor->user_id)
		{
			$error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');
			return false;
		}

		return $this->Resource->hasPermission('react');
	}

	public function isVisible()
	{
		return (
			$this->message_state == 'visible'
			&& $this->Resource
			&& $this->Resource->isVisible()
		);
	}

	public function isIgnored()
	{
		return ($this->Resource && $this->Resource->isIgnored());
	}

	public function isDescription()
	{
		$resource = $this->Resource;
		if (!$resource)
		{
			return false;
		}

		if ($this->resource_update_id == $resource->description_update_id)
		{
			return true;
		}

		// this can be called during an insert where the resource hasn't actually been updated yet
		if (!$resource->description_update_id)
		{
			return ($this->post_date == $resource->resource_date);
		}

		return false;
	}

	public function isLastUpdate()
	{
		$resource = $this->Resource;
		if (!$resource)
		{
			return false;
		}

		if ($this->isDescription())
		{
			// the description isn't an update in the normal sense
			return false;
		}

		return ($this->post_date == $resource->last_update);
	}

	public function isAttachmentEmbedded($attachmentId)
	{
		if (!$this->embed_metadata)
		{
			return false;
		}

		if ($attachmentId instanceof \XF\Entity\Attachment)
		{
			$attachmentId = $attachmentId->attachment_id;
		}

		return isset($this->embed_metadata['attachments'][$attachmentId]);
	}

	/**
	 * @return string
	 */
	public function getResourceTitle()
	{
		return $this->Resource ? $this->Resource->title : '';
	}

	public function getBbCodeRenderOptions($context, $type)
	{
		return [
			'entity' => $this,
			'user' => $this->Resource->User,
			'attachments' => $this->attach_count ? $this->Attachments : [],
			'viewAttachments' => $this->Resource->canViewUpdateImages()
		];
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('message_state', 'visible');
		$approvalChange = $this->isStateChanged('message_state', 'moderated');
		$deletionChange = $this->isStateChanged('message_state', 'deleted');

		if ($this->isUpdate())
		{
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
			if ($this->message_state == 'visible')
			{
				$this->updateInsertedVisible();
			}
		}

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->post_date;
			$approvalQueue->save();
		}
		else if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		$this->updateResourceRecord();

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorChanges('resource_update', $this);
		}

		$this->_postSaveBookmarks();
	}

	protected function updateResourceRecord()
	{
		if (!$this->Resource || !$this->Resource->exists())
		{
			// inserting a resource, don't try to write to it
			return;
		}

		$visibilityChange = $this->isStateChanged('message_state', 'visible');
		if ($visibilityChange == 'enter' && $this->Resource)
		{
			$this->Resource->updateAdded($this);
			$this->Resource->save();
		}
		else if ($visibilityChange == 'leave' && $this->Resource)
		{
			$this->Resource->updateRemoved($this);
			$this->Resource->save();
		}
	}

	protected function updateMadeVisible()
	{

	}

	protected function updateInsertedVisible()
	{

	}

	protected function updateHidden($hardDelete = false)
	{
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('resource_update', $this->resource_update_id);
	}

	protected function submitHamData()
	{
		/** @var \XF\Spam\ContentChecker $submitter */
		$submitter = $this->app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('resource_update', $this->resource_update_id);
	}

	protected function _postDelete()
	{
		if ($this->message_state == 'visible')
		{
			$this->updateHidden(true);
		}

		if ($this->Resource && $this->message_state == 'visible')
		{
			$this->Resource->updateRemoved($this);
			$this->Resource->save();
		}

		if ($this->message_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}

		if ($this->message_state == 'moderated' && $this->ApprovalQueue)
		{
			$this->ApprovalQueue->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('resource_update', $this, 'delete_hard');
		}

		/** @var \XF\Repository\Attachment $attachRepo */
		$attachRepo = $this->repository('XF:Attachment');
		$attachRepo->fastDeleteContentAttachments('resource_update', $this->resource_update_id);

		$this->_postDeleteBookmarks();
	}

	public function softDelete($reason = '', \XF\Entity\User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();
		$resource = $this->Resource;

		if ($this->isDescription())
		{
			return $resource->softDelete($reason, $byUser);
		}
		else
		{
			if ($this->message_state == 'deleted')
			{
				return false;
			}

			$this->message_state = 'deleted';

			/** @var \XF\Entity\DeletionLog $deletionLog */
			$deletionLog = $this->getRelationOrDefault('DeletionLog');
			$deletionLog->setFromUser($byUser);
			$deletionLog->delete_reason = $reason;

			$this->save();

			return true;
		}
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'resources/update';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'resources/update';
	}

	public function getContentTitle(string $context = '')
	{
		if ($this->isDescription())
		{
			return $this->Resource->getContentTitle($context);
		}

		return \XF::phrase('xfrm_resource_update_in_x', [
			'title' => $this->Resource->title
		]);
	}

	protected function setupApiResultData(
		\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = []
	)
	{
		if (!empty($options['with_resource']))
		{
			$result->includeRelation('Resource');
		}

		if ($this->attach_count)
		{
			// note that we allow viewing of thumbs and metadata, regardless of permissions, when viewing the
			// content an attachment is connected to
			$result->includeRelation('Attachments');
		}

		$result->message_parsed = $this->app()->bbCode()->render($this->message, 'apiHtml', 'resource_update:api', $this);

		$this->addReactionStateToApiResult($result);

		$result->can_edit = $this->canEdit();
		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');
		$result->can_react = $this->canReact();
		$result->can_view_attachments = $this->Resource->canViewUpdateImages();

		$result->view_url = $this->getContentUrl(true);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource_update';
		$structure->shortName = 'XFRM:ResourceUpdate';
		$structure->primaryKey = 'resource_update_id';
		$structure->contentType = 'resource_update';
		$structure->columns = [
			'resource_update_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'resource_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'team_user_id' => ['type' => self::UINT, 'required' => true],
			'team_username' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_name',
			],
			'title' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'please_enter_valid_title',
				'censor' => true,
				'api' => true
			],
			'message' => ['type' => self::STR,
				'required' => 'please_enter_valid_message', 'api' => true
			],
			'message_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true
			],
			'post_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'attach_count' => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0, 'api' => true],
			'ip_id' => ['type' => self::UINT, 'default' => 0],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'warning_message' => ['type' => self::STR, 'default' => '', 'maxLength' => 255, 'api' => true],
			'last_edit_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_edit_user_id' => ['type' => self::UINT, 'default' => 0],
			'edit_count' => ['type' => self::UINT, 'default' => 0],
			'embed_metadata' => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null]
		];
		$structure->getters = [
			'resource_title' => true
		];
		$structure->behaviors = [
			'XF:Reactable' => ['stateField' => 'message_state'],
			'XF:Indexable' => [
				'checkForUpdates' => ['message', 'resource_id', 'post_date', 'message_state']
			],
			'XF:NewsFeedPublishable' => [
				'userIdField' => function($update) { return $update->Resource->user_id; },
				'usernameField' => function($update) { return $update->Resource->username; },
				'dateField' => 'post_date'
			]
		];
		$structure->relations = [
			'Resource' => [
				'entity' => 'XFRM:ResourceItem',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true
			],
			'TeamUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$team_user_id']],
				'primary' => true
			],
			'Attachments' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_MANY,
				'conditions' => [
					['content_type', '=', 'resource_update'],
					['content_id', '=', '$resource_update_id']
				],
				'with' => 'Data',
				'order' => 'attach_date'
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource_update'],
					['content_id', '=', '$resource_update_id']
				],
				'primary' => true
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource_update'],
					['content_id', '=', '$resource_update_id']
				],
				'primary' => true
			]
		];
		$structure->options = [
			'log_moderator' => true
		];
		$structure->defaultWith = ['Resource'];

		$structure->withAliases = [
			'api' => []
		];

		static::addReactableStructureElements($structure);
		static::addBookmarkableStructureElements($structure);

		return $structure;
	}
}