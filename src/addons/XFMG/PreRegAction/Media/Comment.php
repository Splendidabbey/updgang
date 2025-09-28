<?php

namespace XFMG\PreRegAction\Media;

use XFMG\PreRegAction\AbstractCommentHandler;

class Comment extends AbstractCommentHandler
{
	public function getContainerContentType(): string
	{
		return 'xfmg_media';
	}
}