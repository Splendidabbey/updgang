<?php

namespace XFRM\Service\ResourceItem;

use XF\Entity\User;
use XFRM\Entity\ResourceItem;

class CreateVersionUpdate extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var \XFRM\Service\ResourceUpdate\Create|null
	 */
	protected $updateCreator;

	/**
	 * @var \XFRM\Service\ResourceVersion\Create|null
	 */
	protected $versionCreator;

	/**
	 * @var User
	 */
	protected $teamUser;

	public function __construct(
		\XF\App $app,
		ResourceItem $resource,
		User $teamUser = null
	)
	{
		parent::__construct($app);

		$this->resource = $resource;
		$this->teamUser = $teamUser ?: \XF::visitor();
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function getTeamUser(): User
	{
		return $this->teamUser;
	}

	public function getUpdateCreator()
	{
		if (!$this->updateCreator)
		{
			$this->updateCreator = $this->service(
				'XFRM:ResourceUpdate\Create',
				$this->resource,
				$this->teamUser
			);
		}

		return $this->updateCreator;
	}

	public function hasUpdateCreator()
	{
		return $this->updateCreator ? true : false;
	}

	public function getVersionCreator()
	{
		if (!$this->versionCreator)
		{
			$this->versionCreator = $this->service(
				'XFRM:ResourceVersion\Create',
				$this->resource,
				$this->teamUser
			);
		}

		return $this->versionCreator;
	}

	public function hasVersionCreator()
	{
		return $this->versionCreator ? true : false;
	}

	public function addResourceChanges($key, $value = null)
	{
		if ($value === null && is_array($key))
		{
			$this->resource->bulkSet($key);
		}
		else
		{
			$this->resource->set($key, $value);
		}
	}

	public function checkForSpam()
	{
		if ($this->updateCreator)
		{
			$this->updateCreator->checkForSpam();
		}
	}

	protected function _validate()
	{
		$errors = [];

		if ($this->versionCreator)
		{
			if (!$this->versionCreator->validate($versionErrors))
			{
				$errors = array_merge($errors, $versionErrors);
			}
		}

		if ($this->updateCreator)
		{
			if (!$this->updateCreator->validate($updateErrors))
			{
				$errors = array_merge($errors, $updateErrors);
			}
		}

		return $errors;
	}

	protected function _save()
	{
		if ($this->versionCreator)
		{
			$this->versionCreator->save();
		}

		if ($this->updateCreator)
		{
			$this->updateCreator->save();
		}

		$this->resource->saveIfChanged();

		return $this->resource;
	}

	public function sendNotifications()
	{
		if ($this->updateCreator)
		{
			$this->updateCreator->sendNotifications();
		}
	}
}