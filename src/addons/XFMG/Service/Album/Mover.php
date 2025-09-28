<?php

namespace XFMG\Service\Album;

use XFMG\Entity\Album;

class Mover extends \XF\Service\AbstractService
{
	/**
	 * @var Album
	 */
	protected $album;

	/**
	 * @var \XF\Entity\User
	 */
	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	protected $notifyWatchers = false;

	protected $redirect = false;
	protected $redirectLength = 0;

	protected $prefixId = null;

	protected $extraSetup = [];

	public function __construct(\XF\App $app, Album $album)
	{
		parent::__construct($app);
		$this->setAlbum($album);

		$this->user = \XF::visitor();
	}

	public function setAlbum(Album $album)
	{
		$this->album = $album;
	}

	public function getAlbum()
	{
		return $this->album;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function setNotifyWatchers($value = true)
	{
		$this->notifyWatchers = (bool)$value;
	}

	public function addExtraSetup(callable $extra)
	{
		$this->extraSetup[] = $extra;
	}

	public function move(\XFMG\Entity\Category $category = null)
	{
		if ($category && $category->category_type != 'album')
		{
			throw new \XF\PrintableException(\XF::phrase('xfmg_cannot_move_album_into_non_album_category'));
		}

		$categoryId = ($category ? $category->category_id : 0);

		$user = $this->user;
		$album = $this->album;
		$moved = ($album->category_id != $categoryId);

		foreach ($this->extraSetup AS $extra)
		{
			call_user_func($extra, $album, $category);
		}

		$album->category_id = $categoryId;

		if (!$album->preSave())
		{
			throw new \XF\PrintableException($album->getErrors());
		}

		$db = $this->db();
		$db->beginTransaction();

		$album->save(true, false);

		$db->commit();

		if ($moved && $album->album_state == 'visible' && $this->alert && $album->user_id != $user->user_id)
		{
			/** @var \XFMG\Repository\Album $albumRepo */
			$albumRepo = $this->repository('XFMG:Album');
			$albumRepo->sendModeratorActionAlert($album, 'move', $this->alertReason);
		}

		return $moved;
	}
}