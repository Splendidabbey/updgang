<?php

namespace AddonFlare\AwardSystem\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

use XF\Util\File;

class Award extends AbstractEmulatedData
{
    protected $title = null;
    protected $description = null;
    protected $imagePath = null;

    protected $existingData = [];

    public function getImportType()
    {
        return 'award';
    }

    public function getEntityShortName()
    {
        return 'AddonFlare\AwardSystem:Award';
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setImagePath($imagePath)
    {
        $this->imagePath = $imagePath;
    }

    public function setExistingData($existingData)
    {
        $this->existingData = $existingData;
    }

    protected function preSave($oldId)
    {
        if ($this->title === null)
        {
            throw new \LogicException("Must call setTitle with a non-null value to save an award");
        }
    }

    protected function getIconDataFromFile($fileName, $awardId)
    {
        $data = [];

        $ext = $path = '';

        if (!empty($this->existingData['is_svg']))
        {
            $ext = 'svg';
        }
        else
        {
            if ($imageInfo = @getimagesize($fileName))
            {
                $ext = strtolower(image_type_to_extension($imageInfo[2], false));
            }
        }

        if ($ext)
        {
            $path = sprintf('data://addonflare/awardsystem/icons/%d.%s',
                $awardId,
                $ext
            );
        }

        $data['ext'] = $ext;
        $data['path'] = $path;

        return $data;
    }

    protected function postSave($oldId, $newId)
    {
        $existingPhrases = $this->em()->getFinder('XF:Phrase')
            ->where('title', ['af_as_award_title.' . $newId, 'af_as_award_desc.' . $newId])
            ->where('language_id', 0)
            ->fetch();

        foreach ($existingPhrases as $phrase)
        {
            $phrase->delete();
        }

        $this->insertMasterPhrase('af_as_award_title.' . $newId, $this->title);
        $this->insertMasterPhrase('af_as_award_desc.' . $newId, $this->description);

        // upload image
        if ($path = $this->imagePath)
        {
            if (File::abstractedPathExists($path))
            {
                if ($tempFile = File::copyAbstractedPathToTempFile($path))
                {
                    $iconData = $this->getIconDataFromFile($tempFile, $newId);
                    if ($iconData['ext'])
                    {
                        File::copyFileToAbstractedPath($tempFile, $iconData['path']);

                        $this->db()->update('xf_af_as_award', [
                            'award_icon_ext'  => $iconData['ext'],
                            'award_icon_date' => $iconData['ext'] ? $this->award_icon_date : 0, // remove the icon date if we couldnt upload
                        ], 'award_id = ?', $newId);
                    }
                }
            }
        }
    }
}