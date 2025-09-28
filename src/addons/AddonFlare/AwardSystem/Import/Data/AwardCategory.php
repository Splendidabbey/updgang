<?php

namespace AddonFlare\AwardSystem\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class AwardCategory extends AbstractEmulatedData
{
    protected $title = null;
    protected $description = null;

    public function getImportType()
    {
        return 'category';
    }

    public function getEntityShortName()
    {
        return 'AddonFlare\AwardSystem:AwardCategory';
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setDisplayMode($displayMode)
    {
        if (!in_array($displayMode, ['visible', 'step', 'hidden']))
        {
            $displayMode = 'visible';
        }

        $this->display_mode = $displayMode;
    }

    protected function preSave($oldId)
    {
        if ($this->title === null)
        {
            throw new \LogicException("Must call setTitle with a non-null value to save a category");
        }
    }

    protected function postSave($oldId, $newId)
    {
        $existingPhrases = $this->em()->getFinder('XF:Phrase')
            ->where('title', ['af_as_award_cat_title.' . $newId, 'af_as_award_cat_desc.' . $newId])
            ->where('language_id', 0)
            ->fetch();

        foreach ($existingPhrases as $phrase)
        {
            $phrase->delete();
        }

        $this->insertMasterPhrase('af_as_award_cat_title.' . $newId, $this->title);
        $this->insertMasterPhrase('af_as_award_cat_desc.' . $newId, $this->description);
    }
}