<?php

namespace XFRM\Entity;

use XF\Entity\LinkableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null resource_version_id
 * @property int resource_id
 * @property int team_user_id
 * @property string team_username
 * @property string version_string
 * @property int release_date
 * @property string download_url
 * @property int download_count
 * @property int rating_count
 * @property int rating_sum
 * @property string version_state
 * @property int file_count
 *
 * GETTERS
 * @property string resource_title
 * @property float|int rating_avg
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem Resource
 * @property \XF\Entity\User TeamUser
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Attachment[] Attachments
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\ResourceDownload[] Downloads
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\ResourceRating[] Ratings
 * @property \XF\Entity\DeletionLog DeletionLog
 * @property \XF\Entity\ApprovalQueue ApprovalQueue
 */
class ResourceVersion extends Entity implements LinkableInterface
{
	public function canView(&$error = null)
	{
		$resource = $this->Resource;

		if (!$resource || !$resource->canView($error))
		{
			return false;
		}

		$visitor = \XF::visitor();

		if ($this->version_state == 'moderated')
		{
			if (
				!$resource->hasPermission('viewModerated')
				&& (!$visitor->user_id || !$resource->isTeamMember())
			)
			{
				return false;
			}
		}
		else if ($this->version_state == 'deleted')
		{
			if (!$resource->hasPermission('viewDeleted'))
			{
				return false;
			}
		}

		return true;
	}

	public function isDownloadable()
	{
		return ($this->download_url || $this->file_count > 0);
	}

	public function canDownload(&$error = null)
	{
		$resource = $this->Resource;

		if (!$resource || !$resource->canDownload($error))
		{
			return false;
		}

		if (!$this->download_url && !$this->file_count)
		{
			// not a version with a download
			return false;
		}

		if ($this->download_url)
		{
			$censoredUrl = $this->app()->stringFormatter()->censorText($this->download_url);
			if ($censoredUrl !== $this->download_url)
			{
				$error = \XF::phraseDeferred('xfrm_download_is_not_available_try_another');
				return false;
			}
		}

		return true;
	}

	public function getExternalDownloadDomain()
	{
		if (!$this->download_url)
		{
			return null;
		}

		$domain = @parse_url($this->download_url, PHP_URL_HOST);
		return $domain ?: null;
	}

	public function canApproveUnapprove(&$error = null)
	{
		return $this->Resource && $this->Resource->canApproveUnapprove();
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();
		$resource = $this->Resource;

		if (!$visitor->user_id || !$resource)
		{
			return false;
		}

		if ($resource->current_version_id == $this->resource_version_id)
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

	public function isCurrentVersion()
	{
		$resource = $this->Resource;
		if (!$resource)
		{
			return false;
		}

		if ($this->resource_version_id == $resource->current_version_id)
		{
			return true;
		}

		// this can be called during an insert where the resource hasn't actually been updated yet
		if (!$resource->current_version_id)
		{
			return ($this->release_date == $resource->post_date);
		}

		return false;
	}

	public function canEditVersionString()
	{
		if (!$this->isCurrentVersion())
		{
			return false;
		}

		$resource = $this->Resource;
		if (!$resource)
		{
			return false;
		}

		if (!$resource->isVersioned())
		{
			return false;
		}

		if ($resource->hasPermission('editAny'))
		{
			// moderators can always edit the current version string
			return true;
		}

		if ($this->release_date < \XF::$time - 86400)
		{
			// otherwise, only let it be edited for 24 hours. After that, a new version should be generally be released.
			return false;
		}

		return true;
	}

	/**
	 * @return string
	 */
	public function getResourceTitle()
	{
		return $this->Resource ? $this->Resource->title : '';
	}

	/**
	 * @return float|int
	 */
	public function getRatingAvg()
	{
		if ($this->rating_count)
		{
			return $this->rating_sum / $this->rating_count;
		}
		else
		{
			return 0;
		}
	}

	public function ratingAdded(ResourceRating $rating)
	{
		$this->rating_count++;
		$this->rating_sum += $rating->rating;
	}

	public function ratingRemoved(ResourceRating $rating)
	{
		$this->rating_count--;
		$this->rating_sum -= $rating->rating;
	}

	protected function _preSave()
	{

	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('version_state', 'visible');
		$approvalChange = $this->isStateChanged('version_state', 'moderated');
		$deletionChange = $this->isStateChanged('version_state', 'deleted');

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

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->release_date;
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
			$this->app()->logger()->logModeratorChanges('resource_version', $this);
		}

		$this->updateResourceRecord();
	}

	protected function updateResourceRecord()
	{
		if (!$this->Resource || !$this->Resource->exists())
		{
			// inserting a resource, don't try to write to it
			return;
		}

		$visibilityChange = $this->isStateChanged('version_state', 'visible');
		if ($visibilityChange == 'enter' && $this->Resource)
		{
			$this->Resource->versionAdded($this);
			$this->Resource->save();
		}
		else if ($visibilityChange == 'leave' && $this->Resource)
		{
			$this->Resource->versionRemoved($this);
			$this->Resource->save();
		}
	}

	protected function _preDelete()
	{
		if ($this->isCurrentVersion())
		{
			throw new \LogicException("Cannot delete the current version");
		}
	}

	protected function _postDelete()
	{
		if ($this->Resource && $this->version_state == 'visible')
		{
			$this->Resource->versionRemoved($this);
			$this->Resource->save();
		}

		if ($this->version_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}

		if ($this->version_state == 'moderated' && $this->ApprovalQueue)
		{
			$this->ApprovalQueue->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('resource_version', $this, 'delete_hard');
		}

		/** @var \XF\Repository\Attachment $attachRepo */
		$attachRepo = $this->repository('XF:Attachment');
		$attachRepo->fastDeleteContentAttachments('resource_version', $this->resource_version_id);

		$this->db()->delete('xf_rm_resource_download', 'resource_version_id = ?', $this->resource_version_id);
	}

	public function softDelete($reason = '', \XF\Entity\User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->version_state == 'deleted')
		{
			return false;
		}

		$this->version_state = 'deleted';

		/** @var \XF\Entity\DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
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
		if (!empty($options['with_resource']))
		{
			$result->includeRelation('Resource');
		}

		if ($this->isDownloadable())
		{
			if ($this->download_url)
			{
				$result->download_url = $this->download_url;
			}
			else
			{
				$apiRouter = $this->app()->router('api');
				$files = [];
				foreach ($this->Attachments AS $attachment)
				{
					$downloadUrl = $apiRouter->buildLink(
						'canonical:resource-versions/download',
						$this,
						['file' => $attachment->attachment_id]
					);

					$files[] = [
						'id' => $attachment->attachment_id,
						'filename' => $attachment->filename,
						'size' => $attachment->file_size,
						'download_url' => $downloadUrl
					];
				}

				$result->files = $files;
			}
		}

		$result->includeGetter('rating_avg');

		$result->can_download = $this->canDownload();
		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');

		$result->view_url = $this->getContentUrl(true);
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = $canonical ? 'canonical:resources/version' : 'resources/version';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'resources/version';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('xfrm_resource_version_in_x', [
			'title' => $this->getResourceTitle()
		]);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource_version';
		$structure->shortName = 'XFRM:ResourceVersion';
		$structure->primaryKey = 'resource_version_id';
		$structure->contentType = 'resource_version';
		$structure->columns = [
			'resource_version_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'resource_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'team_user_id' => ['type' => self::UINT, 'required' => true],
			'team_username' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_name',
			],
			'version_string' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'xfrm_please_enter_valid_version', 'censor' => true, 'api' => true
			],
			'release_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'download_url' => ['type' => self::STR, 'maxLength' => 250, 'default' => '',
				'match' => 'url_empty'
			],
			'download_count' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'rating_count' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'rating_sum' => ['type' => self::UINT, 'default' => 0],
			'version_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true
			],
			'file_count' => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0],
		];
		$structure->getters = [
			'resource_title' => true,
			'rating_avg' => false
		];
		$structure->behaviors = [];
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
					['content_type', '=', 'resource_version'],
					['content_id', '=', '$resource_version_id']
				],
				'with' => ['Data']
			],
			'Downloads' => [
				'entity' => 'XFRM:ResourceDownload',
				'type' => self::TO_MANY,
				'conditions' => 'resource_version_id',
				'key' => 'user_id'
			],
			'Ratings' => [
				'entity' => 'XFRM:ResourceRating',
				'type' => self::TO_MANY,
				'conditions' => 'resource_version_id',
				'key' => 'user_id'
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource_version'],
					['content_id', '=', '$resource_version_id']
				],
				'primary' => true
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'resource_version'],
					['content_id', '=', '$resource_version_id']
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

		return $structure;
	}
}