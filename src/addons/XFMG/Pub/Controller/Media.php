<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;

class Media extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		if ($params->media_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}

		$this->assertNotEmbeddedImageRequest();

		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');

		$categoryParams = $mediaListPlugin->getCategoryListData();
		$viewableCategoryIds = $categoryParams['viewableCategories']->keys();

		if ($this->responseType == 'rss')
		{
			$listParams = $mediaListPlugin->getMediaListData($viewableCategoryIds);

			$title = \XF::phrase('xfmg_media_items');
			$description = \XF::phrase('xfmg_rss_feed_for_all_media_from_x', [
				'board' => $this->options()->boardTitle
			]);
			$link = $this->buildLink('canonical:media');

			return $mediaListPlugin->renderMediaListRss(
				$listParams['mediaItems'],
				$title, $description, $link
			);
		}

		$listParams = $mediaListPlugin->getMediaListData($viewableCategoryIds, $params->page);

		$this->assertValidPage($listParams['page'], $listParams['perPage'], $listParams['totalItems'], 'media');
		$this->assertCanonicalUrl($this->buildLink('media', null, ['page' => $listParams['page']]));

		$listParams['prevPage'] = ($listParams['page'] > 1) ? $this->buildLink('media', null, ['page' => $listParams['page'] - 1] + $listParams['filters']) : null;
		$listParams['nextPage'] = ($listParams['page'] < ceil($listParams['totalItems'] / $listParams['perPage'])) ? $this->buildLink('media', null, ['page' => $listParams['page'] + 1] + $listParams['filters']) : null;

		$viewParams = $categoryParams + $listParams;

		if ($this->filter('lightbox', 'bool'))
		{
			return $this->view('XFMG:Media\LightboxIndex', 'xfmg_media_lightbox_index', $viewParams);
		}
		else
		{
			return $this->view('XFMG:Media\Index', 'xfmg_media_index', $viewParams);
		}
	}

	public function actionFilters()
	{
		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');

		return $mediaListPlugin->actionFilters();
	}

	public function actionLoadPreviousComments(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		$commentRepo = $this->getCommentRepo();

		/** @var \XFMG\Entity\Comment[] $comments */
		$commentList = $commentRepo->findCommentsForContent($mediaItem);

		$comments = $commentList
			->where('comment_date', '<', $this->filter('before', 'uint'))
			->orderByDate('DESC')
			->limit(5)
			->fetch()
			->reverse();

		if ($comments->count())
		{
			$firstCommentDate = $comments->first()->comment_date;

			$moreCommentsFinder = $commentRepo->findCommentsForContent($mediaItem)
				->where('comment_date', '<', $firstCommentDate);

			$loadMore = ($moreCommentsFinder->total() > 0);
		}
		else
		{
			$firstCommentDate = 0;
			$loadMore = false;
		}

		$viewParams = [
			'mediaItem' => $mediaItem,
			'comments' => $comments,
			'firstCommentDate' => $firstCommentDate,
			'loadMore' => $loadMore
		];
		return $this->view('XF:Media\LoadPreviousComments', 'xfmg_media_previous_comments', $viewParams);
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
			$extraWith[] = 'Viewed|' . $visitor->user_id;
			$extraWith[] = 'Reactions|' . $visitor->user_id;
			$extraWith[] = 'Watch|' . $visitor->user_id;
		}
		$mediaItem = $this->assertViewableMediaItem($params->media_id, $extraWith);

		$lightbox = $this->filter('lightbox', 'bool');

		$page = $this->filterPage($params->page);
		$perPage = $this->options()->xfmgCommentsPerPage;

		$this->assertCanonicalUrl($this->buildLink('media', $mediaItem, ['page' => $page]));

		$mediaRepo = $this->getMediaRepo();
		$commentRepo = $this->getCommentRepo();

		/** @var \XFMG\Entity\Comment[] $comments */
		$commentList = $commentRepo->findCommentsForContent($mediaItem);

		if ($lightbox)
		{
			// no pagination so just display the latest comments

			$comments = $commentList->orderByDate('DESC')
				->fetch(5)
				->reverse(true);
		}
		else
		{
			$comments = $commentList->limitByPage($page, $perPage)
				->fetch();
		}

		$totalItems = $commentList->total();

		$mediaRepo->addGalleryEmbedsToContent($comments);
		$mediaRepo->markMediaItemViewedByVisitor($mediaItem);
		$mediaRepo->logMediaView($mediaItem);

		/** @var \XF\Repository\UserAlert $userAlertRepo */
		$userAlertRepo = $this->repository('XF:UserAlert');

		$userAlertRepo->markUserAlertsReadForContent('xfmg_comment', $comments->keys());
		$userAlertRepo->markUserAlertsReadForContent('xfmg_media', $mediaItem->media_id);
		$userAlertRepo->markUserAlertsReadForContent('xfmg_rating', $mediaItem->rating_ids);

		$last = $comments->last();
		if ($last)
		{
			$mediaRepo->markMediaCommentsReadByVisitor($mediaItem, $last->comment_date);
		}

		$this->assertValidPage($page, $perPage, $totalItems, 'media', $mediaItem);

		$viewParams = [
			'mediaItem' => $mediaItem,
			'comments' => $comments,

			'page' => $page,
			'perPage' => $perPage,
			'totalItems' => $totalItems,

			'loadMore' => $lightbox && $totalItems > $comments->count()
		];

		$mirrorAttachment = $mediaItem->MirrorAttachment;
		if ($mirrorAttachment && $mirrorAttachment->Container)
		{
			$mirrorContainer = $mirrorAttachment->Container;
			if (method_exists($mirrorContainer, 'canView') && $mirrorContainer->canView())
			{
				$viewParams['mirrorAttachment'] = $mirrorAttachment;
				$viewParams['mirrorContainer'] = [
					'link' => $mirrorAttachment->getContainerLink(),
					'title' => $mirrorAttachment->getContainerTitle(),
					'container' => $mirrorContainer
				];
			}
		}

		if ($lightbox)
		{
			return $this->view('XFMG:Media\LightboxSidebar', 'xfmg_media_lightbox_sidebar', $viewParams);
		}
		else
		{
			$canInlineModComments = false;
			foreach ($comments as $comment)
			{
				if ($comment->canUseInlineModeration())
				{
					$canInlineModComments = true;
					break;
				}
			}
			$viewParams['canInlineModComments'] = $canInlineModComments;

			if ($mediaItem->media_type == 'image')
			{
				$noteRepo = $this->getNoteRepo();
				$mediaNotes = $noteRepo->findNotesForMedia($mediaItem->media_id)->fetch();
				$mediaNotes = $mediaNotes->filterViewable();

				$viewParams['mediaNotes'] = $mediaNotes;
			}

			/** @var \XFMG\ControllerPlugin\FilmStrip $filmStripPlugin */
			$filmStripPlugin = $this->plugin('XFMG:FilmStrip');
			$viewParams['filmStripParams'] = $filmStripPlugin->getFilmStripParamsForView($mediaItem);

			$session = $this->session();
			if ($session->keyExists('xfmgAvatarUpdated'))
			{
				$session->remove('xfmgAvatarUpdated');
				$viewParams['avatarUpdated'] = true;
			}

			return $this->view('XFMG:Media\View', 'xfmg_media_view', $viewParams);
		}
	}

	public function actionLightbox(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		$viewParams = [
			'mediaItem' => $mediaItem
		];
		return $this->view('XFMG:Media\LightboxView', 'xfmg_media_lightbox_view', $viewParams);
	}

	public function actionFull(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id, ['Attachment']);

		if (!$mediaItem->Attachment)
		{
			return $this->notFound();
		}

		$this->request->set('no_canonical', 1);

		return $this->rerouteController('XF:Attachment', 'index', ['attachment_id' => $mediaItem->Attachment->attachment_id]);
	}

	public function actionOriginal(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id, ['Attachment']);
		if (!$mediaItem->canEditImage($error))
		{
			// Only an allowed image when manipulating / having editing permissions
			return $this->noPermission($error);
		}

		if (!$mediaItem->watermarked)
		{
			// No original stored, so just serve the default full image
			return $this->rerouteController(__CLASS__, 'full', $params);
		}

		/** @var \XF\Entity\Attachment $attachment */
		$attachment = $this->em()->find('XF:Attachment', $mediaItem->Attachment->attachment_id);
		if (!$attachment)
		{
			throw $this->exception($this->notFound());
		}

		if (!$attachment->canView($error))
		{
			return $this->noPermission($error);
		}

		if (!$attachment->Data || !$attachment->Data->isDataAvailable())
		{
			return $this->error(\XF::phrase('attachment_cannot_be_shown_at_this_time'));
		}

		$this->setResponseType('raw');

		$eTag = $this->request->getServer('HTTP_IF_NONE_MATCH');
		if ($eTag && $eTag == '"' . $attachment['attach_date'] . '"')
		{
			$return304 = true;
		}
		else
		{
			$return304 = false;
		}

		$viewParams = [
			'attachment' => $attachment,
			'return304' => $return304,
			'mediaItem' => $mediaItem
		];
		return $this->view('XFMG:Media\Original', '', $viewParams);
	}

	public function actionFilmStripJump(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		$direction = $this->filter('direction', 'str');
		$jumpFromId = $this->filter('jump_from_id', 'uint');
		$jumpFrom = $this->assertViewableMediaItem($jumpFromId);

		/** @var \XFMG\ControllerPlugin\FilmStrip $filmStripPlugin */
		$filmStripPlugin = $this->plugin('XFMG:FilmStrip');
		$filmStripParams = $filmStripPlugin->getFilmStripParamsForJump($jumpFrom, $direction);

		$viewParams = [
			'mediaItem' => $mediaItem,
			'filmStripParams' => $filmStripParams
		];
		return $this->view('XFMG:Media\NavigateContainer', 'xfmg_media_film_strip', $viewParams);
	}

	protected function setupNoter(\XFMG\Entity\MediaItem $mediaItem, \XFMG\Entity\MediaNote $note)
	{
		/** @var \XFMG\Service\Media\Noter $noter */
		$noter = $this->service('XFMG:Media\Noter', $note);

		$noteData = $this->filter('note_data', 'json-array');
		$noter->setNoteData($noteData);

		$taggedUsername = $this->filter('tagged_username', 'str');
		$noteText = $this->filter('note_text', 'str');

		if (!$taggedUsername && !$noteText)
		{
			throw $this->exception($this->error(\XF::phrase('xfmg_cannot_create_media_note_without_tagged_username_or_note_text')));
		}

		$type = $this->filter('note_type', 'str');

		if ($type == 'user_tag')
		{
			$noter->setUserTag($taggedUsername);
		}
		else if ($type == 'note')
		{
			$noter->setNoteText($noteText);
		}

		return $noter;
	}

	public function actionNoteEdit(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		$noteId = $this->filter('note_id', 'uint');
		if ($noteId)
		{
			$note = $this->assertMediaNoteExists($noteId);
			if ($note->media_id != $mediaItem->media_id || !$note->canEdit())
			{
				return $this->noPermission();
			}
		}
		else
		{
			if (!$mediaItem->canAddNote())
			{
				return $this->noPermission();
			}
			$note = $mediaItem->getNewNote();
		}

		if ($this->isPost())
		{
			if ($this->request()->exists('delete'))
			{
				if (!$note->canDelete())
				{
					return $this->noPermission();
				}

				$note->delete();
				$redirect = $this->redirect($this->buildLink('media', $mediaItem));
				if ($this->filter('_xfWithData', 'bool'))
				{
					$redirect->setJsonParam('deleted', true);
				}

				return $redirect;
			}
			else
			{
				$noter = $this->setupNoter($mediaItem, $note);

				if (!$noter->validate($errors))
				{
					return $this->error($errors);
				}

				$note = $noter->save();
			}

			if ($this->filter('_xfWithData', 'bool'))
			{
				$viewParams = [
					'mediaItem' => $mediaItem,
					'note' => $note
				];
				$view = $this->view('XFMG:Media\NoteView','xfmg_media_note_view', $viewParams);
				$view->setJsonParam('message', \XF::phrase('xfmg_note_has_been_saved_successfully'));
				return $view;
			}
			else
			{
				return $this->redirect($this->buildLink('media', $mediaItem));
			}
		}

		$viewParams = [
			'mediaItem' => $mediaItem,
			'note' => $note
		];
		return $this->view('XFMG:Media\NoteEdit', 'xfmg_media_note_edit', $viewParams);
	}

	public function actionNoteApprove(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		$note = $this->assertMediaNoteExists($this->filter('note_id', 'uint'));

		if (!$note->canApproveReject() || $note->media_id !== $mediaItem->media_id)
		{
			return $this->noPermission();
		}

		$note->tag_state = 'approved';
		$note->tag_state_date = time();
		$note->save();

		return $this->redirect($this->buildLink('media', $mediaItem));
	}

	public function actionNoteReject(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		$note = $this->assertMediaNoteExists($this->filter('note_id', 'uint'));

		if (!$note->canApproveReject() || $note->media_id !== $mediaItem->media_id)
		{
			return $this->noPermission();
		}

		$note->tag_state = 'rejected';
		$note->tag_state_date = time();
		$note->save();

		return $this->redirect($this->buildLink('media', $mediaItem));
	}

	public function actionAdd()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!$visitor->canAddMedia())
		{
			return $this->noPermission();
		}

		$categoryRepo = $this->getCategoryRepo();
		$categoryList = $categoryRepo->getViewableCategories();

		$albumCategories = $categoryList->filter(function(\XFMG\Entity\Category $category)
		{
			return ($category->category_type == 'album');
		});
		$hasAlbumCategories = $albumCategories->count();

		$categoryTree = $categoryRepo->createCategoryTree($categoryList);
		$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);
		$categoryAddTree = $categoryTree->filter(null, function($id, \XFMG\Entity\Category $category, $depth, $children, \XF\Tree $tree)
		{
			return ($children || $category->canAddMedia());
		});

		$allowPersonalAlbums = $this->options()->xfmgAllowPersonalAlbums;

		$albumRepo = $this->getAlbumRepo();
		$albumList = $albumRepo->findAlbumsUserCanAddTo(null, $allowPersonalAlbums);

		$albums = $albumList->fetch();
		$albums = $albums->filter(function(\XFMG\Entity\Album $album)
		{
			return ($album->canView() && $album->canAddMedia());
		});

		$canAddToAlbums = ($albums->count() > 0);
		$canCreateAlbums = ($visitor->canCreateAlbum() && $allowPersonalAlbums);

		if (!$categoryAddTree->count() && !$canAddToAlbums && !$canCreateAlbums)
		{
			return $this->noPermission();
		}

		$viewParams = [
			'categoryTree' => $categoryTree,
			'categoryAddTree' => $categoryAddTree,
			'categoryExtras' => $categoryExtras,
			'hasAlbumCategories' => $hasAlbumCategories,
			'canAddToAlbums' => $canAddToAlbums,
			'canCreateAlbums' => $canCreateAlbums
		];
		return $this->view('XFMG:Media\Add', 'xfmg_add_media_chooser', $viewParams);
	}

	public function actionEmbedMedia()
	{
		$context = $this->filter('context', 'array-str');

		$category = null;
		$album = null;

		if (!empty($context['category_id']))
		{
			$category = $this->assertViewableCategory($context['category_id']);
			if (!$category->canAddMedia($error) || !$category->canEmbedMedia($error))
			{
				return $this->noPermission($error);
			}
		}
		else if (!empty($context['album_id']))
		{
			$album = $this->assertViewableAlbum($context['album_id']);
			if (!$album->canAddMedia($error) || !$album->canEmbedMedia($error))
			{
				return $this->noPermission($error);
			}
		}
		else
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$url = $this->filter('url', 'str');

			/** @var \XF\Validator\Url $validator */
			$validator = $this->app->validator('Url');
			$url = $validator->coerceValue($url);

			if (!$validator->isValid($url) || !$this->app->http()->reader()->isRequestableUntrustedUrl($url))
			{
				return $this->error(\XF::phrase('xfmg_pasted_text_does_not_appear_to_be_valid_url'));
			}

			/** @var \XF\Repository\BbCodeMediaSite $bbCodeMediaSiteRepo */
			$bbCodeMediaSiteRepo = $this->repository('XF:BbCodeMediaSite');

			$sites = $bbCodeMediaSiteRepo->findActiveMediaSites()->fetch();
			$match = $bbCodeMediaSiteRepo->urlMatchesMediaSiteList($url, $sites);

			if (!$match)
			{
				return $this->error(\XF::phrase('specified_url_cannot_be_embedded_as_media'));
			}

			$matchBbCode = '[MEDIA=' . $match['media_site_id'] . ']' . $match['media_id'] . '[/MEDIA]';

			/** @var \XFMG\Service\Media\TempCreator $tempCreator */
			$tempCreator = $this->service('XFMG:Media\TempCreator');
			$tempCreator->setMediaSite($url, $match['media_site_id'], $match['media_id']);

			/** @var \XFMG\Entity\MediaTemp $tempMedia */
			$tempMedia = $tempCreator->save();

			if (!$tempMedia)
			{
				return $this->error(\XF::phrase('xfmg_problem_occurred_while_creating_temp_media'));
			}

			$jsonParams = [
				'attachment' => [
					'link' => $url,
					'match_site_id' => $match['media_site_id'],
					'match_media_id' => $match['media_id'],

					'temp_media_id' => $tempMedia->temp_media_id,
					'media_hash' => $tempMedia->media_hash,
					'media_type' => 'embed',

					'title' => $tempMedia->title,
					'description' => $tempMedia->description,
					'temp_media_embed_url' => $url,
					'temp_media_tag' => $matchBbCode,

					'temp_thumbnail_url' => $tempMedia->temp_thumbnail_url,
					'thumbnail_date' => $tempMedia->thumbnail_date
				]
			];

			$view = $this->view('XFMG:Media\PasteLinkAccepted');
			$view->setJsonParams($jsonParams);
			return $view;
		}
		else
		{
			$viewParams = [
				'context' => $context
			];
			return $this->view('XF:Media\EmbedMedia', 'xfmg_media_embed_media', $viewParams);
		}
	}

	public function actionAddAction()
	{
		$this->assertPostOnly();

		$tempMediaItemId = $this->filter('delete', 'uint');

		$tempMediaItem = $this->assertRecordExists('XFMG:MediaTemp', $tempMediaItemId);
		if ($tempMediaItem->user_id != \XF::visitor()->user_id)
		{
			return $this->noPermission();
		}

		$tempMediaItem->delete();

		$reply = $this->redirect($this->getDynamicRedirect());
		$reply->setJsonParam('delete', true);
		return $reply;
	}

	public function actionAddOnInsert()
	{
		$this->assertPostOnly();

		$categoryId = $this->filter('category_id', 'uint');
		$albumId = $this->filter('album_id', 'uint');
		$namePrefix = $this->filter('name_prefix', 'str');

		$mediaItem = $this->em()->create('XFMG:MediaItem');

		if ($categoryId)
		{
			$container = $this->assertViewableCategory($categoryId);
			$mediaItem->category_id = $categoryId;
		}
		else if ($albumId)
		{
			$container = $this->assertViewableAlbum($albumId);
			$mediaItem->album_id = $albumId;
		}
		else
		{
			// Probably creating an album
			$container = $this->em()->create('XFMG:Album');
		}

		$viewParams = [
			'container' => $container,
			'namePrefix' => $namePrefix,
			'mediaItem' => $mediaItem
		];
		return $this->view('XFMG:Media\Add\OnInsert', 'xfmg_media_add_on_insert', $viewParams);
	}

	/**
	 * @return \XFMG\Service\Album\Creator
	 */
	protected function setupAlbumCreate()
	{
		/** @var \XFMG\Service\Album\Creator $creator */
		$creator = $this->service('XFMG:Album\Creator');

		$title = $this->filter('album.title', 'str');
		if (!$title)
		{
			throw $this->exception($this->error(\XF::phrase('xfmg_you_must_specify_title_to_create_new_album_for_these_media_items')));
		}
		$description = $this->filter('album.description', 'str');
		$creator->setTitle($title, $description);

		$viewPrivacy = $this->filter('album.view_privacy', 'str', 'private');
		$viewUsers = $this->filter('album.view_users', 'str');
		$creator->setViewPrivacy($viewPrivacy, $viewUsers);

		return $creator;
	}

	protected function finalizeAlbumCreate(\XFMG\Service\Album\Creator $creator)
	{
		$album = $creator->getAlbum();

		$creator->sendNotifications();

		/** @var \XFMG\Repository\AlbumWatch $watchRepo */
		$watchRepo = $this->repository('XFMG:AlbumWatch');
		$watchRepo->autoWatchAlbum($album, \XF::visitor(), true);
	}

	/**
	 * @param \XFMG\Entity\MediaTemp $mediaTemp
	 * @param \XFMG\Entity\Album | \XFMG\Entity\Category $container
	 * @param array $input
	 *
	 * @return \XFMG\Service\Media\TranscodeEnqueuer
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupTranscodeQueue(\XFMG\Entity\MediaTemp $mediaTemp, \XF\Mvc\Entity\Entity $container, array $input)
	{
		/** @var \XFMG\Service\Media\TranscodeEnqueuer $enqueuer */
		$enqueuer = $this->service('XFMG:Media\TranscodeEnqueuer', $mediaTemp);

		$enqueuer->setContainer($container);
		$enqueuer->setTitle($input['title'], $input['description']);
		$enqueuer->setTags($container->canEditTags() ? $input['tags'] : '');
		$enqueuer->setCustomFields($input['custom_fields']);
		$enqueuer->setAttachment($input['attachment_id'], $input['attachment_hash']);

		return $enqueuer;
	}

	protected function finalizeTranscodeQueue(\XFMG\Service\Media\TranscodeEnqueuer $enqueuer)
	{
		$enqueuer->afterInsert();
	}

	/**
	 * @param \XFMG\Entity\MediaTemp $mediaTemp
	 * @param \XFMG\Entity\Album | \XFMG\Entity\Category $container
	 * @param array $input
	 *
	 * @return \XFMG\Service\Media\Creator
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupMediaItemCreate(\XFMG\Entity\MediaTemp $mediaTemp, \XF\Mvc\Entity\Entity $container, array $input)
	{
		/** @var \XFMG\Service\Media\Creator $creator */
		$creator = $this->service('XFMG:Media\Creator', $mediaTemp);

		$creator->setContainer($container);
		$creator->setTitle($input['title'], $input['description']);

		if ($container->canEditTags())
		{
			$creator->setTags($input['tags']);
		}

		$creator->setCustomFields($input['custom_fields']);

		if ($input['attachment_id'])
		{
			$creator->setAttachment($input['attachment_id'], $input['attachment_hash']);
		}

		if ($input['temp_media_embed_url'])
		{
			$creator->setMediaSite($input['temp_media_embed_url'], $input['temp_media_tag']);
		}

		return $creator;
	}

	protected function finalizeMediaItemCreate(\XFMG\Service\Media\Creator $creator)
	{
		$creator->sendNotifications();

		$session = $this->session();
		$mediaItem = $creator->getMediaItem();

		if ($mediaItem->media_state == 'moderated' && !$session->keyExists('xfmgPendingApproval'))
		{
			$session->set('xfmgPendingApproval', true);
		}

		if ($mediaItem->media_state == 'moderated')
		{
			$session->setHasContentPendingApproval();
		}

		$mediaRepo = $this->getMediaRepo();
		$mediaRepo->markMediaItemViewedByVisitor($mediaItem);

		$visitor = \XF::visitor();

		/** @var \XFMG\Repository\MediaWatch $watchRepo */
		$watchRepo = $this->repository('XFMG:MediaWatch');
		$watchRepo->autoWatchMediaItem($mediaItem, $visitor, true);
	}

	public function actionSaveMedia(ParameterBag $params)
	{
		$this->assertPostOnly();

		$mediaInput = $this->filter('media', 'array');
		if (!$mediaInput)
		{
			return $this->error(\XF::phrase('xfmg_ensure_you_have_added_at_least_one_media_item_before_continuing'));
		}

		$container = $this->getContainerToSaveMedia();

		/** @var \XFMG\Service\Media\Creator[] $creators */
		$creators = [];

		/** @var \XFMG\Service\Media\TranscodeEnqueuer[] $enqueuers */
		$enqueuers = [];

		$errors = [];

		foreach ($mediaInput AS $mediaTempId => $input)
		{
			/** @var \XFMG\Entity\MediaTemp $mediaTemp */
			$mediaTemp = $this->em()->find('XFMG:MediaTemp', $mediaTempId);

			$input = $this->prepareMediaInput($input);

			if (!$mediaTemp || $mediaTemp->media_hash !== $input['media_hash'])
			{
				continue;
			}

			if (($mediaTemp->media_type == 'video' || $mediaTemp->media_type == 'audio')
				&& $mediaTemp->requires_transcoding
			)
			{
				$enqueuer = $this->setupTranscodeQueue($mediaTemp, $container, $input);
				if ($enqueuer->validate($errors))
				{
					$enqueuers[] = $enqueuer;
				}
				else
				{
					break;
				}
			}
			else
			{
				$creator = $this->setupMediaItemCreate($mediaTemp, $container, $input);
				$creator->checkForSpam();
				if ($creator->validate($errors))
				{
					$creators[] = $creator;
				}
				else
				{
					break;
				}
			}
		}

		if ($errors)
		{
			return $this->error($errors);
		}

		$session = $this->session();

		foreach ($creators AS $creator)
		{
			$creator->save();
			$this->finalizeMediaItemCreate($creator);
		}

		foreach ($enqueuers AS $enqueuer)
		{
			$enqueuer->save();
			$this->finalizeTranscodeQueue($enqueuer);

			if (!$session->keyExists('xfmgTranscoding'))
			{
				$session->set('xfmgTranscoding', true);
			}
		}

		if (count($creators) && !count($enqueuers))
		{
			$firstCreator = reset($creators);
			$mediaItem = $firstCreator->getMediaItem();
			$redirect = $this->buildLink('media', $mediaItem);
		}
		else
		{
			if ($container instanceof \XFMG\Entity\Category)
			{
				$redirect = $this->buildLink('media/categories', $container);
			}
			else if ($container instanceof \XFMG\Entity\Album && $container->isVisible())
			{
				$redirect = $this->buildLink('media/albums', $container);
			}
			else
			{
				$redirect = $this->buildLink('media');
			}
		}

		return $this->redirect($redirect);
	}

	protected function getContainerToSaveMedia()
	{
		if ($this->request->exists('album_id') && $this->request->exists('category_id'))
		{
			$albumId = $this->filter('album_id','uint');

			/** @var \XFMG\Entity\Album $album */
			$album = $this->em()->find('XFMG:Album', $albumId);
			if ($album)
			{
				if (!$album->canAddMedia($error))
				{
					throw $this->exception($this->noPermission($error));
				}
				return $album;
			}

			$categoryId = $this->filter('category_id', 'uint');

			/** @var \XFMG\Entity\Category $category */
			$category = $this->assertRecordExists('XFMG:Category', $categoryId);
			if (!$category->canAddMedia($error))
			{
				throw $this->exception($this->noPermission($error));
			}

			$creator = $this->setupAlbumCreate();

			$creator->setCategory($category);
			$creator->checkForSpam();

			if (!$creator->validate($errors))
			{
				throw $this->exception($this->error($errors));
			}

			$album = $creator->save();
			$this->finalizeAlbumCreate($creator);

			if (!$album->canAddMedia($error))
			{
				throw $this->exception($this->noPermission($error));
			}

			return $album;
		}
		else if ($this->request->exists('album_id'))
		{
			$albumId = $this->filter('album_id','uint');

			/** @var \XFMG\Entity\Album $album */
			$album = $this->em()->find('XFMG:Album', $albumId);
			if (!$album)
			{
				$creator = $this->setupAlbumCreate();

				$creator->checkForSpam();

				if (!$creator->validate($errors))
				{
					throw $this->exception($this->error($errors));
				}

				$album = $creator->save();
				$this->finalizeAlbumCreate($creator);
			}
			if (!$album->canAddMedia($error))
			{
				throw $this->exception($this->noPermission($error));
			}

			return $album;
		}
		else if ($this->request->exists('category_id'))
		{
			$categoryId = $this->filter('category_id', 'uint');

			/** @var \XFMG\Entity\Category $category */
			$category = $this->assertRecordExists('XFMG:Category', $categoryId);
			if (!$category->canAddMedia($error))
			{
				throw $this->exception($this->noPermission($error));
			}

			return $category;
		}

		throw $this->exception($this->error(\XF::phrase('xfmg_cannot_ascertain_correct_album_or_category_to_save_media_to')));
	}

	protected function prepareMediaInput(array $rawInput)
	{
		$input = $this->app->inputFilterer()->filterArray($rawInput, [
			'title' => 'str',
			'description' => 'str',
			'attachment_id' => 'uint',
			'temp_media_id' => 'uint',
			'media_hash' => 'str',
			'media_type' => 'str',
			'temp_media_embed_url' => 'str',
			'temp_media_tag' => 'str',
			'tags' => 'str',
			'custom_fields' => 'array'
		]);

		$input['attachment_hash'] = $this->filter('attachment_hash', 'str');

		return $input;
	}

	/**
	 * @param \XFMG\Entity\MediaItem $mediaItem
	 *
	 * @return \XFMG\Service\Media\Editor
	 */
	protected function setupMediaItemEdit(\XFMG\Entity\MediaItem $mediaItem)
	{
		$title = $this->filter('title', 'str');
		$description = $this->filter('description', 'str');

		/** @var \XFMG\Service\Media\Editor $editor */
		$editor = $this->service('XFMG:Media\Editor', $mediaItem);
		$editor->setTitle($title, $description);

		if ($mediaItem->media_type == 'embed')
		{
			$embedUrl = $this->filter('media_embed_url', 'str');
			$editor->setEmbedUrl($embedUrl);
		}

		$customFields = $this->filter('custom_fields', 'array');
		$editor->setCustomFields($customFields);

		if ($this->filter('author_alert', 'bool') && $mediaItem->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	protected function finalizeMediaItemEdit(\XFMG\Service\Media\Editor $editor) {}

	public function actionEdit(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canEdit($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$editor = $this->setupMediaItemEdit($mediaItem);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			$this->finalizeMediaItemEdit($editor);

			return $this->redirect($this->buildLink('media', $mediaItem));
		}
		else
		{
			$viewParams = [
				'mediaItem' => $mediaItem
			];
			return $this->view('XFMG:Media\Edit', 'xfmg_media_edit', $viewParams);
		}
	}

	public function actionDelete(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$mediaItem->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \XFMG\Service\Media\Deleter $deleter */
			$deleter = $this->service('XFMG:Media\Deleter', $mediaItem);

			if ($this->filter('author_alert', 'bool') && $mediaItem->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin('XF:InlineMod')->clearIdFromCookie('xfmg_media', $mediaItem->media_id);

			return $this->redirect($this->buildLink('media'));
		}
		else
		{
			$viewParams = [
				'mediaItem' => $mediaItem
			];
			return $this->view('XFMG:Media\Delete', 'xfmg_media_delete', $viewParams);
		}
	}

	public function actionUndelete(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		/** @var \XF\ControllerPlugin\Undelete $plugin */
		$plugin = $this->plugin('XF:Undelete');
		return $plugin->actionUndelete(
			$mediaItem,
			$this->buildLink('media/undelete', $mediaItem),
			$this->buildLink('media', $mediaItem),
			$mediaItem->title,
			'media_state'
		);
	}

	public function actionApprove(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			/** @var \XFMG\Service\Media\Approver $approver */
			$approver = $this->service('XFMG:Media\Approver', $mediaItem);
			$approver->approve();

			return $this->redirect($this->buildLink('media', $mediaItem));
		}
		else
		{
			$viewParams = [
				'mediaItem' => $mediaItem,
			];
			return $this->view('XFMG:Media\Approve', 'xfmg_media_approve', $viewParams);
		}
	}

	public function actionTags(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canEditTags($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\Service\Tag\Changer $tagger */
		$tagger = $this->service('XF:Tag\Changer', 'xfmg_media', $mediaItem);

		if ($this->isPost())
		{
			$tagger->setEditableTags($this->filter('tags', 'str'));
			if ($tagger->hasErrors())
			{
				return $this->error($tagger->getErrors());
			}

			$tagger->save();

			if ($this->filter('_xfInlineEdit', 'bool'))
			{
				$viewParams = [
					'mediaItem' => $mediaItem
				];
				$reply = $this->view('XFMG:Media\TagsInline', 'xfmg_media_tags_list', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('media', $mediaItem));
			}
		}
		else
		{
			$grouped = $tagger->getExistingTagsByEditability();

			$viewParams = [
				'mediaItem' => $mediaItem,
				'editableTags' => $grouped['editable'],
				'uneditableTags' => $grouped['uneditable']
			];
			return $this->view('XFMG:Media\Tags', 'xfmg_media_tags', $viewParams);
		}
	}

	public function actionWatch(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canWatch($error))
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

			/** @var \XFMG\Repository\MediaWatch $watchRepo */
			$watchRepo = $this->repository('XFMG:MediaWatch');
			$watchRepo->setWatchState($mediaItem, $visitor, $action, $config);

			$redirect = $this->redirect($this->buildLink('media', $mediaItem));
			$redirect->setJsonParam('switchKey', $action == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}
		else
		{
			$viewParams = [
				'mediaItem' => $mediaItem,
				'isWatched' => !empty($mediaItem->Watch[$visitor->user_id])
			];
			return $this->view('XFMG:Media\Watch', 'xfmg_media_watch', $viewParams);
		}
	}

	public function actionIp(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		$breadcrumbs = $mediaItem->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($mediaItem, $breadcrumbs);
	}

	public function actionReport(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'xfmg_media', $mediaItem,
			$this->buildLink('media/report', $mediaItem),
			$this->buildLink('media', $mediaItem)
		);
	}

	public function actionBookmark(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		/** @var \XF\ControllerPlugin\Bookmark $bookmarkPlugin */
		$bookmarkPlugin = $this->plugin('XF:Bookmark');

		return $bookmarkPlugin->actionBookmark(
			$mediaItem, $this->buildLink('media/bookmark', $mediaItem)
		);
	}

	public function actionReact(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($mediaItem, 'media');
	}

	public function actionReactions(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		$breadcrumbs = $mediaItem->getBreadcrumbs();
		$title = \XF::phrase('xfmg_members_who_reacted_to_media', ['title' => $mediaItem->title]);

		$this->request()->set('page', $params->page);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactions(
			$mediaItem,
			'media/reactions',
			$title, $breadcrumbs
		);
	}

	public function actionSetAsAvatar(ParameterBag $params)
	{
		$visitor = \XF::visitor();
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		if (!$mediaItem->canSetAsAvatar())
		{
			return $this->noPermission();
		}

		$abstractedDataPath = $mediaItem->getAvailableAbstractedDataPath();
		if (!$abstractedDataPath)
		{
			return $this->noPermission();
		}

		$tempFile = \XF\Util\File::copyAbstractedPathToTempFile($abstractedDataPath);

		if ($this->isPost())
		{
			/** @var \XF\Service\User\Avatar $avatarService */
			$avatarService = $this->service('XF:User\Avatar', $visitor);
			if (!$avatarService->setImage($tempFile))
			{
				return $this->error($avatarService->getError());
			}

			if ($avatarService->updateAvatar())
			{
				$this->session()->set('xfmgAvatarUpdated', true);
			}

			return $this->redirect($this->buildLink('media', $mediaItem));
		}
		else
		{
			$imageManager = $this->app->imageManager();
			$image = $imageManager->imageFromFile($tempFile);

			if (!$image)
			{
				return $this->error(\XF::phrase('unexpected_error_occurred'));
			}

			$image->resizeAndCrop($this->app->container('avatarSizeMap')['l']);

			$previewUri = $image->getDataUri($tempFile);

			$viewParams = [
				'mediaItem' => $mediaItem,
				'previewUri' => $previewUri
			];
			return $this->view('XFMG:Media\SetAsAvatar', 'xfmg_media_set_as_avatar', $viewParams);
		}
	}

	public function actionWarn(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);

		if (!$mediaItem->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $mediaItem->getBreadcrumbs();

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'xfmg_media', $mediaItem,
			$this->buildLink('media/warn', $mediaItem),
			$breadcrumbs
		);
	}

	public function actionModeratorActions(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canViewModeratorLogs($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $mediaItem->getBreadcrumbs();
		$title = $mediaItem->title;

		$this->request()->set('page', $params->page);

		/** @var \XF\ControllerPlugin\ModeratorLog $modLogPlugin */
		$modLogPlugin = $this->plugin('XF:ModeratorLog');
		return $modLogPlugin->actionModeratorActions(
			$mediaItem,
			['media/moderator-actions', $mediaItem],
			$title, $breadcrumbs
		);
	}

	/**
	 * @param \XFMG\Entity\MediaItem $mediaItem
	 * @param array $cropData
	 *
	 * @return \XFMG\Service\Media\ImageEditor
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function getImageEditorService(\XFMG\Entity\MediaItem $mediaItem, array $cropData)
	{
		/** @var \XFMG\Service\Media\ImageEditor $imageEditor */
		$imageEditor = $this->service('XFMG:Media\ImageEditor', $mediaItem, $cropData);

		if (!$imageEditor->validateCropData())
		{
			throw $this->exception($this->error(\XF::phrase('xfmg_crop_data_provided_indicates_there_no_valid_manipulations_to_apply')));
		}

		return $imageEditor;
	}

	public function actionEditImage(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canEditImage($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$cropData = $this->filter('crop_data', 'json-array');
			$imageEditor = $this->getImageEditorService($mediaItem, $cropData);

			if ($this->request->exists('preview'))
			{
				$previewUri = $imageEditor->preview();

				$viewParams = [
					'mediaItem' => $mediaItem,
					'cropData' => $cropData,
					'previewUri' => $previewUri
				];
				return $this->view('XFMG\Media\EditImagePreview', 'xfmg_media_edit_image_preview', $viewParams);
			}
			else
			{
				if (!$imageEditor->save())
				{
					return $this->error(\XF::phrase('xfmg_unable_to_edit_selected_image'));
				}

				return $this->redirect($this->buildLink('media', $mediaItem));
			}
		}
		else
		{
			$viewParams = [
				'mediaItem' => $mediaItem
			];
			return $this->view('XFMG:Media\EditImage', 'xfmg_media_edit_image', $viewParams);
		}
	}

	/**
	 * @param \XFMG\Entity\MediaItem $mediaItem
	 *
	 * @return \XFMG\Service\Media\ThumbnailChanger
	 */
	protected function getThumbnailChangerService(\XFMG\Entity\MediaItem $mediaItem)
	{
		$service = $this->service('XFMG:Media\ThumbnailChanger', $mediaItem);

		return $service;
	}

	public function actionChangeThumbnail(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canChangeThumbnail($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$changer = $this->getThumbnailChangerService($mediaItem);

			$thumbnailType = $this->filter('thumbnail_type', 'str');
			if ($thumbnailType == 'default')
			{
				$changer->useDefaultThumbnail();
			}
			else
			{
				$upload = $this->request->getFile('upload', false, false);

				if (!$upload)
				{
					return $this->error(\XF::phrase('xfmg_new_thumbnail_could_not_be_processed'));
				}

				if (!$changer->setThumbnailFromUpload($upload))
				{
					return $this->error($changer->getError());
				}

				if (!$changer->updateThumbnail())
				{
					return $this->error(\XF::phrase('xfmg_new_thumbnail_could_not_be_processed'));
				}
			}

			return $this->redirect($this->buildLink('media', $mediaItem));
		}
		else
		{
			$viewParams = [
				'mediaItem' => $mediaItem
			];
			return $this->view('XFMG:Media\ChangeThumbnail', 'xfmg_media_change_thumbnail', $viewParams);
		}
	}

	/**
	 * @return \XFMG\Service\Media\Mover
	 */
	protected function setupMediaMove(\XFMG\Entity\MediaItem $mediaItem)
	{
		$targetType = $this->filter('target_type', 'str');

		$targetAlbum = null;
		$targetCategory = null;

		if ($targetType == 'category')
		{
			$targetCategoryId = $this->filter('target_category_id', 'uint');
			$targetCategory = $this->assertViewableCategory($targetCategoryId);
			if (!$mediaItem->canMoveMediaTo($targetCategory, $error))
			{
				throw $this->exception($this->noPermission($error));
			}
		}
		else
		{
			$albumType = $this->filter('album_type', 'str');

			if ($albumType == 'create')
			{
				$albumCreator = $this->setupAlbumCreate();

				$albumCreator->checkForSpam();

				if (!$albumCreator->validate($errors))
				{
					throw $this->exception($this->error($errors));
				}

				$targetAlbum = $albumCreator->save();
			}
			else
			{
				$targetAlbumUrl = $this->filter('album_url', 'str');

				$targetAlbum = $this->getAlbumRepo()->getAlbumFromUrl($targetAlbumUrl, $error);
				if (!$targetAlbum)
				{
					throw $this->exception($this->error($error));
				}

				if ($targetAlbum->category_id)
				{
					$targetCategory = $targetAlbum->Category;
				}
			}

			if (!$mediaItem->canMoveMediaTo($targetAlbum, $error))
			{
				throw $this->exception($this->noPermission($error));
			}
		}

		/** @var \XFMG\Service\Media\Mover $mover */
		$mover = $this->service('XFMG:Media\Mover', $targetCategory, $targetAlbum);

		if ($this->filter('author_alert', 'bool') && $mediaItem->canSendModeratorActionAlert())
		{
			$mover->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $mover;
	}

	public function actionMove(ParameterBag $params)
	{
		$mediaItem = $this->assertViewableMediaItem($params->media_id);
		if (!$mediaItem->canMove($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$mover = $this->setupMediaMove($mediaItem);
			$mover->move($mediaItem);

			return $this->redirect($this->buildLink('media', $mediaItem));
		}
		else
		{
			$categoryRepo = $this->getCategoryRepo();
			$categoryList = $categoryRepo->getViewableCategories();

			$categoryTree = $categoryRepo->createCategoryTree($categoryList);
			$categoryTree = $categoryTree->filter(null, function($id, \XFMG\Entity\Category $category, $depth, $children, \XF\Tree $tree) use ($mediaItem)
			{
				return ($children || ($category->category_type == 'media' && $mediaItem->canMoveMediaTo($category)));
			});

			$viewParams = [
				'mediaItem' => $mediaItem,
				'categoryTree' => $categoryTree
			];
			return $this->view('XFMG:Media\Move', 'xfmg_media_move_chooser', $viewParams);
		}
	}

	public function actionMarkViewed()
	{
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
			$categoryRepo = $this->getCategoryRepo();
			$mediaRepo = $this->getMediaRepo();

			$categoryList = $categoryRepo->getViewableCategories();
			$categoryIds = $categoryList->keys();

			$mediaRepo->markMediaViewedByVisitor($categoryIds, $markDate);
			$mediaRepo->markAllMediaCommentsReadByVisitor($categoryIds, $markDate);

			return $this->redirect(
				$this->buildLink('media'),
				\XF::phrase('xfmg_all_media_items_marked_as_viewed')
			);
		}
		else
		{
			$viewParams = [
				'date' => $markDate
			];
			return $this->view('XFMG:Media\MarkViewed', 'xfmg_media_mark_viewed', $viewParams);
		}
	}

	public function actionDialogYours()
	{
		$categoryRepo = $this->getCategoryRepo();

		$categoryList = $categoryRepo->getViewableCategories();
		$viewableCategories = $categoryList->filter(function($category)
		{
			return ($category->category_type == 'media' || $category->category_type == 'album');
		});
		$categoryIds = $viewableCategories->keys();

		$page = $this->filterPage();
		$perPage = $this->options()->xfmgMediaPerPage;

		$mediaRepo = $this->getMediaRepo();
		$mediaList = $mediaRepo->findMediaForUser(\XF::visitor(), $categoryIds, ['visibility' => false])
			->where('media_state', 'visible')
			->limitByPage($page, $perPage, 1);

		$mediaItems = $mediaList->fetch();

		$hasMore = false;
		if ($mediaItems->count() > $perPage)
		{
			$hasMore = true;
			$mediaItems = $mediaItems->slice(0, $perPage);
		}

		$viewParams = [
			'mediaItems' => $mediaItems,
			'page' => $page,
			'hasMore' => $hasMore
		];
		return $this->view('XFMG:Media\Dialog\Yours', 'xfmg_dialog_your_media', $viewParams);
	}

	public function actionDialogBrowse()
	{
		$categoryRepo = $this->getCategoryRepo();

		$categoryList = $categoryRepo->getViewableCategories();
		$viewableCategories = $categoryList->filter(function($category)
		{
			return ($category->category_type == 'media' || $category->category_type == 'album');
		});
		$categoryIds = $viewableCategories->keys();

		$page = $this->filterPage();
		$perPage = $this->options()->xfmgMediaPerPage;

		$mediaRepo = $this->getMediaRepo();
		$mediaList = $mediaRepo->findMediaForIndex($categoryIds, ['visibility' => false])
			->where('media_state', 'visible')
			->where('user_id', '<>', \XF::visitor()->user_id)
			->limitByPage($page, $perPage, 1);

		$mediaItems = $mediaList->fetch();

		$hasMore = false;
		if ($mediaItems->count() > $perPage)
		{
			$hasMore = true;
			$mediaItems = $mediaItems->slice(0, $perPage);
		}

		$viewParams = [
			'mediaItems' => $mediaItems,
			'page' => $page,
			'hasMore' => $hasMore
		];
		return $this->view('XFMG:Media\Dialog\Browse', 'xfmg_dialog_browse_media', $viewParams);
	}

	public function actionUsers(ParameterBag $params)
	{
		/** @var \XF\Entity\User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');

		$categoryParams = $mediaListPlugin->getCategoryListData();
		$viewableCategoryIds = $categoryParams['viewableCategories']->keys();

		$listParams = $mediaListPlugin->getMediaListData($viewableCategoryIds, $params->page, $user);

		$this->assertValidPage($listParams['page'], $listParams['perPage'], $listParams['totalItems'], 'media/users', $user);
		$this->assertCanonicalUrl($this->buildLink('media/users', $user, ['page' => $listParams['page']]));

		$listParams['prevPage'] = ($listParams['page'] > 1) ? $this->buildLink('media/users', $user, ['page' => $listParams['page'] - 1] + $listParams['filters']) : null;
		$listParams['nextPage'] = ($listParams['page'] < ceil($listParams['totalItems'] / $listParams['perPage'])) ? $this->buildLink('media/users', $user, ['page' => $listParams['page'] + 1] + $listParams['filters']) : null;

		$viewParams = $categoryParams + $listParams;

		return $this->view('XFMG:Media\User\Index', 'xfmg_media_user_index', $viewParams);
	}

	public function actionUsersFilters(ParameterBag $params)
	{
		/** @var \XF\Entity\User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var \XFMG\ControllerPlugin\MediaList $mediaListPlugin */
		$mediaListPlugin = $this->plugin('XFMG:MediaList');

		return $mediaListPlugin->actionFilters(null, $user);
	}

	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities, \XF::phrase('xfmg_viewing_media_item'), 'media_id',
			function(array $ids)
			{
				$mediaItems = \XF::em()->findByIds(
					'XFMG:MediaItem',
					$ids,
					['Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($mediaItems->filterViewable() AS $id => $mediaItem)
				{
					$data[$id] = [
						'title' => $mediaItem->title,
						'url' => $router->buildLink('media', $mediaItem)
					];
				}

				return $data;
			},
			\XF::phrase('xfmg_viewing_media_items')
		);
	}
}