<?php

namespace AddonFlare\AwardSystem\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;

class Award extends \XF\Admin\Controller\AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('af_as');
	}

	public function actionIndex()
	{
		return $this->view('AddonFlare\AwardSystem:View', 'af_as_award_system');
	}

	public function actionManager()
	{
		$this->setSectionContext('af_as_award_manager');
		$awardData = $this->getAwardRepo()->getAwardListData(null, false, false);

		$viewParams = [
			'awardData' => $awardData,
		];
		return $this->view('AddonFlare\AwardSystem:Award\Listing', 'af_as_award_list', $viewParams);
	}

	public function awardAddEdit(\AddonFlare\AwardSystem\Entity\Award $award)
	{
		$userCriteria = $this->app->criteria('XF:User', $award->user_criteria);

		$this->setSectionContext('af_as_award_manager');
		$trophies = $this->getTrophyRepo()->findTrophiesForList()->fetch()->pluckNamed('title', 'trophy_id');

		$viewParams = [
			'award' => $award,
			'awardCategories' 	=> $this->getAwardCategoryRepo()->getAwardCategoryTitlePairs(),
			'trophies'			=> $trophies,
			'userCriteria' => $userCriteria,
		];

		return $this->view('AddonFlare\AwardSystem:Award\Edit', 'af_as_award_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$award = $this->assertAwardExists($params['award_id']);

		return $this->awardAddEdit($award);
	}

	public function actionAdd()
	{
		$award = $this->em()->create('AddonFlare\AwardSystem:Award');

		return $this->awardAddEdit($award);
	}

	protected function awardSaveProcess(\AddonFlare\AwardSystem\Entity\Award $award)
	{
		$entityInput = $this->filter([
			'award_category_id' => 'uint',
			'display_order' 	=> 'uint',
			'award_points'  	=> 'uint',
			'can_feature'       => 'bool',
			'show_in_list'      => 'bool',
			'can_request'       => 'bool',
			'can_recommend'     => 'bool',
			'allow_multiple'    => 'bool',
			'inline_css'        => 'str',
			'user_criteria' 	=> 'array'
		]);

        $awardService = $this->service('AddonFlare\AwardSystem:Award\AwardIcon', $award);

        $form = $this->formAction();

        $extraInput = $this->filter([
            'title'       => 'str',
            'description' => 'str',
            'copy_trophy_id' => 'uint',
        ]);

        $isUpdate = $award->isUpdate();

        $form->validate(function(FormAction $form) use ($extraInput)
        {
            if (!$extraInput['title'])
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->basicEntitySave($award, $entityInput);

        $form->setup(function() use ($award, $extraInput)
        {
        	if ($extraInput['copy_trophy_id'])
        	{
        		if ($trophy = $this->em()->find('XF:Trophy', $extraInput['copy_trophy_id']))
        		{
        			$award->user_criteria = $trophy->user_criteria;
        		}
        	}
        });

        $form->validate(function(FormAction $form) use ($awardService, $award)
        {
        	$upload = $this->request->getFile('award_icon', false, false);
        	if ($upload)
	        {
	            if (!$awardService->setImageFromUpload($upload))
	            {
	                $form->logError($awardService->getError(), 'award_icon');
	            }
	            else
	            {
	                $form->apply(function() use ($awardService)
	                {
	                    $awardService->updateAwardIcon();
	                });
	            }
	        }
	        else if($award->getIconUrl() == null)
	        {
	            $form->logError(\XF::phrase('uploaded_file_failed_not_found'), 'award_icon');
	        }
    	});

        $form->apply(function() use ($extraInput, $award)
        {
            $title = $award->getMasterTitlePhrase();
            $title->phrase_text = $extraInput['title'];
            $title->save();

            $description = $award->getMasterDescriptionPhrase();
            $description->phrase_text = $extraInput['description'];
            $description->save();
        });

        if ($isUpdate)
        {
	        $form->complete(function() use ($award)
	        {
	        	if ($award->getValue('award_points') != $award->getPreviousValue('award_points'))
	        	{
		            $this->app->jobManager()->enqueue(
		                'AddonFlare\AwardSystem:UserAwardTotalRebuild',
		                ['awardIds' => [$award->award_id]],
		                true // manual, we want to run now
		            );
	        	}
	        });
        }

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['award_id'])
		{
			$award = $this->assertAwardExists($params['award_id']);
		}
		else
		{
			$award = $this->em()->create('AddonFlare\AwardSystem:Award');
		}

		$this->awardSaveProcess($award)->run();

		return $this->redirect($this->buildLink('award-system/manager'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$award = $this->assertAwardExists($params['award_id']);

		if ($this->isPost())
		{
			$award->delete();

			return $this->redirect($this->buildLink('award-system/manager'));
		}
		else
		{
			$viewParams = [
				'award' => $award
			];

			return $this->view('AddonFlare\AwardSystem:Award\Delete', 'af_as_award_delete', $viewParams);
		}
	}

	public function actionGetAwardIcon()
	{
		$this->assertPostOnly();

		$award = $this->assertAwardExists($this->filter('id', 'uint'));

		$description = "<img src=\"{$award->icon_url}\" style=\"max-width:100px; max-height:75px;\">";

		$view = $this->view('');
		$view->setJsonParam('description', $description);

		return $view;
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \AddonFlare\AwardSystem\Entity\Award
	 */
	protected function assertAwardExists($id, $with = null, $phraseKey = 'af_as_invalid_award_specified')
	{
		return $this->assertRecordExists('AddonFlare\AwardSystem:Award', $id, $with, $phraseKey);
	}

	/**
	 * @return \AddonFlare\AwardSystem\Repository\AwardCategory
	 */
	protected function getAwardCategoryRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:AwardCategory');
	}

	/**
	 * @return \AddonFlare\AwardSystem\Repository\Award
	 */
	protected function getAwardRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:Award');
	}

	/**
	 * @return \XF\Repository\Trophy
	 */
	protected function getTrophyRepo()
	{
		return $this->repository('XF:Trophy');
	}
}