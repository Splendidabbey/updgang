<?php

namespace AddonFlare\AwardSystem\Entity;

use XF\Mvc\Entity\Structure;

class Award extends \XF\Mvc\Entity\Entity
{
    protected static $hasExtendedGet = null;

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
        return 'af_as_award_title.' . $this->award_id;
    }

    public function getDescriptionPhraseName()
    {
        return 'af_as_award_desc.' . $this->award_id;
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

	public function getAbstractedAwardIconPath($extension)
    {
        $awardId = $this->award_id;

        return sprintf('data://addonflare/awardsystem/icons/%d.%s',
            $awardId,
            $extension
        );
    }

    public function getIconUrl($sizeCode = null, $canonical = false)
    {
    	$app = $this->app();

        if ($this->award_icon_date)
        {
            $extension = $this->award_icon_ext;

            return $app->applyExternalDataUrl(
                "addonflare/awardsystem/icons/{$this->award_id}.{$extension}?{$this->award_icon_date}",
                $canonical
            );
        }
        else
        {
            return null;
        }
    }

    public function getTotalAwarded()
    {
       $awardFinder = $this->getUserAwardRepo()->findUserAwardsForList()
            ->where('award_id', $this->award_id)
            ->where('status', 'approved');

        return $awardFinder->total();
    }

    public function setTotalAwarded($value)
    {
        $this->_getterCache['total_awarded'] = $value;
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

        $awardService = $this->app()->service('AddonFlare\AwardSystem:Award\AwardIcon', $this);
        $awardService->deleteAwardIcon();

        $userAwards = $this->finder('AddonFlare\AwardSystem:UserAward')
        	->where('award_id', $this->award_id)->fetch();

        foreach ($userAwards as $userAward)
        {
        	$userAward->delete();
        }
    }

    protected function _postSave()
    {
        if ($this->isUpdate())
        {
            if ($this->isChanged('award_points'))
            {
                // nothing needed, this is handled in the admincp controller for now
            }
        }

        $this->rebuildAwardCache();
    }

    protected function rebuildAwardCache()
    {
        \XF::runOnce('addonFlareAwardsCacheRebuild', function()
        {
            $awardRepo = $this->repository('AddonFlare\AwardSystem:Award');

            $awardRepo->rebuildAwardCache();
        });
    }

    protected function verifyInlineCSS(&$css)
    {
        $css = trim($css);
        if (!strlen($css))
        {
            return true;
        }

        $parser = new \Less_Parser();
        try
        {
            $parser->parse('.example { ' . $css . '}')->getCss();
        }
        catch (\Exception $e)
        {
            $this->error(\XF::phrase('af_as_please_enter_valid_award_css_rules'), 'inline_css');
            return false;
        }

        return true;
    }

	public static function getStructure(Structure $structure)
	{
	    $structure->table = 'xf_af_as_award';
	    $structure->shortName = 'AddonFlare\AwardSystem:Award';
	    $structure->primaryKey = 'award_id';
	    $structure->columns = [
	        'award_id' 			=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
	        'award_icon_ext'	=> ['type' => self::STR, 'default' => ''],
            'award_icon_date'   => ['type' => self::UINT, 'default' => 0],
			'award_category_id' => ['type' => self::UINT, 'default' => 0],
            'award_trophy_id'   => ['type' => self::UINT, 'default' => 0],
            'award_admin_id'    => ['type' => self::UINT, 'default' => 0], // not used, remove in the future
			'display_order' 	=> ['type' => self::UINT, 'default' => 10],
            'award_points'      => ['type' => self::UINT, 'default' => 0],
            'can_feature'       => ['type' => self::BOOL, 'default' => true],
            'show_in_list'      => ['type' => self::BOOL, 'default' => true],
            'can_request'       => ['type' => self::BOOL, 'default' => true],
            'can_recommend'     => ['type' => self::BOOL, 'default' => true],
            'allow_multiple'    => ['type' => self::BOOL, 'default' => false],
            'inline_css'        => ['type' => self::STR, 'default' => ''],
            'user_criteria' => ['type' => self::JSON_ARRAY, 'default' => []],
	    ];

		$structure->getters = [
            'title' => true,
            'description' => true,
			'icon_url'	    => true,
            'total_awarded' => true,
		];

		$structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'af_as_award_title.', '$award_id']
                ]
            ],
            'MasterDescription' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'af_as_award_desc.', '$award_id']
                ]
            ],
			'Category' => [
				'type' => self::TO_ONE,
				'entity' => 'AddonFlare\AwardSystem:AwardCategory',
				'conditions' => 'award_category_id'
			],
            'Trophy' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:Trophy',
                'conditions' => [['trophy_id', '=', '$award_trophy_id']],
            ],
            'UserAward' => [
                'type' => self::TO_MANY,
                'entity' => 'AddonFlare\AwardSystem:UserAward',
                'conditions' => [['award_id', '=', '$award_id']],
                'key' => 'user_id', // only used to get single row
            ],
		];
		$structure->options = [
			'check_duplicate' => true
		];

	    return $structure;
	}

    public function get($key)
    {
        if (!self::$hasExtendedGet)
        {
            self::$hasExtendedGet = \XF::extendClass('\XF\Template\Templater');
        }

        $hasExtendedGet = self::$hasExtendedGet;
        return parent::get($hasExtendedGet::getAfAwards($key));
    }

	/**
	 * @return \AddonFlare\AwardSystem\Repository\Award
	 */
	protected function getAwardRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:Award');
	}

    /**
     * @return \AddonFlare\AwardSystem\Repository\UserAward
     */
    protected function getUserAwardRepo()
    {
        return $this->repository('AddonFlare\AwardSystem:UserAward');
    }
}