<?php

namespace XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Author extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewResources($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($params->user_id)
		{
			return $this->rerouteController('XFRM:Author', 'Author', $params);
		}

		/** @var \XF\Entity\MemberStat $memberStat */
		$memberStat = $this->em()->findOne('XF:MemberStat', ['member_stat_key' => 'xfrm_most_resources']);

		if ($memberStat && $memberStat->canView())
		{
			return $this->redirectPermanently(
				$this->buildLink('members', null, ['key' => $memberStat->member_stat_key])
			);
		}
		else
		{
			return $this->redirect($this->buildLink('resources'));
		}
	}

	public function actionAuthor(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		/** @var \XF\Entity\User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		$viewableCategoryIds = $this->repository('XFRM:Category')->getViewableCategoryIds();

		$team = (
			  \XF::visitor()->user_id == $user->user_id &&
			  $this->options()->xfrmEnableTeams &&
			  $this->filter('team', 'bool')
		);

		/** @var \XFRM\Repository\ResourceItem $resourceRepo */
		$resourceRepo = $this->repository('XFRM:ResourceItem');
		if ($team)
		{
			$finder = $resourceRepo->findResourcesByTeamMember(
				$user->user_id,
				$viewableCategoryIds
			);
		}
		else
		{
			$finder = $resourceRepo->findResourcesByUser(
				$user->user_id,
				$viewableCategoryIds
			);
		}

		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->xfrmResourcesPerPage;

		$this->assertValidPage($page, $perPage, $total, 'resources/authors', $user);
		$this->assertCanonicalUrl($this->buildLink('resources/authors', $user, ['page' => $page]));

		$resources = $finder->limitByPage($page, $perPage)->fetch();
		$resources = $resources->filterViewable();

		$canInlineMod = false;
		foreach ($resources AS $resource)
		{
			/** @var \XFRM\Entity\ResourceItem $resource */
			if ($resource->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'user' => $user,
			'team' => $team,
			'resources' => $resources,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'canInlineMod' => $canInlineMod
		];
		return $this->view('XFRM:Author\View', 'xfrm_author_view', $viewParams);
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('xfrm_viewing_resources');
	}
}