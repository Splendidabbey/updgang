<?php

namespace DBTech\Credits\Pub\Controller;

use DBTech\Credits\Helper;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Charge
 *
 * @package DBTech\Credits\Pub\Controller
 */
class Charge extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionUnlocked(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertRegistrationRequired();

		$chargeFinder = Helper::finder(\DBTech\Credits\Finder\ChargePurchase::class)
			->where('user_id', \XF::visitor()->user_id)
			->order('content_id', 'DESC')
		;

		$total = $chargeFinder->total();
		if (!$total)
		{
			return $this->error(\XF::phrase('dbtech_credits_could_not_find_unlocked_content'));
		}

		$page = $this->filterPage();
		$perPage = $this->options()->searchResultsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-credits/charge/unlocked');

		$maxResults = max(\XF::options()->maximumSearchResults, 20);

		$results = $chargeFinder->fetch();
		$resultArray = [];
		foreach ($results as $key => $result)
		{
			$resultArray[$key] = $result->toArray();
		}

		$searcher = $this->app->search();
		$resultSet = $searcher->getResultSet($resultArray)->limitResults($maxResults);

		$resultSet->sliceResultsToPage($page, $perPage);

		if (!$resultSet->countResults())
		{
			return $this->message(\XF::phrase('no_results_found'));
		}

		$maxPage = ceil($total / $perPage);

		if ($total > $perPage
			&& $page == $maxPage)
		{
			$lastResult = $resultSet->getLastResultData($lastResultType);
			$getOlderResultsDate = $searcher->handler($lastResultType)->getResultDate($lastResult);
		}
		else
		{
			$getOlderResultsDate = null;
		}

		$resultOptions = [
			'search' => null,
		];
		$resultsWrapped = $searcher->wrapResultsForRender($resultSet, $resultOptions);

		$viewParams = [
			'results' => $resultsWrapped,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,

			'getOlderResultsDate' => $getOlderResultsDate
		];
		return $this->view(
			\DBTech\Credits\Pub\View\Charge\Unlocked::class,
			'dbtech_credits_charge_search_results',
			$viewParams
		);
	}
}