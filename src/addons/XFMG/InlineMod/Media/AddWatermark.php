<?php

namespace XFMG\InlineMod\Media;

use XF\InlineMod\AbstractAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

class AddWatermark extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('xfmg_watermark_media_items...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		/** @var \XFMG\Entity\MediaItem $entity */
		return ($entity->canAddWatermark(true, $error));
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var \XFMG\Entity\MediaItem $entity */
		if ($entity->canAddWatermark())
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = \XF::repository('XFMG:Media');
			$tempWatermark = $mediaRepo->getWatermarkAsTempFile();

			/** @var \XFMG\Service\Media\Watermarker $watermarker */
			$watermarker = \XF::service('XFMG:Media\Watermarker', $entity, $tempWatermark);
			$watermarker->watermark();
		}
	}

	public function renderForm(AbstractCollection $entities, \XF\Mvc\Controller $controller)
	{
		$viewParams = [
			'mediaItems' => $entities,
			'total' => count($entities)
		];
		return $controller->view('XFMG:Public:InlineMod\Media\AddWatermark', 'xfmg_inline_mod_media_add_watermark', $viewParams);
	}
}