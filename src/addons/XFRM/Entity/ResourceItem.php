<?php

namespace XFRM\Entity;

use XF\Entity\BookmarkTrait;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null resource_id
 * @property string title
 * @property string tag_line
 * @property int user_id
 * @property string username
 * @property array team_member_user_ids
 * @property string resource_state
 * @property string resource_type
 * @property int resource_date
 * @property int resource_category_id
 * @property int current_version_id
 * @property int description_update_id
 * @property int discussion_thread_id
 * @property string external_url
 * @property string external_purchase_url
 * @property float price
 * @property string currency
 * @property int view_count
 * @property int download_count
 * @property int rating_count
 * @property int rating_sum
 * @property float rating_avg
 * @property float rating_weighted
 * @property int update_count
 * @property int review_count
 * @property int last_update
 * @property string alt_support_url
 * @property array custom_fields_
 * @property int prefix_id
 * @property int icon_date
 * @property array tags
 *
 * GETTERS
 * @property \XF\CustomField\Set custom_fields
 * @property int real_update_count
 * @property int real_review_count
 * @property array resource_update_ids
 * @property array resource_version_ids
 * @property array resource_rating_ids
 * @property \XF\Draft draft_update
 *
 * RELATIONS
 * @property \XFRM\Entity\Category Category
 * @property \XF\Entity\User User
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\ResourceTeamMember[] TeamMembers
 * @property \XFRM\Entity\ResourceUpdate Description
 * @property \XFRM\Entity\ResourceVersion CurrentVersion
 * @property \XF\Entity\Thread Discussion
 * @property \XFRM\Entity\ResourceFeature Featured
 * @property \XFRM\Entity\ResourcePrefix Prefix
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\ResourceWatch[] Watch
 * @property \XF\Entity\DeletionLog DeletionLog
 * @property \XF\Entity\ApprovalQueue ApprovalQueue
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Draft[] DraftUpdates
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\BookmarkItem[] Bookmarks
 */
class ResourceItem extends Entity implements \XF\Entity\LinkableInterface
{
	use BookmarkTrait;

	const RATING_WEIGHTED_THRESHOLD = 10;
	const RATING_WEIGHTED_AVERAGE = 3;

	public function getBreadcrumbs($includeSelf = true)
	{
		$breadcrumbs = $this->Category ? $this->Category->getBreadcrumbs() : [];
		if ($includeSelf)
		{
			$breadcrumbs[] = [
				'href' => $this->app()->router()->buildLink('resources', $this),
				'value' => $this->title
			];
		}

		return $breadcrumbs;
	}

	public function getAbstractedIconPath($sizeCode = null)
	{
		$resourceId = $this->resource_id;

		return sprintf('data://resource_icons/%d/%d.jpg',
			floor($resourceId / 1000),
			$resourceId
		);
	}

	public function getIconUrl($sizeCode = null, $canonical = false)
	{
		$app = $this->app();

		if ($this->icon_date)
		{
			$group = floor($this->resource_id / 1000);
			return $app->applyExternalDataUrl(
				"resource_icons/{$group}/{$this->resource_id}.jpg?{$this->icon_date}",
				$canonical
			);
		}
		else
		{
			return null;
		}
	}

	public function canView(&$error = null)
	{
		if (!$this->Category || !$this->Category->canView())
		{
			return false;
		}

		$visitor = \XF::visitor();

		if ($this->resource_state == 'moderated')
		{
			if (
				!$this->hasPermission('viewModerated')
				&& (!$visitor->user_id || !$this->isTeamMember())
			)
			{
				$error = \XF::phraseDeferred('xfrm_requested_resource_not_found');
				return false;
			}
		}
		else if ($this->resource_state == 'deleted')
		{
			if (!$this->hasPermission('viewDeleted'))
			{
				$error = \XF::phraseDeferred('xfrm_requested_resource_not_found');
				return false;
			}
		}

		return true;
	}

	public function canViewDeletedContent()
	{
		return $this->hasPermission('viewDeleted');
	}

	public function canViewModeratedContent()
	{
		$visitor = \XF::visitor();
		if ($this->hasPermission('viewModerated'))
		{
			return true;
		}
		else if ($visitor->user_id && $this->isTeamMember())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function canViewUpdateImages()
	{
		return $this->hasPermission('viewUpdateAttach');
	}

	public function canDownload(&$error = null)
	{
		return $this->hasPermission('download') || $this->isTeamMember();
	}

	public function canViewFullDescription()
	{
		if ($this->resource_type != 'fileless')
		{
			return true;
		}

		if (!$this->app()->options()->xfrmFilelessViewFull['limit'])
		{
			return true;
		}

		// this is just checking if they have download permissions
		return $this->hasPermission('download') || $this->isTeamMember();
	}

	public function canRate($checkDownloadIfRequired = true, &$error = null)
	{
		if (!$this->isVisible())
		{
			return false;
		}

		$visitor = \XF::visitor();
		if (!$visitor->user_id || $this->isTeamMember())
		{
			return false;
		}

		if (!$this->hasPermission('rate') || !$this->hasPermission('download'))
		{
			// if you can't download (or see the full resource), you can't rate it
			return false;
		}

		if ($checkDownloadIfRequired && $this->isDownloadable() && $this->app()->options()->xfrmRequireDownloadToRate)
		{
			$version = $this->CurrentVersion;
			if (!$version || !$version->Downloads[$visitor->user_id])
			{
				$error = \XF::phraseDeferred('xfrm_you_only_rate_resource_version_downloaded');
				return false;
			}
		}

		return true;
	}

	public function canRatePreReg($checkDownloadIfRequired = true)
	{
		if (\XF::visitor()->user_id || $this->canRate())
		{
			// quick bypass with the user ID check, then ensure that this can only return true if the visitor
			// can't take the "normal" action
			return false;
		}

		return \XF::canPerformPreRegAction(
			function() use ($checkDownloadIfRequired) { return $this->canRate($checkDownloadIfRequired); }
		);
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->hasPermission('editAny'))
		{
			return true;
		}

		return ($this->isTeamMember() && $this->hasPermission('updateOwn'));
	}

	public function canEditIcon(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id || !$this->app()->options()->xfrmAllowIcons)
		{
			return false;
		}

		if ($this->hasPermission('editAny'))
		{
			return true;
		}

		if (!$this->resource_id)
		{
			// resource hasn't been created yet, so allow users to add an icon
			return true;
		}

		if ($this->isTeamMember())
		{
			if ($this->hasPermission('updateOwn'))
			{
				return true;
			}

			if (!$this->icon_date && $this->resource_date > \XF::$time - 3 * 3600)
			{
				// allow an icon to be set shortly after resource creation, even if not editable since you can't
				// specify an icon during creation
				return true;
			}
		}

		return false;
	}

	public function canLeaveTeam(&$error = null): bool
	{
		if (!$this->app()->options()->xfrmEnableTeams)
		{
			return false;
		}

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->isOwner())
		{
			return false;
		}

		return $this->isTeamMember();
	}


	public function canViewTeamMembers(): bool
	{
		if (!$this->app()->options()->xfrmEnableTeams)
		{
			return false;
		}

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->hasPermission('reassign'))
		{
			return true;
		}

		return $this->isTeamMember();
	}

	public function canManageTeamMembers(&$error = null): bool
	{
		if (!$this->app()->options()->xfrmEnableTeams)
		{
			return false;
		}

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->getMaxTeamMemberCount() == 0)
		{
			return false;
		}

		return $this->canAddTeamMembers() || $this->canRemoveTeamMembers();
	}

	/**
	 * Note: check that canManageTeamMembers is true before relying on this value.
	 *
	 * @return bool
	 */
	public function canAddTeamMembers(): bool
	{
		return (
			\XF::visitor()->user_id == $this->user_id
			&& $this->hasPermission('manageOwnTeam')
		);
	}

	/**
	 * Note: check that canManageTeamMembers is true before relying on this value.
	 *
	 * @return bool
	 */
	public function canRemoveTeamMembers(): bool
	{
		if (!$this->team_member_user_ids)
		{
			return false;
		}

		if ($this->hasPermission('reassign'))
		{
			return true;
		}

		return (
			\XF::visitor()->user_id == $this->user_id
			&& $this->hasPermission('manageOwnTeam')
		);
	}

	public function canTeamMemberBeAdded(User $user): bool
	{
		if (!$user->user_id)
		{
			return false;
		}

		if ($user->user_id == $this->user_id)
		{
			// can't add the author - normally skipped before this
			return false;
		}

		if ($user->isIgnoring($this->user_id))
		{
			// if the target is ignoring the resource author, then don't let them be added
			return false;
		}

		/** @var \XFRM\XF\Entity\User $user */
		return $user->hasResourceCategoryPermission(
			$this->resource_category_id,
			'add'
		);
	}

	public function canReleaseUpdate(&$error = null)
	{
		$visitor = \XF::visitor();

		if (
			!$visitor->user_id
			|| !$this->isTeamMember()
			|| !$this->isVisible()
		)
		{
			return false;
		}

		return $this->hasPermission('updateOwn');
	}

	public function canChangeResourceType(&$error = null)
	{
		if (!$this->canReleaseUpdate($error))
		{
			return false;
		}

		$categoryAllowedTypes = 0;

		foreach (['allow_local', 'allow_external', 'allow_commercial_external', 'allow_fileless'] AS $type)
		{
			if (!empty($this->Category[$type]))
			{
				$categoryAllowedTypes++;
			}
		}

		return ($categoryAllowedTypes > 1);
	}

	public function canMove(&$error = null)
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $this->hasPermission('editAny')
		);
	}

	public function canChangeDiscussionThread(&$error = null)
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $this->hasPermission('editAny')
		);
	}

	public function canReassign(&$error = null)
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $this->hasPermission('reassign')
		);
	}

	public function canFeatureUnfeature(&$error = null)
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $this->hasPermission('featureUnfeature')
		);
	}

	protected function canBookmarkContent(&$error = null)
	{
		return $this->isVisible();
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();

		if ($type != 'soft')
		{
			return $this->hasPermission('hardDeleteAny');
		}

		if ($this->hasPermission('deleteAny'))
		{
			return true;
		}

		return (
			$this->user_id == $visitor->user_id
			&& $this->hasPermission('deleteOwn')
		);
	}

	public function canUndelete(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('undelete');
	}

	public function canSetPublicDeleteReason()
	{
		$visitor = \XF::visitor();

		return (
			$this->app()->options()->xfrmResourceDeleteThreadAction['add_post']
			&& $this->Discussion
			&& $visitor->user_id
			&& $visitor->user_id != $this->user_id
			// technically can allow this for own resource owners, but may be somewhat confusing
		);
	}

	public function canSendModeratorActionAlert()
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& !$this->isTeamMember()
			&& $this->resource_state == 'visible'
		);
	}

	public function canApproveUnapprove(&$error = null)
	{
		return (
			\XF::visitor()->user_id
			&& $this->hasPermission('approveUnapprove')
		);
	}

	public function canWatch(&$error = null)
	{
		$visitor = \XF::visitor();

		// don't let authors watch as all updates will be attributed to them anyway
		return (
			$visitor->user_id
			&& $visitor->user_id != $this->user_id
		);
	}

	public function canEditTags(&$error = null)
	{
		$category = $this->Category;
		return $category ? $category->canEditTags($this, $error) : false;
	}

	public function canUseInlineModeration(&$error = null)
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $this->hasPermission('inlineMod'));
	}

	public function hasPermission($permission)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->hasResourceCategoryPermission($this->resource_category_id, $permission);
	}

	public function isIgnored()
	{
		return \XF::visitor()->isIgnoring($this->user_id);
	}

	public function isVisible()
	{
		return ($this->resource_state == 'visible');
	}

	public function isOwner(): bool
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $visitor->user_id == $this->user_id;
	}

	public function isTeamMember(int $userId = null): bool
	{
		$userId = $userId === null ? \XF::visitor()->user_id : $userId;
		if (!$userId)
		{
			return false;
		}

		if ($userId == $this->user_id)
		{
			return true;
		}

		if (!$this->app()->options()->xfrmEnableTeams)
		{
			return false;
		}

		return in_array($userId, $this->team_member_user_ids);
	}

	public function isNonOwnerTeamMember(int $userId = null): bool
	{
		$userId = $userId === null ? \XF::visitor()->user_id : $userId;
		if (!$userId)
		{
			return false;
		}

		if ($userId == $this->user_id)
		{
			return false;
		}

		return $this->isTeamMember($userId);
	}

	public function isDownloadable()
	{
		return ($this->resource_type == 'download');
	}

	public function isExternalDownload()
	{
		if ($this->resource_type != 'download')
		{
			return false;
		}

		$currentVersion = $this->CurrentVersion;
		if (!$currentVersion)
		{
			return false;
		}

		return ($currentVersion->download_url ? true : false);
	}

	public function isExternalPurchasable()
	{
		return ($this->resource_type == 'external_purchase');
	}

	public function isFileless()
	{
		return ($this->resource_type == 'fileless');
	}

	public function getResourceTypeDetailed()
	{
		if ($this->resource_type == 'download')
		{
			return $this->isExternalDownload() ? 'download_external' : 'download_local';
		}

		return $this->resource_type;
	}

	/**
	 * Represents whether we should outwardly show versioning when appropriate.
	 *
	 * @return bool
	 */
	public function isVersioned()
	{
		if ($this->Category && !$this->Category->enable_versioning)
		{
			return false;
		}

		return ($this->resource_type != 'fileless');
	}

	/**
	 * This determines whether it's possible to post a new resource version. This differs from isVersioned in that
	 * downloadable resources always need versioning to update the file.
	 *
	 * @return bool
	 */
	public function hasUpdatableVersionData()
	{
		if ($this->resource_type == 'download')
		{
			return true;
		}
		else
		{
			return $this->isVersioned();
		}
	}

	public function hasViewableDiscussion()
	{
		if (!$this->discussion_thread_id)
		{
			return false;
		}

		$thread = $this->Discussion;
		if (!$thread)
		{
			return false;
		}

		return $thread->canView();
	}

	public function hasExtraInfoTab()
	{
		if (!$this->getValue('custom_fields'))
		{
			// if they haven't set anything, we can bail out quickly
			return false;
		}

		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $this->custom_fields;
		$definitionSet = $fieldSet->getDefinitionSet()
			->filterOnly($this->Category->field_cache)
			->filterGroup('extra_tab')
			->filterWithValue($fieldSet);

		return ($definitionSet->count() > 0);
	}

	public function getExtraFieldTabs()
	{
		if (!$this->getValue('custom_fields'))
		{
			// if they haven't set anything, we can bail out quickly
			return [];
		}

		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $this->custom_fields;
		$definitionSet = $fieldSet->getDefinitionSet()
			->filterOnly($this->Category->field_cache)
			->filterGroup('new_tab')
			->filterWithValue($fieldSet);

		$output = [];
		foreach ($definitionSet AS $fieldId => $definition)
		{
			$output[$fieldId] = $definition->title;
		}

		return $output;
	}

	public function getExpectedThreadTitle($currentValues = true)
	{
		$price = $currentValues ? $this->getValue('price') : $this->getExistingValue('price');
		$title = $currentValues ? $this->getValue('title') : $this->getExistingValue('title');
		$state = $currentValues ? $this->getValue('resource_state') : $this->getExistingValue('resource_state');

		$template = '';
		$options = $this->app()->options();

		if ($price > 0)
		{
			$template = $options->xfrmPaidResourceThreadTitleTemplate;
		}

		if ($state != 'visible' && $options->xfrmResourceDeleteThreadAction['update_title'])
		{
			$template = $options->xfrmResourceDeleteThreadAction['title_template'];
		}

		if (!$template)
		{
			$template = '{title}';
		}

		$threadTitle = str_replace('{title}', $title, $template);
		return $this->app()->stringFormatter()->wholeWordTrim($threadTitle, 100);
	}

	public function getMaxTeamMemberCount(): int
	{
		return 10;
	}

	public function getNewUpdate(User $teamUser = null)
	{
		if (!$teamUser)
		{
			$teamUser = \XF::visitor();
		}

		$update = $this->_em->create('XFRM:ResourceUpdate');

		$update->resource_id = $this->_getDeferredValue(function()
		{
			return $this->resource_id;
		}, 'save');

		$update->team_user_id = $teamUser->user_id;
		$update->team_username = $teamUser->username;

		return $update;
	}

	public function getNewVersion(User $teamUser = null)
	{
		if (!$teamUser)
		{
			$teamUser = \XF::visitor();
		}

		$version = $this->_em->create('XFRM:ResourceVersion');

		$version->resource_id = $this->_getDeferredValue(function()
		{
			return $this->resource_id;
		}, 'save');

		$version->team_user_id = $teamUser->user_id;
		$version->team_username = $teamUser->username;

		return $version;
	}

	/**
	 * @return \XF\Draft
	 */
	public function getDraftUpdate()
	{
		return \XF\Draft::createFromEntity($this, 'DraftUpdates');
	}

	public function getFieldEditMode()
	{
		$visitor = \XF::visitor();

		$isTeam = ($this->isTeamMember() || !$this->resource_id);
		$isMod = ($visitor->user_id && $this->hasPermission('editAny'));

		if ($isMod || !$isTeam)
		{
			return $isTeam ? 'moderator_user' : 'moderator';
		}
		else
		{
			return 'user';
		}
	}

	/**
	 * @return \XF\CustomField\Set
	 */
	public function getCustomFields()
	{
		$class = 'XF\CustomField\Set';
		$class = $this->app()->extendClass($class);

		$fieldDefinitions = $this->app()->container('customFields.resources');

		return new $class($fieldDefinitions, $this);
	}

	/**
	 * @return int
	 */
	public function getRealUpdateCount()
	{
		if (!$this->canViewDeletedContent() && !$this->canViewModeratedContent())
		{
			return $this->update_count;
		}
		else
		{
			/** @var \XFRM\Repository\ResourceUpdate $updateRepo */
			$updateRepo = $this->repository('XFRM:ResourceUpdate');
			return $updateRepo->findUpdatesInResource($this)->total();
		}
	}

	/**
	 * @return int
	 */
	public function getRealReviewCount()
	{
		if (!$this->canViewDeletedContent())
		{
			return $this->review_count;
		}
		else
		{
			/** @var \XFRM\Repository\ResourceRating $ratingRepo */
			$ratingRepo = $this->repository('XFRM:ResourceRating');
			return $ratingRepo->findReviewsInResource($this)->total();
		}
	}

	/**
	 * @return array
	 */
	public function getResourceUpdateIds()
	{
		return $this->db()->fetchAllColumn("
			SELECT resource_update_id
			FROM xf_rm_resource_update
			WHERE resource_id = ?
			ORDER BY post_date
		", $this->resource_id);
	}

	/**
	 * @return array
	 */
	public function getResourceVersionIds()
	{
		return $this->db()->fetchAllColumn("
			SELECT resource_version_id
			FROM xf_rm_resource_version
			WHERE resource_id = ?
			ORDER BY release_date
		", $this->resource_id);
	}

	/**
	 * @return array
	 */
	public function getResourceRatingIds()
	{
		return $this->db()->fetchAllColumn("
			SELECT resource_rating_id
			FROM xf_rm_resource_rating
			WHERE resource_id = ?
			ORDER BY rating_date
		", $this->resource_id);
	}

	public function rebuildCounters()
	{
		$this->rebuildLastUpdateInfo();
		$this->rebuildUpdateCount();
		$this->rebuildReviewCount();
		$this->rebuildRating();

		return true;
	}

	public function rebuildLastUpdateInfo()
	{
		$lastUpdate = $this->db()->fetchRow("
			SELECT *
			FROM xf_rm_resource_update
			WHERE resource_id = ?
				AND message_state = 'visible'
			ORDER BY post_date DESC
			LIMIT 1
		", $this->resource_id);
		if (!$lastUpdate)
		{
			return false;
		}

		$this->last_update = $lastUpdate ? $lastUpdate['post_date'] : $this->post_date;

		return true;
	}

	public function rebuildUpdateCount()
	{
		$this->update_count = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_rm_resource_update
			WHERE resource_id = ?
				AND resource_update_id <> ?
				AND message_state = 'visible'
		", [$this->resource_id, $this->description_update_id]);

		return $this->update_count;
	}

	public function rebuildReviewCount()
	{
		$this->review_count = $this->db()->fetchOne("
			SELECT COUNT(*)
				FROM xf_rm_resource_rating
				WHERE resource_id = ?
					AND is_review = 1
					AND rating_state = 'visible'
		", $this->resource_id);

		return $this->review_count;
	}

	public function rebuildRating()
	{
		$rating = $this->db()->fetchRow("
			SELECT COUNT(*) AS total,
				SUM(rating) AS sum
			FROM xf_rm_resource_rating
			WHERE resource_id = ?
				AND count_rating = 1
				AND rating_state = 'visible'
		", $this->resource_id);

		$this->rating_sum = $rating['sum'] ?: 0;
		$this->rating_count = $rating['total'] ?: 0;
	}

	protected function updateRatingAverage()
	{
		$threshold = self::RATING_WEIGHTED_THRESHOLD;
		$average = self::RATING_WEIGHTED_AVERAGE;

		$this->rating_weighted = ($threshold * $average + $this->rating_sum) / ($threshold + $this->rating_count);

		if ($this->rating_count)
		{
			$this->rating_avg = $this->rating_sum / $this->rating_count;
		}
		else
		{
			$this->rating_avg = 0;
		}
	}

	public function rebuildCurrentVersion()
	{
		$this->current_version_id = $this->db()->fetchOne("
			SELECT resource_version_id
			FROM xf_rm_resource_version
			WHERE resource_id = ?
				AND version_state = 'visible'
			ORDER BY release_date DESC
			LIMIT 1
		", $this->resource_id);

		return $this->current_version_id;
	}

	public function updateAdded(ResourceUpdate $update)
	{
		if (!$this->description_update_id)
		{
			$this->description_update_id = $update->resource_update_id;
		}
		else
		{
			$this->update_count++;
		}

		if ($update->post_date >= $this->last_update)
		{
			$this->last_update = $update->post_date;
		}

		unset($this->_getterCache['resource_update_ids']);
	}

	public function updateRemoved(ResourceUpdate $update)
	{
		$this->update_count--;

		if ($update->post_date == $this->last_update)
		{
			$this->rebuildLastUpdateInfo();
		}

		unset($this->_getterCache['resource_update_ids']);
	}

	public function versionAdded(ResourceVersion $version)
	{
		$currentVersion = $this->CurrentVersion;

		if (!$currentVersion || $version->release_date >= $currentVersion->release_date)
		{
			$this->current_version_id = $version->resource_version_id;
		}
	}

	public function versionRemoved(ResourceVersion $version)
	{
		if ($version->resource_version_id == $this->current_version_id)
		{
			$this->rebuildCurrentVersion();
		}
	}

	protected function _preSave()
	{
		if ($this->prefix_id && $this->isChanged(['prefix_id', 'resource_category_id']))
		{
			if (!$this->Category->isPrefixValid($this->prefix_id))
			{
				$this->prefix_id = 0;
			}
		}

		$externalPurchaseParts = (floatval($this->price) ? 1 : 0)
			+ ($this->currency ? 1 : 0)
			+ ($this->external_purchase_url ? 1 : 0);

		if ($this->resource_type == 'external_purchase')
		{
			if ($externalPurchaseParts < 3)
			{
				$this->error(\XF::phrase('xfrm_please_complete_all_commercial_resource_related_fields'), 'currency');
			}
		}
		else
		{
			if ($externalPurchaseParts > 0)
			{
				$this->error(\XF::phrase('xfrm_non_purchasable_resources_may_not_define_purchasable_components'), 'currency');
			}
		}

		if ($this->isInsert() || $this->isChanged(['rating_sum', 'rating_count']))
		{
			$this->updateRatingAverage();
		}
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('resource_state', 'visible');
		$approvalChange = $this->isStateChanged('resource_state', 'moderated');
		$deletionChange = $this->isStateChanged('resource_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->resourceMadeVisible();

				if ($approvalChange)
				{
					$this->submitHamData();
				}
			}
			else if ($visibilityChange == 'leave')
			{
				$this->resourceHidden();
			}

			if ($this->isChanged('resource_category_id'))
			{
				$oldCategory = $this->getExistingRelation('Category');
				if ($oldCategory && $this->Category)
				{
					$this->resourceMoved($oldCategory, $this->Category);
				}
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
			if ($this->resource_state == 'visible')
			{
				$this->resourceInsertedVisible();
			}
		}

		if ($this->isUpdate())
		{
			if ($this->isChanged('user_id'))
			{
				$this->resourceReassigned();
			}

			if ($this->isChanged('discussion_thread_id'))
			{
				if ($this->getExistingValue('discussion_thread_id'))
				{
					/** @var \XF\Entity\Thread $oldDiscussion */
					$oldDiscussion = $this->getExistingRelation('Discussion');
					if ($oldDiscussion && $oldDiscussion->discussion_type == 'resource')
					{
						// this will set it back to the forum default type
						$oldDiscussion->discussion_type = '';
						$oldDiscussion->save(false, false);
					}
				}

				if (
					$this->discussion_thread_id
					&& $this->Discussion
					&& $this->Discussion->discussion_type === \XF\ThreadType\AbstractHandler::BASIC_THREAD_TYPE
				)
				{
					$this->Discussion->discussion_type = 'resource';
					$this->Discussion->save(false, false);
				}
			}

			if ($this->discussion_thread_id)
			{
				$newThreadTitle = $this->getExpectedThreadTitle(true);
				if (
					$newThreadTitle != $this->getExpectedThreadTitle(false)
					&& $this->Discussion
					&& $this->Discussion->discussion_type == 'resource')
				{
					$this->Discussion->title = $newThreadTitle;
					$this->Discussion->saveIfChanged($saved, false, false);
				}
			}
		}

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->resource_date;
			$approvalQueue->save();
		}
		else if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		$this->updateCategoryRecord();

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorChanges('resource', $this);
		}

		$this->_postSaveBookmarks();
	}

	protected function resourceMadeVisible()
	{
		$this->adjustUserResourceCountIfNeeded(1);

		if ($this->discussion_thread_id && $this->Discussion && $this->Discussion->discussion_type == 'resource')
		{
			$thread = $this->Discussion;

			switch ($this->app()->options()->xfrmResourceDeleteThreadAction['action'])
			{
				case 'delete':
					$thread->discussion_state = 'visible';
					break;

				case 'close':
					$thread->discussion_open = true;
					break;
			}

			$thread->title = $this->getExpectedThreadTitle();
			$thread->saveIfChanged($saved, false, false);
		}

		/** @var \XF\Repository\Reaction $reactionRepo */
		$reactionRepo = $this->repository('XF:Reaction');
		$reactionRepo->recalculateReactionIsCounted('resource_update', $this->resource_update_ids);
	}

	protected function resourceHidden($hardDelete = false)
	{
		$this->adjustUserResourceCountIfNeeded(-1);

		if ($this->discussion_thread_id && $this->Discussion && $this->Discussion->discussion_type == 'resource')
		{
			$thread = $this->Discussion;

			switch ($this->app()->options()->xfrmResourceDeleteThreadAction['action'])
			{
				case 'delete':
					$thread->discussion_state = 'deleted';
					break;

				case 'close':
					$thread->discussion_open = false;
					break;
			}

			$thread->title = $this->getExpectedThreadTitle();
			$thread->saveIfChanged($saved, false, false);
		}

		if (!$hardDelete)
		{
			// on hard delete the likes will be removed which will do this
			/** @var \XF\Repository\Reaction $reactionRepo */
			$reactionRepo = $this->repository('XF:Reaction');
			$reactionRepo->recalculateReactionIsCounted('resource_update', $this->resource_update_ids, false);
		}

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('resource_update', $this->resource_update_ids);
		$alertRepo->fastDeleteAlertsForContent('resource_rating', $this->resource_rating_ids);
	}

	protected function resourceInsertedVisible()
	{
		$this->adjustUserResourceCountIfNeeded(1);
	}

	protected function submitHamData()
	{
		/** @var \XF\Spam\ContentChecker $submitter */
		$submitter = $this->app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('resource', $this->resource_id);
	}

	protected function resourceMoved(Category $from, Category $to)
	{
	}

	protected function resourceReassigned()
	{
		if ($this->resource_state == 'visible')
		{
			$this->adjustUserResourceCountIfNeeded(-1, $this->getExistingValue('user_id'));
			$this->adjustUserResourceCountIfNeeded(1);
		}
	}

	protected function adjustUserResourceCountIfNeeded($amount, $userId = null)
	{
		if ($userId === null)
		{
			$userId = $this->user_id;
		}

		if ($userId)
		{
			$this->db()->query("
				UPDATE xf_user
				SET xfrm_resource_count = GREATEST(0, xfrm_resource_count + ?)
				WHERE user_id = ?
			", [$amount, $userId]);
		}
	}

	protected function updateCategoryRecord()
	{
		if (!$this->Category)
		{
			return;
		}

		$category = $this->Category;

		if ($this->isUpdate() && $this->isChanged('resource_category_id'))
		{
			// moved, trumps the rest
			if ($this->resource_state == 'visible')
			{
				$category->resourceAdded($this);
				$category->save();
			}

			if ($this->getExistingValue('resource_state') == 'visible')
			{
				/** @var Category $oldCategory */
				$oldCategory = $this->getExistingRelation('Category');
				if ($oldCategory)
				{
					$oldCategory->resourceRemoved($this);
					$oldCategory->save();
				}
			}

			if ($this->discussion_thread_id && $this->Discussion && $this->Discussion->discussion_type == 'resource')
			{
				$thread = $this->Discussion;
				if ($category->thread_node_id)
				{
					$thread->node_id = $category->thread_node_id;
					$thread->prefix_id = $category->thread_prefix_id;
					if ($this->resource_state == 'visible' && $thread->discussion_state == 'deleted')
					{
						// presumably the thread was soft deleted by being moved to a category without a thread
						$thread->discussion_state = 'visible';
					}
				}
				else
				{
					// this category doesn't have a thread
					$thread->discussion_state = 'deleted';
				}

				$thread->saveIfChanged($saved, false, false);
			}

			return;
		}

		// check for entering/leaving visible
		$visibilityChange = $this->isStateChanged('resource_state', 'visible');
		if ($visibilityChange == 'enter' && $category)
		{
			$category->resourceAdded($this);
			$category->save();
		}
		else if ($visibilityChange == 'leave' && $category)
		{
			$category->resourceRemoved($this);
			$category->save();
		}
		else if ($this->isUpdate() && $this->resource_state == 'visible')
		{
			$category->resourceDataChanged($this);
			$category->save();
		}
	}

	protected function _postDelete()
	{
		if ($this->resource_state == 'visible')
		{
			$this->resourceHidden(true);
		}

		if ($this->Category && $this->resource_state == 'visible')
		{
			$this->Category->resourceRemoved($this);
			$this->Category->save();
		}

		if ($this->resource_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}

		if ($this->resource_state == 'moderated' && $this->ApprovalQueue)
		{
			$this->ApprovalQueue->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('resource', $this, 'delete_hard');
		}

		$db = $this->db();

		$updateIds = $this->resource_update_ids;
		if ($updateIds)
		{
			$this->_postDeleteUpdates($updateIds);
		}

		$versionIds = $this->resource_version_ids;
		if ($versionIds)
		{
			$this->_postDeleteVersions($versionIds);
		}

		$ratingIds = $this->resource_rating_ids;
		if ($ratingIds)
		{
			$this->_postDeleteRatings($ratingIds);
		}

		$db->delete('xf_rm_resource_feature', 'resource_id = ?', $this->resource_id);
		$db->delete('xf_rm_resource_team_member', 'resource_id = ?', $this->resource_id);
		$db->delete('xf_rm_resource_watch', 'resource_id = ?', $this->resource_id);

		/** @var \XFRM\Service\ResourceItem\Icon $iconService */
		$iconService = $this->app()->service('XFRM:ResourceItem\Icon', $this);
		$iconService->deleteIconForResourceDelete();

		$this->_postDeleteBookmarks();
	}

	protected function _postDeleteUpdates(array $updateIds)
	{
		$db = $this->db();

		/** @var \XF\Repository\Attachment $attachRepo */
		$attachRepo = $this->repository('XF:Attachment');
		$attachRepo->fastDeleteContentAttachments('resource_update', $updateIds);

		/** @var \XF\Repository\Reaction $reactionRepo */
		$reactionRepo = $this->repository('XF:Reaction');
		$reactionRepo->recalculateReactionIsCounted('resource_update', $updateIds);

		$db->delete('xf_rm_resource_update', 'resource_update_id IN (' . $db->quote($updateIds) . ')');

		$db->delete('xf_approval_queue', 'content_id IN (' . $db->quote($updateIds) . ') AND content_type = ?', 'resource_update');
		$db->delete('xf_deletion_log', 'content_id IN (' . $db->quote($updateIds) . ') AND content_type = ?', 'resource_update');
	}

	protected function _postDeleteVersions(array $versionIds)
	{
		$db = $this->db();

		/** @var \XF\Repository\Attachment $attachRepo */
		$attachRepo = $this->repository('XF:Attachment');
		$attachRepo->fastDeleteContentAttachments('resource_version', $versionIds);

		$db->delete('xf_rm_resource_version', 'resource_version_id IN (' . $db->quote($versionIds) . ')');
		$db->delete('xf_rm_resource_download', 'resource_version_id IN (' . $db->quote($versionIds) . ')');

		$db->delete('xf_approval_queue', 'content_id IN (' . $db->quote($versionIds) . ') AND content_type = ?', 'resource_version');
		$db->delete('xf_deletion_log', 'content_id IN (' . $db->quote($versionIds) . ') AND content_type = ?', 'resource_version');
	}

	protected function _postDeleteRatings(array $ratingIds)
	{
		$db = $this->db();

		$db->delete('xf_rm_resource_rating', 'resource_version_id IN (' . $db->quote($ratingIds) . ')');
	}

	public function softDelete($reason = '', \XF\Entity\User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->resource_state == 'deleted')
		{
			return false;
		}

		$this->resource_state = 'deleted';

		/** @var \XF\Entity\DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'resources';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'resources';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('xfrm_resource_x', [
			'title' => $this->title
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
		$result->username = $this->User ? $this->User->username : $this->username;

		if ($verbosity > self::VERBOSITY_NORMAL)
		{
			$description = $this->Description->toApiResult()->render();
			$result->description = $description->message;
			$result->description_parsed = $description->message_parsed;
			$result->description_attach_count = $description->attach_count;
			if (isset($description->Attachments))
			{
				$result->DescriptionAttachments = $description->Attachments;
			}
			$result->reaction_score = $description->reaction_score;

			$result->update_count = $this->real_update_count;
			$result->review_count = $this->real_review_count;

			if ($this->canDownload())
			{
				if ($this->isDownloadable())
				{
					$version = $this->CurrentVersion->toApiResult()->render();
					if (isset($version->download_url))
					{
						$result->current_download_url = $version->download_url;
					}
					if (isset($version->files))
					{
						$result->current_files = $version->files;
					}
				}
				else if ($this->isExternalPurchasable())
				{
					$result->external_purchase_url = $this->external_purchase_url;
				}
			}
		}

		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			$result->is_watching = isset($this->Watch[$visitor->user_id]);
		}

		if (!empty($options['skip_category']))
		{
			$result->skipRelation('Category');
		}

		if ($this->app()->options()->xfrmAllowIcons)
		{
			$result->icon_url = $this->getIconUrl(null, true);
		}
		if ($this->isVersioned())
		{
			$result->version = $this->CurrentVersion->version_string;
		}

		$result->custom_fields = (object)$this->custom_fields->getNamedFieldValues($this->Category->field_cache);
		$result->tags = array_column($this->tags, 'tag');

		if ($this->prefix_id)
		{
			$result->prefix = \XF::phrase('resource_prefix.' . $this->prefix_id);
		}

		$result->can_edit = $this->canEdit();
		$result->can_edit_tags = $this->canEditTags();
		$result->can_edit_icon = $this->canEditIcon();
		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');
		$result->can_download = $this->canDownload();
		$result->can_view_description_attachments = $this->canViewUpdateImages();

		$result->view_url = $this->getContentUrl(true);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource';
		$structure->shortName = 'XFRM:ResourceItem';
		$structure->primaryKey = 'resource_id';
		$structure->contentType = 'resource';
		$structure->columns = [
			'resource_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'please_enter_valid_title',
				'censor' => true,
				'api' => true
			],
			'tag_line' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'xfrm_please_enter_valid_tag_line',
				'censor' => true,
				'api' => true
			],
			'user_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_name'
			],
			'team_member_user_ids' => ['type' => self::LIST_COMMA, 'default' => [],
				'list' => ['type' => 'posint', 'unique' => true]
			],
			'resource_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true
			],
			'resource_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['download', 'external_purchase', 'fileless'], 'api' => true
			],
			'resource_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'resource_category_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'current_version_id' => ['type' => self::UINT, 'default' => 0],
			'description_update_id' => ['type' => self::UINT, 'default' => 0],
			'discussion_thread_id' => ['type' => self::UINT, 'default' => 0],
			'external_url' => ['type' => self::STR, 'default' => '',
				'censor' => true,
				'match' => 'url_empty',
				'api' => true
			],
			'external_purchase_url' => ['type' => self::STR, 'default' => '',
				'censor' => true,
				'match' => 'url_empty'
			],
			'price' => ['type' => self::FLOAT, 'default' => 0, 'max' => 99999999, 'min' => 0, 'api' => true],
			'currency' => ['type' => self::STR, 'default' => '', 'maxLength' => 3, 'api' => true],
			'view_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true, 'api' => true],
			'download_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true, 'api' => true],
			'rating_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true, 'api' => true],
			'rating_sum' => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'rating_avg' => ['type' => self::FLOAT, 'default' => 0, 'api' => true],
			'rating_weighted' => ['type' => self::FLOAT, 'default' => 0, 'api' => true],
			'update_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'review_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'last_update' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'alt_support_url' => ['type' => self::STR, 'default' => '',
				'censor' => true,
				'match' => 'url_empty',
				'api' => true
			],
			'custom_fields' => ['type' => self::JSON_ARRAY, 'default' => []],
			'prefix_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'icon_date' => ['type' => self::UINT, 'default' => 0],
			'tags' => ['type' => self::JSON_ARRAY, 'default' => []]
		];
		$structure->getters = [
			'custom_fields' => true,
			'real_update_count' => true,
			'real_review_count' => true,
			'resource_update_ids' => true,
			'resource_version_ids' => true,
			'resource_rating_ids' => true,
			'draft_update' => true
		];
		$structure->behaviors = [
			'XF:Taggable' => ['stateField' => 'resource_state'],
			'XF:Indexable' => [
				'checkForUpdates' => ['title', 'resource_category_id', 'user_id', 'prefix_id', 'tags', 'resource_state']
			],
			'XF:IndexableContainer' => [
				'childContentType' => 'resource_update',
				'childIds' => function($resource) { return $resource->resource_update_ids; },
				'checkForUpdates' => ['resource_category_id', 'resource_state', 'prefix_id']
			],
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'resource_date'
			],
			'XF:CustomFieldsHolder' => [
				'valueTable' => 'xf_rm_resource_field_value',
				'checkForUpdates' => ['resource_category_id'],
				'getAllowedFields' => function($resource) { return $resource->Category ? $resource->Category->field_cache : []; }
			],
			'XF:ContentVotableContainer' => [
				'childContentType' => 'resource_rating',
				'childIds' => function($resource) { return $resource->resource_rating_ids; },
				'stateField' => 'resource_state'
			],
		];
		$structure->relations = [
			'Category' => [
				'entity' => 'XFRM:Category',
				'type' => self::TO_ONE,
				'conditions' => 'resource_category_id',
				'primary' => true,
				'api' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
				'api' => true
			],
			'TeamMembers' => [
				'entity' => 'XFRM:ResourceTeamMember',
				'type' => self::TO_MANY,
				'conditions' => 'resource_id',
				'key' => 'user_id',
				'with' => ['User'],
				'order' => 'User.username'
			],
			'Description' => [
				'entity' => 'XFRM:ResourceUpdate',
				'type' => self::TO_ONE,
				'conditions' => [['resource_update_id', '=', '$description_update_id']],
				'primary' => true
			],
			'CurrentVersion' => [
				'entity' => 'XFRM:ResourceVersion',
				'type' => self::TO_ONE,
				'conditions' => [['resource_version_id', '=', '$current_version_id']],
				'primary' => true
			],
			'Discussion' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => [['thread_id', '=', '$discussion_thread_id']],
				'primary' => true
			],
			'Featured' => [
				'entity' => 'XFRM:ResourceFeature',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true
			],
			'Prefix' => [
				'entity' => 'XFRM:ResourcePrefix',
				'type' => self::TO_ONE,
				'conditions' => 'prefix_id',
				'primary' => true
			],
			'Watch' => [
				'entity' => 'XFRM:ResourceWatch',
				'type' => self::TO_MANY,
				'conditions' => 'resource_id',
				'key' => 'user_id'
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource'],
					['content_id', '=', '$resource_id']
				],
				'primary' => true
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource'],
					['content_id', '=', '$resource_id']
				],
				'primary' => true
			],
			'DraftUpdates' => [
				'entity'     => 'XF:Draft',
				'type'       => self::TO_MANY,
				'conditions' => [
					['draft_key', '=', 'xfrm-resource-', '$resource_id']
				],
				'key'        => 'user_id'
			]
		];
		$structure->options = [
			'log_moderator' => true
		];

		$structure->withAliases = [
			'full' => [
				'User',
				'CurrentVersion',
				'Featured',
				function()
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return ['Watch|' . $userId];
					}

					return null;
				}
			],
			'fullCategory' => [
				'full',
				function()
				{
					$with = ['Category'];

					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						$with[] = 'Category.Watch|' . $userId;
					}

					return $with;
				}
			],
			'api' => [
				'User.api',
				'Category.api',
				'CurrentVersion',
				'Featured',
				function()
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return ['Watch|' . $userId];
					}

					return null;
				}
			]
		];

		static::addBookmarkableStructureElements($structure);

		return $structure;
	}
}