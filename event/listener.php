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

		//$current_id = request_var('f', 0);
		$current_id = $event['item_id'];

		$list = $this->get_forum_list(false, false, true, false);

		$parents = $this->get_crumb_parents($list, $current_id);

		$list = $this->mark_current($list, $current_id, $parents);

		$tree = $this->build_tree($list);

		$branches = $this->choose_branches($tree, $current_id, $parents);

		$html = $this->build_output($branches);

		unset($list, $tree, $branches);

		if(!empty($html))
		{
			$template->assign_vars(array('BREADCRUMB_MENU' => $html));
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
	
		$forum_list = array();
	
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
				'disabled' 		=> $disabled,
			);
		}
		$db->sql_freeresult($result);

		return $forum_list;
	}

	/**
	* Get an array of all the current forum's parents (excluding 0)
	*/
	public function get_crumb_parents($list, $current_id)
	{
		$parents = array();
		
		if($current_id == 0 || empty($list))
		{
			return $parents; // skip if we're not viewing a forum right now
		}
		
		$parent_id = $list[$current_id]['parent_id'];
		
		while($parent_id > 0)
		{
			$parents[] = (int) $parent_id;
			$parent_id = $list[$parent_id]['parent_id'];
		}
		return array_reverse($parents);
	}

	/**
	* Marks the current forum being viewed (and it's parents)
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
			}
		}
		return $list;
	}

	/**
	* Generate a structured forum tree (multi-dimensional array)
	* got it from here: http://stackoverflow.com/a/10336597/1894483
	*/
	public function build_tree($list)
	{
		$tree = array();

		$orphans = true; $i;
		while ($orphans)
		{
			$orphans = false;
			foreach ($list as $forum_id => $values)
			{
				// does $list[$forum_id] have children?
				$children = false;
				foreach ($list as $x => $y)
				{
					if ($y['parent_id'] != false && $y['parent_id'] == $forum_id)
					{
						$children = true;
						$orphans = true;
						break;
					}
				}
				// $list[$forum_id] is a child, without children, so i can move it
				if (!$children && $values['parent_id'] != false)
				{
					$list[$values['parent_id']]['children'][$forum_id] = $values;
					unset ($list[$forum_id]);
				}
			}
		}
		return $list;
	}

	/**
	* Chose the branches of the tree for output, based on the current forum and it's parents
	*/
	public function choose_branches($tree, $current_id, $parents)
	{
		$crumb_ids = $parents;
		$crumb_ids[] = $current_id;

		$branches[$crumb_ids[0]] = $tree; // always include the tree in its entirety too, see below
		$selectors = array();

		// Construct the selectors, so we can point to the correct place in the multi-dimensional array
		foreach ($parents as $level => $parent_id)
		{
			$selector = $tree;
			for ($i = 0; $i <= $level ; $i++)
			{
				$selector = $selector[$parents[$i]]["children"];
			}
			$selectors[] = $selector;
		}

		/* Merge all the selected branches into an array. The keys of the branches are _not_ defined by the 
		position in the tree (the paren'ts ID, as you might expect), but rather by the crumb ID. This is 
		because we want each breadcrumb's drop-down menu to contain it's _siblings_ and children, rather than 
		just it's children. These IDs are only used so we can select the right menu faster using JavaScript. */
		foreach ($selectors as $level => $branch)
		{
			$branches[$crumb_ids[$level + 1]] = $branch;
		}
		return $branches;
	}

	/**
	* Build the overall HTML code of each selected branch
	*/
	public function build_output($branches)
	{
		$html = '';
		foreach($branches as $branch => $array)
		{
			$html .= '<div id="branch-' . $branch . '" class="dropdown hidden"><div class="pointer"><div class="pointer-inner"></div></div><ul class="dropdown-contents">';
			$html .= $this->build_menu($array);
			$html .= '</ul></div>';
		}
		return $html;
	}

	/**
	* Build the menu blocks code (recursively)
	*/
	public function build_menu($item)
	{
		$html = $childhtml = '';

		foreach ($item as $k => $v)
		{

			if (isset($v['children']))
			{
				$childhtml = $this->build_menu($v['children']);
			} else {
				$childhtml = '';
			}
	
			$id = $v['forum_id'];
			$class = (!empty($childhtml)) ? 'children' : '';
			$class .= ($v['current'] == true) ? ' current' : '';

			$html .= '<li class="bcm-' . $id . ((!empty($class)) ? ' ' . $class : '') . '">';

			$html .= '<a href="' . $v['link'] . '">' . $v['forum_name'] . '</a>';

			if (!empty($childhtml))
			{
				$html .= '<div class="fly-out dropdown-contents"><ul>';
				$html .= $childhtml;
				$html .= '</ul></div>';
			}

			$html .= "</li>\n";
		}
		return $html;
	}
}