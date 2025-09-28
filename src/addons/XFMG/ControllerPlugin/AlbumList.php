<?php

namespace XFMG\ControllerPlugin;

class AlbumList extends AbstractList
{
	public function getAlbumListData(array $sourceCategoryIds, $page = 1, \XF\Entity\User $user = null)
	{
		$albumRepo = $this->getAlbumRepo();

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		if ($user)
		{
			$albumFinder = $albumRepo->findAlbumsForUser($user, $sourceCategoryIds, [
				'allowOwnPending' => $allowOwnPending
			]);
		}
		else
		{
			$albumFinder = $albumRepo->findAlbumsForIndex($sourceCategoryIds, [
				'allowOwnPending' => $allowOwnPending
			]);
		}

		$filters = $this->getFilterInput();
		$this->applyFilters($albumFinder, $filters);

		$totalItems = $albumFinder->total();

		$page = $this->filterPage($page);
		$perPage = $this->options()->xfmgAlbumsPerPage;

		$albumFinder->limitByPage($page, $perPage);
		$albums = $albumFinder->fetch()->filterViewable();

		if (!empty($filters['owner_id']) && !$user)
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		$canInlineMod = ($user && \XF::visitor()->user_id == $user->user_id);
		if (!$canInlineMod)
		{
			foreach ($albums AS $album)
			{
				/** @var \XFMG\Entity\Album $album */
				if ($album->canUseInlineModeration())
				{
					$canInlineMod = true;
					break;
				}
			}
		}

		return [
			'albums' => $albums,
			'filters' => $filters,
			'ownerFilter' => $ownerFilter,
			'canInlineMod' => $canInlineMod,

			'totalItems' => $totalItems,
			'page' => $page,
			'perPage' => $perPage,

			'user' => $user
		];
	}

	protected $defaultSort = 'create_date';

	public function getAvailableSorts()
	{
		$sorts = parent::getAvailableSorts();

		return ['create_date' => 'create_date', 'media_count' => 'media_count'] + $sorts;
	}

	protected function apply(array $filters, \XFMG\Entity\Category $category = null, \XF\Entity\User $user = null)
	{
		if ($user)
		{
			return $this->redirect($this->buildLink(
				'media/albums/users',
				$user,
				$filters
			));
		}
		else
		{
			return $this->redirect($this->buildLink(
				$category ? 'media/categories' : 'media/albums',
				$category,
				$filters
			));
		}
	}

	public function actionFilters(\XFMG\Entity\Category $category = null, \XF\Entity\User $user = null)
	{
		$reply = parent::actionFilters($category, $user);

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			if ($user)
			{
				$reply->setParam('action', $this->buildLink('media/albums/users/filters', $user));
			}
			else
			{
				$reply->setParam('action', $this->buildLink($category ? 'media/categories/filters' : 'media/albums/filters', $category));
			}
		}

		return $reply;
	}
}