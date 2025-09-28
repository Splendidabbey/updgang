<?php

namespace XFMG\Template;

use XF\Util\Arr;

class TemplaterSetup
{
	protected $transparentThumbUri;

	public function fnAllowedMedia(\XF\Template\Templater $templater, &$escape, $type)
	{
		$options = \XF::options();

		switch ($type)
		{
			case 'image':
			case 'video':
			case 'audio':
				$option = 'xfmg' . ucfirst($type) . 'Extensions';
				return Arr::stringToArray($options->{$option});
			case 'embed':
				return \XF::registry()->get('bbCodeMedia');
		}

		throw new \InvalidArgumentException('Unknown media type.');
	}

	public function fnWatermark(\XF\Template\Templater $templater, &$escape, $type)
	{
		$options = \XF::options();
		$watermarkHash = $options->xfmgWatermarking['watermark_hash'];

		if (!$watermarkHash)
		{
			return null;
		}

		if ($type == 'url')
		{
			$path = sprintf("xfmg/watermark/%s.jpg",
				$watermarkHash
			);
			return \XF::app()->applyExternalDataUrl($path);
		}
		else
		{
			return \XF::repository('XFMG:Media')->getAbstractedWatermarkPath($watermarkHash);
		}
	}

	public function fnThumbnail(\XF\Template\Templater $templater, &$escape, \XF\Mvc\Entity\Entity $entity, $additionalClasses = '', $inline = false, $forceType = null)
	{
		if (!($entity instanceof \XFMG\Entity\Album) && !($entity instanceof \XFMG\Entity\MediaItem))
		{
			trigger_error('Thumbnail content must be an Album or MediaItem entity.', E_USER_WARNING);
			return '';
		}

		$escape = false;

		if ($entity instanceof \XFMG\Entity\MediaItem)
		{
			$type = $entity->media_type;
		}
		else
		{
			$type = 'album';
		}

		$class = 'xfmgThumbnail xfmgThumbnail--' . $type;
		if ($additionalClasses)
		{
			$class .= " $additionalClasses";
		}

		if (!$entity->isVisible())
		{
			$class .= ' xfmgThumbnail--notVisible xfmgThumbnail--notVisible--';
			if ($entity->content_type == 'xfmg_media')
			{
				$class .= $entity->media_state;
			}
			else
			{
				$class .= $entity->album_state;
			}
		}

		$thumbnailUrl = null;
		if ($entity->thumbnail_date)
		{
			$thumbnailUrl = $entity->getThumbnailUrl();
		}

		$customThumbnailUrl = null;
		if ($entity->custom_thumbnail_date)
		{
			$customThumbnailUrl = $entity->getCustomThumbnailUrl();
		}

		$outputUrl = null;
		$hasThumbnail = false;
		if ($customThumbnailUrl && $forceType != 'default')
		{
			$outputUrl = $customThumbnailUrl;
			$hasThumbnail = ($entity->custom_thumbnail_date > 0);
		}
		else if ($thumbnailUrl && $forceType != 'custom')
		{
			$outputUrl = $thumbnailUrl;
			$hasThumbnail = ($entity->thumbnail_date> 0);
		}
		if (!$hasThumbnail)
		{
			$class .= ' xfmgThumbnail--noThumb';
			$outputUrl = $this->getTransparentThumbUri($templater);
		}

		$title = $templater->filterForAttr($templater, $entity->title, $null);

		if ($inline)
		{
			$tag = 'span';
		}
		else
		{
			$tag = 'div';
		}

		return "<$tag class='{$class}'>
			<img class='xfmgThumbnail-image' src='{$outputUrl}' loading='lazy' alt='{$title}' />
			<span class='xfmgThumbnail-icon'></span>
		</$tag>";
	}

	protected function getTransparentThumbUri(\XF\Template\Templater $templater)
	{
		if (!$this->transparentThumbUri)
		{
			$thumbDims = \XF::options()->xfmgThumbnailDimensions;

			if ($thumbDims['width'] != $thumbDims['height']) // only applies to non-square aspect ratio
			{
				// TODO: Only generated max. once per page load but consider permanently caching this.

				$img = imagecreatetruecolor($thumbDims['width'], $thumbDims['height']);
				imagesavealpha($img, true);

				$color = imagecolorallocatealpha($img, 255, 255, 255, 127);
				imagefill($img, 0, 0, $color);

				ob_start();
				imagepng($img);
				$contents = ob_get_contents();
				imagedestroy($img);
				ob_end_clean();

				$uri = 'data:image/png;base64,' . base64_encode($contents);
			}
			else
			{
				$uri = $templater->func('transparent_img');
			}

			$this->transparentThumbUri = $uri;
		}

		return $this->transparentThumbUri;
	}
}