<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Edit extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	/**
	 * @var \XFRM\Service\ResourceUpdate\Edit|null
	 */
	protected $descriptionEditor;

	/**
	 * @var \XF\Service\Tag\Changer
	 */
	protected $tagChanger;

	protected $performValidations = true;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;

		$this->tagChanger = $this->service('XF:Tag\Changer', 'resource', $resource);
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool)$perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setTitle($title)
	{
		$this->resource->title = $title;
		$this->getDescriptionEditor()->setTitle($title);
	}

	public function setPrefix($prefixId)
	{
		$this->resource->prefix_id = $prefixId;
	}

	public function setCustomFields(array $customFields, $subsetUpdate = false)
	{
		$resource = $this->resource;

		$editMode = $resource->getFieldEditMode();

		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $resource->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, $editMode)
			->filterOnly($resource->Category->field_cache);

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($subsetUpdate)
		{
			// only updating the values passed through, so remove anything not present
			foreach ($customFieldsShown AS $k => $fieldName)
			{
				if (!isset($customFields[$fieldName]))
				{
					unset($customFieldsShown[$k]);
				}
			}
		}

		if ($customFieldsShown)
		{
			$fieldSet->bulkSet($customFields, $customFieldsShown, $editMode);
		}
	}

	public function setExternalPurchaseData($price, $currency, $url = null)
	{
		if (!$this->resource->isExternalPurchasable())
		{
			throw new \LogicException("Resource is not the external_purchase type, so can't have external purchase data");
		}

		$this->resource->bulkSet([
			'price' => $price,
			'currency' => $currency
		]);

		if ($url !== null)
		{
			$this->resource->external_purchase_url = $url;
		}
	}

	public function setExternalDownloadUrl($url)
	{
		if (!$this->resource->isExternalDownload())
		{
			throw new \LogicException("Resource is not an external download so can't change data");
		}

		$currentVersion = $this->resource->CurrentVersion;
		if ($currentVersion)
		{
			if ($url)
			{
				$currentVersion->download_url = $url;
				$this->resource->addCascadedSave($currentVersion);
			}
			else
			{
				$this->resource->error(\XF::phrase('please_enter_valid_url'), 'download_url');
			}
		}
	}

	public function setVersionString($versionString)
	{
		$currentVersion = $this->resource->CurrentVersion;
		if ($currentVersion)
		{
			$currentVersion->version_string = $versionString;
			$this->resource->addCascadedSave($currentVersion);
		}
	}

	public function setTags($tags)
	{
		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->setEditableTags($tags);
		}
	}

	public function addTags($tags)
	{
		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->addTags($tags);
		}
	}

	public function removeTags($tags)
	{
		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->removeTags($tags);
		}
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	/**
	 * @return \XFRM\Service\ResourceUpdate\Edit
	 */
	public function getDescriptionEditor()
	{
		if (!$this->descriptionEditor)
		{
			$this->descriptionEditor = $this->service('XFRM:ResourceUpdate\Edit', $this->resource->Description);
		}

		return $this->descriptionEditor;
	}

	public function checkForSpam()
	{
		// if we don't have this, then nothing has changed in the body
		if ($this->descriptionEditor)
		{
			$this->descriptionEditor->checkForSpam();
		}
	}

	protected function finalSetup()
	{
	}

	protected function _validate()
	{
		$this->finalSetup();

		$resource = $this->resource;

		$resource->preSave();
		$errors = $resource->getErrors();

		if ($this->descriptionEditor && !$this->descriptionEditor->validate($childErrors))
		{
			$errors = array_merge($errors, $childErrors);
		}

		if ($this->performValidations)
		{
			if (!$resource->prefix_id
				&& $resource->Category->require_prefix
				&& $resource->Category->getUsablePrefixes()
				&& !$resource->canMove()
			)
			{
				$errors[] = \XF::phraseDeferred('please_select_a_prefix');
			}

			// the canMove check allows moderators to bypass this requirement when editing; they're likely editing
			// another user's thread so don't force them to add a prefix

			if ($this->tagChanger->canEdit() && $this->tagChanger->tagsChanged())
			{
				$tagErrors = $this->tagChanger->getErrors();
				if ($tagErrors)
				{
					$errors = array_merge($errors, $tagErrors);
				}
			}
		}

		return $errors;
	}

	protected function _save()
	{
		$resource = $this->resource;

		$resource->save(true, false);

		if ($this->descriptionEditor)
		{
			$this->descriptionEditor->save();
		}

		if ($this->tagChanger->canEdit() && $this->tagChanger->tagsChanged())
		{
			$this->tagChanger->save($this->performValidations);
		}

		if ($resource->isVisible() && $this->alert && $resource->user_id != \XF::visitor()->user_id)
		{
			/** @var \XFRM\Repository\ResourceItem $resourceRepo */
			$resourceRepo = $this->repository('XFRM:ResourceItem');
			$resourceRepo->sendModeratorActionAlert($this->resource, 'edit', $this->alertReason);
		}

		return $resource;
	}
}