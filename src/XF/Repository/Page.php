<?php

namespace XF\Repository;

use XF\Mvc\Entity\Repository;

class Page extends Repository
{
	public function logView(\XF\Entity\Page $page, \XF\Entity\User $user)
	{
		// TODO: update batching?
		$this->db()->query(
			'-- XFDB=noForceAllWrite
				UPDATE xf_page
				SET view_count = view_count + 1
				WHERE node_id = ?',
			[$page->node_id]
		);
	}
}