<?php

namespace AddonFlare\AwardSystem\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XF\MvC\Entity\ArrayCollection;

class AwardStatus extends \XF\Admin\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('af_as');
    }

    public function actionIndex()
    {
        return $this->rerouteController(__CLASS__, 'list');
    }

    public function actionList()
    {
        $userAwardRepo = $this->getUserAwardRepo();

        $status = $this->filter('status', 'str');
        $actions = $userAwardRepo->getStatusActions($status);

        $this->setSectionContext("af_as_{$status}_awards");

        $page = $this->filterPage();
        $perPage = 25;

        $userAwardFinder = $userAwardRepo->findUserAwardsForList(0, $status)
            ->with('Award', true)
            ->with('User', true)
            ->with('RecommendedUser', false)
            ->with('GivenUser', false)
            ->limitByPage($page, $perPage)
            ->order('date_requested','DESC');

        $userAwards = $userAwardFinder->fetch();

        $viewParams = [
            'userAwardsTotal' => $userAwards->count(),
            'userAwards'    => $userAwards,
            'status'        => $status,
            'statusPhrase'  => \XF::phrase('af_as_user_award_status.' . $status),
            'actions'       => $actions,
            'page'          => $page,
            'perPage'       => $perPage,
            'total'         => $userAwardFinder->total(),
        ];
        return $this->view('AddonFlare\AwardSystem:Award\Listing', 'af_as_award_status_list', $viewParams);
    }

    public function actionUpdate(ParameterBag $params)
    {
        $userAwardRepo = $this->getUserAwardRepo();

        $userAwardIds = $this->filter('user_award_ids', 'array-uint');
        $userAwards = $this->finder('AddonFlare\AwardSystem:UserAward')
            ->with('Award', true)
            ->with('User', true)
            ->where('user_award_id', $userAwardIds)
            ->fetch();

        $status = $this->filter('status', 'str');

        if ($status == 'delete')
        {
            foreach ($userAwards as $userAward)
            {
                $userAward->delete();
            }
        }
        else
        {
            $approved = [];

            $db = $this->app()->db();
            $db->beginTransaction();

            foreach ($userAwards as $userAward)
            {
                $actions = $userAwardRepo->getStatusActions($userAward->status);

                if (!in_array($status, $actions))
                {
                    // don't error, just skip
                    continue;
                }

                if ($status == 'approved')
                {
                    $validRecipient = $userAwardRepo->getValidatedRecipients($userAward->Award, $userAward->User, $error);

                    if ($error)
                    {
                        throw $this->errorException($error);
                    }

                    $approved[] = $userAward;
                }

                $userAward->status = $status;
                $userAward->save(true, false);
            }

            $db->commit();

            if ($approved)
            {
                $alertRepo = $this->repository('XF:UserAlert');
                foreach ($approved as $userAward)
                {
                    $userAward->fastUpdate('date_received', \XF::$time);
                    $alertRepo->alertFromUser($userAward->User, $userAward->User, 'af_as_award', $userAward->Award->award_id, 'award');
                }
            }
        }
        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionConfirmUpdate()
    {
        $userAwardIds = $this->filter('user_award_ids', 'array-uint');
        $status = $this->filter('status', 'str');

        if (!$userAwardIds)
        {
            return $this->error(\XF::phrase('please_enter_at_least_one_choice'));
        }

        $viewParams = [
                'userAwardIds'  => $userAwardIds,
                'status'        => $status,
                'total'         => count($userAwardIds)
        ];

        return $this->view('AddonFlare\AwardSystem:UserAward\Update', 'af_as_user_award_update_list', $viewParams);
    }

    protected function userAwardAddEdit(\AddonFlare\AwardSystem\Entity\UserAward $userAward)
    {
        $award = $userAward->Award;

        $awardOptions = $this->getAwardRepo()->findAwardsForList()
            ->fetch()
            ->pluckNamed('title', 'award_id');

        $viewParams = [
            'award'     => $award,
            'userAward' => $userAward,
            'awardOptions' => $awardOptions,
        ];

        return $this->view('', 'af_as_user_award_edit', $viewParams);
        // TODO: maybe add pending as an option set when changing the status, might not be needed tho
    }

    public function actionAdd()
    {
        $this->setSectionContext('af_as_add_user_award');

        $userAward = $this->em()->create('AddonFlare\AwardSystem:UserAward');

        $userAward->award_id = $this->filter('award_id', 'uint');

        return $this->userAwardAddEdit($userAward);
    }

    // not needed for now, leave incase we use it in the future
    // public function actionEdit(ParameterBag $params)
    // {
    //     $userAward = $this->assertUserAwardExists($params->user_award_id);

    //     return $this->userAwardAddEdit($userAward);
    // }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->user_award_id)
        {
            $userAward = $this->assertUserAwardExists($params->user_award_id);
        }
        else
        {
            $userAward = $this->em()->create('AddonFlare\AwardSystem:UserAward');
            $userAward->date_received = \XF::$time;
            $userAward->date_requested = \XF::$time;
            $userAward->status = 'approved';
        }

        $this->userAwardSaveProcess($userAward)->run();

        return $this->redirect($this->buildLink('award-system/status/list', null, ['status' => 'approved']));
    }

    protected function userAwardSaveProcess(\AddonFlare\AwardSystem\Entity\UserAward $userAward)
    {
        ### We're here, saving the form data when an user award is addded ###
        $recipients = $this->filter('recipients', 'str');

        $entityInput = $this->filter([
            'award_id' => 'uint',
            'award_reason' => 'str',
        ]);

        $award = $this->assertAwardExists($entityInput['award_id']);

        $entityInput['award_id'] = $award->award_id;

        $userAwardRepo = $this->getUserAwardRepo();

        $recipients = $userAwardRepo->getValidatedRecipients($award, $recipients, $error);

        if ($error)
        {
            throw $this->errorException($error);
        }
        else if (!count($recipients))
        {
            throw $this->errorException(\XF::phrase('af_as_invalid_recipients'));
        }

        $form = $this->formAction();

        $alertRepo = $this->repository('XF:UserAlert');

        $givenUserId = \XF::visitor()->user_id;

        foreach ($recipients as $userId => $recipient)
        {
            $insertUserAward = clone $userAward;
            $form->basicEntitySave($insertUserAward, $entityInput + ['user_id' => $userId, 'recommended_user_id' => $userId, 'given_by_user_id' => $givenUserId]);

            $form->complete(function() use ($alertRepo, $insertUserAward, $award)
            {
                $alertRepo->alertFromUser($insertUserAward->User, $insertUserAward->User, 'af_as_award', $award->award_id, 'award');
            });
        }

        $form->complete(function() use ($recipients, $award)
        {
            if (!$award->allow_multiple)
            {
                $db = $this->app()->db();
                // delete any pending requests for this award for the recipients that were just awarded and don't support multiple
                $db->delete('xf_af_as_user_award',
                    "award_id = ?
                    AND status = 'pending'
                    AND user_id IN (".$db->quote($recipients->keys()).")"
                , [$award->award_id]);
            }
        });

        return $form;
    }

    protected function assertUserAwardExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('AddonFlare\AwardSystem:UserAward', $id, $with, $phraseKey);
    }
    protected function assertAwardExists($id, $with = null, $phraseKey = 'af_as_invalid_award_specified')
    {
        return $this->assertRecordExists('AddonFlare\AwardSystem:Award', $id, $with, $phraseKey);
    }

    protected function getUserAwardRepo()
    {
        return $this->repository('AddonFlare\AwardSystem:UserAward');
    }
    protected function getAwardRepo()
    {
        return $this->repository('AddonFlare\AwardSystem:Award');
    }
}