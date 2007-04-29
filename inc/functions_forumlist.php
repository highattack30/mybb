<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

/**
* Build a list of forum bits.
*
* @param int The parent forum to fetch the child forums for (0 assumes all)
* @param int The depth to return forums with.
* @return array Array of information regarding the child forums of this parent forum
*/
function build_forumbits($pid=0, $depth=1)
{
	global $fcache, $moderatorcache, $forumpermissions, $theme, $mybb, $templates, $bgcolor, $collapsed, $lang, $showdepth, $plugins, $parser, $forum_viewers;
	
	$forum_listing = '';

	// If no forums exist with this parent, do nothing
	if(!is_array($fcache[$pid]))
	{
		return;
	}

	// Foreach of the forums in this parent
	foreach($fcache[$pid] as $parent)
	{
		foreach($parent as $forum)
		{
			$forums = $subforums = $sub_forums = '';
			$lastpost_data = '';
			$counters = '';

			// Get the permissions for this forum
			$permissions = $forumpermissions[$forum['fid']];

			// If this user doesnt have permission to view this forum and we're hiding private forums, skip this forum
			if($permissions['canview'] != "yes" && $mybb->settings['hideprivateforums'] == "yes")
			{
				continue;
			}
			
			$plugins->run_hooks("build_forumbits_forum");

			// Build the link to this forum
			$forum_url = get_forum_link($forum['fid']);

			// This forum has a password, and the user isn't authenticated with it - hide post information
			$hideinfo = false;
			if($forum['password'] != '' && $_COOKIE['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
			{
				$hideinfo = true;
			}
			
			$lastpost_data = array(
				"lastpost" => $forum['lastpost'],
				"lastpostsubject" => $forum['lastpostsubject'],
				"lastposter" => $forum['lastposter'],
				"lastposttid" => $forum['lastposttid'],
				"lastposteruid" => $forum['lastposteruid']
			);
			
			// Fetch subforums of this forum
			if(isset($fcache[$forum['fid']]))
			{
				$forum_info = build_forumbits($forum['fid'], $depth+1);

				// Increment forum counters with counters from child forums
				$forum['threads'] += $forum_info['counters']['threads'];
				$forum['posts'] += $forum_info['counters']['posts'];
				$forum['unapprovedthreads'] += $forum_info['counters']['unapprovedthreads'];
				$forum['unapprovedposts'] += $forum_info['counters']['unapprovedposts'];
				$forum['viewers'] +- $forum_info['counters']['viewing'];

				// If the child forums' lastpost is greater than the one for this forum, set it as the child forums greatest.
				if($forum_info['lastpost']['lastpost'] > $lastpost_data['lastpost'])
				{
					$lastpost_data = $forum_info['lastpost'];
				}

				$sub_forums = $forum_info['forum_list'];
			}

			// If we are hiding information (lastpost) because we aren't authenticated against the password for this forum, remove them
			if($hideinfo == true)
			{
				unset($lastpost_data);
			}
			
			// If the current forums lastpost is greater than other child forums of the current parent, overwrite it
			if($lastpost_data['lastpost'] > $parent_lastpost['lastpost'])
			{
				$parent_lastpost = $lastpost_data;
			}

			if(is_array($forum_viewers) && $forum_viewers[$forum['fid']] > 0)
			{
				$forum['viewers'] = $forum_viewers[$forum['fid']];
			}

			// Increment the counters for the parent forum (returned later)
			if($hideinfo != true)
			{
				$parent_counters['threads'] += $forum['threads'];
				$parent_counters['posts'] += $forum['posts'];
				$parent_counters['unapprovedposts'] += $forum['unapprovedposts'];
				$parent_counters['unapprovedthreads'] += $forum['unapprovedthreads'];
				$parent_counters['viewers'] += $forum['viewers'];
			}

			// Done with our math, lets talk about displaying - only display forums which are under a certain depth
			if($depth > $showdepth)
			{
				continue;
			}

			// Get the lightbulb status indicator for this forum based on the lastpost
			$lightbulb = get_forum_lightbulb($forum, $lastpost_data, $hideinfo);

			// Fetch the number of unapproved threads and posts for this forum
			$unapproved = get_forum_unapproved($forum);
			
			if($hideinfo == true)
			{
				unset($unapproved);
			}
			// If this is a forum and we've got subforums of it, load the subforums list template
			if($depth == 2 && $sub_forums)
			{
				eval("\$subforums = \"".$templates->get("forumbit_subforums")."\";");
			}
			// A depth of three indicates a comma separated list of forums within a forum
			else if($depth == 3)
			{
				if($donecount < $mybb->settings['subforumsindex'])
				{
					$statusicon = '';

					// Showing mini status icons for this forum
					if($mybb->settings['subforumsstatusicons'] == "yes")
					{
						$lightbulb['folder'] = "mini".$lightbulb['folder'];
						eval("\$statusicon = \"".$templates->get("forumbit_depth3_statusicon", 1, 0)."\";");
					}

					// Fetch the template and append it to the list
					eval("\$forum_list .= \"".$templates->get("forumbit_depth3", 1, 0)."\";");
					$comma = ", ";
				}

				// Have we reached our max visible subforums? put a nice message and break out of the loop
				++$donecount;
				if($donecount == $mybb->settings['subforumsindex'])
				{
					if(count($parent) > $donecount)
					{
						$forum_list .= $comma.sprintf($lang->more_subforums, (count($parent) - $donecount));
					}
				}
				continue;
			}


			// Forum is a category, set template type
			if($forum['type'] == "c")
			{
				$forumcat = "_cat";
			}
			// Forum is a standard forum, set template type
			else
			{
				$forumcat = "_forum";
			}


			if($forum['type'] == "f" && $forum['linkto'] == '')
			{
				// No posts have been made in this forum - show never text
				if(($lastpost_data['lastpost'] == 0 || $lastpost_data['lastposter'] == '') && $hideinfo != true)
				{
					$lastpost = "<span style=\"text-align: center;\">".$lang->lastpost_never."</span>";
				}
				elseif($hideinfo != 1)
				{
					// Format lastpost date and time
					$lastpost_date = my_date($mybb->settings['dateformat'], $lastpost_data['lastpost']);
					$lastpost_time = my_date($mybb->settings['timeformat'], $lastpost_data['lastpost']);

					// Set up the last poster, last post thread id, last post subject and format appropriately
					$lastpost_profilelink = build_profile_link($lastpost_data['lastposter'], $lastpost_data['lastposteruid']);
					$lastpost_link = get_thread_link($lastpost_data['lastposttid'], 0, "lastpost");
					$lastpost_subject = $full_lastpost_subject = $parser->parse_badwords($lastpost_data['lastpostsubject']);
					if(my_strlen($lastpost_subject) > 25)
					{
						$lastpost_subject = my_substr($lastpost_subject, 0, 25)."...";
					}
					$lastpost_subject = htmlspecialchars_uni($lastpost_subject);
					$full_lastpost_subject = htmlspecialchars_uni($full_lastpost_subject);

					// Call lastpost template
					eval("\$lastpost = \"".$templates->get("forumbit_depth$depth$forumcat"."_lastpost")."\";");

				}
				
				$forum_viewers_text = '';
				if($mybb->settings['showforumviewing'] != "no" && $forum['viewers'] > 0)
				{
					$forum_viewers_text = sprintf($lang->viewing, $forum['viewers']);
				}
				
			}
			// If this forum is a link or is password protected and the user isn't authenticated, set lastpost and counters to "-"
			if($forum['linkto'] != '' || $hideinfo == true)
			{
				$lastpost = "<span style=\"text-align: center;\">-</span>";
				$posts = "-";
				$threads = "-";
			}
			// Otherwise, format thread and post counts
			else
			{
				$posts = my_number_format($forum['posts']);
				$threads = my_number_format($forum['threads']);
			}

			// Moderator column is not off
			if($mybb->settings['modlist'] != "off")
			{
				$moderators = '';
				// Fetch list of moderators from this forum and its parents
				$parentlistexploded = explode(",", $forum['parentlist']);
				foreach($parentlistexploded as $mfid)
				{
					// This forum has moderators
					if(is_array($moderatorcache[$mfid]))
					{
						// Fetch each moderator from the cache and format it, appending it to the list
						foreach($moderatorcache[$mfid] as $moderator)
						{
							$moderators .= "{$comma}<a href=\"".get_profile_link($moderator['uid'])."\">{$moderator['username']}</a>";
							$comma = ", ";
						}
					}
				}
				$comma = '';

				// If we have a moderators list, load the template
				if($moderators)
				{
					eval("\$modlist = \"".$templates->get("forumbit_moderators")."\";");
				}
				else
				{
					$modlist = '';
				}
			}

			// Descriptions aren't being shown - blank them
			if($mybb->settings['showdescriptions'] == "no")
			{
				$forum['description'] = '';
			}

			// Check if this category is either expanded or collapsed and hide it as necessary.
			$expdisplay = '';
			$collapsed_name = "cat_{$forum['fid']}_c";
			if(isset($collapsed[$collapsed_name]) && $collapsed[$collapsed_name] == "display: show;")
			{
				$expcolimage = "collapse_collapsed.gif";
				$expdisplay = "display: none;";
				$expaltext = "[+]";
			}
			else
			{
				$expcolimage = "collapse.gif";
				$expaltext = "[-]";
			}

			// Swap over the alternate backgrounds
			$bgcolor = alt_trow();

			// Add the forum to the list
			eval("\$forum_list .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
		}
	}
	
	// Return an array of information to the parent forum including child forums list, counters and lastpost information
	return array(
		"forum_list" => $forum_list,
		"counters" => $parent_counters,
		"lastpost" => $parent_lastpost
	);
}

/**
 * Fetch the status indicator for a forum based on its last post and the read date
 *
 * @param array Array of information about the forum
 * @param array Array of information about the lastpost date
 * @return array Array of the folder image to be shown and the alt text
 */
function get_forum_lightbulb($forum, $lastpost, $locked=0)
{
	global $mybb, $lang, $db, $unread_forums;
	
	if($forum['type'] == 'c')
	{
		return;
	}

	// This forum is closed, so override the folder icon with the "offlock" icon.
	if($forum['open'] == "no" || $locked)
	{
		$folder = "offlock";
		$altonoff = $lang->forum_locked;
	}
	else
	{
		// Fetch the last read date for this forum
		if($forum['lastread'])
		{
			$forum_read = $forum['lastread'];
		}
		else
		{
		 	$forum_read = my_get_array_cookie("forumread", $forum['fid']);
		}

		if(!$forum_read)
		{
			$forum_read = $mybb->user['lastvisit'];
		}
		
 	    // If the lastpost is greater than the last visit and is greater than the forum read date, we have a new post 
		if($lastpost['lastpost'] > $forum_read && $lastpost['lastpost'] != 0) 
		{
			$unread_forums++;
			$folder = "on";
			$altonoff = $lang->new_posts;
		}
		// Otherwise, no new posts
		else
		{
			$folder = "off";
			$altonoff = $lang->no_new_posts;
		}
	}

	return array(
		"folder" => $folder,
		"altonoff" => $altonoff
	);
}

/**
 * Fetch the number of unapproved posts, formatted, from a forum
 *
 * @param array Array of information about the forum
 * @return array Array containing formatted string for posts and string for threads
 */
function get_forum_unapproved($forum)
{
	global $lang;

	$unapproved_threads = $unapproved_posts = '';

	// If the user is a moderator we need to fetch the count
	if(is_moderator($forum['fid']))
	{
		// Forum has one or more unaproved posts, format language string accordingly
		if($forum['unapprovedposts'])
		{
			if($forum['unapprovedposts'] > 1)
			{
				$unapproved_posts_count = sprintf($lang->forum_unapproved_posts_count, $forum['unapprovedposts']);
			}
			else
			{
				$unapproved_posts_count = sprintf($lang->forum_unapproved_post_count, 1);
			}
			$unapproved_posts = " <span title=\"{$unapproved_posts_count}\">(".my_number_format($forum['unapprovedposts']).")</span>";
		}
		// Forum has one or more unapproved threads, format language string accordingly
		if($forum['unapprovedthreads'])
		{
			if($forum['unapprovedthreads'] > 1)
			{
				$unapproved_threads_count = sprintf($lang->forum_unapproved_threads_count, $forum['unapprovedthreads']);
			}
			else
			{
				$unapproved_threads_count = sprintf($lang->forum_unapproved_thread_count, 1);
			}
			$unapproved_threads = " <span title=\"{$unapproved_threads_count}\">(".my_number_format($forum['unapprovedthreads']).")</span>";
		}
	}
	return array(
		"unapproved_posts" => $unapproved_posts,
		"unapproved_threads" => $unapproved_threads
	);
}
?>