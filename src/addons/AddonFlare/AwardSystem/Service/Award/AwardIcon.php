<?php

namespace AddonFlare\AwardSystem\Service\Award;

use AddonFlare\AwardSystem\Entity\Award;

class AwardIcon extends \XF\Service\AbstractService
{
    /**
     * @var \AddonFlare\AwardSystem\Entity\Award
     */
    protected $award;

    protected $logIp = false;

    protected $fileName;

    protected $width;

    protected $height;

    protected $type;

    public $extension;

    protected $error = null;

    protected $allowedTypes = [];


    public function __construct(\XF\App $app, Award $award)
    {
        parent::__construct($app);
        $this->award = $award;
    }

    public function getAward()
    {
        return $this->award;
    }

    public function logIp($logIp)
    {
        $this->logIp = $logIp;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setImage($fileName)
    {
        if (!$this->validateImageAsIcon($fileName, $error))
        {
            $this->error = $error;
            $this->fileName = null;
            return false;
        }

        $this->fileName = $fileName;
        return true;
    }

    public function setImageFromUpload(\XF\Http\Upload $upload)
    {
        $upload->requireImage();

        if (!$upload->isValid($errors))
        {
            $this->error = reset($errors);
            return false;
        }

        $this->deleteAwardIconFiles();
        $this->extension = $upload->getExtension();

        return $this->setImage($upload->getTempFile());
    }

    public function validateImageAsIcon($fileName, &$error = null)
    {
        $error = null;

        if (!file_exists($fileName))
        {
            throw new \InvalidArgumentException("Invalid file '$fileName' passed to award icon service");
        }
        if (!is_readable($fileName))
        {
            throw new \InvalidArgumentException("'$fileName' passed to award icon service is not readable");
        }

        $imageInfo = filesize($fileName) ? getimagesize($fileName) : false;
        if (!$imageInfo)
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $type = $imageInfo[2];
        /*if (!in_array($type, $this->allowedTypes))
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }*/

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        $this->width = $width;
        $this->height = $height;
        $this->type = $type;

        return true;
    }

    public function updateAwardIcon()
    {
        if (!$this->fileName)
        {
            throw new \LogicException("No source file for award icon set");
        }

        $outputFile = $this->fileName;

        if (!$outputFile)
        {
            throw new \RuntimeException("Failed to save image to temporary file; check internal_data/data permissions");
        }

        $dataFile = $this->award->getAbstractedAwardIconPath($this->extension);
        \XF\Util\File::copyFileToAbstractedPath($outputFile, $dataFile);

        $this->award->award_icon_ext = $this->extension;
        $this->award->award_icon_date = \XF::$time;
        $this->award->save();

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        return true;
    }

    public function deleteAwardIcon()
    {
        $this->deleteAwardIconFiles();

        if ($this->award && !$this->award->isDeleted())
        {
            $this->award->award_icon_ext = '';
            $this->award->award_icon_date = 0;
            $this->award->save();
        }

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('delete', $ip);
        }

        return true;
    }

    public function deleteAwardIconForResourceDelete()
    {
        $this->deleteAwardIconFiles();

        return true;
    }

    protected function deleteAwardIconFiles()
    {
        $award_ext = $this->award->award_icon_ext;

        if ($this->award->award_icon_date)
        {
            \XF\Util\File::deleteFromAbstractedPath($this->award->getAbstractedAwardIconPath($award_ext));
        }
    }

    protected function writeIpLog($action, $ip)
    {
        $award = $this->award;

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'af_awardSystem', $award->award_id, 'icon_' . $action);
    }
}