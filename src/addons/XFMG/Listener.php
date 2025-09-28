<?php

namespace XFMG;

use XF\Util\Arr;

class Listener
{
	public static function userContentChangeInit(\XF\Service\User\ContentChange $changeService, array &$updates)
	{
		$updates['xf_mg_album'] = [
			['user_id', 'username'],
			['last_comment_user_id', 'last_comment_username']
		];
		$updates['xf_mg_album_comment_read'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_album_watch'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_category_watch'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_comment'] = ['user_id', 'username'];
		$updates['xf_mg_media_comment_read'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_media_item'] = [
			['user_id', 'username'],
			['last_comment_user_id', 'last_comment_username']
		];
		$updates['xf_mg_media_note'] = [
			['user_id', 'username'],
			['tagged_user_id', 'tagged_username']
		];
		$updates['xf_mg_media_temp'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_media_watch'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_rating'] = ['user_id', 'username'];
		$updates['xf_mg_shared_map_add'] = ['user_id', 'emptyable' => false];
		$updates['xf_mg_shared_map_view'] = ['user_id', 'emptyable' => false];
	}

	public static function userDeleteCleanInit(\XF\Service\User\DeleteCleanUp $deleteService, array &$deletes)
	{
		$deletes['xf_mg_album_comment_read'] = 'user_id = ?';
		$deletes['xf_mg_album_watch'] = 'user_id = ?';
		$deletes['xf_mg_category_watch'] = 'user_id = ?';
		$deletes['xf_mg_media_comment_read'] = 'user_id = ?';
		$deletes['xf_mg_media_temp'] = 'user_id = ?';
		$deletes['xf_mg_media_user_view'] = 'user_id = ?';
		$deletes['xf_mg_media_watch'] = 'user_id = ?';
		$deletes['xf_mg_shared_map_add'] = 'user_id = ?';
		$deletes['xf_mg_shared_map_view'] = 'user_id = ?';
	}

	public static function userMergeCombine(
		\XF\Entity\User $target, \XF\Entity\User $source, \XF\Service\User\Merge $mergeService
	)
	{
		$target->xfmg_media_quota += $source->xfmg_media_quota;
		$target->xfmg_media_count += $source->xfmg_media_count;
		$target->xfmg_album_count += $source->xfmg_album_count;
	}

	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		$sortOrders = array_replace($sortOrders, [
			'xfmg_media_count' => \XF::phrase('xfmg_media_count'),
			'xfmg_album_count' => \XF::phrase('xfmg_album_count')
		]);
	}

	public static function memberStatResultPrepare($order, array &$cacheResults)
	{
		switch ($order)
		{
			case 'xfmg_media_count':
			case 'xfmg_album_count':
				$cacheResults = array_map(function($value)
				{
					return \XF::language()->numberFormat($value);
				}, $cacheResults);
				break;
		}
	}

	public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
	{
		/** @var \XFMG\Template\TemplaterSetup $templaterSetup */
		$class = \XF::extendClass('XFMG\Template\TemplaterSetup');
		$templaterSetup = new $class();

		$templater->addFunction('xfmg_allowed_media', [$templaterSetup, 'fnAllowedMedia']);
		$templater->addFunction('xfmg_watermark', [$templaterSetup, 'fnWatermark']);
		$templater->addFunction('xfmg_thumbnail', [$templaterSetup, 'fnThumbnail']);
	}

	public static function navigationSetup(\XF\Pub\App $app, array &$navigationFlat, array &$navigationTree)
	{
		if (isset($navigationFlat['xfmg']) && self::visitor()->canViewMedia() && \XF::options()->xfmgUnviewedCounter)
		{
			$session = $app->session();

			$mediaUnviewed = $session->get('xfmgUnviewedMedia');
			if ($mediaUnviewed)
			{
				$navigationFlat['xfmg']['counter'] = count($mediaUnviewed['unviewed']);
			}
		}
	}

	public static function appSetup(\XF\App $app)
	{
		$container = $app->container();

		$container['customFields.xfmgMediaFields'] = $app->fromRegistry('xfmgMediaFields',
			function(\XF\Container $c) { return $c['em']->getRepository('XFMG:MediaField')->rebuildFieldCache(); },
			function(array $mediaFieldsInfo) use ($app)
			{
				$class = 'XF\CustomField\DefinitionSet';
				$class = $app->extendClass($class);

				$definitionSet = new $class($mediaFieldsInfo);
				$definitionSet->addFilter('display_add_media', function(array $field)
				{
					return (bool)$field['display_add_media'];
				});
				return $definitionSet;
			}
		);
	}

	public static function appPubStartEnd(\XF\Pub\App $app)
	{
		$visitor = self::visitor();

		if ($visitor->user_id && $visitor->canViewMedia() && \XF::options()->xfmgUnviewedCounter)
		{
			$session = $app->session();

			$mediaUnviewed = array_replace([
				'unviewed' => [],
				'lastUpdateDate' => 0
			], $session->get('xfmgUnviewedMedia') ?: []);

			if ($mediaUnviewed['lastUpdateDate']  < (\XF::$time - 5 * 60)) // 5 minutes
			{
				$categoryRepo = \XF::repository('XFMG:Category');
				$categoryList = $categoryRepo->getViewableCategories();
				$categoryIds = $categoryList->keys();

				$mediaRepo = \XF::repository('XFMG:Media');
				$mediaItems = $mediaRepo->findMediaForIndex($categoryIds)
					->unviewedOnly($visitor->user_id)
					->orderByDate()
					->fetch();

				if ($mediaItems->count())
				{
					$mediaUnviewed['unviewed'] = array_fill_keys($mediaItems->keys(), true);
				}
			}

			$mediaUnviewed['lastUpdateDate'] = \XF::$time;
			$session->set('xfmgUnviewedMedia', $mediaUnviewed);
		}
	}

	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'xfmg_media_count':
				if (isset($user->xfmg_media_count) && $user->xfmg_media_count >= $data['media_items'])
				{
					$returnValue = true;
				}
				break;

			case 'xfmg_album_count':
				if (isset($user->xfmg_album_count) && $user->xfmg_album_count >= $data['albums'])
				{
					$returnValue = true;
				}
				break;
		}
	}

	public static function criteriaPage($rule, array $data, \XF\Entity\User $user, array $params, &$returnValue)
	{
		if ($rule === 'xfmg_categories')
		{
			$returnValue = false;

			if (!empty($data['category_ids']))
			{
				$selectedCategoryIds = $data['category_ids'];

				if (isset($params['breadcrumbs']) && is_array($params['breadcrumbs']) && empty($data['category_only']))
				{
					foreach ($params['breadcrumbs'] AS $i => $navItem)
					{
						if (
							isset($navItem['attributes']['category_id'])
							&& in_array($navItem['attributes']['category_id'], $selectedCategoryIds)
						)
						{
							$returnValue = true;
						}
					}
				}

				if (!empty($params['containerKey']))
				{
					list ($type, $id) = explode('-', $params['containerKey'], 2);

					if ($type == 'xfmgCategory' && $id && in_array($id, $selectedCategoryIds))
					{
						$returnValue = true;
					}
				}
			}
		}
	}

	public static function criteriaTemplateData(array &$templateData)
	{
		$categoryRepo = \XF::repository('XFMG:Category');
		$templateData['xfmgCategories'] = $categoryRepo->getCategoryOptionsData(false);
	}

	public static function templaterTemplatePreRenderPublicEditor(\XF\Template\Templater $templater, &$type, &$template, array &$params)
	{
		if (!self::visitor()->canViewMedia())
		{
			$params['removeButtons'][] = 'xfCustom_gallery';
		}
	}

	public static function editorDialog(array &$data, \XF\Pub\Controller\AbstractController $controller)
	{
		$controller->assertRegistrationRequired();

		$data['template'] = 'xfmg_editor_dialog_gallery';
	}

	public static function importImporterClasses(\XF\SubContainer\Import $container, \XF\Container $parentContainer, array &$importers)
	{
		$importers = array_merge(
			$importers, \XF\Import\Manager::getImporterShortNamesForType('XFMG')
		);
	}

	/**
	 * @return \XFMG\XF\Entity\User
	 */
	public static function visitor()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor;
	}
}