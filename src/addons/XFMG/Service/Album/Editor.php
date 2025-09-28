<?php

namespace XFMG\Service\Album;

use XFMG\Entity\Album;

class Editor extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var Album
	 */
	protected $album;

	/**
	 * @var \XFMG\Service\Album\Preparer
	 */
	protected $albumPreparer;

	protected $alert = false;
	protected $alertReason = '';

	protected $addUsers;
	protected $viewUsers;

	public function __construct(\XF\App $app, Album $album)
	{
		parent::__construct($app);
		$this->setAlbum($album);
	}

	public function setAlbum(Album $album)
	{
		$this->album = $album;
		$this->albumPreparer = $this->service('XFMG:Album\Preparer', $this->album);
	}

	public function getAlbum()
	{
		return $this->album;
	}

	public function getAlbumPreparer()
	{
		return $this->albumPreparer;
	}

	public function setTitle($title, $description = null)
	{
		$this->album->title = $title;
		if ($description !== null)
		{
			$this->setDescription($description);
		}
	}

	public function setDescription($description)
	{
		$this->album->description = $description;
	}

	public function setAddPrivacy($value, $addUsers = null)
	{
		$album = $this->album;
		$album->add_privacy = $value;
		$this->addUsers = $addUsers;
	}

	public function setViewPrivacy($value, $viewUsers = null)
	{
		$album = $this->album;
		$album->view_privacy = $value;
		$this->viewUsers = $viewUsers;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function checkForSpam()
	{
		if ($this->album->album_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->albumPreparer->checkForSpam();
		}
	}

	protected function finalSetup() {}

	protected function _validate()
	{
		$this->finalSetup();

		$this->album->preSave();
		return $this->album->getErrors();
	}

	protected function _save()
	{
		$album = $this->album;
		$visitor = \XF::visitor();

		$db = $this->db();
		$db->beginTransaction();

		$album->save(true, false);

		if ($album->canChangePrivacy())
		{
			/** @var SharedUserManager $viewManager */
			$viewManager = $this->service('XFMG:Album\SharedUserManager', $album, $this->viewUsers, 'view');
			$album->fastUpdate('view_users', $viewManager->saveSharedUsers());
			$viewManager->notifyUsers();

			/** @var SharedUserManager $addManager */
			$addManager = $this->service('XFMG:Album\SharedUserManager', $album, $this->addUsers, 'add');
			$album->fastUpdate('add_users', $addManager->saveSharedUsers());
			$addManager->notifyUsers();
		}

		$this->albumPreparer->afterUpdate();

		if ($album->album_state == 'visible' && $this->alert && $album->user_id != $visitor->user_id)
		{
			/** @var \XFMG\Repository\Album $albumRepo */
			$albumRepo = $this->repository('XFMG:Album');
			$albumRepo->sendModeratorActionAlert($album, 'edit', $this->alertReason);
		}

		$db->commit();

		return $album;
	}
}