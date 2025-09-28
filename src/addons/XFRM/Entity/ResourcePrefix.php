<?php

namespace XFRM\Entity;

use XF\Entity\AbstractPrefix;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null prefix_id
 * @property int prefix_group_id
 * @property int display_order
 * @property int materialized_order
 * @property string css_class
 * @property array allowed_user_group_ids
 *
 * GETTERS
 * @property string|\XF\Phrase title
 * @property bool has_usage_help
 * @property string|\XF\Phrase description
 * @property string|\XF\Phrase usage_help
 * @property array resource_category_ids
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XFRM\Entity\ResourcePrefixGroup PrefixGroup
 * @property \XF\Entity\Phrase MasterDescription
 * @property \XF\Entity\Phrase MasterUsageHelp
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\CategoryPrefix[] CategoryPrefixes
 */
class ResourcePrefix extends AbstractPrefix
{
	protected function getClassIdentifier()
	{
		return 'XFRM:ResourcePrefix';
	}

	protected static function getContentType()
	{
		return 'resource';
	}

	/**
	 * @return array
	 */
	public function getResourceCategoryIds()
	{
		if (!$this->prefix_id)
		{
			return [];
		}

		return $this->db()->fetchAllColumn("
			SELECT resource_category_id
			FROM xf_rm_category_prefix
			WHERE prefix_id = ?
		", $this->prefix_id);
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		$this->repository('XFRM:CategoryPrefix')->removePrefixAssociations($this);
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_rm_resource_prefix', 'XFRM:ResourcePrefix', [
			'has_description' => true,
			'has_usage_help' => true
		]);

		$structure->getters['resource_category_ids'] = true;

		$structure->relations['CategoryPrefixes'] = [
			'entity' => 'XFRM:CategoryPrefix',
			'type' => self::TO_MANY,
			'conditions' => 'prefix_id'
		];

		return $structure;
	}
}