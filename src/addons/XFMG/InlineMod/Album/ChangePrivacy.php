<?php

namespace XFMG\InlineMod\Album;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\InlineMod\AlertSendableTrait;

class ChangePrivacy extends AbstractAction
{
	use AlertSendableTrait;

	public function getTitle()
	{
		return \XF::phrase('xfmg_change_privacy...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		/** @var \XFMG\Entity\Album $entity */
		return $entity->canChangePrivacy($error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var \XFMG\Service\Album\Editor $editor */
		/** @var \XFMG\Entity\Album $entity */
		$editor = $this->app()->service('XFMG:Album\Editor', $entity);

		if ($options['change_view'])
		{
			$editor->setViewPrivacy($options['view_privacy'], $options['view_users']);
		}
		if ($options['change_add'])
		{
			$editor->setAddPrivacy($options['add_privacy'], $options['add_users']);
		}

		if ($options['alert'] && $entity->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $options['alert_reason']);
		}

		if ($options['change_view'] || $options['change_add'])
		{
			$editor->save();
		}
	}

	public function getBaseOptions()
	{
		return [
			'change_view' => false,
			'view_privacy' => 'private',
			'view_users' => [],
			'change_add' => false,
			'add_privacy' => 'private',
			'add_users' => [],
			'alert' => false,
			'alert_reason' => ''
		];
	}

	public function renderForm(AbstractCollection $entities, \XF\Mvc\Controller $controller)
	{
		$viewParams = [
			'albums' => $entities,
			'total' => count($entities),
			'canSendAlert' => $this->canSendAlert($entities)
		];
		return $controller->view('XFMG:Public:InlineMod\Album\ChangePrivacy', 'xfmg_inline_mod_album_change_privacy', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'change_view' => $request->filter('change_view', 'bool'),
			'view_privacy' => $request->filter('view_privacy', 'str'),
			'view_users' => $request->filter('view_users', 'str'),
			'change_add' => $request->filter('change_add', 'bool'),
			'add_privacy' => $request->filter('add_privacy', 'str'),
			'add_users' => $request->filter('add_users', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str')
		];
	}
}