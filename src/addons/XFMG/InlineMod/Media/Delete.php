<?php

namespace XFMG\InlineMod\Media;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\InlineMod\AlertSendableTrait;

class Delete extends AbstractAction
{
	use AlertSendableTrait;

	public function getTitle()
	{
		return \XF::phrase('xfmg_delete_media_items...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		/** @var \XFMG\Entity\MediaItem $entity */
		return $entity->canDelete($options['type'], $error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var \XFMG\Service\Media\Deleter $deleter */
		/** @var \XFMG\Entity\MediaItem $entity */
		$deleter = $this->app()->service('XFMG:Media\Deleter', $entity);

		if ($options['alert'] && $entity->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);
	}

	public function getBaseOptions()
	{
		return [
			'type' => 'soft',
			'reason' => '',
			'alert' => false,
			'alert_reason' => ''
		];
	}

	public function renderForm(AbstractCollection $entities, \XF\Mvc\Controller $controller)
	{
		$viewParams = [
			'mediaItems' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
			'canSendAlert' => $this->canSendAlert($entities)
		];
		return $controller->view('XFMG:Public:InlineMod\Media\Delete', 'xfmg_inline_mod_media_delete', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'type' => $request->filter('hard_delete', 'bool') ? 'hard' : 'soft',
			'reason' => $request->filter('reason', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str')
		];
	}
}