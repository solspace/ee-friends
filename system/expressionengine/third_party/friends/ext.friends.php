<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Extension
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @version		1.6.5
 * @filesource	friends/ext.friends.php
 */

require_once 'addon_builder/extension_builder.php';

class Friends_ext extends Extension_builder_friends
{
	public $settings		= array();
	public $name			= '';
	public $version			= '';
	public $description		= '';
	public $settings_exist	= 'n';
	public $docs_url		= '';

	private $SESS			= FALSE;
	public $friends;

	public $required_by 	= array('module');
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct($settings = array())
	{
		parent::__construct();

		// --------------------------------------------
		//  Settings
		// --------------------------------------------

		$this->settings = $settings;

		//keeps us from calling this a bajallion times
		$this->clean_site_id = ee()->db->escape_str( ee()->config->item( 'site_id' ) );
	}
	// END Friends_extension_base()


	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension enabled, they have to install the module.
	 *
	 * @access	public
	 * @return	null
	 */

	public function activate_extension(){}
	// END activate_extension()


	// --------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension disabled, they have to uninstall the module.
	 *
	 * @access	public
	 * @return	null
	 */

	public function disable_extension(){}
	// END disable_extension()


	// --------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * A required method that we actually ignore because this extension is updated by its module
	 * and no other place.  We cannot redirect to the module upgrade script because we require a
	 * confirmation dialog, whereas extensions were designed to update automatically as they will try
	 * to call the update script on both the User and CP side.
	 *
	 * @access	public
	 * @return	null
	 */

	public function update_extension()
	{

	}
	// END update_extension()


	// --------------------------------------------------------------------

	/**
	 * Error Page
	 *
	 * @access	public
	 * @param	string	$error	Error message to display
	 * @return	null
	 */

	public function error_page($error = '')
	{
		$this->cached_vars['error_message'] = $error;

		$this->cached_vars['page_title'] = lang('error');

		// -------------------------------------
		//  Output
		// -------------------------------------

		$this->ee_cp_view('error_page.html');
	}
	// END error_page()


	// --------------------------------------------------------------------

	/**
	 * Allowed Ability for Group
	 *
	 * @access	public
	 * @param	string	$which	Name of permission
	 * @return	bool
	 */

	function allowed_group($which = '')
	{
		if ($which == '')
		{
			return FALSE;
		}
		// Super Admins always have access

		if (ee()->session->userdata['group_id'] == 1)
		{
			return TRUE;
		}

		return ! ( ! isset(ee()->session->userdata[$which]) OR ee()->session->userdata[$which] !== 'y');
	}

	/* END allowed_group() */


	// --------------------------------------------------------------------

	/**
	 * Comment end
	 *
	 * This notifies subscribed friends groups of comments
	 *
	 * @access	public
	 * @return	null
	 */

	function comment_end( $data, $moderate, $comment_id )
	{
		ee()->extensions->end_script = FALSE;

		$entry_id	= ee()->security->xss_clean( $data['entry_id'] );

		//	----------------------------------------
		//	Prep notification
		//	----------------------------------------

		if ( ee()->input->get_post('friends_notification_template') !== FALSE AND
			 ee()->input->get_post('friends_notification_template') != '' )
		{
			$email['notification_template']		= ee()->input->get_post('friends_notification_template');
			$email['subject']					= ee()->input->get_post('friends_subject');
			$email['from_email']				= $data['email'];
			$email['from_name']					= $data['name'];
			$email['extra']['friends_comment']	= $data['comment'];

			$sql	= " SELECT 		fgp.member_id, fgp.group_id
						FROM 		exp_friends_group_posts fgp
						LEFT JOIN 	exp_friends_group_entry_posts fgep
						ON 			fgep.group_id = fgp.group_id
						WHERE 		fgep.entry_id = '" . ee()->db->escape_str( $entry_id ) . "'
						AND 		fgp.member_id != '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'
						AND 		fgp.accepted = 'y'
						AND 		fgp.declined = 'n'
						AND 		fgp.request_accepted = 'y'
						AND 		fgp.request_declined = 'n'
						AND 		fgp.notify_comments = 'y'";

			$query	= ee()->db->query( $sql );

			$members	= array();
			$groups		= array();

			foreach ( $query->result_array() as $row )
			{
				$members[$row['member_id']]	= $row['member_id'];
				$groups[$row['group_id']]	= $row['group_id'];
			}

			// ----------------------------------------
			//	No members to notify?
			// ----------------------------------------

			if ( count( $members ) == 0 ) return FALSE;

			// ----------------------------------------
			//	Instantiate
			// ----------------------------------------

			if ( class_exists('Friends') === FALSE )
			{
				require_once 'mod.friends.php';
			}

			$Friends = new Friends();

			$email['extra']['friends_comment_id']	= $comment_id;

			foreach ( $groups as $group_id )
			{
				$email['extra']['group_id']	= $group_id;

				if ( $Friends->comment_notify( array( $entry_id ), $members, $email ) === FALSE )
				{
					//fairy dust ***...***...***
				}
			}
		}
	}
	//	End comment end


	// --------------------------------------------------------------------

	/**
	 * Delete entry
	 *
	 * @access	public
	 * @return	null
	 */

	function delete_entry()
	{
		//	----------------------------------------
		//	Execute?
		//	----------------------------------------

		if ( isset( $_POST['delete'] ) === FALSE )
		{
			return;
		}

		$post	= ee()->security->xss_clean( $_POST['delete'] );

		if ( count( $post ) == 0 ) return;

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$sql = "SELECT 	entry_id
				FROM 	exp_friends_group_entry_posts
				WHERE 	site_id = '" . ee()->db->escape_str(ee()->config->item('site_id')) . "'
				AND 	(";

		foreach ($post as $key => $val)
		{
			$sql .= " entry_id = '" . ee()->db->escape_str( $val ) . "' OR ";
		}

		$sql = substr($sql, 0, -3).')';

		$query = ee()->db->query($sql);

		//	----------------------------------------
		//	Delete entries
		//	----------------------------------------

		if ( $query->num_rows() == 0 ) return;

		$sql = array();

		foreach( $query->result_array() as $row )
		{
			$sql[] = "DELETE FROM 	exp_friends_group_entry_posts
					  WHERE 		entry_id = '" . $row['entry_id'] . "'";
		}

		foreach ( $sql as $q )
		{
			ee()->db->query($q);
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return;
	}
	//	End delete entry


	// --------------------------------------------------------------------

	/**
	 * delete members from the user_delete_account_end hook
	 *
	 * This prunes friends tables of members that no longer exist.
	 *
	 * @access	public
	 * @return	null
	 */

	public function user_delete_account_end ($instance)
	{
		$member_id = 0;

		if (is_object($instance) AND is_callable(array($instance, '_param')))
		{
			//at this point the member is already deleted
			//and no checks will work on whether this is valid
			$member_id = $instance->_param('member_id');
		}
		//this.. shouldn't happen? Backup parachute,
		else if (ee()->input->post('member_id') AND
				 is_numeric(ee()->input->post('member_id')) AND
				 ee()->input->post('member_id') > 0)
		{
			$member_id 	= ee()->input->post('member_id');
		}

		//lets make sure we don't nuke a member_id thats still there
		//in theory this should not be either, but still...
		$row_query = ee()->db->query(
			"SELECT member_id
			 FROM 	exp_members
			 WHERE 	member_id = " . ee()->db->escape_str($member_id)
		);

		if ($row_query->num_rows() > 0 )
		{
			$member_id = 0;
		}

		//last resort is just checking for any missing members
		return $this->delete_members_from_friends($member_id);
	}
	//END user_delete_account_end


	// --------------------------------------------------------------------

	/**
	 * Delete non-existent members
	 *
	 * This prunes tables of members that no longer exist.
	 *
	 * @access	public
	 * @return	null
	 */

	public function delete_non_existent_members ()
	{
		$member_ids = 0;

		$delete = ee()->input->post('delete');

		if ($delete)
		{
			if (is_array($delete))
			{
				$ids = array();

				foreach ($delete as $key => $val)
				{
					if ($val != '' AND is_numeric($val) AND $val > 0)
					{
						$ids[] = ee()->db->escape_str($val);
					}
				}

				if ( ! empty($ids))
				{
					$member_ids = $ids;
				}
			}
			else if ( is_numeric($delete) AND $delete > 0 )
			{
				$member_id = $delete;
			}
		}

		return $this->delete_members_from_friends($member_ids);
	}
	//	End delete_non_existent_members


	// --------------------------------------------------------------------

	/**
	 * Delete non-existent members
	 *
	 * alias for delete_non_existent_members for back compatibility
	 *
	 * @access	public
	 * @return	null
	 */

	public function delete_members()
	{
		return $this->delete_non_existent_members();
	}
	//	End delete_members


	// --------------------------------------------------------------------

	/**
	 * Entry end
	 *
	 * @access	public
	 * @return	null
	 */

	function entry_end( $entry_id, $data, $ping_message )
	{
		ee()->extensions->end_script = FALSE;

		//--------------------------------------------
		//	This is only for SAEF
		//--------------------------------------------

		if (REQ == 'CP') return;

		//--------------------------------------------
		//	ee 2? the third argument is the data
		//--------------------------------------------


		$data = array_merge($data, $ping_message);

		// ----------------------------------------
		//	Empty?
		// ----------------------------------------

		if ( isset( $_POST['friends_group_id'] ) === FALSE )
		{
			return;
		}

		// ----------------------------------------
		//	Instantiate Friends class. We'll need it
		// ----------------------------------------

		if ( class_exists('Friends') === FALSE )
		{
			require 'mod.friends.php';
		}

		$Friends = new Friends();

		//	----------------------------------------
		//	Instantiate Friends Groups class as well
		//	----------------------------------------

		ee()->load->library('friends_groups');

		$FG =& ee()->friends_groups;

		// ----------------------------------------
		//	Clean post
		// ----------------------------------------

		$_POST['friends_group_id']	= ee()->security->xss_clean( $_POST['friends_group_id'] );

		// ----------------------------------------
		//	Prep groups
		// ----------------------------------------

		$temp	= array();

		if ( is_array( $_POST['friends_group_id'] ) === TRUE )
		{
			$temp		= $Friends->_only_numeric( $_POST['friends_group_id'] );
		}
		elseif ( is_numeric( $_POST['friends_group_id'] ) === TRUE )
		{
			$temp[]	= $_POST['friends_group_id'];
		}

		// ----------------------------------------
		//	Determine privacy
		// ----------------------------------------

		$groups	= array();

		foreach ( $temp as $val )
		{
			if ( isset( $_POST['friends_group_private_entry_'.$val] ) === TRUE AND
				 $_POST['friends_group_private_entry_'.$val] == 'y' )
			{
				$groups[ $val ]	= 'y';
			}
			else
			{
				$groups[ $val ]	= 'n';
			}
		}

		unset( $temp );

		// ----------------------------------------
		//	Get members
		// ----------------------------------------

		$sql	= "SELECT 	member_id, group_id
				   FROM 	exp_friends_group_posts
				   WHERE 	site_id = " . ee()->db->escape_str( ee()->config->item('site_id') ) . "
				   AND 		accepted = 'y'
				   AND 		declined = 'n'
				   AND 		request_accepted = 'y'
				   AND 		request_declined = 'n'
				   AND 		notify_entries = 'y'
				   AND 		group_id
				   IN 		(" . implode( ',', array_keys( $groups ) ) . ")";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) return;

		$members		= array();
		$members_notify	= array();

		foreach ( $query->result_array() as $row )
		{
			$members[$row['group_id']][$row['member_id']]	= $row['member_id'];
			$members_notify[$row['member_id']]				= $row['member_id'];
		}

		// ----------------------------------------
		//	Loop and add
		// ----------------------------------------

		$author_id = (isset($data['author_id']) ?
						$data['author_id'] :
						(isset($data['revision_post']['author_id']) ?
						 $data['revision_post']['author_id'] :
						 0));

		foreach ( $groups as $key => $val )
		{
			// ----------------------------------------
			//	Is this group in our members array in
			//	order to allow us to conduct our next
			//	two conditions? Is the author of the
			//	entry being submitted in the group?
			//	And is the person submitting the entry,
			//	author or not, also in the group?
			// ----------------------------------------

			if ( isset( $members[$key] ) === FALSE OR
				 in_array( $author_id, $members[$key] ) === FALSE OR
				 in_array( ee()->session->userdata('member_id'), $members[$key] ) === FALSE ) continue;

			ee()->db->query(
				ee()->db->insert_string(
					'exp_friends_group_entry_posts',
					array(
						'group_id' 	=> $key,
						'private' 	=> $val,
						'entry_id' 	=> $entry_id,
						'member_id' => ee()->session->userdata('member_id'),
						'site_id'	=> $this->clean_site_id
					)
				)
			);

			$FG->_update_group_stats( $key );

			//	----------------------------------------
			//	Prep notification
			//	----------------------------------------

			if ( ee()->input->get_post('friends_notification_template') !== FALSE AND
				 ee()->input->get_post('friends_notification_template') != '' )
			{
				$email['notification_template']	= ee()->input->get_post('friends_notification_template');
				$email['subject']				= ee()->input->get_post('friends_subject');
				$email['from_email']			= ee()->session->userdata('email');
				$email['from_name']				= ee()->session->userdata('screen_name');
				$email['member_id']				= ee()->session->userdata('member_id');
				$email['extra']['group_id']		= $key;

				$ids	= array( $entry_id );

				if ( $Friends->entry_notify( $ids, $members_notify, $email ) === FALSE )
				{
					//fairy dust ***...***...***
				}
			}
		}
	}

	/*	End entry end */


	// --------------------------------------------------------------------

	/**
	 * Filter
	 *
	 * @access	public
	 * @return	null
	 */

	function _filter( $hide_privates = FALSE, $entry_ids = array() )
	{
		// ----------------------------------------
		//	Get prohibited entry ids
		// ----------------------------------------

		if ( ee()->session->userdata['member_id'] == 0 OR $hide_privates === TRUE )
		{
			// ----------------------------------------
			//	Get ids that are private
			// ----------------------------------------

			$prohibit	= "SELECT DISTINCT 	fgep.entry_id
						   FROM 			exp_friends_group_entry_posts fgep
						   LEFT JOIN 		exp_friends_groups fg
						   ON 				fg.group_id = fgep.group_id
						   WHERE 			fgep.private = 'y'
						   OR 				fg.private = 'y'";

			if ( count( $entry_ids ) > 0 )
			{
				$prohibit	.= " AND entry_id IN ('".implode( "','", $entry_ids )."')";
			}
		}
		else
		{
			// ----------------------------------------
			//	Filter for member
			// ----------------------------------------
			// 	Of the ids provided, if some are
			// 	protected, we want to know which of those
			// 	belong to groups the member NOT belongs to.
			// ----------------------------------------

			$prohibit	= "SELECT DISTINCT 	fgep.entry_id
						   FROM 			exp_friends_group_entry_posts fgep
						   LEFT JOIN 		exp_friends_group_posts fgp
						   ON 				fgep.group_id = fgp.group_id
						   LEFT JOIN 		exp_friends_groups fg
						   ON 				fgep.group_id = fg.group_id
						   WHERE 			fgep.entry_id != ''";

			if ( count( $entry_ids ) > 0 )
			{
				$prohibit	.= " AND fgep.entry_id IN ('".implode( "','", $entry_ids )."')";
			}

			$prohibit	.= " AND 	fgep.private = 'y'
							 AND 	fg.private = 'y'
							 AND 	fgep.group_id
							 NOT IN (
								SELECT 	fgp.group_id
								FROM 	exp_friends_group_posts fgp
								WHERE 	fgp.accepted = 'y'
								AND 	fgp.declined = 'n'
								AND 	fgp.request_accepted = 'y'
								AND 	fgp.request_declined = 'n'
								AND 	fgp.member_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'
							 )";
		}

		// ----------------------------------------
		//	Run the prohibit query
		// ----------------------------------------

		$query	= ee()->db->query( $prohibit );

		// ----------------------------------------
		//	Assemble disallowed ids
		// ----------------------------------------

		$no	= array();

		foreach ( $query->result_array() as $row )
		{
			$no[]	= $row['entry_id'];
		}

		// ----------------------------------------
		//	Return?
		// ----------------------------------------

		if ( count( $no ) == 0 )
		{
			return FALSE;
		}
		else
		{
			return $no;
		}
	}

	/*	End filter */


	// --------------------------------------------------------------------

	/**
	 * Filter private
	 *
	 * This alters the SQL query that grabs weblog entries.
	 *
	 * @access	public
	 * @return	null
	 */

	function filter_private ( $entry_id = '', $sql = '' )
	{
		// ----------------------------------------
		//	Set return sql
		// ----------------------------------------

		if ( isset( ee()->extensions->last_call ) === TRUE AND
			 ee()->extensions->last_call != '' )
		{
			$sql	= ee()->extensions->last_call;
		}

		// ----------------------------------------
		//	Should we even execute?
		// ----------------------------------------

		if ( $this->check_yes(ee()->TMPL->fetch_param('disable_friends_filter') ) )
		{
			return $sql;
		}

		// ----------------------------------------
		//	Hide all private stuff?
		// ----------------------------------------

		$hide_privates	= $this->check_yes(ee()->TMPL->fetch_param('friends_hide_privates'));

		// ----------------------------------------
		//	Get prohibited entry ids
		// ----------------------------------------

		if ( ( $no	= $this->_filter( $hide_privates ) ) === FALSE )
		{
			return $sql;
		}

		// ----------------------------------------
		//	Modify SQL
		// ----------------------------------------

		if ( preg_match( "/(WHERE)/s", $sql, $match ) AND count( $no ) > 0 )
		{
			$sql_a	= " WHERE t.entry_id NOT IN ( '".implode( "','", $no )."' ) AND ";

			$sql	= str_replace( $match['1'], $sql_a, $sql );
		}

		return $sql;
	}

	//	End filter private


	// --------------------------------------------------------------------

	/**
	 * Filter private comments
	 *
	 * This alters the result_ids array to filter out private comments if necessary.
	 *
	 * @access	public
	 * @return	null
	 */

	function filter_private_comments ( $sql = "" )
	{
		// ----------------------------------------
		//	Set return sql
		// ----------------------------------------

		if ( isset( ee()->extensions->last_call ) === TRUE AND
			 ee()->extensions->last_call != '' )
		{
			$sql	= ee()->extensions->last_call;
		}

		// ----------------------------------------
		//	Should we even execute?
		// ----------------------------------------

		if ( $this->check_yes(ee()->TMPL->fetch_param('disable_friends_filter') ) )
		{
			return $sql;
		}

		// ----------------------------------------
		//	Hide all private stuff?
		// ----------------------------------------

		$hide_privates	= $this->check_yes(ee()->TMPL->fetch_param('friends_hide_privates'));

		// ----------------------------------------
		//	Get prohibited entry ids
		// ----------------------------------------

		if ( ( $no	= $this->_filter( $hide_privates ) ) === FALSE )
		{
			return $sql;
		}

		// ----------------------------------------
		//	Modify SQL
		// ----------------------------------------

		if ( preg_match( "/(WHERE)/s", $sql, $match ) AND count( $no ) > 0 )
		{
			$sql_a	= " WHERE entry_id NOT IN ( '".implode( "','", $no )."' ) AND ";

			$sql	= str_replace( $match['1'], $sql_a, $sql );
		}

		return $sql;
	}

	//	End filter private comments


	// --------------------------------------------------------------------

	/**
	 * Filter private search
	 *
	 * This alters the SQL query that grabs search results.
	 *
	 * @access	public
	 * @return	null
	 */

	function filter_private_search ( $sql = '' )
	{
		// ----------------------------------------
		//	Set return sql
		// ----------------------------------------

		if ( isset( ee()->extensions->last_call ) === TRUE AND
			 ee()->extensions->last_call != '' )
		{
			$sql	= ee()->extensions->last_call;
		}

		// ----------------------------------------
		//	Should we even execute?
		// ----------------------------------------

		if ( $this->check_yes(ee()->TMPL->fetch_param('disable_friends_filter') ) )
		{
			return $sql;
		}

		// ----------------------------------------
		//	Hide all private stuff?
		// ----------------------------------------

		$hide_privates	= $this->check_yes(ee()->TMPL->fetch_param('friends_hide_privates'));

		// ----------------------------------------
		//	Get prohibited entry ids
		// ----------------------------------------

		if ( ( $no	= $this->_filter( $hide_privates ) ) === FALSE )
		{
			return $sql;
		}

		// ----------------------------------------
		//	Modify SQL
		// ----------------------------------------

		if ( preg_match( "/(WHERE)/s", $sql, $match ) AND count( $no ) > 0 )
		{
			$sql_a	= " WHERE {$this->sc->db->channel_titles}.entry_id NOT IN ( '".implode( "','", $no )."' ) AND ";

			$sql	= str_replace( $match['1'], $sql_a, $sql );
		}

		return $sql;
	}

	/*	End filter private search */


	// --------------------------------------------------------------------

	/**
	 * Referral
	 *
	 * This is soon to be depracated.
	 *
	 * @access	public
	 * @return	null
	 */

	function referral ( $row = array() )
	{
		return FALSE;
	}

	/*	End referral */

	// --------------------------------------------------------------------

	/**
	 * called by the user_register hook to process referrals on a new sign up
	 *
	 * @access	public
	 * @param	object	user object ref
	 * @param	int		member_id
	 * @return	bool	worked
	 */

	public function user_register_referrals(&$obj, $member_id)
	{
		return $this->update_referrals($member_id, $obj->insert_data['email']);
	}
	//END user_register_referrals


	// --------------------------------------------------------------------

	/**
	 * called by the member_member_register hook to process referrals on a new sign up
	 *
	 * @access	public
	 * @param	array 	data from registration inserted into member table
	 * @param	int		member_id (only EE 2)
	 * @return	mixed	bool/string
	 */

	public function member_register_referrals($data, $member_id = 0)
	{
		//only EE 2 gives us the member_id sadly...
		if ( $member_id == 0)
		{
			$query = ee()->db->query(
				"SELECT	member_id
				 FROM 	exp_members
				 WHERE	username = '" . ee()->db->escape_str($data['username']) . "'
				 LIMIT  1"
			);

			//this should always be true unless something weird happens
			if ($query->num_rows() > 0)
			{
				$member_id = $query->row('member_id');
			}
		}

		//good member_id?
		if (is_numeric($member_id) AND $member_id != 0)
		{
			return $this->update_referrals($member_id, $data['email']);
		}

		return FALSE;
	}
	//END member_register_referrals


	// --------------------------------------------------------------------

	/**
	 * called by the member_member_register hook to process referrals on a new sign up
	 *
	 * @access	public
	 * @param	int 	member_id of registered person
	 * @param	string 	email of registered person
	 * @return	bool	worked
	 */

	public function update_referrals($member_id = 0, $email = '')
	{
		if ( ! is_numeric($member_id) OR $member_id == 0)
		{
			return FALSE;
		}

		//--------------------------------------------
		//	get email if missing
		//--------------------------------------------

		if ($email == '')
		{
			$query = ee()->db->query(
				"SELECT email
				 FROM 	exp_members
				 WHERE 	member_id = '" . ee()->db->escape_str($member_id) . "'"
			);

			//should never happen... right?
			if ($query->num_rows() == 0)
			{
				return FALSE;
			}

			$email = $query->row('email');
		}

		//--------------------------------------------
		//	is this a fresh referral?
		//--------------------------------------------

		$query = ee()->db->query(
			"SELECT	member_id, entry_id, group_id, referrer_id, site_id
			 FROM 	exp_friends
			 WHERE 	email = '" . ee()->db->escape_str($email) . "'"
		);

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		//--------------------------------------------
		//	friends object?
		//--------------------------------------------

		if ( ! is_object($this->friends))
		{
			if ( ! class_exists('Friends'))
			{
				require_once 'mod.friends.php';
			}

			//yay new friends!
			$this->friends = new Friends();
		}

		//--------------------------------------------
		//	update all people who referred
		//--------------------------------------------

		foreach ( $query->result_array() as $row )
		{
			//--------------------------------------------
			//	add member_id temp friend row
			//--------------------------------------------

			ee()->db->query(
				ee()->db->update_string(
					'exp_friends',
					array(
						'friend_id'	=> $member_id,
						'edit_date'	=> ee()->localize->now
					),
					array(
						'entry_id'	=> $row['entry_id']
					)
				)
			);

			//--------------------------------------------
			//	group_id and its all legit?
			//--------------------------------------------

			if ( ! in_array($row['group_id'], array(0, '0', '', FALSE), TRUE)  AND
				$group_data = $this->data->get_group_data_from_group_id(
					ee()->config->item('site_id'),
					$row['group_id']
				))
			{
				$owner_id = $group_data['owner_member_id'];

				$group_id = $row['group_id'];

				//lets add them to the group if its all legit
				$this->friends->_add_friends_to_group(
					array($member_id),
					array(),
					$owner_id,
					$group_id
				);
			}

			//	----------------------------------------
			//	Insert a referral
			//	----------------------------------------

			ee()->db->query(
				ee()->db->insert_string(
					'exp_friends_referrals',
					array(
						'member_id' 	=> $member_id,
						'referrer_id' 	=> $row['referrer_id'],
						'site_id'		=> $row['site_id']
					)
				)
			);
		}

	}
	//END update_referrals


	// --------------------------------------------------------------------

	/**
	 * Deletes members from a passed list or all non
	 *
	 * @access	public
	 * @return	null
	 */

	public function delete_members_from_friends ( $member_id = 0)
	{
		// --------------------------------
		//  Prep members
		// --------------------------------

		$members = array();
		$not 	 = '';

		if (is_numeric($member_id) AND $member_id > 0)
		{
			$members = $member_id;
		}
		else if (is_array($member_id))
		{
			$members = implode(',', ee()->db->escape_str($member_id));
		}
		//if member id is 0 or bad, lets just check all missing
		else
		{
			$not 	 = 'NOT';

			$query = ee()->db->query(
				"SELECT member_id
				 FROM 	exp_members"
			);

			foreach ($query->result_array() as $row)
			{
				$members[] = ee()->db->escape_str($row['member_id']);
			}

			$members = implode(',', $members);
		}

		// -------------------------------------
		//	sql building
		// -------------------------------------

		$sql	= array();

		$sql[]	= "DELETE FROM 	exp_friends
				   WHERE 		friend_id != 0
				   AND 			( friend_id {$not} IN ( {$members} )
								  OR member_id {$not} IN ( {$members} )
								)";

		$sql[]	= "DELETE FROM 	exp_friends_referrals
				   WHERE 		member_id
				   {$not} IN 	( {$members} )
				   OR 			referrer_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_groups
				   WHERE 		member_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_group_posts
				   WHERE 		member_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_group_entry_posts
				   WHERE 		member_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_group_comments
				   WHERE 		author_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_status
				   WHERE 		member_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_profile_comments
				   WHERE 		author_id
				   {$not} IN 	( {$members} )
				   OR 			friend_id
				   {$not} IN 	( {$members} )";

		$sql[]	= "DELETE FROM 	exp_friends_hugs
				   WHERE 		member_id
				   {$not} IN 	( {$members} )
				   OR 			friend_id
				   {$not} IN 	( {$members} )";

		foreach ( $sql as $q )
		{
			ee()->db->query( $q );
		}
	}
	//	End delete_non_existent_members
}
// END Class Friends_extension