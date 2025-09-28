<?php

namespace XFMG\Service\Album;

use XF\Service\AbstractService;

class Creator extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFMG\XF\Entity\User
	 */
	protected $user;

	/**
	 * @var \XFMG\Entity\Album
	 */
	protected $album;

	/**
	 * @var Preparer
	 */
	protected $albumPreparer;

	/**
	 * @var SharedUserManager
	 */
	protected $viewShareManager;

	/**
	 * @var SharedUserManager
	 */
	protected $addShareManager;

	protected $logIp = true;

	public function __construct(\XF\App $app)
	{
		parent::__construct($app);
		$this->setAlbum();
	}

	protected function setAlbum()
	{
		$this->album = $this->em()->create('XFMG:Album');
		$this->albumPreparer = $this->service('XFMG:Album\Preparer', $this->album);

		$this->setUser(\XF::visitor());
		$this->setAlbumDefaults();

		$this->viewShareManager = $this->service('XFMG:Album\SharedUserManager', $this->album, null, 'view');
		$this->addShareManager = $this->service('XFMG:Album\SharedUserManager', $this->album, null, 'add');
	}

	public function setUser(\XF\Entity\User $user)
	{
		$this->user = $user;

		$this->album->user_id = $user->user_id;
		$this->album->username = $user->username;
	}

	public function getAlbum()
	{
		return $this->album;
	}

	public function getAlbumPreparer()
	{
		return $this->albumPreparer;
	}

	public function logIp($logIp)
	{
		$this->albumPreparer->logIp($logIp);
	}

	protected function setAlbumDefaults()
	{
		$this->album->album_state = 'visible';
	}

	public function setTitle($title, $description = '')
	{
		$this->album->title = $title;

		if ($description)
		{
			$this->albumPreparer->setDescription($description);
		}
	}

	public function setCategory(\XFMG\Entity\Category $category)
	{
		$this->album->category_id = $category->category_id;

		$this->setViewPrivacy('inherit');
	}

	public function setAddPrivacy($value, $addUsers = null, $usersById = false)
	{
		$this->album->add_privacy = $value;
		$this->addShareManager->resetUsers();

		if ($value === 'shared' && $addUsers)
		{
			$this->includeAddPrivacyUsers($addUsers, $usersById);
		}
	}

	public function includeAddPrivacyUsers($users, $usersById = false)
	{
		if ($this->album->add_privacy != 'shared')
		{
			throw new \LogicException("Only applies when add_privacy is shared");
		}

		if ($usersById)
		{
			$this->addShareManager->addUsersById($users);
		}
		else
		{
			$this->addShareManager->addUsersByName($users);
		}
	}

	public function setViewPrivacy($value, $viewUsers = null, $usersById = false)
	{
		$this->album->view_privacy = $value;
		$this->viewShareManager->resetUsers();

		if ($value === 'shared' && $viewUsers)
		{
			$this->includeViewPrivacyUsers($viewUsers, $usersById);
		}
	}

	public function includeViewPrivacyUsers($users, $usersById = false)
	{
		if ($this->album->view_privacy != 'shared')
		{
			throw new \LogicException("Only applies when view_privacy is shared");
		}

		if ($usersById)
		{
			$this->viewShareManager->addUsersById($users);
		}
		else
		{
			$this->viewShareManager->addUsersByName($users);
		}
	}

	public function checkForSpam()
	{
		if ($this->album->album_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->albumPreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
		$this->album->create_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$album = $this->album;
		$album->preSave();

		return $album->getErrors();
	}

	protected function _save()
	{
		$album = $this->album;

		$db = $this->db();
		$db->beginTransaction();

		$album->save();

		$album->fastUpdate('view_users', $this->viewShareManager->saveSharedUsers());
		$this->viewShareManager->notifyUsers();

		$album->fastUpdate('add_users', $this->addShareManager->saveSharedUsers());
		$this->addShareManager->notifyUsers();

		$this->albumPreparer->afterInsert();

		$db->commit();

		return $album;
	}

	public function sendNotifications()
	{
		/** @var \XFMG\Service\Album\Notifier $notifier */
		$notifier = $this->service('XFMG:Album\Notifier', $this->album);
		$notifier->setMentionedUserIds($this->albumPreparer->getMentionedUserIds());
		$notifier->notifyAndEnqueue(3);
	}
}