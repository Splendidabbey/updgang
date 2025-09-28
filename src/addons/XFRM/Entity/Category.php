<?php

namespace XFRM\Entity;

use XF\Entity\AbstractCategoryTree;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null resource_category_id
 * @property string title
 * @property string description
 * @property int resource_count
 * @property int featured_count
 * @property int last_update
 * @property string last_resource_title
 * @property int last_resource_id
 * @property array field_cache
 * @property array review_field_cache
 * @property array prefix_cache
 * @property bool require_prefix
 * @property int thread_node_id
 * @property int thread_prefix_id
 * @property bool allow_local
 * @property bool allow_external
 * @property bool allow_commercial_external
 * @property bool allow_fileless
 * @property bool always_moderate_create
 * @property bool always_moderate_update
 * @property int min_tags
 * @property bool enable_versioning
 * @property bool enable_support_url
 * @property int parent_category_id
 * @property int display_order
 * @property int lft
 * @property int rgt
 * @property int depth
 * @property array breadcrumb_data
 *
 * GETTERS
 * @property \XF\Mvc\Entity\ArrayCollection prefixes
 * @property \XF\Draft draft_resource
 *
 * RELATIONS
 * @property \XF\Entity\Forum ThreadForum
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\CategoryWatch[] Watch
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Draft[] DraftResources
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\PermissionCacheContent[] Permissions
 */
class Category extends AbstractCategoryTree implements \XF\Entity\LinkableInterface
{
	protected $_viewableDescendants = [];

	public function canView(&$error = null)
	{
		return $this->hasPermission('view');
	}

	public function canViewDeletedResources()
	{
		return $this->hasPermission('viewDeleted');
	}

	public function canViewModeratedResources()
	{
		return $this->hasPermission('viewModerated');
	}

	public function canEditTags(ResourceItem $resource = null, &$error = null)
	{
		if (!$this->app()->options()->enableTagging)
		{
			return false;
		}

		// if no resource, assume will be owned by this person
		if (!$resource || $resource->isTeamMember())
		{
			if ($this->hasPermission('tagOwnResource'))
			{
				return true;
			}
		}

		return (
			$this->hasPermission('tagAnyResource')
			|| $this->hasPermission('manageAnyTag')
		);
	}

	public function canUseInlineModeration(&$error = null)
	{
		return $this->hasPermission('inlineMod');
	}

	public function canUploadAndManageUpdateImages()
	{
		return $this->hasPermission('uploadUpdateAttach');
	}

	public function canUploadVideos()
	{
		return $this->hasPermission('uploadUpdateVideo');
	}

	public function canAddResource(&$error = null)
	{
		if (!\XF::visitor()->user_id || !$this->hasPermission('add'))
		{
			return false;
		}

		$hasAllowedTypes = (
			$this->allow_local
			|| $this->allow_external
			|| $this->allow_commercial_external
			|| $this->allow_fileless
		);
		if (!$hasAllowedTypes)
		{
			$error = \XF::phraseDeferred('xfrm_category_not_allow_new_resources');
			return false;
		}

		return true;
	}

	public function canWatch(&$error = null)
	{
		return (\XF::visitor()->user_id ? true : false);
	}

	public function getDefaultSelectedType()
	{
		if ($this->allow_local)
		{
			return 'download_local';
		}
		if ($this->allow_external)
		{
			return 'download_external';
		}
		if ($this->allow_commercial_external)
		{
			return 'external_purchase';
		}
		if ($this->allow_fileless)
		{
			return 'fileless';
		}

		return null;
	}

	public function hasPermission($permission)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->hasResourceCategoryPermission($this->resource_category_id, $permission);
	}

	public function getViewableDescendants()
	{
		$userId = \XF::visitor()->user_id;
		if (!isset($this->_viewableDescendants[$userId]))
		{
			$viewable = $this->repository('XFRM:Category')->getViewableCategories($this);
			$this->_viewableDescendants[$userId] = $viewable->toArray();
		}

		return $this->_viewableDescendants[$userId];
	}

	public function cacheViewableDescendents(array $descendents, $userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}

		$this->_viewableDescendants[$userId] = $descendents;
	}

	/**
	 * @return \XF\Draft
	 */
	public function getDraftResource()
	{
		return \XF\Draft::createFromEntity($this, 'DraftResources');
	}

	public function getUsablePrefixes($forcePrefix = null)
	{
		$prefixes = $this->prefixes;

		if ($forcePrefix instanceof ResourcePrefix)
		{
			$forcePrefix = $forcePrefix->prefix_id;
		}

		$prefixes = $prefixes->filter(function($prefix) use ($forcePrefix)
		{
			if ($forcePrefix && $forcePrefix == $prefix->prefix_id)
			{
				return true;
			}
			return $this->isPrefixUsable($prefix);
		});

		return $prefixes->groupBy('prefix_group_id');
	}

	public function getPrefixesGrouped()
	{
		return $this->prefixes->groupBy('prefix_group_id');
	}

	/**
	 * @return \XF\Mvc\Entity\ArrayCollection
	 */
	public function getPrefixes()
	{
		if (!$this->prefix_cache)
		{
			return $this->_em->getEmptyCollection();
		}

		$prefixes = $this->finder('XFRM:ResourcePrefix')
			->where('prefix_id', $this->prefix_cache)
			->order('materialized_order')
			->fetch();

		return $prefixes;
	}

	public function isPrefixUsable($prefix, \XF\Entity\User $user = null)
	{
		if (!$this->isPrefixValid($prefix))
		{
			return false;
		}

		if (!($prefix instanceof ResourcePrefix))
		{
			$prefix = $this->em()->find('XFRM:ResourcePrefix', $prefix);
			if (!$prefix)
			{
				return false;
			}
		}

		return $prefix->isUsableByUser($user);
	}

	public function isPrefixValid($prefix)
	{
		if ($prefix instanceof ResourcePrefix)
		{
			$prefix = $prefix->prefix_id;
		}

		return (!$prefix || isset($this->prefix_cache[$prefix]));
	}

	public function hasVersioningSupport()
	{
		if (!$this->enable_versioning)
		{
			return false;
		}

		if (
			$this->allow_local
			|| $this->allow_external
			|| $this->allow_commercial_external
		)
		{
			 return true;
		}

		return false;
	}

	public function getNewResource()
	{
		$resource = $this->_em->create('XFRM:ResourceItem');
		$resource->resource_category_id = $this->resource_category_id;

		return $resource;
	}

	public function getNewContentState(ResourceItem $resource = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $this->hasPermission('approveUnapprove'))
		{
			return 'visible';
		}

		if (!$visitor->hasPermission('general', 'submitWithoutApproval'))
		{
			return 'moderated';
		}

		if ($resource)
		{
			return $this->always_moderate_update ? 'moderated' : 'visible';
		}
		else
		{
			return $this->always_moderate_create ? 'moderated' : 'visible';
		}
	}

	public function getBreadcrumbs($includeSelf = true, $linkType = 'public')
	{
		if ($linkType == 'public')
		{
			$link = 'resources/categories';
		}
		else
		{
			$link = 'resource-manager/categories';
		}
		return $this->_getBreadcrumbs($includeSelf, $linkType, $link);
	}

	public function getCategoryListExtras()
	{
		return [
			'resource_count' => $this->resource_count,
			'last_update' => $this->last_update,
			'last_resource_title' => $this->last_resource_title,
			'last_resource_id' => $this->last_resource_id
		];
	}

	public function resourceAdded(ResourceItem $resource)
	{
		$this->resource_count++;

		if ($resource->last_update >= $this->last_update)
		{
			$this->last_update = $resource->last_update;
			$this->last_resource_title = $resource->title;
			$this->last_resource_id = $resource->resource_id;
		}

		if ($resource->Featured)
		{
			$this->featured_count++;
		}
	}

	public function resourceDataChanged(ResourceItem $resource)
	{
		if ($resource->isChanged(['last_update', 'title']))
		{
			if ($resource->last_update >= $this->last_update)
			{
				$this->last_update = $resource->last_update;
				$this->last_resource_title = $resource->title;
				$this->last_resource_id = $resource->resource_id;
			}
			else if ($resource->getExistingValue('last_update') == $this->last_update)
			{
				$this->rebuildLastResource();
			}
		}
	}

	public function resourceRemoved(ResourceItem $resource)
	{
		$this->resource_count--;

		if ($resource->last_update == $this->last_update)
		{
			$this->rebuildLastResource();
		}

		if ($resource->Featured)
		{
			$this->featured_count--;
		}
	}

	public function rebuildCounters()
	{
		$counters = $this->db()->fetchRow("
			SELECT COUNT(*) AS resource_count
			FROM xf_rm_resource
			WHERE resource_category_id = ?
				AND resource_state = 'visible'
		", $this->resource_category_id);

		$this->resource_count = $counters['resource_count'];

		$this->featured_count = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_rm_resource_feature AS feature
				INNER JOIN xf_rm_resource AS resource ON (resource.resource_id = feature.resource_id)
			WHERE resource.resource_category_id = ?
				AND resource.resource_state = 'visible'
		", $this->resource_category_id);

		$this->rebuildLastResource();
	}

	public function rebuildLastResource()
	{
		$resource = $this->db()->fetchRow("
			SELECT *
			FROM xf_rm_resource
			WHERE resource_category_id = ?
				AND resource_state = 'visible'
			ORDER BY last_update DESC
			LIMIT 1
		", $this->resource_category_id);
		if ($resource)
		{
			$this->last_update = $resource['last_update'];
			$this->last_resource_title = $resource['title'];
			$this->last_resource_id = $resource['resource_id'];
		}
		else
		{
			$this->last_update = 0;
			$this->last_resource_title = '';
			$this->last_resource_id = 0;
		}
	}

	protected function _preSave()
	{
		if ($this->isChanged(['thread_node_id', 'thread_prefix_id']))
		{
			if (!$this->thread_node_id)
			{
				$this->thread_prefix_id = 0;
			}
			else
			{
				if (!$this->ThreadForum)
				{
					$this->thread_node_id = 0;
					$this->thread_prefix_id = 0;
				}
				else if ($this->thread_prefix_id && !$this->ThreadForum->isPrefixValid($this->thread_prefix_id))
				{
					$this->thread_prefix_id = 0;
				}
			}
		}
	}

	protected function _postDelete()
	{
		$db = $this->db();

		$db->delete('xf_rm_category_field', 'resource_category_id = ?', $this->resource_category_id);
		$db->delete('xf_rm_category_prefix', 'resource_category_id = ?', $this->resource_category_id);
		$db->delete('xf_rm_category_watch', 'resource_category_id = ?', $this->resource_category_id);

		if ($this->getOption('delete_resources'))
		{
			$this->app()->jobManager()->enqueueUnique('xfrmCategoryDelete' . $this->resource_category_id, 'XFRM:CategoryDelete', [
				'resource_category_id' => $this->resource_category_id
			]);
		}
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'resources/categories';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'resources/categories';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('xfrm_resource_category_x', [
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
		if ($verbosity > self::VERBOSITY_NORMAL)
		{
			$result->prefixes = $this->prefixes->toApiResults();

			$fields = [];
			if ($this->field_cache)
			{
				$fieldEntities = $this->repository('XFRM:ResourceField')->findFieldsForList()
					->whereIds($this->field_cache)
					->fetch();
				$fields = $fieldEntities->toApiResults();
			}

			$result->custom_fields = $fields;
		}

		$result->can_add = $this->canAddResource();
		$result->can_upload_images = $this->canUploadAndManageUpdateImages();

		$result->view_url = $this->getContentUrl(true);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_category';
		$structure->shortName = 'XFRM:Category';
		$structure->primaryKey = 'resource_category_id';
		$structure->contentType = 'resource_category';
		$structure->columns = [
			'resource_category_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'please_enter_valid_title', 'api' => true
			],
			'description' => ['type' => self::STR, 'default' => '', 'api' => true],
			'resource_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true, 'api' => true],
			'featured_count' => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'last_update' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_resource_title' => ['type' => self::STR, 'default' => '', 'maxLength' => 100,
				'censor' => true, 'api' => true
			],
			'last_resource_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'field_cache' => ['type' => self::JSON_ARRAY, 'default' => []],
			'review_field_cache' => ['type' => self::JSON_ARRAY, 'default' => []],
			'prefix_cache' => ['type' => self::JSON_ARRAY, 'default' => []],
			'require_prefix' => ['type' => self::BOOL, 'default' => false],
			'thread_node_id' => ['type' => self::UINT, 'default' => 0],
			'thread_prefix_id' => ['type' => self::UINT, 'default' => 0],
			'allow_local' => ['type' => self::BOOL, 'default' => true, 'api' => true],
			'allow_external' => ['type' => self::BOOL, 'default' => true, 'api' => true],
			'allow_commercial_external' => ['type' => self::BOOL, 'default' => true, 'api' => true],
			'allow_fileless' => ['type' => self::BOOL, 'default' => true, 'api' => true],
			'always_moderate_create' => ['type' => self::BOOL, 'default' => false],
			'always_moderate_update' => ['type' => self::BOOL, 'default' => false],
			'min_tags' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'max' => 100, 'api' => true],
			'enable_versioning' => ['type' => self::BOOL, 'default' => true, 'api' => true],
			'enable_support_url' => ['type' => self::BOOL, 'default' => true, 'api' => true],
		];
		$structure->getters = [
			'prefixes' => true,
			'draft_resource' => true
		];
		$structure->relations = [
			'ThreadForum' => [
				'entity' => 'XF:Forum',
				'type' => self::TO_ONE,
				'conditions' => [
					['node_id', '=', '$thread_node_id']
				],
				'primary' => true,
				'with' => 'Node'
			],
			'Watch' => [
				'entity' => 'XFRM:CategoryWatch',
				'type' => self::TO_MANY,
				'conditions' => 'resource_category_id',
				'key' => 'user_id'
			],
			'DraftResources' => [
				'entity'     => 'XF:Draft',
				'type'       => self::TO_MANY,
				'conditions' => [
					['draft_key', '=', 'xfrm-category-', '$resource_category_id']
				],
				'key'        => 'user_id'
			]
		];
		$structure->options = [
			'delete_resources' => true
		];
		$structure->withAliases = [
			'api' => []
		];

		static::addCategoryTreeStructureElements($structure, [
			'breadcrumb_json' => true
		]);

		return $structure;
	}
}