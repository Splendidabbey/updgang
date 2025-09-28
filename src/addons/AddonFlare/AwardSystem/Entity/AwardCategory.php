<?php

namespace AddonFlare\AwardSystem\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class AwardCategory extends Entity
{
	public function getTitle()
	{
		return \XF::phrase($this->getTitlePhraseName());
	}

    public function getDescription()
    {
        return \XF::phrase($this->getDescriptionPhraseName());
    }

    public function getTitlePhraseName()
    {
    	if ($this->award_category_id)
    	{
			return 'af_as_award_cat_title.' . $this->award_category_id;
    	}
    	else
    	{
    		return 'af_as_award_cat_title.uncategorized';
    	}
    }

    public function getDescriptionPhraseName()
    {
    	if ($this->award_category_id)
    	{
			return 'af_as_award_cat_desc.' . $this->award_category_id;
    	}
    	else
    	{
    		return 'af_as_award_cat_desc.uncategorized';
    	}
    }

    public function getMasterTitlePhrase()
    {
        $phrase = $this->MasterTitle;
        if (!$phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getTitlePhraseName(); });
            $phrase->language_id = 0;
            $phrase->addon_id = '';
        }

        return $phrase;
    }

    public function getMasterDescriptionPhrase()
    {
        $phrase = $this->MasterDescription;
        if (!$phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getDescriptionPhraseName(); });
            $phrase->language_id = 0;
            $phrase->addon_id = '';
        }

        return $phrase;
    }

    public function isModeStep()
    {
        return $this->isMode('step');
    }

    public function isMode($modes)
    {
        if (!is_array($modes))
        {
            $modes = [$modes];
        }

        return in_array($this->display_mode, $modes);
    }

	protected function _postDelete()
	{
        if ($this->MasterTitle)
        {
            $this->MasterTitle->delete();
        }
        if ($this->MasterDescription)
        {
            $this->MasterDescription->delete();
        }

        if ($this->getOption('delete_awards'))
        {
            $awards = $this->finder('AddonFlare\AwardSystem:Award')
                ->where('award_category_id', $this->award_category_id)->fetch();

            foreach ($awards as $award)
            {
                $award->delete();
            }
        }
        else
        {
            $this->db()->update('xf_af_as_award', [
                'award_category_id' => 0
            ], 'award_category_id = ?', $this->award_category_id);
        }
	}

    public function getDisplayModeOptions()
    {
        $options = [];

        if (isset($this->_structure->columns['display_mode']['allowedValues']))
        {
            $modes = $this->_structure->columns['display_mode']['allowedValues'];
            foreach ($modes as $mode)
            {
                $options[$mode] = \XF::phrase('af_as_award_cat_display_mode_' . $mode);
            }
        }

        return $options;
    }

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_af_as_award_category';
		$structure->shortName = 'AddonFlare\AwardSystem:AwardCategory';
		$structure->primaryKey = 'award_category_id';
		$structure->columns = [
			'award_category_id' 	=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true,
				'unique' => 'award_category_ids_must_be_unique'
			],
			'display_order' => ['type' => self::UINT, 'default' => 0],
            'display_mode'  => ['type' => self::STR, 'default' => 'visible',
                'allowedValues' => ['visible', 'step', 'hidden']
            ],
            'overwrite' => ['type' => self::BOOL, 'default' => false],
		];
		$structure->getters = [
			'title' => true,
			'description' => true,
            'display_mode_options' => true,
		];

        $structure->options = [
            'delete_awards' => false,
        ];

		$structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'af_as_award_cat_title.', '$award_category_id']
                ]
            ],
            'MasterDescription' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'af_as_award_cat_desc.', '$award_category_id']
                ]
            ],
			'Awards' => [
				'entity' 		=> 'AddonFlare\AwardSystem:Award',
				'type' 			=> self::TO_MANY,
				'conditions' 	=> [
					['award_category_id', '=', '$award_category_id']
				]
			],
		];

		return $structure;
	}
}