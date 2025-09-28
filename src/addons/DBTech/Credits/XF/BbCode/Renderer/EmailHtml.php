<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\BbCode\Renderer;

class EmailHtml extends XFCP_EmailHtml
{
	public function getDefaultOptions()
	{
		$options = parent::getDefaultOptions();
		$options['noDbtechCreditsCharge'] = true;

		return $options;
	}
}