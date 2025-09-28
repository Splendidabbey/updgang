<?php

namespace AddonFlare\AwardSystem\Import\Importer;

use XF\Import\Data\EntityEmulator;
use XF\Import\StepState;

class bdMedal extends \XF\Import\Importer\AbstractImporter
{
    protected $db;

    public static function getListInfo()
    {
        return [
            'target' => '[AddonFlare] Awards System (XF2)',
            'source' => '[bd] Medal (XF2)',
            'beta'   => false
        ];
    }

    protected function getBaseConfigDefault()
    {
        return [];
    }

    public function renderBaseConfigOptions(array $vars)
    {
        return '';
    }

    public function validateBaseConfig(array &$baseConfig, array &$errors)
    {
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
                'title' => 'Medal Categories',
            ],
            'medals' => [
                'title' => 'Medals',
                'depends' => ['categories'],
            ],
            'userMedals' => [
                'title' => 'User Medals',
                'depends' => ['categories', 'medals'],
            ],
        ];
    }

    // ########################### STEP: Categories ###############################

    public function stepCategories(StepState $state)
    {
        $categories = $this->db->fetchAllKeyed('
            SELECT *
            FROM xf_bdmedal_category
            ORDER BY display_order
        ', 'category_id');

        if (!$categories)
        {
            return $state->complete();
        }

        foreach ($categories as $oldId => $category)
        {
            $import = $this->newHandler('AddonFlare\AwardSystem:AwardCategory');

            $import->setTitle((string)$category['name']);
            $import->setDescription((string)$category['description']);

            $import->display_order = $category['display_order'];

            if ($newId = $import->save($oldId))
            {
                $state->imported++;
            }
        }

        return $state->complete();
    }

    // ########################### STEP: Medals ###############################

    public function getStepEndMedals()
    {
        return $this->db->fetchOne("
            SELECT MAX(medal_id)
            FROM xf_bdmedal_medal
        ") ?: 0;
    }

    public function stepMedals(StepState $state, array $stepConfig, $maxTime, $limit = 1000)
    {
        $timer = new \XF\Timer($maxTime);

        $medals = $this->db->fetchAllKeyed("
            SELECT *
            FROM xf_bdmedal_medal
            WHERE
                medal_id > ? AND medal_id <= ?
            ORDER BY medal_id
            LIMIT {$limit}
        ", 'medal_id', [$state->startAfter, $state->end]);

        if (!$medals)
        {
            return $state->complete();
        }

        $this->lookup('category', $this->pluck($medals, 'category_id'));

        foreach ($medals AS $oldId => $medal)
        {
            $state->startAfter = $oldId;

            $import = $this->newHandler('AddonFlare\AwardSystem:Award');
            $import->setExistingData($medal);

            $import->award_category_id = $this->lookupId('category', $medal['category_id'], 0);

            $import->setTitle((string)$medal['name']);
            $import->setDescription((string)$medal['description']);

            $import->award_icon_ext = ''; // updater after image transfer / copy
            $import->award_icon_date = $medal['image_date'];
            $import->display_order = $medal['display_order'];

            if ($import->award_icon_date)
            {
                $path = 'data://';

                if (!empty($medal['is_svg']))
                {
                    $path .= sprintf('medal/%d_%d.svg', $oldId, $import->award_icon_date);
                }
                else
                {
                    $path .= sprintf('medal/%d_%d%s.jpg', $oldId, $import->award_icon_date, 'l'); // get the biggest one
                }

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

    // ########################### STEP: User Medals ###############################

    public function getStepEndUserMedals()
    {
        return $this->db->fetchOne("
            SELECT MAX(awarded_id)
            FROM xf_bdmedal_awarded
        ") ?: 0;
    }

    public function stepUserMedals(StepState $state, array $stepConfig, $maxTime, $limit = 1000)
    {
        $timer = new \XF\Timer($maxTime);

        $userMedals = $this->db->fetchAllKeyed("
            SELECT *
            FROM xf_bdmedal_awarded
            WHERE
                awarded_id > ? AND awarded_id <= ?
            ORDER BY awarded_id
            LIMIT {$limit}
        ", 'awarded_id', [$state->startAfter, $state->end]);

        if (!$userMedals)
        {
            return $state->complete();
        }

        $this->lookup('award', $this->pluck($userMedals, 'medal_id'));

        foreach ($userMedals AS $oldId => $userMedal)
        {
            $state->startAfter = $oldId;

            $import = $this->newHandler('AddonFlare\AwardSystem:UserAward');

            if (!$import->award_id = $this->lookupId('award', $userMedal['medal_id'], 0))
            {
                // shouldn't get here, but incase
                continue;
            }

            $import->bulkSet([
                'user_id' => $userMedal['user_id'],
                'recommended_user_id' => $userMedal['user_id'], // this feature didn't exist, so make it the same user
                'award_reason'   => strip_tags($userMedal['award_reason']) ?: 'N/A', // remove HTML from reason
                'date_received'  => $userMedal['award_date'],
                'date_requested' => $userMedal['award_date'],
                'status'         => 'approved',
            ]);

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
}