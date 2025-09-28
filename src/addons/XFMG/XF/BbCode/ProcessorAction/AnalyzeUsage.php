<?php

namespace XFMG\XF\BbCode\ProcessorAction;

use XF\BbCode\Processor;
use XF\BbCode\ProcessorAction\AnalyzerHooks;

class AnalyzeUsage extends XFCP_AnalyzeUsage
{
	protected $galleryEmbeds = [];

	public function addAnalysisHooks(AnalyzerHooks $hooks)
	{
		parent::addAnalysisHooks($hooks);

		$hooks->addTagHook('gallery', 'analyzeGalleryTag');
	}

	public function getGalleryEmbeds()
	{
		return $this->galleryEmbeds;
	}

	public function analyzeGalleryTag(array $tag, array $options, $finalOutput, Processor $processor)
	{
		if (!$finalOutput || !$tag['option'])
		{
			// was stripped
			return;
		}

		$parts = explode(',', $tag['option']);
		foreach ($parts AS &$part)
		{
			$part = trim($part);
			$part = str_replace(' ', '', $part);
		}

		$type = strtolower(array_shift($parts));
		$id = array_shift($parts);
		if ($type && $id)
		{
			$this->galleryEmbeds[$type][$id] = $id;
		}
	}
}