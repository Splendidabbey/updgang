<?php

namespace XFRM\Spam\Cleaner;

use XF\Spam\Cleaner\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function canCleanUp(array $options = [])
	{
		return !empty($options['action_threads']);
	}

	public function cleanUp(array &$log, &$error = null)
	{
		$app = \XF::app();

		$resources = $app->finder('XFRM:ResourceItem')
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($resources->count())
		{
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('resource', $resources->keys());

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['resource'] = [
				'deleteType' => $deleteType,
				'resourceIds' => []
			];

			foreach ($resources AS $resourceId => $resource)
			{
				$log['resource']['resourceIds'][] = $resourceId;

				/** @var \XFRM\Entity\ResourceItem $resource */
				$resource->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$resource->softDelete();
				}
				else
				{
					$resource->delete();
				}
			}
		}

		return true;
	}

	public function restore(array $log, &$error = null)
	{
		if ($log['deleteType'] == 'soft')
		{
			$resources = \XF::app()->finder('XFRM:ResourceItem')
				->where('resource_id', $log['resourceIds'])
				->fetch();

			foreach ($resources AS $resource)
			{
				/** @var \XFRM\Entity\ResourceItem $resource */
				$resource->setOption('log_moderator', false);
				$resource->resource_state = 'visible';
				$resource->save();
			}
		}

		return true;
	}
}