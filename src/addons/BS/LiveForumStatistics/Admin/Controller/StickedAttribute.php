<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class StickedAttribute extends AbstractController
{
    public function actionIndex()
    {
        $page = $this->filterPage();
        $perPage = 20;

        $attributeFinder = $this->getStickedAttributeRepo()
            ->findAttributesForList()
            ->limitByPage($page, $perPage);

        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);
        if (strlen($filter['text']))
        {
            $attributeFinder->whereOr([
                ['MasterTitle.phrase_text', 'LIKE', $attributeFinder->escapeLike($filter['text'], $filter['prefix'] ? '?%' : '%?%')]
            ]);
        }

        $viewParams = [
            'attributes' => $attributeFinder->fetch(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $attributeFinder->total()
        ];

        return $this->view('BS\LiveForumStatistics:StickedAttribute\List', 'lfs_sticked_attribute_list', $viewParams);
    }

    protected function linkAddEdit(\BS\LiveForumStatistics\Entity\StickedAttribute $attribute)
    {
        $viewParams = [
            'attribute' => $attribute
        ];
        return $this->view('BS\LiveForumStatistics:StickedAttribute\Edit', 'lfs_sticked_attribute_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $attribute = $this->assertStickedAttributeExists($params->attribute_id);
        return $this->linkAddEdit($attribute);
    }

    public function actionAdd()
    {
        $attribute = $this->em()->create('BS\LiveForumStatistics:StickedAttribute');
        return $this->linkAddEdit($attribute);
    }

    protected function attributeSaveProccess(\BS\LiveForumStatistics\Entity\StickedAttribute $attribute)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'attribute_key' => 'str',
            'cost_amount'  => 'float',
            'allowable' => 'array-str',
            'type' => 'str'
        ]);

        $input['allowable'] = array_filter($input['allowable']);

        $extraInput = $this->filter([
            'title' => 'str'
        ]);

        $form->validate(function (FormAction $form) use ($extraInput)
        {
            if ($extraInput['title'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->basicEntitySave($attribute, $input);

        $form->apply(function() use ($extraInput, $attribute)
        {
            $title = $attribute->getMasterTitlePhrase();
            $title->phrase_text = $extraInput['title'];
            $title->save();
        });

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->attribute_id)
        {
            $attribute = $this->assertStickedAttributeExists($params->attribute_id);
        }
        else
        {
            $attribute = $this->em()->create('BS\LiveForumStatistics:StickedAttribute');
        }

        $this->attributeSaveProccess($attribute)->run();

        return $this->redirect($this->buildLink('lfs/sticked-attributes')  . $this->buildLinkHash($attribute->attribute_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $attribute = $this->assertStickedAttributeExists($params->attribute_id);

        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $attribute,
            $this->buildLink('lfs/sticked-attributes/delete', $attribute),
            $this->buildLink('lfs/sticked-attributes/edit', $attribute),
            $this->buildLink('lfs/sticked-attributes'),
            $attribute->title
        );
    }

    /** @return \BS\LiveForumStatistics\Entity\StickedAttribute */
    protected function assertStickedAttributeExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\LiveForumStatistics:StickedAttribute', $id, $with, $phraseKey);
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\StickedAttribute
     */
    protected function getStickedAttributeRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedAttribute');
    }
}