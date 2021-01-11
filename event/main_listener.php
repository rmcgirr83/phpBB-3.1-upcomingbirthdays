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
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface as db;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var auth $auth */
	protected $auth;

	/** @var config $config */
	protected $config;

	/** @var language $language */
	protected $language;

	/** @var db $db */
	protected $db;

	/** @var template $template */
	protected $template;

	/** @var user $user */
	protected $user;

	public function __construct(
		auth $auth,
		config $config,
		db $db,
		language $language,
		template $template,
		user $user)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.acp_extensions_run_action_after'	=>	'acp_extensions_run_action_after',
			'core.index_modify_page_title'			=> 'main',
		];
	}
	/* Display additional metdate in extension details
	*
	* @param $event			event object
	* @param return null
	* @access public
	*/
	public function acp_extensions_run_action_after($event)
	{
		if ($event['ext_name'] == 'rmcgirr83/upcomingbirthdays' && $event['action'] == 'details')
		{
			$this->language->add_lang('common', $event['ext_name']);
			$this->template->assign_var('S_BUY_ME_A_BEER_UCBL', true);
		}
	}

	public function main($event)
	{
		if ($this->config['load_birthdays'] && $this->config['allow_birthdays'] && $this->auth->acl_gets('u_viewprofile', 'a_'))
		{
			$this->language->add_lang('upcomingbirthdays', 'rmcgirr83/upcomingbirthdays');

			$this->upcoming_birthdays();
		}
	}

	// Much of the following thanks to the original code by Lefty74
	// Modified by RMcGirr83 for phpBB 3.1.X
	public function upcoming_birthdays()
	{

		$time = $this->user->create_datetime();
		$now = phpbb_gmgetdate($time->getTimestamp() + $time->getOffset());
		$today = (mktime(0, 0, 0, $now['mon'], $now['mday'], $now['year']));

		// Number of seconds per day
		$secs_per_day = 24 * 60 * 60;

		// set start date to tomorrow
		$date_start = $date_while = $today + $secs_per_day;
		$date_end = $date_start + ((int) $this->config['allow_birthdays_ahead'] * $secs_per_day);

		// Only care about dates ahead of today.  Start date is always tomorrow
		$sql_array = [];
		while ($date_while < $date_end)
		{
			$day = date('j', $date_while);
			$month = date('n', $date_while);
			$date = $this->db->sql_escape(sprintf('%2d-%2d-', $day, $month));
			$sql_array[] = "u.user_birthday " . $this->db->sql_like_expression($date . $this->db->get_any_char());
			$date_while = $date_while + $secs_per_day;
		}

		$sql = 'SELECT u.user_id, u.username, u.user_colour, u.user_birthday, b.ban_id
			FROM ' . USERS_TABLE . ' u
			LEFT JOIN ' . BANLIST_TABLE . " b ON (u.user_id = b.ban_userid)
			WHERE (b.ban_id IS NULL
				OR b.ban_exclude = 1)
				AND (" . implode(' OR ', $sql_array) . ")
				AND " . $this->db->sql_in_set('u.user_type', [USER_NORMAL , USER_FOUNDER]);
		// cache the query for 5 minutes
		$result = $this->db->sql_query($sql, 300);

		$upcomingbirthdays = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$bdday = $bdmonth = 0;
			list($bdday, $bdmonth) = array_map('intval', explode('-', $row['user_birthday']));

			$bdcheck = strtotime(gmdate('Y') . '-' . (int) trim($bdmonth) . '-' . (int) trim($bdday) . ' UTC');
			$bdyear = ($bdcheck < $today) ? (int) gmdate('Y') + 1 : (int) gmdate('Y');
			$bddate = ($bdyear . '-' . (int) $bdmonth . '-' . (int) $bdday);

			// re-write those who have feb 29th as a birthday but only on non leap years
			if ((int) trim($bdday) == 29 && (int) trim($bdmonth) == 2)
			{
				if (!$this->is_leap_year($bdyear) && !$time->format('L'))
				{
					$bdday = 28;
					$bddate = ($bdyear . '-' . (int) trim($bdmonth) . '-' . (int) trim($bdday));
				}
			}

			$upcomingbirthdays[] = [
				'user_birthday_tstamp' 	=> 	strtotime($bddate. ' UTC'),
				'username'				=>	$row['username'],
				'user_birthdayyear' 	=> 	$bdyear,
				'user_birthday' 		=> 	$row['user_birthday'],
				'user_id'				=>	$row['user_id'],
				'user_colour'			=>	$row['user_colour'],
			];

		}
		$this->db->sql_freeresult($result);

		//sort on birthday date and then on username...this requires PHP 5.5.0
		$bd_tstamp = array_column($upcomingbirthdays, 'user_birthday_tstamp');
		$username = array_column($upcomingbirthdays, 'username');
		array_multisort($bd_tstamp, SORT_ASC, SORT_NUMERIC, $username, SORT_ASC, SORT_STRING|SORT_FLAG_CASE, $upcomingbirthdays);

		$birthday_ahead_list = [];

		for ($i = 0, $end = sizeof($upcomingbirthdays); $i < $end; $i++)
		{
			if ($upcomingbirthdays[$i]['user_birthday_tstamp'] >= $date_start && $upcomingbirthdays[$i]['user_birthday_tstamp'] <= $date_end)
			{
				$user_link = get_username_string('full', $upcomingbirthdays[$i]['user_id'], $upcomingbirthdays[$i]['username'], $upcomingbirthdays[$i]['user_colour']);
				$birthdate = phpbb_gmgetdate($upcomingbirthdays[$i]['user_birthday_tstamp']);

				// the default hover of the extension
				$birthdate_hover = $birthdate['mday'] . '-' . $birthdate['mon'] . '-' . $birthdate['year'];

				if ($this->config['ubl_date_format'])
				{
					$birthdate_hover = $birthdate['mon'] . '-' . $birthdate['mday'] . '-' . $birthdate['year'];
				}

				$birthday_ahead_list[$i] = '<span title="' . $birthdate_hover . '">' . $user_link . '</span>';

				if ($age = (int) substr($upcomingbirthdays[$i]['user_birthday'], -4))
				{
					$birthday_ahead_list[$i] .= ' (' . ($upcomingbirthdays[$i]['user_birthdayyear'] - $age) . ')';
				}
			}
		}

		$birthday_ahead_list = implode(', ', $birthday_ahead_list);

		if (!$birthday_ahead_list)
		{
			$birthday_ahead_list = '';
		}

		// Assign index specific vars
		$this->template->assign_vars([
			'BIRTHDAYS_AHEAD_LIST'	=> $birthday_ahead_list,
			'L_BIRTHDAYS_AHEAD'	=> $this->language->lang('BIRTHDAYS_AHEAD', (int) $this->config['allow_birthdays_ahead']),
		]);
	}

	private function is_leap_year($year = null)
	{
		if (is_numeric($year))
		{
			return checkdate( 2, 29, (int) $year);
		}
	}
}
