<?php

namespace DBTech\Credits\Finder;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;

/**
 * Class Transaction
 * @package DBTech\Credits\Finder
 */
class Transaction extends Finder
{
	/**
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false): Transaction
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->canViewModeratedDbtechCreditsTransactions())
		{
			$viewableStates[] = 'moderated';
		}
		elseif ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'transaction_state' => 'moderated',
				'user_id' => $visitor->user_id
			];
		}

		$conditions[] = ['transaction_state', $viewableStates];
		$this->whereOr($conditions);

		if (!$visitor->canViewAnyDbtechCreditsTransaction())
		{
			$this->whereOr([
				['user_id', $visitor->user_id],
				['source_user_id', $visitor->user_id]
			]);
		}

		$this->where([
			['Event.active', true],
			['Event.display', true],
		]);

		/** @var \DBTech\Credits\Entity\Currency[]|ArrayCollection $currencies */
		$container = $this->app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			$currencies = $currencies
				->filter(function (\DBTech\Credits\Entity\Currency $currency): ?\DBTech\Credits\Entity\Currency
				{
					if (!$currency->isActive())
					{
						return null;
					}

					return $currency;
				})
			;

			if (!$visitor->canBypassDbtechCreditsCurrencyPrivacy())
			{
				$currencyIds = $currencies
					->filter(function (\DBTech\Credits\Entity\Currency $currency): ?\DBTech\Credits\Entity\Currency
					{
						if (!$currency->privacy)
						{
							return null;
						}

						return $currency;
					})
					->pluckNamed('currency_id')
				;
			}
			else
			{
				$currencyIds = $currencies->pluckNamed('currency_id');
			}

			$this->where('currency_id', $currencyIds);
		}

		return $this;
	}

	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function useDefaultOrder(): Transaction
	{
//		$defaultOrder = $this->app()->options()->dbtechCreditsListDefaultOrder ?: 'last_update';
		$defaultOrder = 'dateline';
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		$this->setDefaultOrder([
			[$defaultOrder, $defaultDir],
			['transaction_id', 'desc']
		]);

		return $this;
	}
}