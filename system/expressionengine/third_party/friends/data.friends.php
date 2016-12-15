<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Data Models
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @version		1.6.5
 * @filesource	friends/data.friends.php
 */

require_once 'addon_builder/data.addon_builder.php';

class Friends_data extends Addon_builder_data_friends
{
	public $cached = array();

	// --------------------------------------------------------------------

	/**
	 * Create message folders for members
	 *
	 * @access	public
	 * @return	boolean
	 */

	function create_message_folders_for_members( $members = array() )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		//	----------------------------------------
		//	No friends?
		//	----------------------------------------

		if ( count( $members ) == 0 ) return FALSE;

		//	----------------------------------------
		//	Get members with folders
		//	----------------------------------------

		$ids	= array();

		$query	= ee()->db->query(
			'SELECT member_id
			 FROM 	exp_message_folders
			 WHERE 	member_id
			 IN 	(\''.implode( "','", $members ).'\')' );

		foreach ( $query->result_array() as $row )
		{
			$ids[]	= $row['member_id'];
		}

		//	----------------------------------------
		//	Diff to determine who's missing
		//	----------------------------------------

		$ids	= array_diff( $members, $ids );

		//	----------------------------------------
		//	Create
		//	----------------------------------------

		foreach ( $ids as $id )
		{
			ee()->db->query(
				ee()->db->insert_string(
					'exp_message_folders',
					array(
						'member_id' => $id,
						'folder1_name' => 'InBox',
						'folder2_name' => 'Sent'
					)
				)
			);
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $this->cached[$cache_name][$cache_hash] = TRUE;
	}

	/*	End create message folders for members */

	// --------------------------------------------------------------------

	/**
	 * Get friend ids from member id
	 *
	 * @access	public
	 * @return	array
	 */

	function get_friend_ids_from_member_id( $site_id = '', $member_id = '' )
	{
		$site_id	= ( is_array( $site_id ) === TRUE ) ? $site_id: array( $site_id );

		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = array();

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $member_id == '' ) return array();

		// --------------------------------------------
		//  Get friend ids
		// --------------------------------------------

		$query	= ee()->db->query(
			"SELECT friend_id
			 FROM 	exp_friends
			 WHERE 	site_id
			 IN 	(" . implode( ",", $site_id ) . ")
			 AND 	member_id = " . ee()->db->escape_str( $member_id )
		);

		$arr	= array();

		foreach ( $query->result_array() as $row )
		{
			$arr[]	= $row['friend_id'];
		}

		$this->cached[$cache_name][$cache_hash] = $arr;

		return $arr;
	}

	/* End get friend ids from member id */


	// --------------------------------------------------------------------

	/**
	 * Get joined gruops from member id
	 *
	 * @access	public
	 * @return	array
	 */

	function get_joined_groups_from_member_id( $site_id = '', $member_id = '' )
	{
		$site_id	= ( is_array( $site_id ) === TRUE ) ? $site_id: array( $site_id );

		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = array();

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $member_id == '' ) return array();

		// --------------------------------------------
		//  Get friend ids
		// --------------------------------------------

		$query	= ee()->db->query(
			"SELECT group_id, entry_date AS group_join_date
			 FROM 	exp_friends_group_posts
			 WHERE 	site_id
			 IN 	(" . implode( ",", $site_id ) . ")
			 AND 	member_id = " . ee()->db->escape_str( $member_id )
		);

		$arr	= array();

		foreach ( $query->result_array() as $row )
		{
			$arr[ $row['group_id'] ]	= $row;
		}

		$this->cached[$cache_name][$cache_hash] = $arr;

		return $arr;
	}

	/* End get joined groups from member id */

	// --------------------------------------------------------------------

	/**
	 * Get group data from group id
	 *
	 * @access	public
	 * @return	array
	 */

	function get_group_data_from_group_id( $site_id = '', $group_id = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $group_id == '' ) return FALSE;

		// --------------------------------------------
		//  Check DB for friends group
		// --------------------------------------------

		$sql	= "SELECT 		fg.group_id, fg.member_id, fg.name,
								fg.title, fg.description, fg.private,
								m.member_id 	AS owner_member_id,
								m.email 		AS owner_email,
								m.username 		AS owner_username,
								m.screen_name 	AS owner_screen_name
				   FROM 		exp_friends_groups fg
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fg.member_id
				   WHERE 		fg.site_id = " . ee()->db->escape_str( $site_id ) . "
				   AND 			fg.group_id = " . ee()->db->escape_str( $group_id ) . "
				   LIMIT 		1";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) return FALSE;

		// --------------------------------------------
		//  Cache and return
		// --------------------------------------------

		$this->cached[$cache_name][$cache_hash] = $query->row_array();

		$this->cached['get_group_id_from_group_name'][ $this->_imploder( array( $site_id, $query->row('name') ) ) ] = $query->row('group_id');

		$this->cached['get_member_id_from_group_id'][ $this->_imploder( array( $site_id, $query->row('group_id') ) ) ] = $query->row('member_id');

		$this->cached['get_group_data_from_group_name'][ $this->_imploder( array( $site_id, $query->row('name') ) ) ] = $query->row_array();

		return $query->row_array();
	}

	/* End get group data from group id */

	// --------------------------------------------------------------------

	/**
	 * Get group data from group name
	 *
	 * @access	public
	 * @return	array
	 */

	function get_group_data_from_group_name( $site_id = '', $group_name = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $group_name == '' ) return FALSE;

		// --------------------------------------------
		//  Check DB for friends group
		// --------------------------------------------

		$sql	= "SELECT 		fg.group_id, fg.member_id, fg.name,
								fg.title, fg.description, fg.private,
								m.email AS owner_email,
								m.screen_name AS owner_screen_name
				   FROM 		exp_friends_groups fg
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fg.member_id
				   WHERE 		fg.site_id = " . ee()->db->escape_str( $site_id ) . "
				   AND 			name = '".ee()->db->escape_str( $group_name )."'
				   LIMIT 		1";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) return FALSE;

		// --------------------------------------------
		//  Cache and return
		// --------------------------------------------

		$this->cached[$cache_name][$cache_hash] = $query->row_array();

		$this->cached['get_group_id_from_group_name'][ $this->_imploder( array( $site_id, $query->row('name') ) ) ] = $query->row('group_id');

		$this->cached['get_member_id_from_group_id'][ $this->_imploder( array( $site_id, $query->row('group_id') ) ) ] = $query->row('member_id');

		$this->cached['get_group_data_from_group_id'][ $this->_imploder( array( $site_id, $query->row('group_id') ) ) ] = $query->row_array();

		return $query->row_array();
	}

	/* End get group data from group name */

	// --------------------------------------------------------------------

	/**
	 * Get group id from group name
	 *
	 * @access	public
	 * @return	integer
	 */

	function get_group_id_from_group_name( $site_id = '', $group_name = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $group_name == '' ) return FALSE;

		// --------------------------------------------
		//  Get group data
		// --------------------------------------------

		if ( ( $row = $this->get_group_data_from_group_name( $site_id, $group_name ) ) === FALSE )
		{
			return FALSE;
		}

		$this->cached[$cache_name][$cache_hash] = $row['group_id'];

		return $row['group_id'];
	}

	/* End get group id from group name */

	// --------------------------------------------------------------------

	/**
	 * Get member id from group id
	 *
	 * @access	public
	 * @return	integer
	 */

	function get_member_id_from_group_id( $site_id = '', $group_id = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $group_id == '' ) return FALSE;

		// --------------------------------------------
		//  Get group data
		// --------------------------------------------

		if ( ( $row = $this->get_group_data_from_group_id( $site_id, $group_id ) ) === FALSE )
		{
			return FALSE;
		}

		$this->cached[$cache_name][$cache_hash] = $row['member_id'];

		return $row['member_id'];
	}

	/* End get member id from group id */

	// --------------------------------------------------------------------

	/**
	 * Get member id from status id
	 *
	 * @access	public
	 * @return	integer
	 */

	function get_member_id_from_status_id($status_id = '')
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $status_id == '' ) return FALSE;

		// --------------------------------------------
		//  Hit the DB
		// --------------------------------------------

		$sql	= "SELECT 	member_id
				   FROM 	exp_friends_status
				   WHERE	status_id = " . ee()->db->escape_str( $status_id );

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) return FALSE;

		$this->cached[$cache_name][$cache_hash] = $query->row('member_id');

		return $query->row('member_id');
	}

	/* End get member id from status id */


	// --------------------------------------------------------------------

	/**
	 * Get member id from group id
	 *
	 * @access	public
	 * @return	integer
	 */

	function get_data_from_group_comment_id( $site_id = '', $comment_id = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $comment_id == '' ) return FALSE;

		// --------------------------------------------
		//  Hit the DB
		// --------------------------------------------

		$sql	= "SELECT 	*
				   FROM 	exp_friends_group_comments
				   WHERE 	site_id 	= " . ee()->db->escape_str( $site_id ) . "
				   AND 		comment_id 	= " . ee()->db->escape_str( $comment_id );

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) return FALSE;

		$this->cached[$cache_name][$cache_hash] = $query->row_array();

		return $this->cached[$cache_name][$cache_hash];
	}

	/* End get member id from group comment id */


	// --------------------------------------------------------------------

	/**
	 * Get member id from status id
	 *
	 * @access	public
	 * @return	integer
	 */

	function get_data_from_profile_comment_id( $site_id = '', $comment_id = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $comment_id == '' ) return FALSE;

		// --------------------------------------------
		//  Hit the DB
		// --------------------------------------------

		$sql	= "SELECT 	*
				   FROM 	exp_friends_profile_comments
				   WHERE 	site_id 	= " . ee()->db->escape_str( $site_id ) . "
				   AND 		comment_id 	= " . ee()->db->escape_str( $comment_id );

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) return FALSE;

		$this->cached[$cache_name][$cache_hash] = $query->row_array();

		return $this->cached[$cache_name][$cache_hash];
	}

	/* End get member id from profile comment id */


	// --------------------------------------------------------------------

	/**
	 * Get member id from username
	 *
	 * @access	public
	 * @return	integer
	 */

	function get_member_id_from_username( $site_id = '', $username = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $username == '' ) return FALSE;

		// --------------------------------------------
		//  Check SESS
		// --------------------------------------------

		if ( ee()->session->userdata('username') == $username )
		{
			$this->cached[$cache_name][$cache_hash] = ee()->session->userdata('member_id');

			return ee()->session->userdata('member_id');
		}

		// --------------------------------------------
		//  Check DB
		// --------------------------------------------

		$sql	= "SELECT 	member_id
				   FROM 	exp_members
				   WHERE 	username = '" . ee()->db->escape_str( $username ) . "'
				   LIMIT 	1";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}

		$this->cached[$cache_name][$cache_hash] = $query->row('member_id');

		return $query->row('member_id');
	}

	/* End get member id from username */

	// --------------------------------------------------------------------

	/**
	 * Get message folders for member
	 *
	 * Grab a member's message folders.
	 *
	 * @access	private
	 * @return	array
	 */

	function get_message_folders_for_member( $member_id = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = array();

		// --------------------------------------------
		//  No member id?
		// --------------------------------------------

		if ( $member_id == '' ) return array();

		//	----------------------------------------
		//	Get folders
		//	----------------------------------------

		$folders	= array(
			1 => 'InBox',
			2 => 'Sent',
			0 => 'Trash'
		);

		$sql	= "SELECT 	*
				   FROM 	exp_message_folders
				   WHERE 	member_id = ".ee()->db->escape_str( $member_id );

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->row_array() as $key => $val )
			{
				if ( $key == 'member_id' OR $val == '' ) continue;
				$folders[ str_replace( array( 'folder', '_name' ), '', $key ) ]	= $val;
			}
		}
		else
		{
			$this->create_message_folders_for_members( array( $member_id ) );
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		asort( $folders );

		return $this->cached[$cache_name][$cache_hash] = $folders;
	}

	/*	End get message folders for member */


	// --------------------------------------------------------------------

	/**
	 * Get get preference from site id
	 *
	 * @access	public
	 * @return	array
	 */

	function get_preference_from_site_id( $site_id = '', $preference = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = '';

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $preference == '' ) return FALSE;

		// --------------------------------------------
		//  Get all prefs for site
		// --------------------------------------------

		$query	= ee()->db->query(
			"SELECT preferences
			 FROM 	exp_friends_preferences
			 WHERE 	site_id = " . ee()->db->escape_str( $site_id )
		);

		if ( $query->num_rows() == 0 )
		{
			return '';
		}

		// --------------------------------------------
		//  Save prefs
		// --------------------------------------------

		$prefs	= unserialize( base64_decode( $query->row('preferences') ) );

		foreach ( $prefs as $key => $val )
		{
			$c_hash = $this->_imploder( array( $site_id, $key ) );
			$this->cached[$cache_name][$c_hash] = $val;
		}

		// --------------------------------------------
		//  Is our pref present?
		// --------------------------------------------

		if ( isset( $this->cached[$cache_name][$cache_hash] ) === TRUE )
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		return '';
	}

	/* End get preference from site id */

	// --------------------------------------------------------------------

	/**
	 * Member of group
	 *
	 * @access	public
	 * @return	array
	 */

	function member_of_group( $site_id = '', $member_id = '', $group_id = '' )
	{
		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//  Validate
		// --------------------------------------------

		if ( $site_id == '' OR $member_id == '' OR $group_id == '' ) return FALSE;

		// --------------------------------------------
		//  Check DB for friends group
		// --------------------------------------------

		$sql	= "SELECT 	COUNT(*) AS count
				   FROM 	exp_friends_group_posts fgp
				   WHERE 	fgp.site_id = " . ee()->db->escape_str( $site_id ) . "
				   AND 		fgp.member_id = " . ee()->db->escape_str( $member_id ) . "
				   AND 		fgp.group_id = " . ee()->db->escape_str( $group_id ) . "
				   AND 		fgp.accepted = 'y'
				   AND 		fgp.declined = 'n'
				   AND 		fgp.request_accepted = 'y'
				   AND 		fgp.request_declined = 'n'
				   LIMIT 	1";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 OR $query->row('count') == 0 ) return FALSE;

		// --------------------------------------------
		//  Cache and return
		// --------------------------------------------

		return $this->cached[$cache_name][$cache_hash] = TRUE;
	}

	/* End member of group */

	// --------------------------------------------------------------------

	/**
	 * Time to check referrals for invitee
	 *
	 * @access	public
	 * @return	boolean
	 */

	function time_to_check_referrals_for_invitee( $site_id = '', $member_id = '' )
	{
		// --------------------------------------------
		//  Set site id
		// --------------------------------------------

		$site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;

		// --------------------------------------------
		//  Set member id
		// --------------------------------------------

		$member_id	= ( $member_id == '' ) ? ee()->session->userdata('member_id'): $member_id;

		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder( func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//	Prune old data, older than 5 days
		// --------------------------------------------

		// We don't prune any data here. The invitee check
		// happens only once during the lifetime of that person's
		// site membership. We need a record of it so that we
		// don't do it again, ever.

		// --------------------------------------------
		//	Zero interval or no member id?
		// --------------------------------------------

		if ( $member_id == 0 ) return FALSE;

		// --------------------------------------------
		//  Should we check?
		// --------------------------------------------
		// If there is a record in the DB that the referral
		// check has already been done, we return FALSE
		// since we don't need to do it again.
		// --------------------------------------------

		$sql	= "SELECT 	COUNT(*) AS count
				   FROM 	exp_friends_automations
				   WHERE 	site_id = " . ee()->db->escape_str( $site_id ) . "
				   AND 		member_id = " . ee()->db->escape_str( $member_id ) . "
				   AND 		action = 'invitee_referral_check'";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 OR $query->row('count') == 0 )
		{
			$this->cached[$cache_name][$cache_hash]	= TRUE;
		}

		return $this->cached[$cache_name][$cache_hash];
	}

	/*	End time to check referrals for invitee */

	// --------------------------------------------------------------------

	/**
	 * Time to check referrals for inviter
	 *
	 * @access	public
	 * @return	boolean
	 */

	function time_to_check_referrals_for_inviter( $site_id = '', $member_id = '', $interval = 0 )
	{
		// --------------------------------------------
		//  Set site id
		// --------------------------------------------

		$site_id	= ( $site_id == '' ) ? ee()->config->item('site_id') : $site_id;

		// --------------------------------------------
		//  Set member id
		// --------------------------------------------

		$member_id	= ( $member_id == '' ) ? ee()->session->userdata('member_id'): $member_id;

		// --------------------------------------------
		//  Prep Cache, Return if Set
		// --------------------------------------------

		$cache_name = __FUNCTION__;
		$cache_hash = $this->_imploder(func_get_args());

		if (isset($this->cached[$cache_name][$cache_hash]))
		{
			return $this->cached[$cache_name][$cache_hash];
		}

		$this->cached[$cache_name][$cache_hash] = FALSE;

		// --------------------------------------------
		//	Prune old data, older than 5 days
		// --------------------------------------------

		ee()->db->query(
			"DELETE FROM 	exp_friends_automations
			 WHERE 			site_id = " . ee()->db->escape_str( $site_id ) . "
			 AND 			action = 'inviter_referral_check'
			 AND 			entry_date <= ( UNIX_TIMESTAMP() - ( 86400 * 5 ) )"
		);

		// --------------------------------------------
		//	Zero interval or no member id?
		// --------------------------------------------

		if ( $interval == 0 OR $member_id == 0 ) return FALSE;

		// --------------------------------------------
		//  Should we check?
		// --------------------------------------------
		// If there is a record in the DB that the referral
		// check has already been done within the specified
		// interval, we return FALSE since we don't need to do it again.
		// --------------------------------------------

		$sql	= "SELECT 	COUNT(*) AS count
				   FROM 	exp_friends_automations
				   WHERE 	site_id = " . ee()->db->escape_str( $site_id ) . "
				   AND 		member_id = " . ee()->db->escape_str( $member_id ) . "
				   AND 		action = 'inviter_referral_check'
				   AND 		entry_date >= ( UNIX_TIMESTAMP() - ( 60 * " . ee()->db->escape_str( $interval ) . " ) )";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 OR $query->row('count') == 0 )
		{
			$this->cached[$cache_name][$cache_hash]	= TRUE;
		}

		return $this->cached[$cache_name][$cache_hash];
	}

	/*	End time to check referrals for inviter */

	// --------------------------------------------------------------------

	/**
	* Update data in batch
	*
	* Generates a platform-specific batch update string from the supplied data
	*
	* @access	public
	* @param	string	the table name
	* @param	array	the update data
	* @param	array	the where clause
	* @return	string
	*/

	function update_data_in_batch( $table, $values, $index, $where = NULL )
	{
	/*
		$ids = array();
		$where = ($where != '' AND count($where) >=1) ? implode(" ", $where).' AND ' : '';

		foreach($values as $key => $val)
		{
			$ids[] = $val[$index];

			foreach(array_keys($val) as $field)
			{
				if ($field != $index)
				{
					$final[$field][] = Ê'WHEN '.$index.' = '.$val[$index].' THEN '.$val[$field];
				}
			}
		}

		$sql = "UPDATE ".$table." SET ";
		$cases = '';

		foreach($final as $k => $v)
		{
			$cases .= $k.' = CASE '."\n";
			foreach ($v as $row)
			{
				$cases .= $row."\n";
			}

			$cases .= 'ELSE '.$k.' END, ';
		}

		$sql .= substr($cases, 0, -2);

		$sql .= ' WHERE '.$where.$index.' IN ('.implode(',', $ids).')';

		return $sql;
		*/
	}
	/* End update data in batch */
}
// END CLASS Friends_data