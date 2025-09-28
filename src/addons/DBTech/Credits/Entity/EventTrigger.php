<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $event_trigger_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property string $callback_class
 * @property int $multiplier
 * @property string $multiplier_label
 * @property bool $multiplier_popup
 * @property string $parent
 * @property string $category
 * @property bool $global
 * @property bool $revert
 * @property bool $cancel
 * @property bool $rebuild
 * @property bool $charge
 * @property bool $usergroups
 * @property int $currency
 * @property string $referformat
 * @property bool $outbound
 * @property bool $inbound
 * @property float $value
 * @property array $settings
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Event[] $Events
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Transaction[] $Transactions
 */
class EventTrigger extends Entity
{
	/**
	 * @param bool $throw
	 *
	 * @return \DBTech\Credits\EventTrigger\AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(bool $throw = true): ?\DBTech\Credits\EventTrigger\AbstractHandler
	{
		return Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler($this->event_trigger_id, $throw)
		;
	}

	/**
	 * Undocumented function
	 *
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * Undocumented function
	 *
	 * @return bool
	 */
	public function hasEvents(): bool
	{
		return $this->Events->count() > 0;
	}

	/**
	 * Undocumented function
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function canRebuild(): bool
	{
		if (!\XF::$debugMode && !$this->active)
		{
			// Inactive event trigger
			return false;
		}

		if (!$this->rebuild)
		{
			// This event trigger cannot be rebuilt
			return false;
		}

		if (!$this->getHandler(false))
		{
			// This event trigger cannot be rebuilt
			return false;
		}

		return true;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getSetting(string $key)
	{
		if (!isset($this->settings[$key]))
		{
			$defaultSettings = $this->getDefaultSettings();
			if (!isset($defaultSettings[$key]))
			{
				throw new \LogicException("Attempting to access setting key '$key' which doesn't exist.");
			}

			return $defaultSettings[$key];
		}

		return $this->settings[$key];
	}

	/**
	 * @return array
	 */
	public function getDefaultSettings(): array
	{
		return [];
	}

	/**
	 * Undocumented function
	 *
	 * @param string|array &$label [description]
	 * @param bool|string $fieldName [description]
	 * @param array $columnOptions [description]
	 *
	 * @return bool
	 */
	protected function verifyMultiplierLabel(&$label, bool $fieldName = false, array $columnOptions = []): bool
	{
		if (!$label || $label == "''")
		{
			// We want to allow an empty label config
			return true;
		}

		if (!is_array($label))
		{
			$label = explode('|', $label);
		}

		if (count($label) != 2)
		{
			// Wrong label
			$this->error(\XF::phrase('dbtech_credits_multplier_match'), $fieldName);
			return false;
		}

		if (
			($label[0] && !$label[1])
			|| (!$label[0] && $label[1])
		) {
			// needs either just plural or both
			$this->error(\XF::phrase('dbtech_credits_multplier_match'), $fieldName);
			return false;
		}

		// Finally compress it
		$label = implode('|', $label);

		// Check to make sure it's not just |
		$label = $label == '|' ? '' : $label;

		return true;
	}

	/**
	 * @return bool
	 */
	protected function _preSave(): bool
	{
		switch ($this->multiplier)
		{
			case 1:
				if (!$this->multiplier_label)
				{
					// This needs to match
					$this->error(\XF::phrase('dbtech_credits_multplier_match'));
					return false;
				}
				break;

			case 2:
			case 3:
				$this->setTrusted('multiplier_label', "''");
				break;
		}

		return true;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_event_trigger';
		$structure->shortName = 'DBTech\Credits:EventTrigger';
		$structure->primaryKey = 'event_trigger_id';
		$structure->columns = [
			'event_trigger_id'	=> ['type' => self::STR, 'required' => true,
				'unique' => 'dbtech_credits_event_trigger_exists'
			],
			'title' 			=> ['type' => self::STR, 'required' => true],
			'description' 		=> ['type' => self::STR, 'default' => ''],
			'active' 			=> ['type' => self::BOOL, 'default' => true],
			'callback_class' 	=> ['type' => self::STR, 'required' => true],
			'multiplier' 		=> ['type' => self::UINT, 'required' => true],
			'multiplier_label' 	=> ['type' => self::STR, 'default' => ''],
			'multiplier_popup'	=> ['type' => self::BOOL, 'default' => false],
			'parent' 			=> ['type' => self::STR, 'default' => ''],
			'category' 			=> ['type' => self::STR, 'required' => true],
			'global' 			=> ['type' => self::BOOL, 'default' => true],
			'revert' 			=> ['type' => self::BOOL, 'default' => false],
			'cancel' 			=> ['type' => self::BOOL, 'default' => false],
			'rebuild' 			=> ['type' => self::BOOL, 'default' => false],
			'charge' 			=> ['type' => self::BOOL, 'default' => true],
			'usergroups' 		=> ['type' => self::BOOL, 'default' => true],
			'currency' 			=> ['type' => self::UINT, 'default' => 0],
			'referformat' 		=> ['type' => self::STR, 'default' => ''],
			'outbound' 			=> ['type' => self::BOOL, 'default' => true],
			'inbound' 			=> ['type' => self::BOOL, 'default' => true],
			'value' 			=> ['type' => self::FLOAT, 'default' => 1.0],
			'settings' 			=> ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->relations = [
			'Events' => [
				'entity' => 'DBTech\Credits:Event',
				'type' => self::TO_MANY,
				'conditions' => 'event_trigger_id',
				'cascadeDelete' => true
			],
			'Transactions' => [
				'entity' => 'DBTech\Credits:Transaction',
				'type' => self::TO_MANY,
				'conditions' => 'event_trigger_id',
				'cascadeDelete' => true
			]
		];

		return $structure;
	}

	/**
	 *
	 */
	protected function _setupDefaults()
	{
		$this->active = true;
		$this->multiplier = 0;
		$this->multiplier_popup = false;
		$this->global = true;
		$this->revert = false;
		$this->cancel = false;
		$this->rebuild = false;
		$this->charge = true;
		$this->usergroups = true;
		$this->outbound = true;
		$this->inbound = true;
		$this->value = 1.0;
		$this->settings	= $this->getDefaultSettings();
	}
}