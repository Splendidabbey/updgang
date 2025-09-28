<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Entity\LinkableInterface;
use XF\Db\Schema\Alter;

/**
 * COLUMNS
 * @property int $currency_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property int $display_order
 * @property string $table
 * @property bool $use_table_prefix
 * @property string $column
 * @property bool $use_user_id
 * @property string $user_id_column
 * @property int $decimals
 * @property int $privacy
 * @property bool $blacklist
 * @property string $prefix
 * @property string $suffix
 * @property bool $is_display_currency
 * @property bool $show_amounts
 * @property bool $sidebar
 * @property bool $member_dropdown
 * @property bool $postbit
 * @property int $negative
 * @property int $maxtime
 * @property float $earnmax
 * @property float $value
 * @property bool $inbound
 * @property bool $outbound
 *
 * GETTERS
 * @property \DBTech\Credits\XF\Entity\User[]|\XF\Mvc\Entity\AbstractCollection $RichestUsers
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Event[] $Events
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Transaction[] $Transactions
 */
class Currency extends Entity implements LinkableInterface
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @param \XF\Entity\User|null $user
	 *
	 * @return bool
	 */
	public function canView(?\XF\Entity\User $user = null): bool
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$user = $user ?: $visitor;

		switch ($this->privacy)
		{
			case 0:
				if (!$visitor->canBypassDbtechCreditsCurrencyPrivacy())
				{
					return false;
				}
				break;

			case 1:
				if (!$visitor->canBypassDbtechCreditsCurrencyPrivacy() && $user->user_id != $visitor->user_id)
				{
					return false;
				}
				break;
		}

		return $this->isActive();
	}

	/**
	 * @param \XF\Entity\User|null $userInfo
	 * @param bool $format
	 *
	 * @return mixed
	 */
	public function getValueFromUser(?\XF\Entity\User $userInfo = null, bool $format = true)
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$userInfo = $userInfo ?: $visitor;

		$value = $userInfo->{$this->column};

		if ($format)
		{
			// We need to format the value
			$value = $this->getFormattedValue($value);
		}

		return $value;
	}

	/**
	 * @param float $value
	 *
	 * @return string
	 */
	public function getFormattedValue(float $value = 0.00): string
	{
		if ($value < 0 && $this->negative == 1)
		{
			// Make sure this is displaying correctly
			$value = 0;
		}

		return \XF::language()->numberFormat($value, $this->decimals);
	}

	/**
	 * @param int $limit
	 *
	 * @return \DBTech\Credits\XF\Entity\User[]|\XF\Mvc\Entity\AbstractCollection
	 */
	public function getRichestUsers(int $limit = 5)
	{
		return Helper::repository(\DBTech\Credits\Repository\Currency::class)
			->getRichestUsers($this, $limit)
			->fetch()
		;
	}

	/**
	 * @param $criteria
	 * @param string $key
	 *
	 * @return bool
	 */
	public function isCriteriaSelected($criteria, string $key): bool
	{
		return !((!is_array(
			$criteria
		) || !isset($criteria['dbtech_credits_currency_' . $this->currency_id . '_' . $key])));
	}

	/**
	 * @param $criteria
	 * @param string $key
	 *
	 * @return int
	 */
	public function getCriteriaValue($criteria, string $key): int
	{
		return (!is_array($criteria) || !isset($criteria['dbtech_credits_currency_' . $this->currency_id .'_' . $key]))
			? 0
			: $criteria['dbtech_credits_currency_' . $this->currency_id .'_' . $key]['amount'];
	}

	/**
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	public function verifyAdjustEvent(): bool
	{
		if (!$this->exists())
		{
			return false;
		}

		// Whether we need to add an adjust event
		$foundEvent = false;

		/** @var \DBTech\Credits\Entity\Event $event */
		foreach ($this->Events as $eventId => $event)
		{
			if ($event->event_trigger_id == 'adjust')
			{
				$foundEvent = true;

				if ($event->curtarget == 0)
				{
					// We need to correct this
					$event->curtarget = 1;
					$event->save();
				}
			}
		}

		if (!$foundEvent)
		{
			$event = Helper::createEntity(Event::class);
			$event->bulkSet([
					'event_trigger_id' => 'adjust',
					'curtarget' => 1,
					'currency_id' => $this->currency_id
				]);
			$event->save();
		}

		return true;
	}

	/**
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	public function verifyChargeEvent(): bool
	{
		if (!$this->exists())
		{
			return false;
		}

		// Whether we need to add a charge event
		$foundEvent = false;

		/** @var \DBTech\Credits\Entity\Event $event */
		foreach ($this->Events as $eventId => $event)
		{
			if ($event->event_trigger_id == 'content')
			{
				$foundEvent = true;
				break;
			}
		}

		if (!$foundEvent)
		{
			$event = Helper::createEntity(Event::class);
			$event->bulkSet([
					'event_trigger_id' => 'content',
					'currency_id' => $this->currency_id
				]);
			$event->save();
		}

		return true;
	}

	/**
	 * @param string $column
	 *
	 * @return bool
	 */
	protected function verifySqlSafe(string &$column): bool
	{
		if ($column === '')
		{
			// Invalid
			return false;
		}

		// Ensure this is valid
		$column = preg_replace('/[^a-zA-Z0-9_]/i', '_', preg_quote($column));

		return true;
	}

	/**
	 * @return bool
	 */
	protected function _preSave(): bool
	{
		$db = $this->db();
		$sm = $db->getSchemaManager();

		$tableName = ($this->use_table_prefix ? 'xf_' : '') . $this->table;

		if (!$sm->tableExists($tableName))
		{
			// Invalid
			$this->error(\XF::phrase('dbtech_credits_missing_table'), 'table');
			return false;
		}

		$columns = $sm->getTableColumnDefinitions($tableName);

		if (!array_key_exists($this->user_id_column, $columns))
		{
			// Invalid
			$this->error(\XF::phrase('dbtech_credits_missing_user_column'), 'table');
			return false;
		}

		if (
			$this->isInsert()
			|| $this->isChanged('column')
		) {
			// deal with desired table column
			$this->blacklist = isset($columns[$this->column]);

			if (!$this->blacklist)
			{
				// create or switch custom columns
				if (
					$this->isUpdate()
					&& $this->isChanged('column')
					&& !$this->getPreviousValue('blacklist')
				) {
					// Invalid
					$sm->alterTable($tableName, function (Alter $table) use ($columns)
					{
						if (!array_key_exists($this->getPreviousValue('column'), $columns))
						{

							// Column was either not changed
							$table->addColumn($this->column, 'decimal')
								->length('65,8')
								->unsigned(false)
								->setDefault('0')
							;
						}
						else
						{
							// Column was changed but not blacklisted so rename the column
							$table->changeColumn($this->getPreviousValue('column'), 'decimal')
								->length('65,8')
								->unsigned(false)
								->setDefault('0')
								->renameTo($this->column)
							;
						}
					});
				}
				else
				{
					$sm->alterTable($tableName, function (Alter $table)
					{
						// Column was either not changed
						$table->addColumn($this->column, 'decimal')
							->length('65,8')
							->unsigned(false)
							->setDefault('0')
						;
					});
				}
			}
			elseif (
				$this->isChanged('column')
				&& $this->isUpdate()
				&& !$this->getExistingValue('blacklist')
			) {
				$sm->alterTable($tableName, function (Alter $table)
				{
					$table->dropColumns([$this->getPreviousValue('column')]);
				});
			}
		}

		/** @var \DBTech\Credits\Entity\Currency $existing */
		if (
			$this->is_display_currency
			&& $existing = Helper::finder(\DBTech\Credits\Finder\Currency::class)
				->where('is_display_currency', 1)
				->where('currency_id', '!=', $this->currency_id)
				->fetchOne()
		) {
			// We're changing display currency
			$existing->fastUpdate('is_display_currency', 0);
		}

		return true;
	}

	/**
	 * @throws \XF\PrintableException
	 */
	protected function _postSave()
	{
		// Make sure we have an adjust event
		$this->verifyAdjustEvent();
	}

	/**
	 *
	 */
	protected function _postDelete()
	{
		$db = $this->db();
		$sm = $db->getSchemaManager();

		$tableName = ($this->use_table_prefix ? 'xf_' : '') . $this->table;

		if (!$this->blacklist)
		{
			$sm->alterTable($tableName, function (Alter $table)
			{
				$table->dropColumns([$this->column]);
			});
		}

		if ($sm->tableExists('xf_dbtech_shop_currency'))
		{
			$db->update(
				'xf_dbtech_shop_currency',
				['credits_currency_id' => 0],
				'credits_currency_id = ?',
				[$this->currency_id]
			);
		}

		$this->app()->jobManager()->enqueue('DBTech\Credits:CurrencyDeleteCleanUp', [
			'currencyId' => $this->currency_id,
			'title' => $this->title
		]);
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return mixed|string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-credits/currency' : 'dbtech-credits/currency';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-credits/currency';
	}

	/**
	 * @param string $context
	 *
	 * @return \XF\Phrase
	 */
	public function getContentTitle(string $context = ''): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_currency_x', ['title' => $this->title]);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_currency';
		$structure->shortName = 'DBTech\Credits:Currency';
		$structure->primaryKey = 'currency_id';
		$structure->columns = [
			'currency_id'			=> ['type' => self::UINT, 'autoIncrement' => true],
			'title' 				=> ['type' => self::STR, 'required' => true],
			'description' 			=> ['type' => self::STR, 'default' => ''],
			'active' 				=> ['type' => self::BOOL, 'default' => true],
			'display_order' 		=> ['type' => self::UINT, 'default' => 10],
			'table' 				=> ['type' => self::STR,
								   'verify' => 'verifySqlSafe', 'default' => 'user'
			],
			'use_table_prefix' 		=> ['type' => self::BOOL, 'default' => true],
			'column' 				=> ['type' => self::STR, 'required' => true],
			'use_user_id' 			=> ['type' => self::BOOL, 'default' => true],
			'user_id_column' 		=> ['type' => self::STR,
									 'verify' => 'verifySqlSafe', 'default' => 'user_id'
			],
			'decimals' 				=> ['type' => self::UINT, 'default' => 0, 'max' => 8],
			'privacy' 				=> ['type' => self::UINT, 'max' => 2, 'default' => 2],
			'blacklist' 			=> ['type' => self::BOOL, 'default' => false],
			'prefix' 				=> ['type' => self::STR, 'default' => ''],
			'suffix' 				=> ['type' => self::STR, 'default' => ''],
			'is_display_currency' 	=> ['type' => self::BOOL, 'default' => false],
			'show_amounts' 			=> ['type' => self::BOOL, 'default' => true],
			'sidebar' 				=> ['type' => self::BOOL, 'default' => true],
			'member_dropdown' 		=> ['type' => self::BOOL, 'default' => false],
			'postbit' 				=> ['type' => self::BOOL, 'default' => true],
			'negative' 				=> ['type' => self::UINT, 'max' => 2, 'default' => 2],
			'maxtime' 				=> ['type' => self::UINT, 'default' => 0],
			'earnmax' 				=> ['type' => self::FLOAT, 'default' => 0.0],
			'value' 				=> ['type' => self::FLOAT, 'default' => 1.0],
			'inbound' 				=> ['type' => self::BOOL, 'default' => true],
			'outbound' 				=> ['type' => self::BOOL, 'default' => true]
		];
		$structure->behaviors = [
			'DBTech\Credits:Cacheable' => []
		];
		$structure->getters = [
			'RichestUsers' => true
		];
		$structure->relations = [
			'Events' => [
				'entity' => 'DBTech\Credits:Event',
				'type' => self::TO_MANY,
				'conditions' => 'currency_id',
				'cascadeDelete' => true
			],
			'Transactions' => [
				'entity' => 'DBTech\Credits:Transaction',
				'type' => self::TO_MANY,
				'conditions' => 'currency_id'
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
		$this->display_order = 10;
		$this->table = 'user';
		$this->use_table_prefix = true;
		$this->use_user_id = true;
		$this->user_id_column = 'user_id';
		$this->decimals = 0;
		$this->privacy = 2;
		$this->is_display_currency = false;
		$this->sidebar = true;
		$this->member_dropdown = false;
		$this->postbit = true;
		$this->blacklist = false;
		$this->negative = 2;
		$this->maxtime = 0;
		$this->earnmax = 0.0;
		$this->value =  1;
		$this->inbound = true;
		$this->outbound = true;
	}
}