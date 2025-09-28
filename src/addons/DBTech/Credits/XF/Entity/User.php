<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\Entity\User
 *
 * COLUMNS
 * @property int dbtech_credits_lastdaily
 * @property int dbtech_credits_lastinterest
 * @property int dbtech_credits_lastpaycheck
 * @property int dbtech_credits_lasttaxation
 */
class User extends XFCP_User
{
	/**
	 * @return bool
	 */
	public function canViewDbtechCredits()
	{
		return $this->hasPermission('dbtechCredits', 'view');
	}

	/**
	 * @return bool
	 */
	public function canViewAnyDbtechCreditsTransaction()
	{
		return ($this->canViewDbtechCredits()
			&& $this->hasPermission('dbtechCredits', 'viewAnyLog')
		);
	}

	/**
	 * @return bool
	 */
	public function canViewModeratedDbtechCreditsTransactions()
	{
		return ($this->canViewDbtechCredits()
			&& $this->hasPermission('dbtechCredits', 'viewModerated')
		);
	}

	/**
	 * @return bool
	 */
	public function canBypassDbtechCreditsCurrencyPrivacy()
	{
		return ($this->canViewDbtechCredits()
			&& $this->hasPermission('dbtechCredits', 'bypassCurrencyPrivacy')
		);
	}

	/**
	 * @return bool
	 */
	public function canBypassDbtechCreditsCharge()
	{
		return ($this->canViewDbtechCredits()
			&& $this->hasPermission('dbtechCredits', 'bypassChargeTag')
		);
	}

	/**
	 * @return bool
	 */
	public function canTriggerDbtechCreditsEvents()
	{
		return $this->hasPermission('dbtechCredits', 'triggerEvents');
	}

	/**
	 * @return bool
	 */
	public function canAdjustDbtechCreditsCurrencies()
	{
		return ($this->canTriggerDbtechCreditsEvents()
			&& $this->hasPermission('dbtechCredits', 'adjust')
		);
	}

	/**
	 * @param \DBTech\Credits\Entity\Currency $currency
	 *
	 * @return mixed|null
	 */
	public function getDbtechCreditsCurrency(\DBTech\Credits\Entity\Currency $currency)
	{
		if (!$this->offsetExists($currency->column))
		{
			throw new \LogicException("Attempted to access column $currency->column on user, which did not exist.");
		}

		return $this->{$currency->column};
	}

	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		if ($this->isUpdate() && $this->isChanged('avatar_date'))
		{
			// Avatar event
			$avatarEvent = $eventTriggerRepo->getHandler('avatar');

			if ($this->avatar_date && !$this->getPreviousValue('avatar_date'))
			{
				$avatarEvent->testApply([], $this);
			}
			elseif (!$this->avatar_date && $this->getPreviousValue('avatar_date'))
			{
				$avatarEvent->testUndo([], $this);
			}
		}

		parent::_preSave();
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		if ($this->isUpdate() && $this->isChanged('avatar_date'))
		{
			// Avatar event
			$avatarEvent = $eventTriggerRepo->getHandler('avatar');

			if ($this->avatar_date && !$this->getPreviousValue('avatar_date'))
			{
				$avatarEvent->apply($this->avatar_date, [
					'content_type' => 'user',
					'content_id'   => $this->user_id
				], $this);
			}
			elseif (!$this->avatar_date && $this->getPreviousValue('avatar_date'))
			{
				$avatarEvent->undo($this->getPreviousValue('avatar_date'), [
					'content_type' => 'user',
					'content_id'   => $this->user_id
				], $this);
			}
		}

		parent::_postSave();
	}

	/**
	 *
	 */
	protected function _postDelete()
	{
		$db = $this->db();
		$userId = $this->user_id;

		$db->delete('xf_dbtech_credits_charge_purchase', 'user_id = ?', $userId);
		$db->delete('xf_dbtech_credits_transaction', 'user_id = ?', $userId);
		$db->delete('xf_dbtech_credits_transaction', 'source_user_id = ?', $userId);
		$db->delete('xf_dbtech_credits_transaction', 'owner_id = ?', $userId);

		parent::_postDelete();
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->getters['dbtech_credits_currency'] = true;

		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			/** @var \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\AbstractCollection $currencies */
			foreach ($currencies as $currencyId => $currency)
			{
				// Add all currencies matching
				$structure->columns[$currency->column] = ['type' => Entity::FLOAT, 'default' => 0, 'changeLog' => false];
			}
		}

		$structure->columns['dbtech_credits_lastdaily'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_credits_lastinterest'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_credits_lastpaycheck'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_credits_lasttaxation'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];

		return $structure;
	}
}