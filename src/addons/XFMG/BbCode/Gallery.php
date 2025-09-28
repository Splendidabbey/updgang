<?php

namespace XFMG\BbCode;

class Gallery
{
	public static function renderTagGallery($tagChildren, $tagOption, $tag, array $options, \XF\BbCode\Renderer\AbstractRenderer $renderer)
	{
		if (!$tag['option'])
		{
			return $renderer->renderUnparsedTag($tag, $options);
		}

		$parts = explode(',', $tag['option']);
		foreach ($parts AS &$part)
		{
			$part = trim($part);
			$part = str_replace(' ', '', $part);
		}

		$type = $renderer->filterString(array_shift($parts),
			array_merge($options, [
				'stopSmilies' => true,
				'stopLineBreakConversion' => true
			])
		);
		$type = strtolower($type);
		$id = array_shift($parts);

		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewMedia()
			|| $renderer instanceof \XF\BbCode\Renderer\SimpleHtml
			|| $renderer instanceof \XF\BbCode\Renderer\EmailHtml
		)
		{
			return self::renderTagSimple($type, $id);
		}

		$viewParams = [
			'type' => $type,
			'id' => intval($id),
			'text' => isset($tag['children']) ? $tag['children'] : ''
		];

		if ($type == 'media')
		{
			if (isset($options['galleryMedia'][$id]))
			{
				$mediaItem = $options['galleryMedia'][$id];
			}
			else
			{
				$mediaItem = \XF::em()->find('XFMG:MediaItem', $id, [
					'Category.Permissions|' . $visitor->permission_combination_id
				]);
			}
			if (!$mediaItem || !$mediaItem->canView())
			{
				return self::renderTagSimple($type, $id);
			}
			else if ($visitor->isIgnoring($mediaItem->user_id))
			{
				return '';
			}
			$viewParams['mediaItem'] = $mediaItem;

			return $renderer->getTemplater()->renderTemplate('public:xfmg_gallery_bb_code_media', $viewParams);
		}
		else if ($type == 'album')
		{
			if (isset($options['galleryAlbums'][$id]))
			{
				$album = $options['galleryAlbums'][$id];
			}
			else
			{
				$album = \XF::em()->find('XFMG:Album', $id, [
					'Category.Permissions|' . $visitor->permission_combination_id
				]);
			}
			if (!$album || !$album->canView())
			{
				return self::renderTagSimple($type, $id);
			}
			else if ($visitor->isIgnoring($album->user_id))
			{
				return '';
			}

			$mediaItems = $album->MediaCache->filterViewable();

			// Show up to 10 thumbs or 9 plus an indicator that there are X more
			$showXMore = $mediaItems->count() > 10 ? true : false;
			$length = $showXMore ? 9 : 10;
			$mediaItems = $mediaItems->slice(0, $length);

			$viewParams['album'] = $album;
			$viewParams['mediaItems'] = $mediaItems;
			$viewParams['showXMore'] = $showXMore;
			$viewParams['placeholders'] = array_fill(0, (10 - $mediaItems->count() - ($showXMore ? 1 : 0)), true);

			return $renderer->getTemplater()->renderTemplate('public:xfmg_gallery_bb_code_album', $viewParams);
		}

		return self::renderTagSimple($type, $id);
	}

	protected static function renderTagSimple($type, $id)
	{
		$router = \XF::app()->router('public');

		switch ($type)
		{
			case 'media':

				$link = $router->buildLink('full:media', ['media_id' => $id]);
				$phrase = \XF::phrase('xfmg_view_media_item_x', ['id' => $id]);

				return '<a href="' . htmlspecialchars($link) .'">' . $phrase .'</a>';

			case 'album':

				$link = $router->buildLink('full:media/albums', ['album_id' => $id]);
				$phrase = \XF::phrase('xfmg_view_album_x', ['id' => $id]);

				return '<a href="' . htmlspecialchars($link) .'">' . $phrase .'</a>';

			default:

				return '[GALLERY]';
		}
	}
}