<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class ChangeType extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var \XFRM\Entity\ResourceVersion
	 */
	protected $version;

	/**
	 * @var \XFRM\Service\ResourceVersion\Preparer
	 */
	protected $versionPreparer;

	protected $hasSetType = false;

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;

		$this->version = $resource->getNewVersion();
		$this->versionPreparer = $this->service('XFRM:ResourceVersion\Preparer', $this->version);
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function setLocalDownload($versionAttachmentHash)
	{
		$this->resource->bulkSet([
			'resource_type' => 'download',
			'price' => 0,
			'currency' => '',
			'external_purchase_url' => ''
		]);

		$this->versionPreparer->setAttachmentHash($versionAttachmentHash);

		$this->hasSetType = true;
	}

	public function setExternalDownload($downloadUrl)
	{
		$this->resource->bulkSet([
			'resource_type' => 'download',
			'price' => 0,
			'currency' => '',
			'external_purchase_url' => ''
		]);

		$this->versionPreparer->setDownloadUrl($downloadUrl);

		$this->hasSetType = true;
	}

	public function setExternalPurchasable($price, $currency, $url)
	{
		$this->resource->bulkSet([
			'resource_type' => 'external_purchase',
			'price' => $price,
			'currency' => $currency,
			'external_purchase_url' => $url
		]);

		$this->hasSetType = true;
	}

	public function setFileless()
	{
		$this->resource->bulkSet([
			'resource_type' => 'fileless',
			'price' => 0,
			'currency' => '',
			'external_purchase_url' => ''
		]);

		$this->hasSetType = true;
	}

	public function setVersionString($version, $useDefault = false)
	{
		$this->versionPreparer->setVersionString($version, $useDefault);
	}

	protected function _validate()
	{
		$resource = $this->resource;

		$resource->preSave();
		$errors = $resource->getErrors();

		if (!$this->versionPreparer->validateFiles($versionError) && empty($errors['download_url']))
		{
			$errors[] = $versionError;
		}

		if (!$this->hasSetType)
		{
			$errors[] = \XF::phrase('xfrm_please_choose_valid_type_for_this_resource');
		}

		return $errors;
	}

	protected function _save()
	{
		$resource = $this->resource;

		$this->db()->beginTransaction();

		$resource->save(true, false);
		$this->version->save(true, false);

		$this->versionPreparer->afterInsert();

		$this->db()->commit();

		return $resource;
	}
}