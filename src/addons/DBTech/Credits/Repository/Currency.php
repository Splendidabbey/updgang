<?php

namespace DBTech\Credits\Repository;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Currency
 * @package DBTech\Credits\Repository
 */
class Currency extends Repository
{
	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];

		/** @var \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\AbstractCollection $entities */
		$entities = Helper::finder(\DBTech\Credits\Finder\Currency::class)->fetch();
		foreach ($entities as $entity)
		{
			$cache[$entity->getIdentifier()] = $entity->toArray(false);
		}

		return $cache;
	}

	/**
	 * @return array
	 */
	public function rebuildCache(): array
	{
		$cache = $this->getCacheData();
		\XF::registry()->set('dbtCreditsCurrencies', $cache);
		return $cache;
	}

	/**
	 * @return Finder
	 */
	public function findCurrenciesForList(): Finder
	{
		/** @var \DBTech\Credits\Finder\Currency $finder */
		$finder = Helper::finder(\DBTech\Credits\Finder\Currency::class);

		return $finder->orderForList();
	}

	/**
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	public function getCurrenciesFromContainer(): AbstractCollection
	{
		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			/** @var \DBTech\Credits\Entity\Currency[]|AbstractCollection $currencies */
			return $currencies;
		}

		return $this->em->getEmptyCollection();
	}

	/**
	 * @param \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\AbstractCollection $events
	 *
	 * @return \XF\Mvc\Entity\ArrayCollection
	 */
	public function getCurrenciesFromEvents(
		AbstractCollection $events
	): ArrayCollection {
		$currencies = [];
		foreach ($events as $event)
		{
			$currencies[$event->currency_id] = $event->Currency;
		}

		return new ArrayCollection($currencies);
	}

	/**
	 * @param \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\AbstractCollection $events
	 * @param bool $onlyActive
	 *
	 * @return array|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getCurrencyTitlePairsFromEvents(
		AbstractCollection $events,
		bool $onlyActive = false
	) {
		$currencies = [];
		foreach ($events as $event)
		{
			$currencies[$event->currency_id] = $event->Currency;
		}

		return $this->getCurrencyTitlePairs($onlyActive, new ArrayCollection($currencies));
	}

	/**
	 * @param bool $onlyActive
	 * @param \XF\Mvc\Entity\AbstractCollection|null $currencies
	 *
	 * @return array|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getCurrencyTitlePairs(bool $onlyActive = false, ?AbstractCollection $currencies = null)
	{
		if ($currencies === null)
		{
			$currencyFinder = $this->findCurrenciesForList();

			$currencies = $currencyFinder->fetch();
		}

		if ($onlyActive)
		{
			$currencies = $currencies->filterViewable();
		}

		return $currencies->pluckNamed('title', 'currency_id');
	}

	/**
	 * @param bool $includeEmpty
	 * @param null $type
	 *
	 * @return array
	 */
	public function getCurrencyOptionsData(bool $includeEmpty = true, $type = null): array
	{
		$choices = [];
		if ($includeEmpty)
		{
			$choices = [
				0 => ['_type' => 'option', 'value' => 0, 'label' => \XF::phrase('(none)')]
			];
		}

		$currencies = $this->getCurrencyTitlePairs();

		foreach ($currencies AS $currencyId => $label)
		{
			$choices[$currencyId] = [
				'value' => $currencyId,
				'label' => $label
			];
			if ($type !== null)
			{
				$choices[$currencyId]['_type'] = $type;
			}
		}

		return $choices;
	}

	/**
	 * @param bool $filterViewable
	 *
	 * @return \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getCurrencies(bool $filterViewable = false)
	{
		$container = $this->app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			if ($filterViewable)
			{
				$currencies = $currencies->filterViewable();
			}

			return $currencies;
		}

		return $this->em->getEmptyCollection();
	}

	/**
	 * @return \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getViewableCurrencies()
	{
		return $this->getCurrencies(true);
	}

	/**
	 * @return Finder
	 */
	public function getDisplayCurrency(): Finder
	{
		return Helper::finder(\DBTech\Credits\Finder\Currency::class)
			->where('is_display_currency', 1);
	}

	/**
	 * @return \DBTech\Credits\Entity\Currency
	 */
	public function getChargeCurrency(): \DBTech\Credits\Entity\Currency
	{
		$options = $this->options();
		$currencyId = $options->dbtech_credits_eventtrigger_content_currency;

		if (!$currencyId)
		{
			/** @var \DBTech\Credits\Entity\Currency $currency */
			$currency = Helper::finder(\DBTech\Credits\Finder\Currency::class)
				->fetchOne()
			;

			$optionRepo = Helper::repository(\XF\Repository\Option::class);
			$optionRepo->updateOptions([
				'dbtech_credits_eventtrigger_content_currency' => $currency->currency_id
			]);

			$currencyId = $currency->currency_id;
		}

		return Helper::find(\DBTech\Credits\Entity\Currency::class, $currencyId);
	}

	/**
	 * @param \DBTech\Credits\Entity\Currency $currency
	 * @param int $limit
	 *
	 * @return Finder
	 */
	public function getRichestUsers(\DBTech\Credits\Entity\Currency $currency, int $limit = 5): Finder
	{
		return Helper::finder(\XF\Finder\User::class)
			->isValidUser()
			->order($currency->column, 'DESC')
			->limit($limit)
		;
	}

	/**
	 * @param ArrayCollection|null $currencies
	 */
	public function resetCurrencies(?ArrayCollection $currencies = null)
	{
		if ($currencies === null)
		{
			$currencies = $this->getCurrencies();
		}

		/** @var \DBTech\Credits\Entity\Currency $currency */
		foreach ($currencies as $currency)
		{
			$this->db()->update('xf_user', [
				$currency->column => 0
			], null);
		}
	}

	/**
	 * @param int $userId
	 * @param int $currencyId
	 *
	 * @return float
	 */
	public function getUserBalanceFromTransactionLog(int $userId, int $currencyId): float
	{
		/** @var \DBTech\Credits\Entity\Transaction $latestTransaction */
		$latestTransaction = Helper::finder(\DBTech\Credits\Finder\Transaction::class)
			->where('user_id', $userId)
			->where('currency_id', $currencyId)
			->order('dateline', 'desc')
			->fetchOne()
		;
		if (!$latestTransaction)
		{
			return 0.00;
		}

		return $latestTransaction->balance;
	}
}