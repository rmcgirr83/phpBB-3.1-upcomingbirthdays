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
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	public function __construct(
		auth $auth,
		config $config,
		driver_interface $db,
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
		return array(
			'core.index_modify_page_title'			=> 'main',
		);
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
		$sql_array = array();
		while ($date_while <= $date_end)
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
				AND " . $this->db->sql_in_set('u.user_type', array(USER_NORMAL , USER_FOUNDER));
		// cache the query for 5 minutes
		$result = $this->db->sql_query($sql, 300);

		$upcomingbirthdays = array();
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

			$upcomingbirthdays[] = array(
				'user_birthday_tstamp' 	=> 	strtotime($bddate. ' UTC'),
				'username'				=>	$row['username'],
				'user_birthdayyear' 	=> 	$bdyear,
				'user_birthday' 		=> 	$row['user_birthday'],
				'user_id'				=>	$row['user_id'],
				'user_colour'			=>	$row['user_colour'],
			);

		}
		$this->db->sql_freeresult($result);

		//sort on birthday date and then on username...this requires PHP 5.5.0
		$bd_tstamp = array_column($upcomingbirthdays, 'user_birthday_tstamp');
		$username = array_column($upcomingbirthdays, 'username');
		array_multisort($bd_tstamp, SORT_ASC, SORT_NUMERIC, $username, SORT_ASC, SORT_STRING|SORT_FLAG_CASE, $upcomingbirthdays);

		$birthday_ahead_list = array();

		for ($i = 0, $end = sizeof($upcomingbirthdays); $i < $end; $i++)
		{
			if ($upcomingbirthdays[$i]['user_birthday_tstamp'] >= $date_start && $upcomingbirthdays[$i]['user_birthday_tstamp'] <= $date_end)
			{
				$user_link = get_username_string('full', $upcomingbirthdays[$i]['user_id'], $upcomingbirthdays[$i]['username'], $upcomingbirthdays[$i]['user_colour']);
				$birthdate = phpbb_gmgetdate($upcomingbirthdays[$i]['user_birthday_tstamp']);

				//lets add to the birthday_ahead list.
				$birthday_ahead_list[$i] = '<span title="' . $birthdate['mday'] . '-' . $birthdate['mon'] . '-' . $birthdate['year'] . '">' . $user_link . '</span>';
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
		$this->template->assign_vars(array(
			'BIRTHDAYS_AHEAD_LIST'	=> $birthday_ahead_list,
			'L_BIRTHDAYS_AHEAD'	=> $this->language->lang('BIRTHDAYS_AHEAD', (int) $this->config['allow_birthdays_ahead']),
		));
	}

	private function is_leap_year($year = null)
	{
		if (is_numeric($year))
		{
			return checkdate( 2, 29, (int) $year);
		}
	}
}
