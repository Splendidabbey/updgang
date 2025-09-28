<?php

namespace XFMG\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

abstract class AbstractBatchUpdate extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');
	}

	abstract protected function getClassIdentifier();

	abstract protected function getLinkPrefix();

	abstract protected function getTemplatePrefix();

	abstract protected function getSectionContext();

	public function actionIndex()
	{
		$this->setSectionContext($this->getSectionContext());

		$searcher = $this->searcher($this->getClassIdentifier());

		$viewParams = [
			'criteria' => $searcher->getFormCriteria(),
			'success' => $this->filter('success', 'bool'),
			'linkPrefix' => $this->getLinkPrefix(),
		] + $searcher->getFormData();
		return $this->view($this->getClassIdentifier() . '\BatchUpdate', $this->getTemplatePrefix(), $viewParams);
	}

	public function actionConfirm()
	{
		$this->setSectionContext($this->getSectionContext());

		$this->assertPostOnly();

		$criteria = $this->filter('criteria', 'array');

		$searcher = $this->searcher($this->getClassIdentifier(), $criteria);

		$ids = $this->filter('ids', 'array-uint');

		$total = count($ids) ?: $searcher->getFinder()->total();
		if (!$total)
		{
			throw $this->exception($this->error(\XF::phraseDeferred('no_items_matched_your_filter')));
		}

		$viewParams = [
			'total' => $total,
			'ids' => $ids,
			'criteria' => $searcher->getFilteredCriteria(),
			'linkPrefix' => $this->getLinkPrefix()
		];
		return $this->view($this->getClassIdentifier() . '\BatchUpdate\Confirm', $this->getTemplatePrefix() . '_confirm', $viewParams);
	}

	public function actionList()
	{
		$this->setSectionContext($this->getSectionContext());

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 50;

		$showingAll = $this->filter('all', 'bool');
		if ($showingAll)
		{
			$page = 1;
			$perPage = 5000;
		}

		$searcher = $this->searcher($this->getClassIdentifier(), $criteria);
		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$items = $finder->fetch();

		$viewParams = [
			'items' => $items,

			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,

			'showingAll' => $showingAll,
			'showAll' => (!$showingAll && $total <= 5000),

			'criteria' => $searcher->getFilteredCriteria(),
			'order' => $order,
			'direction' => $direction,

			'linkPrefix' => $this->getLinkPrefix()
		];
		return $this->view($this->getClassIdentifier() . '\BatchUpdate\Listing', $this->getTemplatePrefix() . '_list', $viewParams);
	}

	public function actionAction()
	{
		$this->setSectionContext($this->getSectionContext());

		$this->assertPostOnly();

		if ($this->request->exists('ids'))
		{
			$ids = $this->filter('ids', 'json-array');
			$total = count($ids);
			$jobCriteria = null;
		}
		else
		{
			$criteria = $this->filter('criteria', 'json-array');

			$searcher = $this->searcher($this->getClassIdentifier(), $criteria);
			$total = $searcher->getFinder()->total();
			$jobCriteria = $searcher->getFilteredCriteria();

			$ids = null;
		}

		if (!$total)
		{
			throw $this->exception($this->error(\XF::phraseDeferred('no_items_matched_your_filter')));
		}

		$actions = $this->filter('actions', 'array');

		if ($this->request->exists('confirm_delete') && empty($actions['delete']))
		{
			return $this->error(\XF::phrase('you_must_confirm_deletion_to_proceed'));
		}

		$this->app->jobManager()->enqueueUnique($this->getSectionContext() . 'Action', $this->getClassIdentifier() . 'Action', [
			'total' => $total,
			'actions' => $actions,
			'ids' => $ids,
			'criteria' => $jobCriteria
		]);

		return $this->redirect($this->buildLink($this->getLinkPrefix(), null, ['success' => true]));
	}
}