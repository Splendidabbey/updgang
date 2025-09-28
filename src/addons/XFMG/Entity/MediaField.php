<?php

namespace XFMG\Entity;

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
 * @property bool album_use
 * @property bool display_add_media
 *
 * GETTERS
 * @property \XF\Phrase title
 * @property \XF\Phrase description
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XF\Entity\Phrase MasterDescription
 * @property \XF\Mvc\Entity\AbstractCollection|\XFMG\Entity\CategoryField[] CategoryFields
 */
class MediaField extends AbstractField
{
	protected function getClassIdentifier()
	{
		return 'XFMG:MediaField';
	}

	protected static function getPhrasePrefix()
	{
		return 'xfmg_media_field';
	}

	protected function _postDelete()
	{
		/** @var \XFMG\Repository\CategoryField $repo */
		$repo = $this->repository('XFMG:CategoryField');
		$repo->removeFieldAssociations($this);

		$this->db()->delete('xf_mg_media_field_value', 'field_id = ?', $this->field_id);

		parent::_postDelete();
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_mg_media_field',
			'XFMG:MediaField',
			[
				'groups' => ['below_media', 'below_info', 'extra_info_sidebar_block', 'new_sidebar_block'],
				'has_wrapper_template' => true
			]
		);

		$structure->columns['album_use'] = ['type' => self::BOOL, 'default' => true];
		$structure->columns['display_add_media'] = ['type' => self::BOOL, 'default' => true];

		$structure->relations['CategoryFields'] = [
			'entity' => 'XFMG:CategoryField',
				'type' => self::TO_MANY,
				'conditions' => 'field_id'
		];

		return $structure;
	}
}