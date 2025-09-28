<?php

namespace AddonFlare\AwardSystem\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\ArrayCollection;

class User extends XFCP_User
{
    public function canManageAwards()
    {
        return $this->isMemberOf($this->app()->options()->af_as_ugs_manage);
    }

    public function canShowFeaturedAwards()
    {
        $ugs = $this->app()->options()->af_as_ugs_can_show_featured;

        return $this->isMemberOf($ugs);
    }

    public function canManuallyFeatureAwards()
    {
        $ugs = $this->app()->options()->af_as_ugs_can_manually_feature;
        $notUgs = $this->app()->options()->af_as_ugs_can_not_manually_feature;

        return ($this->isMemberOf($ugs) && !$this->isMemberOf($notUgs));
    }

    public function canViewAwardsUserProfile()
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('general', 'viewProfile'))
        {
            return false;
        }

        if ($visitor->canBypassUserPrivacy())
        {
            return true;
        }

        return (
            $this->isPrivacyCheckMet('af_as_allow_view_profile', $visitor)
        );
    }

    public function showAwardLevel()
    {
        $options = $this->app()->options();
        return (
            $options->af_as_levels_enabled &&
            ($this->af_as_award_level || $options->af_as_levels_show_level0)
        );
    }

    public function getAwardLevelData()
    {
        $levels = $this->repository('AddonFlare\AwardSystem:UserAward')->getLevelsArr();
        // \XF::dump($levels);

        $currentLevel = $this->af_as_award_level;
        $currentPoints = $this->af_as_award_points;

        $currentLevelArr = $levels[$currentLevel];
        $nextLevelArr = $levels[$currentLevel + 1];

        $currentPointsRelative = $currentPoints - $currentLevelArr['p'];
        $nextLevelPointsRelative = $nextLevelArr['p'] - $currentLevelArr['p'];

        // \XF::dump($currentPointsRelative);
        // \XF::dump($nextLevelPointsRelative);

        $pointUntilNextLevel = $nextLevelPointsRelative - $currentPointsRelative;
        $currentLevelPercent = ($currentPointsRelative / $nextLevelPointsRelative) * 100;

        return [
            'nextLevel' => $nextLevelArr,
            'pointUntilNextLevel' => $pointUntilNextLevel,
            'currentLevelPercent' => $currentLevelPercent,
        ];
    }

    public function getMaxFeaturedAwards()
    {
        $options = $this->app()->options();

        // set default
        $max = $options->af_as_max_featured_awards;

        $currentLevel = $this->af_as_award_level;

        foreach ($options->af_as_max_featured_awards_per_level as $level => $featuredForLevel)
        {
            if ($currentLevel >= $level)
            {
                $max = $featuredForLevel;
            }

            // stop looking, since it's in order
            if ($currentLevel < $level)
            {
                break;
            }
        }

        return $max;
    }

    public function getAwardLevelColorClass()
    {
        $options = $this->app()->options();

        $class = '';

        $currentLevel = $this->af_as_award_level;

        foreach ($options->af_as_levels_colors as $level => $color)
        {
            if ($currentLevel >= $level)
            {
                $class = "afAwardLevel--style-{$level}";
            }

            // stop looking, since it's in order
            if ($currentLevel < $level)
            {
                break;
            }
        }

        return $class;
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['af_as_award_total'] = ['type' => self::UINT, 'default' => 0];

        $structure->columns['af_as_award_points'] = ['type' => self::UINT, 'default' => 0];

        $structure->getters['AwardSystemUserAwards'] = false; // prevent any possible bugs, there's already a relation cache in use

        $structure->getters['af_as_award_level'] = true;
        $structure->getters['award_level_data'] = true;
        $structure->getters['max_featured_awards'] = true;
        $structure->getters['award_level_color_class'] = true;

        $structure->relations['AwardSystemUserAwards'] =
        [
            'entity' => 'AddonFlare\AwardSystem:UserAward',
            'type' => self::TO_MANY,
            'conditions' => [
                ['user_id', '=', '$user_id'],
                ['status', '=', 'approved'],
                ['is_featured', '=', 1],
                ['Award.can_feature', '=', 1],
            ],
            'key'   => 'user_award_id',
            'with'  => ['Award'],
            'order' => 'display_order',
        ];

        return $structure;
    }

    // similar to Entity::getRelation()
    // used so individual and pre-cached calls share the same cache
    protected function getAwardSystemUserAwards()
    {
        $key = 'AwardSystemUserAwards';

        $relations = $this->_structure->relations;

        if (empty($relations[$key]))
        {
            throw new \InvalidArgumentException("Unknown relation $key");
        }

        if (!array_key_exists($key, $this->_relations))
        {
            $collection = $this->_em->getRelation($relations[$key], $this);
            $this->_relations[$key] = $collection;
        }

        return $this->_relations[$key];
    }

    public function getAFasAwardLevel()
    {
        $userAwardRepo = $this->repository('AddonFlare\AwardSystem:UserAward');

        return $userAwardRepo->getLevelFromPoints($this->af_as_award_points);
    }
}