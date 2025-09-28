<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

class Album extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		if ($params->album_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}

		$this->assertNotEmbeddedImageRequest();

		/** @var \XFMG\ControllerPlugin\AlbumList $albumListPlugin */
		$albumListPlugin = $this->plugin('XFMG:AlbumList');

		$categoryParams = $albumListPlugin->getCategoryListData();
		$viewableCategoryIds = $categoryParams['albumCategories']->keys();

		$listParams = $albumListPlugin->getAlbumListData($viewableCategoryIds, $params->page);

		$this->assertValidPage($listParams['page'], $listParams['perPage'], $listParams['totalItems'], 'albums');
		$this->assertCanonicalUrl($this->buildLink('media/albums', null, ['page' => $listParams['page']]));

		$viewParams = $categoryParams + $listParams;
		return $this->view('XFMG:Album\Index', 'xfmg_album_index', $viewParams);
	}

	public function actionView(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		$visitor = \XF::visitor();
		$extraWith = ['Category'];

		if ($visitor->user_id)
		{
			$extraWith[] = 'Category.Permissions|' . $visitor->permission_combination_id;
			$extraWith[] = 'DraftComments|' . $visitor->user_id;
			$extraWith[] = 'CommentRead|' . $visitor->user_id;
			$extraWith[] = 'Reactions|' . $visitor->user_id;
			$extraWith[] = 'Watch|' . $visitor->user_id;
		}

		$album = $this->assertViewableAlbum($params->album_id, $extraWith);

		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');

		$page = $this->filterPage($params->page);
		$perPage = $this->options()->xfmgMediaPerPage;

		$commentPage = $this->filter('comment_page', 'uint');
		$commentsPerPage = $this->options()->xfmgCommentsPerPage;

		$this->assertCanonicalUrl($this->buildLink('media/albums', $album, ['page' => $page]));

		$albumRepo = $this->getAlbumRepo();
		$commentRepo = $this->getCommentRepo();
		$mediaRepo = $this->getMediaRepo();

		$albumRepo->logAlbumView($album);

		$commentList = $commentRepo->findCommentsForContent($album)
			->limitByPage($commentPage, $commentsPerPage);

		/** @var \XF\Mvc\Entity\ArrayCollection|\XFMG\Entity\Comment[] $comments */
		$comments = $commentList->fetch();
		$totalComments = $commentList->total();

		/** @var \XF\Repository\UserAlert $userAlertRepo */
		$userAlertRepo = $this->repository('XF:UserAlert');

		$userAlertRepo->markUserAlertsReadForContent('xfmg_comment', $comments->keys());
		$userAlertRepo->markUserAlertsReadForContent('xfmg_album', $album->album_id);
		$userAlertRepo->markUserAlertsReadForContent('xfmg_rating', $album->rating_ids);

		$last = $comments->last();
		if ($last)
		{
			$albumRepo->markAlbumCommentsReadByVisitor($album, $last->comment_date);
		}

		$canInlineModComments = false;
		foreach ($comments AS $comment)
		{
			if ($comment->canUseInlineModeration())
			{
				$canInlineModComments = true;
				break;
			}
		}

		$mediaList = $mediaRepo
			->findMediaForAlbum($album->album_id, [
				'allowOwnPending' => $this->hasContentPendingApproval()
			])
			->limitByPage($page, $perPage);

		if ($this->responseType == 'rss')
		{
			$mediaItems = $mediaList
				->limitByPage(1, $perPage * 2)
				->fetch();

			$title = \XF::phrase('xfmg_media_items');
			$description = \XF::phrase('xfmg_rss_feed_for_all_media_in_album_x', [
				'album' => $album->title
			]);
			$link = $this->buildLink('canonical:media/albums', $album);

			return $mediaListPlugin->renderMediaListRss(
				$mediaItems,
				$title, $description, $link
			);
		}

		$filters = $mediaListPlugin->getFilterInput();
		$mediaListPlugin->applyFilters($mediaList, $filters);

		if (!empty($filters['owner_id']))
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		/** @var \XFMG\Entity\MediaItem[] $mediaItems */
		$mediaItems = $mediaList->fetch();
		$totalItems = $mediaList->total();

		$canInlineModMediaItems = false;
		foreach ($mediaItems AS $mediaItem)
		{
			if ($mediaItem->canUseInlineModeration())
			{
				$canInlineModMediaItems = true;
				break;
			}
		}
		
		$this->assertValidPage($page, $perPage, $totalItems, 'media/albums', $album);

		$viewParams = [
			'album' => $album,
			'comments' => $comments,
			'mediaItems' => $mediaItems,

			'commentPage' => $commentPage,
			'commentsPerPage' => $commentsPerPage,
			'totalComments' => $totalComments,

			'page' => $page,
			'perPage' => $perPage,
			'totalItems' => $totalItems,

			'prevPage' => $page > 1 ? $this->buildLink('media/albums', $album, ['page' => $page - 1] + $filters) : null,
			'nextPage' => $page < ceil($totalItems / $perPage) ? $this->buildLink('media/albums', $album, ['page' => $page + 1] + $filters) : null,

			'filters' => $filters,
			'ownerFilter' => $ownerFilter,

			'canInlineModComments' => $canInlineModComments,
			'canInlineModMediaItems' => $canInlineModMediaItems,

			'addUsers' => $this->em()->findByIds('XF:User', $album->add_users ?: []),
			'viewUsers' => $this->em()->findByIds('XF:User', $album->view_users ?: [])

		] + $mediaListPlugin->getMediaListMessages();
		return $this->view('XFMG:Album\View', 'xfmg_album_view', $viewParams);
	}

	public function actionFilters(ParameterBag $params)
	{
		if ($params->album_id)
		{
			$album = $this->assertViewableAlbum($params->album_id);
		}
		else
		{
			$album = null;
		}

		if ($album)
		{
			// sort of a special case in this context - we're filtering a media list rather than an album list

			/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
			$mediaListPlugin = $this->plugin('XFMG:MediaList');

			if ($this->filter('apply', 'bool'))
			{
				$filters = $mediaListPlugin->getFilterInput();

				$commentPage = $this->filter('comment_page', 'uint');
				if ($commentPage)
				{
					$filters['comment_page'] = $commentPage;
				}

				return $this->redirect($this->buildLink(
					'media/albums',
					$album,
					$filters
				));
			}
			else
			{
				$reply = $mediaListPlugin->actionFilters();
				$reply->setParam('action', $this->buildLink('media/albums/filters', $album));

				$commentPage = $this->filter('comment_page', 'uint');
				if ($commentPage)
				{
					$reply->setParam('commentPage', $commentPage);
				}

				return $reply;
			}
		}
		else
		{
			/** @var \XFMG\ControllerPlugin\AlbumList $albumListPlugin */
			$albumListPlugin = $this->plugin('XFMG:AlbumList');
			return $albumListPlugin->actionFilters();
		}
	}

	public function actionChangePrivacy(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canChangePrivacy($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$editor = $this->setupAlbumEdit($album);

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			$this->finalizeAlbumEdit($editor);

			return $this->redirect($this->buildLink('media/albums', $album));
		}
		else
		{
			$em = $this->em();

			$viewParams = [
				'album' => $album,
				'addUsers' => $em->findByIds('XF:User', $album->add_users ?: [])->pluckNamed('username'),
				'viewUsers' => $em->findByIds('XF:User', $album->view_users ?: [])->pluckNamed('username')
			];
			return $this->view('XFMG:Album\ChangePrivacy', 'xfmg_album_change_privacy', $viewParams);
		}
	}

	public function actionAdd(ParameterBag $params)
	{
		if (!$params->offsetExists('album_id'))
		{
			return $this->rerouteController(__CLASS__, 'addMedia', $params);
		}

		$album = $this->assertViewableAlbum($params->album_id);

		if (!$album->canAddMedia($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\Repository\Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		$attachmentData = $attachmentRepo->getEditorData('xfmg_media', $album);

		$viewParams = [
			'album' => $album,
			'attachmentData' => $attachmentData
		];
		return $this->view('XFMG:Album\Add', 'xfmg_album_add', $viewParams);
	}

	public function actionAddMedia(ParameterBag $params)
	{
		$page = $this->filterPage($params->page);
		$perPage = $this->options()->xfmgAlbumsPerPage;

		$this->assertCanonicalUrl($this->buildLink('media/albums/add', null, ['page' => $page]));

		$categoryRepo = $this->getCategoryRepo();

		$categoryList = $categoryRepo->getViewableCategories();
		$categoryTree = $categoryRepo->createCategoryTree($categoryList);
		$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);

		$categoryList = $categoryList->filter(function($category)
		{
			return ($category->category_type == 'album');
		});
		$categoryIds = $categoryList->keys();

		$albumRepo = $this->getAlbumRepo();
		$albumList = $albumRepo->findAlbumsUserCanAddTo($categoryIds)
			->limitByPage($page, $perPage);

		$albums = $albumList->fetch();
		$totalItems = $albumList->total();

		$this->assertValidPage($page, $perPage, $totalItems, 'albums');

		$viewParams = [
			'categoryTree' => $categoryTree,
			'categoryExtras' => $categoryExtras,
			'albums' => $albums,

			'page' => $page,
			'perPage' => $perPage,
			'totalItems' => $totalItems
		];
		return $this->view('XFMG:Album\Index', 'xfmg_add_media_album_chooser', $viewParams);
	}

	public function actionCreate()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canCreateAlbum())
		{
			return $this->noPermission();
		}

		$baseAlbum = $this->em()->create('XFMG:Album');

		if (!$baseAlbum->canUploadMedia() && !$baseAlbum->canEmbedMedia())
		{
			return $this->noPermission();
		}

		/** @var \XF\Repository\Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		$attachmentData = $attachmentRepo->getEditorData('xfmg_media', $baseAlbum);

		$viewParams = [
			'attachmentData' => $attachmentData,
			'album' => $baseAlbum
		];
		return $this->view('XFMG:Album\Create', 'xfmg_album_create', $viewParams);
	}

	/**
	 * @param \XFMG\Entity\Album $album
	 *
	 * @return \XFMG\Service\Album\Editor
	 */
	protected function setupAlbumEdit(\XFMG\Entity\Album $album)
	{
		/** @var \XFMG\Service\Album\Editor $editor */
		$editor = $this->service('XFMG:Album\Editor', $album);

		if ($this->request->exists('title') && $this->request->exists('description'))
		{
			$title = $this->filter('title', 'str');
			$description = $this->filter('description', 'str');

			$editor->setTitle($title, $description);
		}

		if ($album->canChangePrivacy())
		{
			$privacy = $this->filter([
				'view_privacy' => 'str',
				'view_users' => 'str',
				'add_privacy' => 'str',
				'add_users' => 'str'
			]);

			$editor->setViewPrivacy($privacy['view_privacy'], $privacy['view_users']);
			$editor->setAddPrivacy($privacy['add_privacy'], $privacy['add_users']);
		}

		if ($this->filter('author_alert', 'bool') && $album->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	protected function finalizeAlbumEdit(\XFMG\Service\Album\Editor $editor) {}

	public function actionEdit(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canEdit($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$editor = $this->setupAlbumEdit($album);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			$this->finalizeAlbumEdit($editor);

			return $this->redirect($this->buildLink('media/albums', $album));
		}
		else
		{
			$viewParams = [
				'album' => $album,
				'addUsers' => $this->em()->findByIds('XF:User', $album->add_users ?: [])->pluckNamed('username'),
				'viewUsers' => $this->em()->findByIds('XF:User', $album->view_users ?: [])->pluckNamed('username')
			];
			return $this->view('XFMG:Album\Edit', 'xfmg_album_edit', $viewParams);
		}
	}

	public function actionDelete(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$album->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \XFMG\Service\Album\Deleter $deleter */
			$deleter = $this->service('XFMG:Album\Deleter', $album);

			if ($this->filter('author_alert', 'bool') && $album->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin('XF:InlineMod')->clearIdFromCookie('xfmg_album', $album->album_id);

			return $this->redirect($this->buildLink('media/albums'));
		}
		else
		{
			$viewParams = [
				'album' => $album
			];
			return $this->view('XFMG:Album\Delete', 'xfmg_album_delete', $viewParams);
		}
	}

	public function actionUndelete(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		/** @var \XF\ControllerPlugin\Undelete $plugin */
		$plugin = $this->plugin('XF:Undelete');
		return $plugin->actionUndelete(
			$album,
			$this->buildLink('media/albums/undelete', $album),
			$this->buildLink('media/albums', $album),
			$album->title,
			'album_state'
		);
	}

	public function actionApprove(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			/** @var \XFMG\Service\Album\Approver $approver */
			$approver = $this->service('XFMG:Album\Approver', $album);
			$approver->approve();

			return $this->redirect($this->buildLink('media/albums', $album));
		}
		else
		{
			$viewParams = [
				'album' => $album,
			];
			return $this->view('XFMG:Album\Approve', 'xfmg_album_approve', $viewParams);
		}
	}

	public function actionWatch(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canWatch($error))
		{
			return $this->noPermission($error);
		}

		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			if ($this->filter('stop', 'bool'))
			{
				$action = 'delete';
				$config = [];
			}
			else
			{
				$action = 'watch';
				$config = $this->filter([
					'notify_on' => 'str',
					'send_alert' => 'bool',
					'send_email' => 'bool'
				]);
			}

			/** @var \XFMG\Repository\AlbumWatch $watchRepo */
			$watchRepo = $this->repository('XFMG:AlbumWatch');
			$watchRepo->setWatchState($album, $visitor, $action, $config);

			$redirect = $this->redirect($this->buildLink('media/albums', $album));
			$redirect->setJsonParam('switchKey', $action == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}
		else
		{
			$viewParams = [
				'album' => $album,
				'isWatched' => !empty($album->Watch[$visitor->user_id])
			];
			return $this->view('XFMG:Album\Watch', 'xfmg_album_watch', $viewParams);
		}
	}

	/**
	 * @param \XFMG\Entity\Album $album
	 * @param \XFMG\Entity\Category $category
	 *
	 * @return \XFMG\Service\Album\Mover
	 */
	protected function setupAlbumMove(\XFMG\Entity\Album $album)
	{
		$options = $this->filter([
			'notify_watchers' => 'bool',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str'
		]);

		/** @var \XFMG\Service\Album\Mover $mover */
		$mover = $this->service('XFMG:Album\Mover', $album);

		if ($options['author_alert'])
		{
			$mover->setSendAlert(true, $options['author_alert_reason']);
		}

		if ($options['notify_watchers'])
		{
			$mover->setNotifyWatchers();
		}

		return $mover;
	}

	public function actionMove(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canMove($error))
		{
			return $this->noPermission($error);
		}

		$category = $album->Category;

		if ($this->isPost())
		{
			$targetCategoryId = $this->filter('target_category_id', 'uint');

			/** @var \XFMG\Entity\Category $targetCategory */
			$targetCategory = $this->app()->em()->find('XFMG:Category', $targetCategoryId);

			if ($targetCategory && !$targetCategory->canView())
			{
				return $this->error(\XF::phrase('requested_category_not_found'));
			}

			$this->setupAlbumMove($album)->move($targetCategory);

			return $this->redirect($this->buildLink('media/albums', $album));
		}
		else
		{
			$categoryRepo = $this->getCategoryRepo();
			$categories = $categoryRepo->getViewableCategories();

			$viewParams = [
				'album' => $album,
				'category' => $category,
				'categoryTree' => $categoryRepo->createCategoryTree($categories)
			];
			return $this->view('XFMG:Album\Move', 'xfmg_album_move', $viewParams);
		}
	}

	public function actionIp(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		$breadcrumbs = $album->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($album, $breadcrumbs);
	}

	public function actionReport(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'xfmg_album', $album,
			$this->buildLink('media/albums/report', $album),
			$this->buildLink('media/albums', $album)
		);
	}

	public function actionBookmark(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		/** @var \XF\ControllerPlugin\Bookmark $bookmarkPlugin */
		$bookmarkPlugin = $this->plugin('XF:Bookmark');

		return $bookmarkPlugin->actionBookmark(
			$album, $this->buildLink('media/albums/bookmark', $album)
		);
	}

	public function actionReact(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($album, 'media/albums');
	}

	public function actionReactions(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		$breadcrumbs = $album->getBreadcrumbs();
		$title = \XF::phrase('xfmg_members_who_reacted_to_album', ['title' => $album->title]);

		$this->request()->set('page', $params->page);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactions(
			$album,
			'media/albums/reactions',
			$title, $breadcrumbs
		);
	}

	public function actionWarn(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		if (!$album->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $album->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'xfmg_album', $album,
			$this->buildLink('media/albums/warn', $album),
			$breadcrumbs
		);
	}

	public function actionModeratorActions(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);
		if (!$album->canViewModeratorLogs($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $album->getBreadcrumbs();
		$title = $album->title;

		$this->request()->set('page', $params->page);

		/** @var \XF\ControllerPlugin\ModeratorLog $modLogPlugin */
		$modLogPlugin = $this->plugin('XF:ModeratorLog');
		return $modLogPlugin->actionModeratorActions(
			$album,
			['media/albums/moderator-actions', $album],
			$title, $breadcrumbs
		);
	}

	public function actionMarkViewed(ParameterBag $params)
	{
		$album = $this->assertViewableAlbum($params->album_id);

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return $this->noPermission();
		}

		$markDate = $this->filter('date', 'uint');
		if (!$markDate)
		{
			$markDate = \XF::$time;
		}

		if ($this->isPost())
		{
			$mediaRepo = $this->getMediaRepo();
			$albumRepo = $this->getAlbumRepo();

			$mediaRepo->markAlbumMediaViewedByVisitor($album->album_id, $markDate);
			$mediaRepo->markAlbumMediaCommentsReadByVisitor($album->album_id, $markDate);
			$albumRepo->markAllAlbumCommentsReadByVisitor($markDate);

			return $this->redirect(
				$this->buildLink('media/albums', $album),
				\XF::phrase('xfmg_album_x_marked_as_viewed', ['title' => $album->title])
			);
		}
		else
		{
			$viewParams = [
				'album' => $album,
				'date' => $markDate
			];
			return $this->view('XFMG:Album\MarkViewed', 'xfmg_album_mark_viewed', $viewParams);
		}
	}

	public function actionDialogYours()
	{
		$categoryRepo = $this->getCategoryRepo();

		$categoryList = $categoryRepo->getViewableCategories();
		$viewableCategories = $categoryList->filter(function($category)
		{
			return ($category->category_type == 'album');
		});
		$categoryIds = $viewableCategories->keys();

		$page = $this->filterPage();
		$perPage = $this->options()->xfmgAlbumsPerPage;

		$albumRepo = $this->getAlbumRepo();
		$albumList = $albumRepo
			->findAlbumsForUser(\XF::visitor(), $categoryIds, ['visibility' => false])
			->where('album_state', 'visible')
			->limitByPage($page, $perPage, 1);

		$albums = $albumList->fetch();

		$hasMore = false;
		if ($albums->count() > $perPage)
		{
			$hasMore = true;
			$albums = $albums->slice(0, $perPage);
		}

		$viewParams = [
			'albums' => $albums,
			'page' => $page,
			'hasMore' => $hasMore
		];
		return $this->view('XFMG:Album\Dialog\Yours', 'xfmg_dialog_your_albums', $viewParams);
	}

	public function actionDialogBrowse()
	{
		$categoryRepo = $this->getCategoryRepo();

		$categoryList = $categoryRepo->getViewableCategories();
		$viewableCategories = $categoryList->filter(function($category)
		{
			return ($category->category_type == 'album');
		});
		$categoryIds = $viewableCategories->keys();

		$page = $this->filterPage();
		$perPage = $this->options()->xfmgAlbumsPerPage;

		$albumRepo = $this->getAlbumRepo();
		$albumList = $albumRepo->findAlbumsForIndex($categoryIds, ['visibility' => false])
			->where('album_state', 'visible')
			->where('user_id', '<>', \XF::visitor()->user_id)
			->limitByPage($page, $perPage);

		$albums = $albumList->fetch();

		$hasMore = false;
		if ($albums->count() > $perPage)
		{
			$hasMore = true;
			$albums = $albums->slice(0, $perPage);
		}

		$viewParams = [
			'albums' => $albums,
			'page' => $page,
			'hasMore' => $hasMore
		];
		return $this->view('XFMG:Album\Dialog\Browser', 'xfmg_dialog_browse_albums', $viewParams);
	}

	public function actionUsers(ParameterBag $params)
	{
		/** @var \XF\Entity\User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var \XFMG\ControllerPlugin\AlbumList $albumListPlugin */
		$albumListPlugin = $this->plugin('XFMG:AlbumList');

		$categoryParams = $albumListPlugin->getCategoryListData();
		$viewableCategoryIds = $categoryParams['albumCategories']->keys();

		$listParams = $albumListPlugin->getAlbumListData($viewableCategoryIds, $params->page, $user);

		$this->assertValidPage($listParams['page'], $listParams['perPage'], $listParams['totalItems'], 'media/albums/users', $user);
		$this->assertCanonicalUrl($this->buildLink('media/albums/users', $user, ['page' => $listParams['page']]));

		$viewParams = $categoryParams + $listParams;

		return $this->view('XFMG:Album\User\Index', 'xfmg_album_user_index', $viewParams);
	}

	public function actionUsersFilters(ParameterBag $params)
	{
		/** @var \XF\Entity\User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var \XFMG\ControllerPlugin\AlbumList $albumListPlugin */
		$albumListPlugin = $this->plugin('XFMG:AlbumList');

		return $albumListPlugin->actionFilters(null, $user);
	}

	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities, \XF::phrase('xfmg_viewing_album'), 'album_id',
			function(array $ids)
			{
				$albums = \XF::em()->findByIds(
					'XFMG:Album',
					$ids,
					['Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($albums->filterViewable() AS $id => $album)
				{
					$data[$id] = [
						'title' => $album->title,
						'url' => $router->buildLink('media/albums', $album)
					];
				}

				return $data;
			},
			\XF::phrase('xfmg_viewing_albums')
		);
	}
}