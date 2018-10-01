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

class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
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
		if ($this->config['load_birthdays'] && $this->config['allow_birthdays'] && ($this->config['allow_birthdays_ahead'] > 0) && $this->auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
		{
			$this->user->add_lang_ext('rmcgirr83/upcomingbirthdays', 'upcomingbirthdays');

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

		// We will use the timezone offset for our cache name
		$cache_name = $time->getOffset();
		$cache_name = str_replace('-', 'minus_', $cache_name);
		$cache_name = $cache_name . '_ubl';

		if (($upcomingbirthdays = $this->cache->get('_' . $cache_name)) === false)
		{
			// Only care about dates ahead of today.  Start date is always tomorrow
			$date_start = $now[0] + $secs_per_day;
			$date_end = $date_start + ((int) $this->config['allow_birthdays_ahead'] * $secs_per_day);
			$sql_array = array();
			while ($date_start <= $date_end)
			{
				$day = date('j', $date_start);
				$month = date('n', $date_start);
				$date = $this->db->sql_escape(sprintf('%2d-%2d-', $day, $month));
				$sql_array[] = "u.user_birthday " . $this->db->sql_like_expression($date . $this->db->get_any_char());
				$date_start = $date_start + $secs_per_day;
			}

			$sql = 'SELECT u.user_id, u.username, u.user_colour, u.user_birthday, b.ban_id
				FROM ' . USERS_TABLE . ' u
				LEFT JOIN ' . BANLIST_TABLE . " b ON (u.user_id = b.ban_userid)
				WHERE (b.ban_id IS NULL
					OR b.ban_exclude = 1)
					AND (" . implode(' OR ', $sql_array) . ")
					AND " . $this->db->sql_in_set('u.user_type', array(USER_NORMAL , USER_FOUNDER));
			$result = $this->db->sql_query($sql);

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
			// cache this data for five minutes, this improves performance
			$this->cache->put('_' . $cache_name, $upcomingbirthdays, 300);
		}
		sort($upcomingbirthdays);

		$birthday_ahead_list = '';
		$tomorrow = (mktime(0, 0, 0, $now['mon'], $now['mday']+1, $now['year']));

		for ($i = 0, $end = sizeof($upcomingbirthdays); $i < $end; $i++)
		{
			if ($upcomingbirthdays[$i]['user_birthday_tstamp'] >= $today)
			{
				$user_link = get_username_string('full', $upcomingbirthdays[$i]['user_id'], $upcomingbirthdays[$i]['username'], $upcomingbirthdays[$i]['user_colour']);
				$birthdate = phpbb_gmgetdate($upcomingbirthdays[$i]['user_birthday_tstamp']);

				//lets add to the birthday_ahead list.
				$birthday_ahead_list .= (($birthday_ahead_list != '') ? $this->user->lang['COMMA_SEPARATOR'] : '') . '<span title="' . $birthdate['mday'] . '-' . $birthdate['mon'] . '-' . $birthdate['year'] . '">' . $user_link . '</span>';
				if ($age = (int) substr($upcomingbirthdays[$i]['user_birthday'], -4))
				{
					$birthday_ahead_list .= ' (' . ($upcomingbirthdays[$i]['user_birthdayyear'] - $age) . ')';
				}
			}
		}

		// Assign index specific vars
		$this->template->assign_vars(array(
			'BIRTHDAYS_AHEAD_LIST'	=> $birthday_ahead_list,
			'L_BIRTHDAYS_AHEAD'	=> $this->user->lang('BIRTHDAYS_AHEAD', (int) $this->config['allow_birthdays_ahead']),
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
