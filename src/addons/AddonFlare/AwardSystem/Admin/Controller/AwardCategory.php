<?php

namespace AddonFlare\AwardSystem\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class AwardCategory extends \XF\Admin\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('af_as');
    }

	public function actionIndex()
	{
		return $this->redirectPermanently($this->buildLink('award-system/manager'));
	}

	public function awardCategoryAddEdit(\AddonFlare\AwardSystem\Entity\AwardCategory $awardCategory)
	{
		$this->setSectionContext('af_as_award_manager');

		$viewParams = [
			'awardCategory' => $awardCategory
		];
		return $this->view('AddonFlare\AwardSystem:AwardCategory\Edit', 'af_as_award_category_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$awardCategory = $this->assertAwardCategoryExists($params['award_category_id']);
		return $this->awardCategoryAddEdit($awardCategory);
	}

	public function actionAdd()
	{
		$awardCategory = $this->em()->create('AddonFlare\AwardSystem:AwardCategory');
		return $this->awardCategoryAddEdit($awardCategory);
	}


	protected function awardCategorySaveProcess(\AddonFlare\AwardSystem\Entity\AwardCategory $awardCategory)
	{
		$entityInput = $this->filter([
			'display_order' => 'uint',
            'display_mode'  => 'str',
            'overwrite'  => 'uint'
		]);

        $form = $this->formAction();

        $form->basicEntitySave($awardCategory, $entityInput);

        $extraInput = $this->filter([
            'title'       => 'str',
            'description' => 'str'
        ]);

        $form->validate(function(FormAction $form) use ($extraInput)
        {
            if (!$extraInput['title'])
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->apply(function() use ($extraInput, $awardCategory)
        {
            $title = $awardCategory->getMasterTitlePhrase();
            $title->phrase_text = $extraInput['title'];
            $title->save();

            $description = $awardCategory->getMasterDescriptionPhrase();
            $description->phrase_text = $extraInput['description'];
            $description->save();
        });

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['award_category_id'])
		{
			$awardCategory = $this->assertAwardCategoryExists($params['award_category_id']);
		}
		else
		{
			$awardCategory = $this->em()->create('AddonFlare\AwardSystem:AwardCategory');
		}

		$this->awardCategorySaveProcess($awardCategory)->run();

		return $this->redirect($this->buildLink('award-system/manager'));
	}


	public function actionDelete(ParameterBag $params)
	{
		$awardCategory = $this->assertAwardCategoryExists($params['award_category_id']);

		if ($this->isPost())
		{
            $awardCategory->setOption('delete_awards', $this->filter('delete_awards', 'bool'));
			$awardCategory->delete();

			return $this->redirect($this->buildLink('award-system/manager'));
		}
		else
		{
			$viewParams = [
				'awardCategory' => $awardCategory
			];
			return $this->view('AddonFlare\AwardSystem:AwardCategory\Delete', 'af_as_award_category_delete', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \AddonFlare\AwardSystem\Entity\AwardCategory
	 */
	protected function assertAwardCategoryExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('AddonFlare\AwardSystem:AwardCategory', $id, $with, $phraseKey);
	}

	/**
	 * @return \AddonFlare\AwardSystem\Repository\AwardCategory
	 */
	protected function getAwardCategoryRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:AwardCategory');
	}
}