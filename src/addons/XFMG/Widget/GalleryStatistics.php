<?php

namespace XFMG\Widget;

use XF\Widget\AbstractWidget;

class GalleryStatistics extends AbstractWidget
{
	public function render()
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewMedia') || !$visitor->canViewMedia())
		{
			return '';
		}

		$viewParams = [
			'galleryStatistics' => $this->app->simpleCache()->XFMG->statisticsCache
		];
		return $this->renderer('xfmg_widget_gallery_statistics', $viewParams);
	}

	public function getOptionsTemplate()
	{
		return;
	}
}