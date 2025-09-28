<?php

namespace XFMG\XF\Service\Message;

class Preparer extends XFCP_Preparer
{
	protected $galleryEmbeds = [];

	public function prepare($message, $checkValidity = true)
	{
		$message = parent::prepare($message, $checkValidity);

		/** @var \XFMG\XF\BbCode\ProcessorAction\AnalyzeUsage $usage */
		$usage = $this->bbCodeProcessor->getAnalyzer('usage');
		$this->galleryEmbeds = $usage->getGalleryEmbeds();

		return $message;
	}

	public function getEmbeddedGalleryItems()
	{
		return $this->galleryEmbeds;
	}

	public function getEmbedMetadata()
	{
		$metadata = parent::getEmbedMetadata();
		if ($this->galleryEmbeds)
		{
			$metadata['galleryEmbeds'] = $this->galleryEmbeds;
		}

		return $metadata;
	}

	public function checkValidity($message)
	{
		$isValid = parent::checkValidity($message);

		/** @var \XF\BbCode\ProcessorAction\AnalyzeUsage $usage */
		$usage = $this->bbCodeProcessor->getAnalyzer('usage');

		if ($this->isValid)
		{
			$maxImages = $this->constraints['maxImages'];
			if ($maxImages && $usage->getTagCount('img') + $usage->getTagCount('gallery') > $maxImages)
			{
				$this->errors[] = \XF::phraseDeferred(
					'please_enter_message_with_no_more_than_x_images',
					['count' => $maxImages]
				);
				$this->isValid = false;
			}
		}

		return $this->isValid;
	}
}