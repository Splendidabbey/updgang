<?php

namespace XFRM\Entity;

use XF\Entity\AbstractPrefixGroup;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null prefix_group_id
 * @property int display_order
 *
 * GETTERS
 * @property \XF\Phrase|string title
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\ResourcePrefix[] Prefixes
 */
class ResourcePrefixGroup extends AbstractPrefixGroup
{
	protected function getClassIdentifier()
	{
		return 'XFRM:ResourcePrefix';
	}

	protected static function getContentType()
	{
		return 'resource';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_rm_resource_prefix_group',
			'XFRM:ResourcePrefixGroup',
			'XFRM:ResourcePrefix'
		);

		return $structure;
	}
}