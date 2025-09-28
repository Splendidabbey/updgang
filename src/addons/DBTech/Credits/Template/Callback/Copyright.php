<?php

namespace DBTech\Credits\Template\Callback;

class Copyright
{
	/**
	 * @return string
	 */
	public static function getCopyrightText(): string
	{
		/** @var \XF\App $app */
		$app = \XF::app();

		$branding = $app->offsetExists('dbtech_branding') ? $app->dbtech_branding : [];

		if (!count($branding) OR !is_array($branding))
		{
			// We had nothing left, another DBTech mod would have done it
			return '';
		}

		$brandingVariables = [
			'utm_source' 		=> str_replace('www.', '', htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'CLI')),
			'utm_content' 		=> 'footer',
		];

		// Create this long string
		$html = '<div>
			Parts of this site powered by <a class="u-concealed" rel="nofollow noopener" href="https://www.dragonbyte-tech.com/store/categories/xenforo.5/?utm_source=' . $brandingVariables['utm_source'] . '&utm_campaign=site&utm_medium=footer&utm_content=' . $brandingVariables['utm_content'] . '" target="_blank">XenForo add-ons from DragonByte&#8482;</a>
			&copy;2011-' . date('Y') . ' <a class="u-concealed" rel="nofollow noopener" href="https://www.dragonbyte-tech.com/?utm_source=' . $brandingVariables['utm_source'] . '&utm_campaign=site&utm_medium=footer&utm_content=' . $brandingVariables['utm_content'] . '" target="_blank">DragonByte Technologies</a>
			(<a class="u-concealed" rel="nofollow noopener" href="https://www.dragonbyte-tech.com/store/details/?products=' . implode(',', $branding) . '&utm_source=' . $brandingVariables['utm_source'] . '&utm_campaign=product&utm_medium=footer&utm_content=' . $brandingVariables['utm_content'] . '" target="_blank">Details</a>)
		</div>';

		// Make sure we null this out
		$app->dbtech_branding = [];

		return $html;
	}
}