<?php

namespace XFRM\InlineMod\ResourceItem;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

class Delete extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('xfrm_delete_resources...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		/** @var \XFRM\Entity\ResourceItem $entity */
		return $entity->canDelete($options['type'], $error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var \XFRM\Entity\ResourceItem $entity */

		/** @var \XFRM\Service\ResourceItem\Delete $deleter */
		$deleter = $this->app()->service('XFRM:ResourceItem\Delete', $entity);

		if ($options['alert'])
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		if ($options['public_delete_reason'] && $entity->canSetPublicDeleteReason())
		{
			$deleter->setPostDeleteReason($options['public_delete_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);

		if ($options['type'] == 'hard')
		{
			$this->returnUrl = $this->app()->router()->buildLink('resources/categories', $entity->Category);
		}
	}

	public function getBaseOptions()
	{
		return [
			'type' => 'soft',
			'reason' => '',
			'public_delete_reason' => '',
			'alert' => false,
			'alert_reason' => ''
		];
	}

	public function renderForm(AbstractCollection $entities, \XF\Mvc\Controller $controller)
	{
		$canSetPublicReason = false;

		/** @var \XFRM\Entity\ResourceItem $entity */
		foreach ($entities AS $entity)
		{
			if ($entity->canSetPublicDeleteReason())
			{
				$canSetPublicReason = true;
				break;
			}
		}

		$viewParams = [
			'resources' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
			'canSetPublicReason' => $canSetPublicReason
		];
		return $controller->view('XFRM:Public:InlineMod\ResourceItem\Delete', 'inline_mod_resource_delete', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'type' => $request->filter('hard_delete', 'bool') ? 'hard' : 'soft',
			'reason' => $request->filter('reason', 'str'),
			'public_delete_reason' => $request->filter('public_delete_reason', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str')
		];
	}
}