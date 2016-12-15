<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Updater
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @version		1.6.5
 * @filesource	friends/upd.friends.php
 */

require_once 'addon_builder/module_builder.php';

class Friends_upd extends Module_builder_friends
{

	public 	$module_actions	= array();
	public 	$hooks			= array();

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------
		//  Module Actions
		// --------------------------------------------

		$this->module_actions = array(
			'edit_group',
			'invite_friends',
			'message_folder_edit',
			'opt_out',
			'send_message',
			'status_update',
			'update',
			'insert_group_wall_comment',
			'insert_profile_wall_comment'
		);

		// --------------------------------------------
		//  Extension Hooks
		// --------------------------------------------

		$this->default_settings = array();

		$default = array(
			'class'			=> $this->extension_name,
			'settings'		=> '',
			'priority'		=> 10,
			'version'		=> FRIENDS_VERSION,
			'enabled'		=> 'y'
		);

		$this->hooks = array(
			array_merge( $default,
				array(
					'method'		=> 'comment_end',
					'hook'			=> 'insert_comment_end'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'delete_entry',
					'hook'			=> 'delete_entries_start'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'delete_non_existent_members',
					'hook'			=> 'cp_members_member_delete_end'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'user_delete_account_end',
					'hook'			=> 'user_delete_account_end'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'filter_private_search',
					'hook'			=> 'search_module_alter_sql'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'filter_private_comments',
					'hook'			=> 'comment_module_alter_entries_sql'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'user_register_referrals',
					'hook'			=> 'user_register_end'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'member_register_referrals',
					'hook'			=> 'member_member_register'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'filter_private',
					'hook'			=> 'channel_module_alter_sql'
				)
			),
			array_merge( $default,
				array(
					'method'		=> 'entry_end',
					'hook'			=> 'entry_submission_end'
				)
			)
		);
	}
	// END Friends_updater_base()


	// --------------------------------------------------------------------

	/**
	 * Module Installer
	 *
	 * @access	public
	 * @return	bool
	 */

	public function install()
	{
		// Already installed, let's not install again.
		if ($this->database_version() !== FALSE)
		{
			return FALSE;
		}

		// --------------------------------------------
		//  Our Default Install
		// --------------------------------------------
		// From pre-Hermes versions of Friends
		ee()->db->query("DELETE FROM exp_extensions WHERE class = 'Friends_ext'");

		if ($this->default_module_install() == FALSE)
		{
			return FALSE;
		}

		// --------------------------------------------
		//	Additional install routines
		// --------------------------------------------

		//table altering
		$sql	= array_merge($this->_sql_alter_members( 'install' ), $this->_sql_add_prefs() );

		// --------------------------------------------
		//  Module Install
		// --------------------------------------------

		$sql[] = ee()->db->insert_string(
			'exp_modules',
			array(
				'module_name'		=> $this->class_name,
				'module_version'	=> FRIENDS_VERSION,
				'has_cp_backend'	=> 'y'
			)
		);

		foreach ($sql as $query)
		{
			ee()->db->query($query);
		}

		return TRUE;
	}
	// END install()


	// --------------------------------------------------------------------

	/**
	 * Module Uninstaller
	 *
	 * @access	public
	 * @return	bool
	 */

	public function uninstall()
	{
		$sql	= array();

		// Cannot uninstall what does not exist, right?
		if ($this->database_version() === FALSE)
		{
			return FALSE;
		}

		// --------------------------------------------
		//  Default Module Uninstall
		// --------------------------------------------

		if ($this->default_module_uninstall() == FALSE)
		{
			return FALSE;
		}

		// --------------------------------------------
		//	Additional uninstall routines
		// --------------------------------------------

		$sql	= array_merge( $sql, $this->_sql_alter_members( 'deinstall' ) );

		foreach ($sql as $query)
		{
			ee()->db->query($query);
		}

		//remove public friends
		if ( $this->column_exists( 'total_public_friends', 'exp_members' ) === TRUE )
		{
			ee()->db->query("ALTER TABLE exp_members DROP `total_public_friends`");
		}

		return TRUE;
	}
	// END uninstall()


	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 *
	 * @access	public
	 * @return	bool
	 */

	public function update($current = "")
	{
		if ($current == $this->version)
		{
			return FALSE;
		}

		// --------------------------------------------
		//  Default Module Update
		// --------------------------------------------

		$this->default_module_update();

		// --------------------------------------------
		// run default table installs from SQL
		// (SHOULD ALWAYS BE AN IF NOT EXISTS CLAUSE IN SQL FILE)
		// --------------------------------------------

		$this->install_module_sql();

		// --------------------------------------------
		//	Tables
		// --------------------------------------------

		$sql	= array_merge(
			$this->_sql_alter_members(),
			$this->_sql_friends(),
			$this->_sql_group_posts(),
			$this->_sql_groups()
		);

		//remove public friends
		if ( $this->column_exists( 'site_id', 'exp_friends_profile_comments' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE exp_friends_profile_comments ADD site_id int(10) unsigned NOT NULL default '1'";
		}

		//remove public friends
		if ( $this->column_exists( 'site_id', 'exp_friends_group_comments' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE exp_friends_group_comments ADD site_id int(10) unsigned NOT NULL default '1'";
		}

		foreach ($sql as $query)
		{
			ee()->db->query($query);
		}

		//remove public friends
		if ( $this->column_exists( 'total_public_friends', 'exp_members' ) === TRUE )
		{
			ee()->db->query("ALTER TABLE exp_members DROP `total_public_friends`");
		}

		// --------------------------------------------
		//  lets do one last global friends cleanup before
		//	we resort to doing it on individuals
		// --------------------------------------------

		if ($this->version_compare($this->database_version(), '<', '1.5.6'))
		{
			//no arg means all non-real members removed
			if ( ! class_exists('Friends_ext'))
			{
				require_once 'ext.friends.php';
			}

			$EXT = new Friends_ext();

			$EXT->delete_members_from_friends();
		}

		// --------------------------------------------
		//  Version Number Update - LAST!
		// --------------------------------------------

		ee()->db->update(
			'exp_modules',
			array(
				'module_version'	=> FRIENDS_VERSION
			),
			array(
				'module_name'		=> $this->class_name
			)
		);

		return TRUE;
	}
	// END update()


	// --------------------------------------------------------------------

	/**
	 * SQL for add prefs
	 *
	 * @access	public
	 * @return	array
	 */

	function _sql_add_prefs()
	{
		$sql	= array();

		$prefs	= array(
			'max_message_chars'				=> 6000,
			'message_waiting_period'		=> 24,
			'message_throttling'			=> 30,
			'message_day_limit'				=> 1000,
			'max_recipients_per_message'	=> 20,
		);

		$prefs	= base64_encode( serialize( $prefs ) );

		//	----------------------------------------
		//	Get site ids
		//	----------------------------------------

		$query	= ee()->db->query( "SELECT site_id FROM exp_sites" );

		foreach ( $query->result_array() as $row )
		{
			$sql[]	= ee()->db->insert_string(
				'exp_friends_preferences',
				array(
					'site_id' 		=> $row['site_id'],
					'preferences' 	=> $prefs
				)
			);
		}

		return $sql;
	}

	//	End add prefs

	// --------------------------------------------------------------------

	/**
	 * SQL for exp_members alters
	 *
	 * @access	public
	 * @return	array
	 */

	function _sql_alter_members( $mode = 'install' )
	{
		$sql	= array();

		//	----------------------------------------
		//	Deinstall mode?
		//	----------------------------------------

		if ( $mode == 'deinstall' )
		{
			$remove = array(
				'total_friends',
				'total_reciprocal_friends',
				'total_blocked_friends',
				'friends_opt_out',
				'friends_groups_public',
				'friends_groups_private',
				'friends_group_entries_notify',
				'friends_group_comments_notify',
				'friends_group_joins_notify',
				'friends_group_favorites_notify',
				'friends_group_ratings_notify',
				'friends_total_hugs'
			);

			foreach($remove as $column)
			{
				$sql[]	= "ALTER TABLE exp_members DROP `" . $column . "`";
			}

			return $sql;
		}

		//	----------------------------------------
		//	Check for columns in members table
		//	----------------------------------------

		if ( $this->column_exists( 'friends_total_hugs', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_total_hugs int(10) unsigned NOT NULL DEFAULT '0'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_groups_public', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_groups_public int(10) unsigned NOT NULL DEFAULT '0'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_groups_private', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_groups_private int(10) unsigned NOT NULL DEFAULT '0'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_opt_out', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_opt_out char(1) DEFAULT 'n'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'total_blocked_friends', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			total_blocked_friends int(10) unsigned NOT NULL DEFAULT '0'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'total_reciprocal_friends', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			total_reciprocal_friends int(10) unsigned NOT NULL DEFAULT '0'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'total_friends', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			total_friends int(10) unsigned NOT NULL DEFAULT '0'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_group_entries_notify', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_group_entries_notify char(1) NOT NULL DEFAULT 'y'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_group_comments_notify', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_group_comments_notify char(1) NOT NULL DEFAULT 'y'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_group_joins_notify', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_group_joins_notify char(1) NOT NULL DEFAULT 'y'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_group_favorites_notify', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_group_favorites_notify char(1) NOT NULL DEFAULT 'y'
					   AFTER 		total_forum_posts";
		}

		if ( $this->column_exists( 'friends_group_ratings_notify', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	exp_members
					   ADD 			friends_group_ratings_notify char(1) NOT NULL DEFAULT 'y'
					   AFTER 		total_forum_posts";
		}

		return $sql;
	}

	//	End SQL for exp_members alters


	// --------------------------------------------------------------------

	/**
	 * SQL for friends table creation
	 *
	 * @access	public
	 * @return	bool
	 */

	function _sql_friends()
	{
		$sql	= array();

		//	----------------------------------------
		//	Table exists?
		//	----------------------------------------

		if ( ee()->db->table_exists( 'exp_friends' ) )
		{
			if ( ! $this->column_exists( 'referrer_id', 'exp_friends' ) )
			{
				$sql[]	= "ALTER TABLE 	exp_friends
						   ADD 			referrer_id int(10) unsigned NOT NULL DEFAULT '0'
						   AFTER 		friend_id";
			}

			if ( ! $this->column_exists( 'group_id', 'exp_friends' ) )
			{
				$sql[]	= "ALTER TABLE 	exp_friends
						   ADD 			group_id varchar(132) NOT NULL DEFAULT ''
						   AFTER 		referrer_id";
			}
		}

		return $sql;
	}

	//	End SQL for friends table creation


	// --------------------------------------------------------------------

	/**
	 * SQL for group posts table creation
	 *
	 * @access	public
	 * @return	bool
	 */

	function _sql_group_posts()
	{
		$sql	= array();

		//	----------------------------------------
		//	Table exists?
		//	----------------------------------------

		if ( ee()->db->table_exists( 'exp_friends_group_posts' ) )
		{
			if ( $this->column_exists( 'accepted', 'exp_friends_group_posts' ) === FALSE )
			{
				$sql[]	= "ALTER TABLE 	exp_friends_group_posts
						   ADD 			accepted char(1) NOT NULL DEFAULT 'n'";
			}

			if ( $this->column_exists( 'invite_or_request', 'exp_friends_group_posts' ) === FALSE )
			{
				$sql[]	= "ALTER TABLE 	exp_friends_group_posts
						   ADD 			invite_or_request varchar(7) NOT NULL DEFAULT ''";
			}
		}

		return $sql;
	}

	//	End SQL for group posts table creation


	// --------------------------------------------------------------------

	/**
	 * SQL for groups table creation
	 *
	 * @access	public
	 * @return	bool
	 */

	function _sql_groups()
	{
		$sql	= array();

		//	----------------------------------------
		//	Table exists?
		//	----------------------------------------

		if ( ee()->db->table_exists( 'exp_friends_groups' ) )
		{
			if ( $this->column_exists( 'total_members', 'exp_friends_groups' ) === FALSE )
			{
				$sql[]	= "ALTER TABLE 	exp_friends_groups
						   ADD 			total_members int(10) unsigned NOT NULL DEFAULT '0'";
			}

			if ( $this->column_exists( 'description', 'exp_friends_groups' ) === FALSE )
			{
				$sql[]	= "ALTER TABLE 	exp_friends_groups
						   ADD 			description text NOT NULL DEFAULT ''
						   AFTER 		title";
			}
		}

		return $sql;
	}
	//End _sql_groups

}
// END Class Friends_updater_base