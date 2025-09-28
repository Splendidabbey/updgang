<?php

namespace DBTech\Credits\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Index
 *
 * @package DBTech\Credits\Pub\Controller
 */
class Index extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechCredits())
		{
			throw $this->exception($this->noPermission());
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('DBTech\Credits:Overview');

		$viewParams = $overviewPlugin->getCoreListData();

		$this->assertValidPage($viewParams['page'], $viewParams['perPage'], $viewParams['total'], 'dbtech-credits');
		$this->assertCanonicalUrl($this->buildLink('dbtech-credits', null, ['page' => $viewParams['page']]));

		return $this->view(
			\DBTech\Credits\Pub\View\Overview::class,
			'dbtech_credits_transactions',
			$viewParams
		);
	}

	/**
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \Exception
	 */
	public function actionFilters(): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('DBTech\Credits:Overview');

		return $overviewPlugin->actionFilters();
	}
}