<?php

namespace XFRM\Job;

use XF\Job\AbstractEmbedMetadataJob;
use XF\Mvc\Entity\Entity;

class ResourceUpdateEmbedMetadata extends AbstractEmbedMetadataJob
{
	protected function getIdsToRebuild(array $types)
	{
		// See XF Bug #153298 as to why we are not using the xf_conversation_message table right now
		return $this->getIdsBug153298Workaround('resource_update');

		$db = $this->app->db();

		// Note: only attachments are supported currently, so we filter based on attach count for efficiency.
		// If other types become available, this condition will need to change.
//		return $db->fetchAllColumn($db->limit(
//			"
//				SELECT resource_update_id
//				FROM xf_rm_resource_update
//				WHERE resource_update_id > ?
//					AND attach_count > 0
//				ORDER BY resource_update_id
//			", $this->data['batch']
//		), $this->data['start']);
	}

	protected function getRecordToRebuild($id)
	{
		return $this->app->em()->find('XFRM:ResourceUpdate', $id);
	}

	protected function getPreparerContext()
	{
		return 'resource_update';
	}

	protected function getMessageContent(Entity $record)
	{
		return $record->message;
	}

	protected function rebuildAttachments(Entity $record, \XF\Service\Message\Preparer $preparer, array &$embedMetadata)
	{
		$embedMetadata['attachments'] = $preparer->getEmbeddedAttachments();
	}

	protected function getActionDescription()
	{
		$rebuildPhrase = \XF::phrase('rebuilding');
		$type = \XF::phrase('xfrm_resources');
		return sprintf('%s... %s', $rebuildPhrase, $type);
	}
}