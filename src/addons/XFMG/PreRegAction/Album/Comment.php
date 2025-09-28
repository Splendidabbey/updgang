<?php

namespace XFMG\PreRegAction\Album;

use XFMG\PreRegAction\AbstractCommentHandler;

class Comment extends AbstractCommentHandler
{
	public function getContainerContentType(): string
	{
		return 'xfmg_album';
	}
}