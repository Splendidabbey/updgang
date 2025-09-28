<?php

namespace XFRM\Entity;

use XF\Entity\AbstractField;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string field_id
 * @property int display_order
 * @property string field_type
 * @property array field_choices
 * @property string match_type
 * @property array match_params
 * @property int max_length
 * @property bool required
 * @property string display_template
 * @property string display_group
 * @property string wrapper_template
 *
 * GETTERS
 * @property \XF\Phrase title
 * @property \XF\Phrase description
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XF\Entity\Phrase MasterDescription
 * @property \XF\Mvc\Entity\AbstractCollection|\XFRM\Entity\CategoryField[] CategoryFields
 */
class ResourceField extends AbstractField
{
	protected function getClassIdentifier()
	{
		return 'XFRM:ResourceField';
	}

	protected static function getPhrasePrefix()
	{
		return 'xfrm_resource_field';
	}

	protected function _postDelete()
	{
		/** @var \XFRM\Repository\CategoryField $repo */
		$repo = $this->repository('XFRM:CategoryField');
		$repo->removeFieldAssociations($this);

		$this->db()->delete('xf_rm_resource_field_value', 'field_id = ?', $this->field_id);

		parent::_postDelete();
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_rm_resource_field',
			'XFRM:ResourceField',
			[
				'groups' => ['above_info', 'below_info', 'extra_tab', 'new_tab'],
				'has_wrapper_template' => true
			]
		);

		$structure->relations['CategoryFields'] = [
			'entity' => 'XFRM:CategoryField',
			'type' => self::TO_MANY,
			'conditions' => 'field_id'
		];

		return $structure;
	}
}