<?php

namespace XFRM\Service\ResourceVersion;

use XF\Entity\User;
use XFRM\Entity\ResourceVersion;

class Create extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var ResourceVersion
	 */
	protected $version;

	/**
	 * @var \XFRM\Service\ResourceVersion\Preparer
	 */
	protected $versionPreparer;

	protected $attachmentHash;

	public function __construct(
		\XF\App $app,
		\XFRM\Entity\ResourceItem $resource,
		User $teamUser = null
	)
	{
		parent::__construct($app);

		$this->resource = $resource;
		$this->version = $resource->getNewVersion($teamUser);
		$this->versionPreparer = $this->service('XFRM:ResourceVersion\Preparer', $this->version);
		$this->setVersionDefaults();
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function getVersion()
	{
		return $this->version;
	}

	protected function setVersionDefaults()
	{
		$category = $this->resource->Category;
		$this->version->version_state = $category->getNewContentState($this->resource);
	}

	public function setVersionString($versionString, $useDefault = false)
	{
		$this->versionPreparer->setVersionString($versionString, $useDefault);
	}

	public function setDefaultVersionString()
	{
		$this->versionPreparer->setDefaultVersionString();
	}

	public function setAttachmentHash($hash)
	{
		$this->versionPreparer->setAttachmentHash($hash);
	}

	public function setDownloadUrl($url)
	{
		$this->versionPreparer->setDownloadUrl($url);
	}

	protected function _validate()
	{
		$version = $this->version;

		$version->preSave();
		$errors = $version->getErrors();

		if (!$this->versionPreparer->validateFiles($error) && empty($errors['download_url']))
		{
			$errors[] = $error;
		}

		return $errors;
	}

	protected function _save()
	{
		$version = $this->version;

		$db = $this->db();
		$db->beginTransaction();

		$version->save(true, false);

		$this->versionPreparer->afterInsert();

		$db->commit();

		return $version;
	}
}