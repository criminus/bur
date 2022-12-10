<?php
/**
*
* @package phpBB Extension - Banned user rank
* @copyright (c) 2022 Anix - https://phpbbhacks.ro
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace anix\bur\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\cache\service as cache_service;
use phpbb\language\language;

class listener implements EventSubscriberInterface
{
	
	/** @var \phpbb\request\request */
	protected $request;
	
	/** @var \phpbb\user */
	protected $user;

	/** @var cache */
	protected $cache;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	
	/** @var string phpEx */
	protected $php_ext;

	/** @var language */
	protected $language;
	
	public function __construct
	(
		\phpbb\request\request $request, 
		\phpbb\user $user, 
		cache_service $cache,
		\phpbb\db\driver\driver_interface $db,
		language $language,
		$php_ext
	)
	{
		$this->request = $request;
		$this->user = $user;
		$this->cache = $cache;
		$this->db = $db;
		$this->language = $language;
		$this->php_ext = $php_ext;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_modify_post_row'		=> 'viewtopic_modify_post_row',
			'core.memberlist_prepare_profile_data'	=> 'memberlist_prepare_profile_data',
			'core.common'							=> 'load_language',
		);
	}

	public function load_language($event)
	{
		$this->user->add_lang_ext('anix/bur', 'bur');
	}

	//Viewtopic page
	public function viewtopic_modify_post_row($event)
	{
		$post_row = $event['post_row'];
		$poster_id = $event['poster_id'];

		$banned_users = $this->get_banned_users($poster_id);

		if (isset($banned_users[$poster_id])) {
			if ($banned_users[$poster_id] == 0)
			{
				$post_row['RANK_TITLE']		= $this->language->lang('PERMABANNED');
				$post_row['RANK_IMG']		= '';
				$post_row['RANK_IMG_SRC']	= '';			
			}
			else if ($banned_users[$poster_id] > time())
			{
				$post_row['RANK_TITLE']		= sprintf($this->language->lang('BANNED_UNTIL'), $this->user->format_date($banned_users[$poster_id], '|d M Y|, H:i'));
				$post_row['RANK_IMG']		='';
				$post_row['RANK_IMG_SRC']	= '';
			}
		}

		$event['post_row'] = $post_row;
	}

	//View Profile page / Memberlist page
	public function memberlist_prepare_profile_data($event) {
		$template_data = $event['template_data'];
		$userid = $event['data']['user_id'];

		$banned_users = $this->get_banned_users($userid);

		if (isset($banned_users[$userid])) {
			if ($banned_users[$userid] == 0)
			{
				$template_data['RANK_TITLE']	= $this->language->lang('PERMABANNED');
				$template_data['RANK_IMG']		= '';
				$template_data['RANK_IMG_SRC']	= '';
			}
			else if ($banned_users[$userid] > time())
			{
				$template_data['RANK_TITLE']	= sprintf($this->language->lang('BANNED_UNTIL'), $this->user->format_date($banned_users[$userid], '|d M Y|, H:i'));
				$template_data['RANK_IMG']		= '';
				$template_data['RANK_IMG_SRC']	= '';
			}
		}

		$event['template_data'] = $template_data;
	}

	//Grab the id of banned users
	protected function get_banned_users($user) {
		global $db;

		if (($banned_users = $this->cache->get('_banned_users')) === false)
		{
			$sql = 'SELECT ban_userid, ban_end
			FROM ' . BANLIST_TABLE . '
			WHERE (ban_end > ' . time() . ' OR ban_end = 0)
		   	AND ban_userid = ' . $user . '';
		   
	   		$result = $db->sql_query($sql);

	   		$banned_users = [];

	   		if ($row = $db->sql_fetchrow($result))
	   		{
		   		$banned_users[$row['ban_userid']] = $row['ban_end'];
	  		}
	   		$db->sql_freeresult($result);

			// cache this data for 5 minutes, this improves performance
			$this->cache->put('_banned_users', $banned_users, 300);
		}

		return $banned_users;
	}
}
