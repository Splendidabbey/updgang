<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Str;

/**
 * @extends \XF\Str\Formatter
 */
class Formatter extends XFCP_Formatter
{
	/**
	 * @param $string
	 * @param array $options
	 *
	 * @return string
	 */
	public function stripBbCode($string, array $options = [])
	{
		return parent::stripBbCode($this->stripDbtechCreditsChargeBbCode($string), $options);
	}
	
	/**
	 * @param $string
	 * @param int $maxLength
	 * @param array $options
	 *
	 * @return mixed|null|string|string[]
	 */
	public function snippetString($string, $maxLength = 0, array $options = [])
	{
		return parent::snippetString($this->stripDbtechCreditsChargeBbCode($string), $maxLength, $options);
	}
	
	/**
	 * @param $string
	 *
	 * @return null|string|string[]
	 */
	protected function stripDbtechCreditsChargeBbCode($string)
	{
		$bbCode = preg_quote(\XF::options()->dbtech_credits_eventtrigger_content_bbcode, '#');
		
		return preg_replace(
			'#\[(' . $bbCode . ')(?![a-z0-9_])[^\]]*\].*\[/\\1\]#siU',
			\XF::phrase('dbtech_credits_stripped_content'),
			$string ?? ''
		);
	}
}