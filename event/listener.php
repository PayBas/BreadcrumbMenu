<?php
/**
 *
 * @package Breadcrumb Menu Extension
 * @copyright (c) 2015 PayBas
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace paybas\breadcrumbmenu\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string PHP extension */
	protected $phpEx;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->phpEx = $phpEx;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header' => 'generate_menu',
		);
	}

	/**
	 * The main script, orchestrating all steps of the process
	 */
	public function generate_menu()
	{
		// When the event is dispatched from posting.php, the forum_id is not passed,
		// so its better to use request->variable instead of $event['item_id']
		$current_id = $this->request->variable('f', 0);

		$list = $this->get_forum_list(false, false, true, false);

		$parents = $this->get_crumb_parents($list, $current_id);

		$list = $this->mark_current($list, $current_id, $parents);

		$tree = $this->build_tree($list);

		$html = $this->build_output($tree);

		unset($list, $tree);

		if (!empty($html))
		{
			$this->template->assign_vars(array(
				'BREADCRUMB_MENU' => $html,
			));
		}
	}

	/**
	 * Modified version of the jumpbox, just lists authed forums (in the correct order)
	 */
	function get_forum_list($ignore_id = false, $ignore_acl = false, $ignore_nonpost = false, $ignore_emptycat = true, $only_acl_post = false)
	{
		// This query is identical to the jumpbox one
		$sql = 'SELECT forum_id, forum_name, parent_id, forum_type, forum_flags, forum_options, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql, 600);

		// We include the forum root/index to make tree traversal easier
		$forum_list[0] = array(
			'forum_id'      => '0',
			'forum_name'    => $this->user->lang['FORUMS'],
			'forum_type'    => '0',
			'link'          => append_sid("{$this->root_path}index.$this->phpEx"),
			'parent_id'     => false,
			'current'       => false,
			'current_child' => false,
			'disabled'      => false,
		);

		// Sometimes it could happen that forums will be displayed here not be displayed within the index page
		// This is the result of forums not displayed at index, having list permissions and a parent of a forum with no permissions.
		// If this happens, the padding could be "broken"

		while ($row = $this->db->sql_fetchrow($result))
		{
			$disabled = false;

			if (!$ignore_acl && $this->auth->acl_gets(array('f_list', 'f_read'), $row['forum_id']))
			{
				if ($only_acl_post && !$this->auth->acl_get('f_post', $row['forum_id']) || (!$this->auth->acl_get('m_approve', $row['forum_id']) && !$this->auth->acl_get('f_noapprove', $row['forum_id'])))
				{
					$disabled = true;
				}
			}
			else if (!$ignore_acl)
			{
				continue;
			}

			if (
				((is_array($ignore_id) && in_array($row['forum_id'], $ignore_id)) || $row['forum_id'] == $ignore_id)
				||
				// Non-postable forum with no subforums, don't display
				($row['forum_type'] == FORUM_CAT && ($row['left_id'] + 1 == $row['right_id']) && $ignore_emptycat)
				||
				($row['forum_type'] != FORUM_POST && $ignore_nonpost)
			)
			{
				$disabled = true;
			}

			$u_viewforum = append_sid("{$this->root_path}viewforum.$this->phpEx", 'f=' . $row['forum_id']);
			$forum_list[$row['forum_id']] = array(
				'forum_id'      => $row['forum_id'],
				'forum_name'    => $row['forum_name'],
				'forum_type'    => $row['forum_type'],
				'link'          => $u_viewforum,
				'parent_id'     => $row['parent_id'],
				'current'       => false,
				'current_child' => false,
				'disabled'      => $disabled,
			);
		}
		$this->db->sql_freeresult($result);

		return $forum_list;
	}

	/**
	 * Get an array of all the current forum's parents
	 *
	 * @param    $list          mixed    The list
	 * @param    $current_id    int        The id of the current forum
	 * @return array $parents    The parents of the current_id
	 */
	public function get_crumb_parents($list, $current_id)
	{
		$parents = array();

		if ($current_id == 0 || empty($list))
		{
			return $parents; // skip if we're not viewing a forum right now
		}

		$parent_id = $list[$current_id]['parent_id'];

		while ($parent_id)
		{
			$parents[] = (int)$parent_id;
			$parent_id = $list[$parent_id]['parent_id'];
		}

		return array_reverse($parents);
	}

	/**
	 * Marks the current forum being viewed (and it's parents)
	 *
	 * @param    $list          mixed    The list
	 * @param    $current_id    int        The id of the current forum
	 * @param    $parents       array    The parents of the current_id
	 * @return mixed $list    The updated list
	 */
	public function mark_current($list, $current_id, $parents)
	{
		if ($current_id == 0 || empty($list))
		{
			return $list; // skip if we're not viewing a forum right now
		}

		$parents[] = $current_id;

		foreach ($parents as $forum_id)
		{
			if (isset($list[$forum_id]))
			{
				$list[$forum_id]['current'] = true;

				// we need this to assign an #id to each crumb branch
				if ($list[$forum_id]['parent_id'] >= 0)
				{
					$parent_id = $list[$forum_id]['parent_id'];
					$list[$parent_id]['current_child'] = (int)$forum_id;
				}
			}
		}

		return $list;
	}

	/**
	 * Generate a structured forum tree (multi-dimensional array)
	 * Thanks to Nicofuma
	 *
	 * @param    $list    mixed    The list
	 * @return    $tree    mixed    The tree
	 */
	public function build_tree($list)
	{
		reset($list);
		$tree[0] = $this->build_tree_rec($list, sizeof($list));

		return $tree;
	}

	/**
	 * Generate a structured forum tree (multi-dimensional array) with a recursive strategy
	 * Thanks to Nicofuma
	 *
	 * @param    $list          mixed    The list
	 * @param    $length        int        The length of the list
	 * @return bool|mixed $tree    The tree
	 */
	public function build_tree_rec(&$list, $length)
	{
		// Is the whole list treated ?
		if (current($list) === false)
		{
			return false;
		}

		$tree = current($list);

		// Loop over all children, we stop if we reach the end of the list
		while (current($list) !== false)
		{
			$next = next($list);
			if (!($tree['forum_id'] == $next['parent_id']))
			{
				// The current node isn't our child, so we backwards and we return the current tree
				prev($list);

				return $tree;
			}
			else
			{
				// Let our child retrieve its own ones
				$tree['children'][] = $this->build_tree_rec($list, $length);
			}
		}

		return $tree;
	}

	/**
	 * Build the tree HTML output (recursively)
	 *
	 * @param    $tree    mixed    The tree
	 * @return string $html      The HTML output
	 */
	public function build_output($tree)
	{
		$html = $childhtml = '';

		foreach ($tree as $values)
		{
			if (isset($values['children']))
			{
				$childhtml = $this->build_output($values['children']);
			}
			else
			{
				$childhtml = '';
			}

			$class = (!empty($childhtml)) ? 'children' : '';
			$class .= ($values['current'] == true) ? ' current' : '';

			$html .= '<li' . ((!empty($class)) ? ' class="' . $class . '"' : '') . '>';
			$html .= '<a href="' . $values['link'] . '">' . $values['forum_name'] . '</a>';

			if (!empty($childhtml))
			{
				$html .= '<div class="touch-trigger button"></div>';
			}

			if (!empty($childhtml))
			{
				$html .= "\n<ul " . (!empty($values['current_child']) ? ('id="crumb-' . $values['current_child'] . '" ') : '') . 'class="fly-out dropdown-contents hidden">';
				$html .= $childhtml;
				$html .= '</ul>';
			}

			$html .= "</li>\n";
		}

		return $html;
	}
}
