<?php
/**
*
* Upcoming Birthday List extension for the phpBB Forum Software package.
*
* @copyright 2020 (c) Rich McGirr
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
*/

namespace rmcgirr83\upcomingbirthdays;

/**
* Extension class for custom enable/disable/purge actions
*/
class ext extends \phpbb\extension\base
{
	const PHPBB_MIN_VERSION = '3.2.6';
	const PHP_MIN_VERSION = '7.1';
	/**
	 * Enable extension if phpBB version requirement is met
	 *
	 * @return bool
	 * @access public
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');

		$enableable = (phpbb_version_compare($config['version'], self::PHPBB_MIN_VERSION, '>=') && phpbb_version_compare(PHP_VERSION, self::PHP_MIN_VERSION, '>='));
		if (!$enableable)
		{
			$language = $this->container->get('language');
			$language->add_lang('upcomingbirthdays', 'rmcgirr83/upcomingbirthdays');

			trigger_error($language->lang('EXTENSION_REQUIREMENTS', self::PHPBB_MIN_VERSION, self::PHP_MIN_VERSION), E_USER_WARNING);
		}

		return $enableable;
	}
}
