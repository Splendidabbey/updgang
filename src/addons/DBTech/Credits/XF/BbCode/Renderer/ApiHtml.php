<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\BbCode\Renderer;

class ApiHtml extends XFCP_ApiHtml
{
	public function getDefaultOptions()
	{
		$options = parent::getDefaultOptions();
		$options['noDbtechCreditsCharge'] = true;

		return $options;
	}
}