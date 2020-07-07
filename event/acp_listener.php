<?php
/**
*
* Upcoming Birthday List extension for the phpBB Forum Software package.
*
* @copyright (c) Rich McGirr
* @author 2015 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace rmcgirr83\upcomingbirthdays\event;

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class acp_listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_board_config_edit_add'	=>	'add_options',
		);
	}

	public function add_options($event)
	{
		// Store display_vars event in a local variable
		$display_vars = $event['display_vars'];

		// Define config vars
		$config_vars = array(
			'allow_birthdays_ahead'	=> array('lang' => 'ALLOW_BIRTHDAYS_AHEAD', 'validate' => 'int:1', 'type' => 'custom:1:365', 'function' => array($this, 'ubl_length'), 'explain' => true),
		);

		if ($event['mode'] == 'features' && isset($display_vars['vars']['allow_birthdays']))
		{
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $config_vars, array('after' => 'allow_birthdays'));
		}
		else if ($event['mode'] == 'load' && isset($event['display_vars']['vars']['load_birthdays']))
		{
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $config_vars, array('after' => 'load_birthdays'));
		}

		// Update the display_vars  event with the new array
		$event['display_vars'] = $display_vars;
	}

	/**
	* Maximum number of days allowed
	*/
	function ubl_length($value, $key = '')
	{
		global $user;

		return '<input id="' . $key . '" type="number" size="3" maxlength="3" min="1" max="365" name="config[allow_birthdays_ahead]" value="' . $value . '" /> ' . $user->lang['DAYS'];
	}
}
