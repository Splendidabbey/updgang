<?php

namespace XFRM\Entity;

use XF\Entity\ContentVoteTrait;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null resource_rating_id
 * @property int resource_id
 * @property int resource_version_id
 * @property int user_id
 * @property int rating
 * @property int rating_date
 * @property string message
 * @property string version_string
 * @property int author_response_team_user_id
 * @property string author_response_team_username
 * @property string author_response
 * @property bool is_review
 * @property bool count_rating
 * @property string rating_state
 * @property int warning_id
 * @property bool is_anonymous
 * @property array custom_fields_
 * @property int vote_score
 * @property int vote_count
 *
 * GETTERS
 * @property string resource_title
 * @property \XF\CustomField\Set custom_fields
 * @property mixed vote_score_short
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem Resource
 * @property \XF\Entity\User User
 * @property \XFRM\Entity\ResourceVersion Version
 * @property \XF\Entity\User AuthorResponseTeamUser
 * @property \XF\Entity\DeletionLog DeletionLog
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\ContentVote[] ContentVotes
 */
class ResourceRating extends Entity implements \XF\Entity\LinkableInterface
{
	use ContentVoteTrait;

	public function canView(&$error = null)
	{
		$resource = $this->Resource;

		if (!$resource || !$resource->canView($error))
		{
			return false;
		}

		if ($this->rating_state == 'deleted')
		{
			if (!$resource->hasPermission('viewDeleted'))
			{
				return false;
			}
		}

		return true;
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$visitor->user_id || !$resource)
		{
			return false;
		}

		if ($type != 'soft')
		{
			return (
				$resource->hasPermission('hardDeleteAny')
				&& $resource->hasPermission('deleteAnyReview')
			);
		}

		if ($this->user_id == $visitor->user_id && !$this->author_response)
		{
			return true;
		}

		return $resource->hasPermission('deleteAnyReview');
	}

	public function canUpdate(&$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$visitor->user_id
			|| $visitor->user_id != $this->user_id
			|| !$resource
			|| !$resource->hasPermission('rate')
		)
		{
			return false;
		}

		if ($this->rating_state != 'visible' || !$this->is_review)
		{
			return true;
		}

		if ($this->author_response)
		{
			$error = \XF::phraseDeferred('xfrm_cannot_update_rating_once_author_response');
			return false;
		}

		return true;
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
			|| !$visitor->user_id
			|| $this->user_id == $visitor->user_id
			|| !$resource->hasPermission('warn')
		)
		{
			return false;
		}

		if ($this->warning_id)
		{
			$error = \XF::phraseDeferred('user_has_already_been_warned_for_this_content');
			return false;
		}

		$user = $this->User;
		return ($user && $user->isWarnable());
	}

	public function canReply(&$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		return (
			$visitor->user_id
			&& $resource
			&& $resource->isTeamMember()
			&& $this->is_review
			&& !$this->author_response
			&& $this->rating_state == 'visible'
			&& $resource->hasPermission('reviewReply')
		);
	}

	public function canDeleteAuthorResponse(&$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$visitor->user_id || !$this->is_review || !$this->author_response || !$resource)
		{
			return false;
		}

		if (
			$resource->isTeamMember() &&
			$visitor->user_id == $this->author_response_team_user_id
		)
		{
			return true;
		}

		return (
			$visitor->user_id == $this->Resource->user_id
			|| $resource->hasPermission('deleteAnyReview')
		);
	}

	public function canViewAnonymousAuthor()
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& (
				$visitor->user_id == $this->user_id
				|| $visitor->canBypassUserPrivacy()
			)
		);
	}

	public function isContentVotingSupported(): bool
	{
		return $this->app()->options()->xfrmReviewVoting !== '';
	}

	public function isContentDownvoteSupported(): bool
	{
		return $this->app()->options()->xfrmReviewVoting === 'yes';
	}

	protected function canVoteOnContentInternal(&$error = null): bool
	{
		if (!$this->isVisible())
		{
			return false;
		}

		$resource = $this->Resource;

		if ($resource->isTeamMember())
		{
			return false;
		}

		return $resource->hasPermission('contentVote');
	}

	public function canSendModeratorActionAlert()
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $visitor->user_id != $this->user_id
			&& $this->rating_state == 'visible'
		);
	}

	public function isVisible()
	{
		return (
			$this->rating_state == 'visible'
			&& $this->Resource
			&& $this->Resource->isVisible()
		);
	}

	public function isIgnored()
	{
		if ($this->is_anonymous)
		{
			return false;
		}

		return \XF::visitor()->isIgnoring($this->user_id);
	}

	/**
	 * @return string
	 */
	public function getResourceTitle()
	{
		return $this->Resource ? $this->Resource->title : '';
	}

	/**
	 * @return \XF\CustomField\Set
	 */
	public function getCustomFields()
	{
		$class = 'XF\CustomField\Set';
		$class = $this->app()->extendClass($class);

		$fieldDefinitions = $this->app()->container('customFields.resourceReviews');

		return new $class($fieldDefinitions, $this);
	}

	protected function _preSave()
	{
		if ($this->isUpdate() && $this->isChanged(['message', 'rating', 'user_id']))
		{
			throw new \LogicException("Cannot change rating message, value or user");
		}

		if ($this->isChanged('message'))
		{
			$this->is_review = strlen($this->message) ? true : false;
		}

		if (!$this->resource_version_id)
		{
			$this->error('Must set resource_version_id', 'resource_version_id', false);
		}
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('rating_state', 'visible');
		$deletionChange = $this->isStateChanged('rating_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->ratingMadeVisible();
			}
			else if ($visibilityChange == 'leave')
			{
				$this->ratingHidden();
			}

			if ($deletionChange == 'leave' && $this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}
		}
		else
		{
			// insert
			if ($this->rating_state == 'visible')
			{
				$this->ratingMadeVisible();
			}
		}

		if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorChanges('resource_rating', $this);
		}
	}

	protected function ratingMadeVisible()
	{
		$resource = $this->Resource;
		$version = $this->Version;

		if ($version)
		{
			$version->ratingAdded($this);
			$version->save();
		}

		if ($this->is_review && $resource)
		{
			$resource->review_count++;
		}

		if ($this->rebuildRatingCounted() && $resource)
		{
			$resource->rebuildRating();
		}

		if ($resource)
		{
			$resource->saveIfChanged();
		}
	}

	protected function ratingHidden($hardDelete = false)
	{
		$resource = $this->Resource;
		$version = $this->Version;

		if ($version)
		{
			$version->ratingRemoved($this);
			$version->save();
		}

		if ($this->is_review && $resource)
		{
			$resource->review_count--;
		}

		if (
			($this->count_rating && $resource) &&
			($this->rebuildRatingCounted() || $hardDelete)
		)
		{
			$resource->rebuildRating();
		}
		if ($resource)
		{
			$resource->saveIfChanged();
		}

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('resource_rating', $this->resource_rating_id);
	}

	protected function rebuildRatingCounted()
	{
		/** @var \XFRM\Repository\ResourceRating $ratingRepo */
		$ratingRepo = $this->repository('XFRM:ResourceRating');

		$countable = $ratingRepo->getCountableRating($this->resource_id, $this->user_id);
		if ($countable && $countable->count_rating)
		{
			// already counted, no action needed
			return false;
		}

		$rebuildRequired = false;

		$counted = $ratingRepo->getCountedRatings($this->resource_id, $this->user_id);

		if ($countable)
		{
			$countable->fastUpdate('count_rating', true);
			$rebuildRequired = true;
		}

		foreach ($counted AS $count)
		{
			if ($countable && $count->resource_rating_id == $countable->resource_rating_id)
			{
				// we've just set this to be counted, ignore it
				continue;
			}

			$count->fastUpdate('count_rating', false);
			$rebuildRequired = true;
		}

		return $rebuildRequired;
	}

	protected function _postDelete()
	{
		if ($this->rating_state == 'visible')
		{
			$this->ratingHidden(true);
		}

		if ($this->rating_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('resource_rating', $this, 'delete_hard');
		}
	}

	public function softDelete($reason = '', \XF\Entity\User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->rating_state == 'deleted')
		{
			return false;
		}

		$this->rating_state = 'deleted';

		/** @var \XF\Entity\DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		if ($this->is_review)
		{
			$route = ($canonical ? 'canonical:' : '') . 'resources/review';
			return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
		}
		else
		{
			return '';
		}
	}

	public function getContentPublicRoute()
	{
		return 'resources/review';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('xfrm_resource_review_in_x', [
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

		$category = $this->Resource->Category;
		if ($category)
		{
			$result->custom_fields = (object)$this->custom_fields->getNamedFieldValues($category->review_field_cache);
		}

		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');
		$result->can_author_reply = $this->canReply();

		if ($this->is_anonymous)
		{
			if ($this->canViewAnonymousAuthor() || \XF::isApiBypassingPermissions())
			{
				$result->AnonymousUser = $this->User;
				$result->anonymous_user_id = $this->user_id;
			}
		}
		else
		{
			$result->includeColumn('user_id');
			$result->includeRelation('User');
		}

		if ($this->is_review)
		{
			$result->view_url = $this->getContentUrl(true);
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource_rating';
		$structure->shortName = 'XFRM:ResourceRating';
		$structure->primaryKey = 'resource_rating_id';
		$structure->contentType = 'resource_rating';
		$structure->columns = [
			'resource_rating_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'resource_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'resource_version_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'rating' => ['type' => self::UINT, 'required' => true, 'min' => 1, 'max' => 5, 'api' => true],
			'rating_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'message' => ['type' => self::STR, 'default' => '', 'api' => true],
			'version_string' => ['type' => self::STR, 'required' => true, 'api' => true],
			'author_response_team_user_id' => ['type' => self::UINT, 'default' => 0],
			'author_response_team_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'author_response' => ['type' => self::STR, 'default' => '', 'api' => true],
			'is_review' => ['type' => self::BOOL, 'default' => false],
			'count_rating' => ['type' => self::BOOL, 'default' => false],
			'rating_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'deleted'], 'api' => true
			],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'is_anonymous' => ['type' => self::BOOL, 'default' => false, 'api' => true],
			'custom_fields' => ['type' => self::JSON_ARRAY, 'default' => []]
		];
		$structure->getters = [
			'resource_title' => true,
			'custom_fields' => true
		];
		$structure->behaviors = [
			'XF:ContentVotable' => ['stateField' => 'rating_state'],
			'XF:NewsFeedPublishable' => [
				'userIdField' => function($rating) { return $rating->is_anonymous ? 0 : $rating->user_id; },
				'usernameField' => function($rating) { return $rating->is_anonymous ? '' : $rating->User->username; },
				'dateField' => 'rating_date'
			],
			'XF:CustomFieldsHolder' => [
				'valueTable' => 'xf_rm_resource_review_field_value'
			]
		];
		$structure->relations = [
			'Resource' => [
				'entity' => 'XFRM:ResourceItem',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Version' => [
				'entity' => 'XFRM:ResourceVersion',
				'type' => self::TO_ONE,
				'conditions' => 'resource_version_id',
				'primary' => true
			],
			'AuthorResponseTeamUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$author_response_team_user_id']],
				'primary' => true
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource_rating'],
					['content_id', '=', '$resource_rating_id']
				],
				'primary' => true
			]
		];
		$structure->options = [
			'log_moderator' => true
		];
		$structure->defaultWith = ['Resource'];

		$structure->withAliases = [
			'full' => [
				'User',
				'AuthorResponseTeamUser',
				function()
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return [
							'ContentVotes|' . $userId
						];
					}
				}
			],
			'api' => [
				'User',
				'User.api',
				function($withParams)
				{
					if (!empty($withParams['resource']))
					{
						return ['Resource.api'];
					}
				}
			]
		];

		static::addVotableStructureElements($structure);

		return $structure;
	}
}