<?php
/**
*
* Upcoming Birthday List extension for the phpBB Forum Software package.
*
* @copyright (c) Rich McGirr
* @author 2015 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0-only)
*
*/

namespace rmcgirr83\upcomingbirthdays\event;

/**
* Event listener
*/
use phpbb\config\config;
use phpbb\language\language;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class acp_listener implements EventSubscriberInterface
{
	/** @var config $config */
	protected $config;

	/** @var language $language */
	protected $language;

	public function __construct(config $config, language $language)
	{
		$this->config = $config;
		$this->language = $language;
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.acp_board_config_edit_add'	=>	'add_options',
		];
	}

	public function add_options($event)
	{
		$this->language->add_lang('common', 'rmcgirr83/upcomingbirthdays');
		// Store display_vars event in a local variable
		$display_vars = $event['display_vars'];

		// Define config vars
		$config_vars = [
			'allow_birthdays_ahead'	=> ['lang' => 'ALLOW_BIRTHDAYS_AHEAD', 'validate' => 'int:1', 'type' => 'custom:1:365', 'function' => [$this, 'ubl_length'], 'explain' => true],
			'ubl_date_format' => ['lang' => 'BIRTHDAYS_AHEAD_DATE_FORMAT', 'validate' => 'bool', 'type' => 'custom:0:1', 'function' => [$this, 'ubl_date_format'], 'explain' => true],
		];

		if ($event['mode'] == 'features' && isset($display_vars['vars']['allow_birthdays']))
		{
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $config_vars, ['after' => 'allow_birthdays']);
		}
		else if ($event['mode'] == 'load' && isset($event['display_vars']['vars']['load_birthdays']))
		{
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $config_vars, ['after' => 'load_birthdays']);
		}

		// Update the display_vars  event with the new array
		$event['display_vars'] = $display_vars;
	}

	/**
	* Maximum number of days allowed
	*/
	function ubl_length($value, $key = '')
	{
		return '<input id="' . $key . '" type="number" size="3" maxlength="3" min="1" max="365" name="config[allow_birthdays_ahead]" value="' . $value . '" /> ' . $this->language->lang('DAYS');
	}

	/**
	 * Date format of hover
	 */
	function ubl_date_format($value, $key = '')
	{
		$radio_array = [
			0	=> 'UBL_DATE_FORMAT_DDMMYYYY',
			1	=> 'UBL_DATE_FORMAT_MMDDYYYY',
		];

		return h_radio('config[ubl_date_format]', $radio_array, $value, $key);
	}
}
