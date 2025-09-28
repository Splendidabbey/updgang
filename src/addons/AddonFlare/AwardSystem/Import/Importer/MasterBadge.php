<?php

namespace AddonFlare\AwardSystem\Import\Importer;

use XF\Import\Data\EntityEmulator;
use XF\Import\StepState;

class MasterBadge extends \XF\Import\Importer\AbstractImporter
{
    protected $db;

    public static function getListInfo()
    {
        return [
            'target' => '[AddonFlare] Awards System (XF2)',
            'source' => 'Master Badge (XF1)',
            'beta'   => false
        ];
    }

    protected function getBaseConfigDefault()
    {
        return [
            'reset_data' => false,
        ];
    }

    public function renderBaseConfigOptions(array $vars)
    {
        $vars['reset_data'] = false;

        return $this->app->templater()->renderTemplate('admin:af_as_import_config_masterbadge', $vars);
    }

    public function validateBaseConfig(array &$baseConfig, array &$errors)
    {
        // we don't have any missing fields to validate, if reset_data isn't true simply don't do it
        return true;
    }

    protected function getStepConfigDefault()
    {
        return [];
    }

    public function renderStepConfigOptions(array $vars)
    {
        return '';
    }

    public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
    {
        return true;
    }

    public function canRetainIds()
    {
        return false;
    }

    public function resetDataForRetainIds()
    {
        // no need to do anything
    }

    protected function doInitializeSource()
    {
        // just for easier access since we're importing from the same DB
        $this->db = $this->db();
    }

    public function getFinalizeJobs(array $stepsRun)
    {
        $jobs = [];

        $jobs[] = 'AddonFlare\AwardSystem:UserAwardTotalRebuild';

        return $jobs;
    }

    public function getSteps()
    {
        return [
            'categories' => [
                'title' => 'Badge Categories',
            ],
            'badges' => [
                'title' => 'Badges',
                'depends' => ['categories'],
            ],
            'userBadges' => [
                'title' => 'User Badges',
                'depends' => ['categories', 'badges'],
            ],
        ];
    }

    // ########################### STEP: Categories ###############################

    public function stepCategories(StepState $state)
    {
        if (!empty($this->baseConfig['reset_data']))
        {
            $existingCategories = $this->app->finder('AddonFlare\AwardSystem:AwardCategory')->fetch();

            foreach ($existingCategories as $existingCategory)
            {
                $existingCategory->setOption('delete_awards', true);
                $existingCategory->delete();
            }

            // delete the awards that don't belong to a category too
            $awardsWithoutCategory = $this->app->finder('AddonFlare\AwardSystem:Award')
                ->where('award_category_id', 0)
                ->fetch();

            foreach ($awardsWithoutCategory as $awardWithoutCategory)
            {
                $awardWithoutCategory->delete();
            }

            // truncate tables to reset IDs
            // we don't just use truncate because deleting the entities cleans up the phrases and image files too

            $this->db->emptyTable('xf_af_as_award_category');
            $this->db->emptyTable('xf_af_as_award');
            $this->db->emptyTable('xf_af_as_user_award');
        }

        $categories = $this->db->fetchAllKeyed('
            SELECT *
            FROM xf_badge
            ORDER BY badge_id
        ', 'badge_id');

        if (!$categories)
        {
            return $state->complete();
        }

        foreach ($categories as $oldId => $category)
        {
            $import = $this->newHandler('AddonFlare\AwardSystem:AwardCategory');

            $import->setTitle(\XF::phrase("MasterBadge_badge_{$oldId}_title"));
            $import->setDescription(''); // master badge doesn't have category descriptions
            $import->setDisplayMode($category['type']);

            $import->display_order = $category['display_order'];

            if ($newId = $import->save($oldId))
            {
                $state->imported++;
            }
        }

        return $state->complete();
    }

    // ########################### STEP: Badges ###############################

    public function getStepEndBadges()
    {
        return $this->db->fetchOne("
            SELECT MAX(trophy_id)
            FROM xf_trophy
        ") ?: 0;
    }

    public function stepBadges(StepState $state, array $stepConfig, $maxTime, $limit = 1000)
    {
        $timer = new \XF\Timer($maxTime);

        $badges = $this->db->fetchAllKeyed("
            SELECT *
            FROM xf_trophy
            WHERE
                trophy_id > ? AND trophy_id <= ?
            ORDER BY trophy_id
            LIMIT {$limit}
        ", 'trophy_id', [$state->startAfter, $state->end]);

        if (!$badges)
        {
            return $state->complete();
        }

        $this->lookup('category', $this->pluck($badges, 'badge_id'));

        foreach ($badges AS $oldId => $badge)
        {
            $state->startAfter = $oldId;

            $import = $this->newHandler('AddonFlare\AwardSystem:Award');
            $import->setExistingData($badge);

            $import->award_category_id = $this->lookupId('category', $badge['badge_id'], 0);

            $import->setTitle(\XF::phrase("trophy_title.{$oldId}"));
            $import->setDescription(\XF::phrase("trophy_description.{$oldId}"));

            $import->award_icon_ext = ''; // updater after image transfer / copy
            $import->award_icon_date = $badge['icon_date'];
            $import->display_order = $badge['trophy_order'];
            // Master Badge versions < 2.1.4 don't have this column so default it to 1
            $import->can_feature = isset($badge['allow_featured']) ? $badge['allow_featured'] : 1;
            $import->award_trophy_id = 0;
            $import->award_points = $badge['trophy_points'];

            if (!empty($badge['user_criteria']))
            {
                $import->setDirect('user_criteria', $badge['user_criteria']);
            }

            if ($import->award_icon_date)
            {
                $group = floor($oldId / 1000);

                $path = sprintf('data://trophies/%s/%d_%d_%s.png', $group, $oldId, $import->award_icon_date, 'l'); // get the biggest one

                $import->setImagePath($path);
            }

            if ($newId = $import->save($oldId))
            {
                $state->imported++;
            }

            if ($timer->limitExceeded())
            {
                break;
            }
        }

        return $state->resumeIfNeeded();
    }

    // ########################### STEP: User Badges ###############################

    public function getStepEndUserBadges()
    {
        return $this->db->fetchOne("
            SELECT MAX(user_id)
            FROM xf_user_trophy
        ") ?: 0;
    }

    public function stepUserBadges(StepState $state, array $stepConfig, $maxTime, $limit = 1000)
    {
        $timer = new \XF\Timer($maxTime);

        $userBadges = $this->db->fetchAll("
            SELECT usertrophy.*, user.favorite_badge
            FROM xf_user_trophy usertrophy
            INNER JOIN xf_user user ON (user.user_id = usertrophy.user_id)
            WHERE
                usertrophy.user_id > ? AND usertrophy.user_id <= ?
            ORDER BY usertrophy.user_id, usertrophy.award_date
            LIMIT {$limit}
        ", [$state->startAfter, $state->end]);

        if (!$userBadges)
        {
            return $state->complete();
        }

        $this->lookup('award', $this->pluck($userBadges, 'trophy_id'));

        $featuredBadgesCache = [];

        foreach ($userBadges AS $userBadge)
        {
            $userId = $userBadge['user_id'];
            $state->startAfter = $userId;

            $import = $this->newHandler('AddonFlare\AwardSystem:UserAward');

            if (!$import->award_id = $this->lookupId('award', $userBadge['trophy_id'], 0))
            {
                // shouldn't get here, but incase
                continue;
            }

            if (!isset($featuredBadgesCache[$userId]))
            {
                $featuredBadges = @unserialize($userBadge['favorite_badge']);
                if (!is_array($featuredBadges))
                {
                    $featuredBadges = [];
                }
                else
                {
                    // for easier lookup
                    // array_values is used to be sure the display order counts upn (even tho it should)
                    // key will work as the display order
                    // key => award_id TO award_id => key
                    $featuredBadges = array_flip(array_values($featuredBadges));
                }
                // save for later use
                $featuredBadgesCache[$userId] = $featuredBadges;
            }
            else
            {
                $featuredBadges = $featuredBadgesCache[$userId];
            }

            // check if this badge was featured by the user
            if (isset($featuredBadges[$userBadge['trophy_id']]))
            {
                $import->is_featured = 1;
                $import->display_order = $featuredBadges[$userBadge['trophy_id']];
            }

            $import->bulkSet([
                'user_id' => $userId,
                'recommended_user_id' => $userId, // this feature didn't exist, so make it the same user
                'award_reason'   => 'N/A',
                'date_received'  => $userBadge['award_date'],
                'date_requested' => $userBadge['award_date'],
                'status'         => 'approved',
            ]);

            if ($newId = $import->save(false))
            {
                $state->imported++;
            }

            if ($timer->limitExceeded())
            {
                // don't check for startAfter since it's a multi-key table
                // break;
            }
        }

        return $state; // don't check for startAfter since it's a multi-key table
    }
}