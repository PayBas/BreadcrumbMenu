<?php

/**
*
* @package Breadcrumb Menu Extension
* @copyright (c) 2014 PayBas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace paybas\breadcrumbmenu\event;

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
    exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header' => 'generate_menu',
		);
	}

	/**
	* The main script, orchestrating all steps of the process
	*/
	public function generate_menu($event)
	{
		global $config, $template, $user;

		// When the event is dispatched from posting.php, the forum_id is not passed, so its better to use the request_var
		$current_id = request_var('f', 0);
		//$current_id = $event['item_id']; 

		$list = $this->get_forum_list(false, false, true, false);

		$parents = $this->get_crumb_parents($list, $current_id);

		$list = $this->mark_current($list, $current_id, $parents);

		$tree = $this->build_tree($list);

		$html = $this->build_output($tree);

		unset($list, $tree);

		if(!empty($html))
		{
			$template->assign_vars(array(
				'BREADCRUMB_MENU' => $html,
			));
		}
	}


	/**
	* Modified version of the jumpbox, just lists authed forums (in the correct order)
	*/
	function get_forum_list($ignore_id = false, $ignore_acl = false, $ignore_nonpost = false, $ignore_emptycat = true, $only_acl_post = false)
	{
		global $db, $user, $auth, $cache;
		global $phpbb_root_path, $phpEx;
	
		// This query is identical to the jumpbox one
		$sql = 'SELECT forum_id, forum_name, parent_id, forum_type, forum_flags, forum_options, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';
		$result = $db->sql_query($sql, 600);

		// We include the forum root/index to make tree traversal easier
		$forum_list[0] = array(
			'forum_id' 		=> '0',
			'forum_name' 	=> $user->lang['FORUMS'],
			'forum_type' 	=> '0',
			'link' 			=> append_sid("{$phpbb_root_path}index.$phpEx"),
			'parent_id' 	=> false,
			'current'		=> false,
			'current_child'	=> false,
			'disabled' 		=> false,
		);
	
		// Sometimes it could happen that forums will be displayed here not be displayed within the index page
		// This is the result of forums not displayed at index, having list permissions and a parent of a forum with no permissions.
		// If this happens, the padding could be "broken"
	
		while ($row = $db->sql_fetchrow($result))
		{
			$disabled = false;

			if (!$ignore_acl && $auth->acl_gets(array('f_list', 'a_forum', 'a_forumadd', 'a_forumdel'), $row['forum_id']))
			{
				if ($only_acl_post && !$auth->acl_get('f_post', $row['forum_id']) || (!$auth->acl_get('m_approve', $row['forum_id']) && !$auth->acl_get('f_noapprove', $row['forum_id'])))
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

			$u_viewforum = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']);
			$forum_list[$row['forum_id']] = array(
				'forum_id' 		=> $row['forum_id'],
				'forum_name' 	=> $row['forum_name'],
				'forum_type' 	=> $row['forum_type'],
				'link' 			=> $u_viewforum,
				'parent_id' 	=> $row['parent_id'],
				'current'		=> false,
				'current_child'	=> false,
				'disabled' 		=> $disabled,
			);
		}
		$db->sql_freeresult($result);

		return $forum_list;
	}

	/**
	 * Get an array of all the current forum's parents
	 *
	 * @param	$list		mixed	The list
	 * @param	$current_id	int		The id of the current forum
	 * @return	$parents	array	The parents of the current_id
	 */
	public function get_crumb_parents($list, $current_id)
	{
		$parents = array();
		
		if($current_id == 0 || empty($list))
		{
			return $parents; // skip if we're not viewing a forum right now
		}
		
		$parent_id = $list[$current_id]['parent_id'];
		
		while($parent_id)
		{
			$parents[] = (int) $parent_id;
			$parent_id = $list[$parent_id]['parent_id'];
		}
		return array_reverse($parents);
	}

	/**
	 * Marks the current forum being viewed (and it's parents)
	 *
	 * @param	$list		mixed	The list
	 * @param	$current_id	int		The id of the current forum
	 * @param	$parents	array	The parents of the current_id
	 * @return	$list		mixed	The updated list
	 */
	public function mark_current($list, $current_id, $parents)
	{
		if($current_id == 0 || empty($list))
		{
			return $list; // skip if we're not viewing a forum right now
		}

		$parents[] = $current_id;

		foreach($parents as $key => $forum_id)
		{
			if(isset($list[$forum_id]))
			{
				$list[$forum_id]['current'] = true;

				// we need this to assign an #id to each crumb branch
				if($list[$forum_id]['parent_id'] >= 0)
				{
					$parent_id = $list[$forum_id]['parent_id'];
					$list[$parent_id]['current_child'] = (int) $forum_id;
				}
			}
		}
		return $list;
	}

	/**
	 * Generate a structured forum tree (multi-dimensional array)
	 * Thanks to Nicofuma
	 *
	 * @param	$list	mixed	The list
	 * @return	$tree	mixed	The tree
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
	 * @param	$list		mixed	The list
	 * @param	$length		int		The length of the list
	 * @return	$tree		mixed	The tree
	 */
	public function build_tree_rec(&$list, $length) {
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
			if (! ($tree['forum_id'] == $next['parent_id']))
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
	 * @param	$tree	mixed	The tree
	 * @return	$html	string	The HTML output
	 */
	public function build_output($tree)
	{
		$html = $childhtml = '';

		foreach ($tree as $key => $values)
		{
			if (isset($values['children'])) {
				$childhtml = $this->build_output($values['children']);
			} else {
				$childhtml = '';
			}

			$class = (!empty($childhtml)) ? 'children' : '';

			$class .= ($values['current'] == true) ? ' current' : '';

			$html .= '<li' . ((!empty($class)) ? ' class="' . $class . '"' : '') . '>';
			$html .= '<a href="' . $values['link'] . '">' . $values['forum_name'] . '</a>';

			if (!empty($childhtml))
			{
				$html .= '<ul ' . (!empty($values['current_child']) ? ('id="crumb-' . $values['current_child'] . '" ') : '') . 'class="fly-out dropdown-contents hidden">';
				$html .= $childhtml;
				$html .= '</ul>';
			}

			$html .= "</li>\n";
		}
		return $html;
	}
}