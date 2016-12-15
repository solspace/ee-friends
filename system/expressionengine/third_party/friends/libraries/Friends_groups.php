<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Groups Class
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @filesource	friends/libraries/Friends_groups.php
 */

require_once realpath(rtrim(dirname(__FILE__), '/') . '/../mod.friends.php');

class Friends_groups extends Friends
{
	public $group_notifications	= array();

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access		public
	 * @return		null
	 */

	public function __construct()
	{
		parent::__construct();

		//	----------------------------------------
		//	Set group notifications array
		//	----------------------------------------

		$this->group_notifications	= array(
			'accept',
			'approve',
			'invite',
			'leave',
			'remove',
			'request'
		);

		//keeps us from calling this a bajallion times
		$this->clean_site_id = ee()->db->escape_str( ee()->config->item( 'site_id' ) );

		ee()->load->helper('text');
	}

	/* End constructor */

	// --------------------------------------------------------------------

	/**
	 * Add friends to group
	 *
	 * This function takes an array of member ids to be added to a given group by the owner of the group.
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _add_friends_to_group( $friends = array(), $prefs = array(), $owner_id = '', $group_id = '' )
	{
		$this->group_id	= ( $group_id != '' ) ? $group_id: $this->group_id;

		//	----------------------------------------
		//	Group id valid?
		//	----------------------------------------

		if ( $this->group_id == 0 OR
			 ( $group_data = $this->data->get_group_data_from_group_id(
				ee()->config->item('site_id'),
				$this->group_id )
			  ) === FALSE )
		{
			$this->message[]	= lang('group_id_required');
			return FALSE;
		}

		//	----------------------------------------
		//	No friends?
		//	----------------------------------------

		if ( count( $friends ) == 0 )
		{
			$this->message[]	= lang('friends_required');
			return FALSE;
		}

		//	----------------------------------------
		//	Clarify array
		//	----------------------------------------

		$arr	= array();

		foreach ( $friends as $friend )
		{
			$temp	= explode( "|", $friend );

			$arr	= array_merge( $arr, $temp );
		}

		$friends	= $arr;

		//	----------------------------------------
		//	Set owner id
		//	----------------------------------------
		//  We call this method inside $Friends::referral_check
		//  and the member id can change so we allow the owner
		//  id to be sent through the method as an argument.
		//  Otherwise we want to assume the person viewing
		//  the page is the owner.
		//	----------------------------------------

		$owner_id	= ( $owner_id == '' ) ? ee()->session->userdata('member_id'): $owner_id;

		//	----------------------------------------
		//	Is the inviter the owner of the group?
		//	----------------------------------------

		if ( $this->data->get_member_id_from_group_id(
				ee()->config->item('site_id'), $this->group_id ) != $owner_id )
		{
			$this->message[]	= lang('not_group_owner');
			return FALSE;
		}

		//	----------------------------------------
		//	Get list of members already in group_posts
		//	----------------------------------------

		$sql		= "SELECT 	member_id, accepted, declined,
								request_accepted, request_declined, invite_or_request
					   FROM 	exp_friends_group_posts
					   WHERE 	site_id = " . ee()->db->escape_str( ee()->config->item('site_id') ) . "
					   AND 		group_id = ".ee()->db->escape_str( $this->group_id ) . "
					   AND 		member_id
					   IN 		( " . implode( ',', $friends ) . " )";

		$query		= ee()->db->query( $sql );

		$members	= array();

		foreach ( $query->result_array() as $row )
		{
			$members[ $row['member_id'] ]	= $row;
		}

		//	----------------------------------------
		//	Get valid user list from DB
		//	----------------------------------------

		$sql	= "SELECT 	member_id, email, username, screen_name,
							" . implode( ",", array_flip( $this->group_prefs ) ) . "
				   FROM 	exp_members
				   WHERE 	friends_opt_out = 'n'
				   AND 		member_id
				   NOT IN 	( 	SELECT 	member_id
								FROM 	exp_friends_group_posts
								WHERE ( declined = 'y' OR request_accepted = 'y' )
								AND 	group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
							)
				   AND 		member_id
				   IN 		('" . implode( "','", $friends ) . "')";

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Empty
		//	----------------------------------------

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('no_valid_members');
			return FALSE;
		}

		//	----------------------------------------
		//	Loop and invite or confirm
		//	----------------------------------------

		$invited	= 0;
		$confirmed	= 0;

		foreach ( $query->result_array() as $row )
		{
			//	----------------------------------------
			//	Confirm?
			//	----------------------------------------
			//  If they are in our members array, we are confirming them.
			//	----------------------------------------

			if ( isset( $members[ $row['member_id'] ] ) === TRUE )
			{
				//	----------------------------------------
				//	Are they already members of the group?
				//	----------------------------------------

				if ( $members[ $row['member_id'] ][ 'accepted' ] == 'y' AND
					 $members[ $row['member_id'] ][ 'request_accepted' ] == 'y' ) continue;

				//	----------------------------------------
				//	Not yet a member, but ready to join?
				//	----------------------------------------

				if ( $members[ $row['member_id'] ][ 'accepted' ] == 'y' AND
					 $members[ $row['member_id'] ][ 'request_accepted' ] == 'n' )
				{
					$confirmed++;

					//	----------------------------------------
					//	Prep update
					//	----------------------------------------

					$sql	= ee()->db->update_string(
						'exp_friends_group_posts',
						array(
							'request_accepted'	=> 'y',
							'request_declined'	=> 'n',
							'entry_date'		=> ee()->localize->now
						),
						array(
							'group_id'	=> $this->group_id,
							'site_id'	=> ee()->config->item('site_id'),
							'member_id'	=> $row['member_id']
						)
					);

					ee()->db->query( $sql );

					//	----------------------------------------
					//	Notify
					//	----------------------------------------

					if ( $this->notify === TRUE AND
						 ! empty( $prefs['notification_approve'] ) AND
						 $prefs['notification_approve'] != '' )
					{
						unset( $email );
						$email['notification_template']					= $prefs['notification_approve'];
						$email['email']									= $row['email'];
						$email['member_id']								= $row['member_id'];
						$email['from_email']							= ee()->session->userdata['email'];
						$email['from_name']								= ee()->session->userdata['screen_name'];
						$email['subject']								= ( ! empty( $prefs['subject_approve'] ) ) ?
																			$prefs['subject_approve'] :
																			lang('subject_approve_email');
						$email['extra']['friends_group_id']				= $this->group_id;
						$email['extra']['friends_group_name']			= $group_data['name'];
						$email['extra']['friends_group_title']			= $group_data['title'];
						$email['extra']['friends_group_description']	= $group_data['description'];
						$email['extra']['friends_owner_screen_name']	= $group_data['owner_screen_name'];
						$email['extra']['friends_owner_username']		= $group_data['owner_username'];
						$email['extra']['friends_owner_member_id']		= $group_data['owner_member_id'];
						$email['extra']['friends_user_screen_name']		= $row['screen_name'];
						$email['extra']['friends_user_username']		= $row['username'];
						$email['extra']['friends_user_member_id']		= $row['member_id'];

						// ----------------------------------------
						// Let's quickly parse some vars just for sanity
						// ----------------------------------------

						foreach ( $email['extra'] as $key => $val )
						{
							$email['subject']	= str_replace(
								array( '%' . $key . '%', LD . $key . RD ),
								array( $val, $val ),
								$email['subject']
							);
						}

						$this->_notify( $email );
					}
				}
			}

			//	----------------------------------------
			//	Invite?
			//	----------------------------------------
			// 	If they are not in our members array, we are inviting them.
			//	----------------------------------------

			if ( isset( $members[ $row['member_id'] ] ) === FALSE )
			{
				$invited++;

				//	----------------------------------------
				//	Respect per member prefs
				//	----------------------------------------

				$arr	= array(
					'group_id'			=> $this->group_id,
					'entry_date'		=> ee()->localize->now,
					'member_id'			=> $row['member_id'],
					'invite_or_request'	=> 'invite'
				);

				foreach ( $this->group_prefs as $key => $val )
				{
					if ( isset( $row[$key] ) === FALSE ) continue;

					$arr[$val]	= $row[$key];
				}

				if ( ! isset($arr['site_id']) )
				{
					$arr['site_id'] = $this->clean_site_id;
				}

				ee()->db->query( ee()->db->insert_string( 'exp_friends_group_posts', $arr ) );

				//	----------------------------------------
				//	Notify?
				//	----------------------------------------

				if ( $this->notify === TRUE AND
					 ! empty( $prefs['notification_invite'] ) AND
					 $prefs['notification_invite'] != '' )
				{
					unset( $email );
					$email['notification_template']	= $prefs['notification_invite'];
					$email['email']									= $row['email'];
					$email['member_id']								= $row['member_id'];
					$email['from_email']							= ee()->session->userdata['email'];
					$email['from_name']								= ee()->session->userdata['screen_name'];
					$email['subject']								= ( ! empty( $prefs['subject_invite'] ) ) ?
																		$prefs['subject_invite'] :
																		lang('subject_invite_email');
					$email['extra']['friends_group_id']				= $this->group_id;
					$email['extra']['friends_group_name']			= $group_data['name'];
					$email['extra']['friends_group_title']			= $group_data['title'];
					$email['extra']['friends_group_description']	= $group_data['description'];
					$email['extra']['friends_owner_screen_name']	= $group_data['owner_screen_name'];
					$email['extra']['friends_owner_username']		= $group_data['owner_username'];
					$email['extra']['friends_owner_member_id']		= $group_data['owner_member_id'];
					$email['extra']['friends_user_screen_name']		= $row['screen_name'];
					$email['extra']['friends_user_username']		= $row['username'];
					$email['extra']['friends_user_member_id']		= $row['member_id'];

					//	----------------------------------------
					//	Let's quickly parse some vars just for sanity
					//	----------------------------------------

					foreach ( $email['extra'] as $key => $val )
					{
						$email['subject']	= str_replace(
							array( '%' . $key . '%', LD . $key . RD ),
							array( $val, $val ),
							$email['subject']
						);
					}

					$this->_notify( $email );
				}
			}
		}

		//	----------------------------------------
		//	Prepare messages
		//	----------------------------------------

		if ( ! empty( $invited ) AND $invited > 0 )
		{
			$line	= ( $invited == 1 ) ?
						lang( 'friend_invited' ) :
						str_replace( '%count%', $invited, lang( 'friends_invited' ) );

			$this->message[]	= $line;
		}

		if ( ! empty( $confirmed ) AND $confirmed > 0 )
		{
			$line	= ( $confirmed == 1 ) ?
						lang( 'friend_confirmed' ) :
						str_replace( '%count%', $confirmed, lang( 'friends_confirmed' ) );

			$this->message[]	= $line;
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return TRUE;
	}

	/* End add friends to group */

	// --------------------------------------------------------------------

	/**
	 * Comment notify
	 *
	 * This method notifies members about a comment posting.
	 * It is triggered only by the comment_end() method in the Friends extension.
	 *
	 * @access		public
	 * @return		boolean
	 */

	function comment_notify( $entries = array(), $members = array(), $email = array(), $prefix = 'friends_' )
	{
		//	----------------------------------------
		//	Validate entries
		//	----------------------------------------

		if ( count( $entries ) == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Validate members
		//	----------------------------------------

		if ( count( $members ) == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Get notification template
		//	----------------------------------------

		if ( isset( $email['notification_template'] ) === FALSE OR
			 $email['notification_template'] == '' )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Prepare vars
		//	----------------------------------------

		$data['extra']['friends_entry_id']		= implode( "|", $entries );
		$data['extra']['friends_comment_id']	= ( isset( $email['extra']['friends_comment_id'] ) === TRUE ) ?
													$email['extra']['friends_comment_id'] : '';
		$data['extra']['friends_comment']		= ( isset( $email['extra']['friends_comment'] ) === TRUE ) ?
													$email['extra']['friends_comment'] : '';

		//	----------------------------------------
		//	Get entry info
		//	----------------------------------------

		$sql	= "SELECT 	*
				   FROM 	{$this->sc->db->channel_titles}
				   WHERE 	entry_id = " . ee()->db->escape_str( $entries[0] );

		$query	= ee()->db->query( $sql );

		foreach ( $query->result_array() as $row )
		{
			$this->cache['entries'][$row['entry_id']]	= $row;
		}

		if ( empty( $this->cache['entries'][$entries[0]] ) === FALSE )
		{
			foreach ( $this->cache['entries'][$entries[0]] as $key => $val )
			{
				$data['extra'][$prefix.$key]	= $val;
			}
		}

		//	----------------------------------------
		//	Get group info
		//	----------------------------------------

		if ( empty( $email['extra']['group_id'] ) === FALSE )
		{
			if ( isset( $this->cache['groups'][$email['extra']['group_id']] ) === FALSE )
			{
				$sql	= "SELECT 	group_id,
									name 			AS {$prefix}group_name,
									title 			AS {$prefix}group_title,
									description 	AS {$prefix}group_description,
									private 		AS {$prefix}group_private
						   FROM 	exp_friends_groups
						   WHERE 	group_id = " . ee()->db->escape_str( $email['extra']['group_id'] ) . "
						   LIMIT 	1";

				$query	= ee()->db->query( $sql );

				if ( $query->num_rows() > 0 )
				{
					$this->cache['groups'][$query->row('group_id')]	= $query->row_array();
					$data['extra']	= array_merge( $data['extra'], $query->row_array() );
				}
			}
			else
			{
				$data['extra']	= array_merge(
					$data['extra'],
					$this->cache['groups'][$email['extra']['group_id']]
				);
			}
		}

		//	----------------------------------------
		//	Get member info
		//	----------------------------------------

		$sql	= "SELECT 		m.*, md.*
				   FROM 		exp_members m
				   LEFT JOIN 	exp_member_data md
				   ON 			md.member_id = m.member_id
				   WHERE 		m.member_id
				   IN 			('" . implode( "','", $members ) . "')";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Prep email
		//	----------------------------------------

		$data['notification_template']	= $email['notification_template'];
		$data['from_email']				= ( isset( $email['from_email'] ) === TRUE ) ?
											$email['from_email'] :
											ee()->config->item('webmaster_email');
		$data['from_name']				= ( isset( $email['from_name'] ) === TRUE ) ?
											$email['from_name'] :
											ee()->config->item('webmaster_name');
		$data['subject']				= ( isset( $email['subject'] ) === TRUE ) ?
											$email['subject'] :
											lang('friends_comment_notification');

		//	----------------------------------------
		//	Loop and send
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			//	----------------------------------------
			//	Already notified?
			//	----------------------------------------
			// 	The comment_notify method can be called in
			//	a loop for as many groups as have been
			//	chosen to notify about a given comment.
			//	We want to prevent one person from receiving
			//	a notification for every group they may belong
			//	to in our list of groups. So we put a test
			//	here real quick.
			//	----------------------------------------

			if ( isset( $this->cache['comment_notifications'][
					$email['extra']['friends_comment_id'] ][ $row['member_id'] ] ) === TRUE ) continue;

			$this->cache['comment_notifications'][ $email['extra']['friends_comment_id'] ][ $row['member_id'] ]	= $row['member_id'];

			$data['email']		= $row['email'];
			$data['member_id']	= $row['member_id'];

			foreach ( $row as $key => $val )
			{
				$data['extra'][$key]	= $val;
			}

			$this->_notify( $data );
		}

		//	----------------------------------------
		//	Get out
		//	----------------------------------------

		return TRUE;
	}

	/* End comment notify */

	// --------------------------------------------------------------------

	/**
	 * Create group
	 *
	 * This function takes input and creates a group assigned to the specified member. The incoming array contains this:
	 * [name]			=	name of group
	 * [title]			=	title of group
	 * [private]		=	whether to make the group visible only to its members. Default is public.
	 * [add_friends]	=	optional array of friends to be added to the newly created group
	 *
	 * @access		public
	 * @return		boolean
	 */

	function _create_group( $arr = array(), $create_default = FALSE )
	{
		//	----------------------------------------
		//	Validate member id
		//	----------------------------------------

		if ( $this->member_id == 0 OR
			 $this->_numeric( $this->member_id ) === FALSE )
		{
			$this->message[]	= lang('invalid_member_id');

			return FALSE;
		}

		//	----------------------------------------
		//	Make sure default exists by recurring this
		//	----------------------------------------

		if ( $create_default === FALSE )
		{
			// $this->_create_group( array(), TRUE );
		}

		//	----------------------------------------
		//	Are we creating default?
		//	----------------------------------------

		if ( $create_default === TRUE )
		{
			$arr	= array(
				'name' 			=> 'default',
				'title' 		=> 'Default',
				'description' 	=> ''
			);
		}

		//	----------------------------------------
		//	Validate name
		//	----------------------------------------

		if ( isset( $arr['name'] ) === FALSE OR $arr['name'] == '' )
		{
			$this->message[]	= lang('group_name_required');

			return FALSE;
		}

		//	----------------------------------------
		//	Does group exist?
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	exp_friends_groups
			 WHERE 	member_id = '" . ee()->db->escape_str( $this->member_id ) . "'
			 AND 	name = '" . ee()->db->escape_str( $arr['name'] ) . "'
			 AND    site_id = '" . $this->clean_site_id . "'"
		);

		if ( $query->row('count') > 0 )
		{
			if ( $arr['name'] != 'default' )
			{
				$this->message[]	= str_replace(
					"%group_name%",
					$arr['name'],
					lang('group_exists')
				);
			}

			return FALSE;
		}

		//	----------------------------------------
		//	Validate title
		//	----------------------------------------

		if ( isset( $arr['title'] ) === FALSE OR
			 $arr['title'] == '' )
		{
			$arr['title']	= $arr['name'];
		}

		//	----------------------------------------
		//	Public / private
		//	----------------------------------------

		if ( isset( $arr['private'] ) === FALSE OR
			 $arr['private'] != 'y' )
		{
			$arr['private']	= 'n';
		}

		//	----------------------------------------
		//	Extract friends
		//	----------------------------------------

		$add_friends	= array();
		$prefs			= array();

		if ( isset( $arr['add_friends'] ) === TRUE AND
			 is_array( $arr['add_friends'] ) === TRUE AND
			 count( $arr['add_friends'] ) > 0 )
		{
			$add_friends	= $arr['add_friends'];

			foreach ( array(
				'notification_approve',
				'notification_invite',
				'notification_remove',
				'subject_approve',
				'subject_invite',
				'subject_remove',
				'message',
				'link'
			  ) as $key )
			{
				$prefs[$key]	= ( isset( $arr[$key] ) === TRUE ) ? $arr[$key] : '';
			}
		}

		unset( $arr['add_friends'] );

		//	----------------------------------------
		//	Prep array for insert
		//	----------------------------------------

		$valid	= array( 'name', 'title', 'description', 'private' );

		foreach ( $arr as $key => $val )
		{
			if ( in_array( $key, $valid ) === FALSE )
			{
				unset( $arr[$key] );
			}
			else
			{
				$prefs[$key]	= $arr[$key];
			}
		}

		$arr['member_id']	= $this->member_id;
		$arr['entry_date']	= ee()->localize->now;
		$arr['site_id'] = ee()->config->item('site_id');

		//	----------------------------------------
		//	Insert group
		//	----------------------------------------

		ee()->db->query( ee()->db->insert_string( 'exp_friends_groups', $arr ) );

		$this->group_id	= ee()->db->insert_id();

		//	----------------------------------------
		//	Insert group post for owner
		//	----------------------------------------

		ee()->db->query(
			ee()->db->insert_string(
				'exp_friends_group_posts',
				array(
					'member_id' 	=> $this->member_id,
					'group_id' 		=> $this->group_id,
					'entry_date' 	=> ee()->localize->now,
					'accepted' 		=> 'y',
					'site_id'		=> $this->clean_site_id
				)
			)
		);

		//	----------------------------------------
		//	Add friends
		//	----------------------------------------

		if ( count( $add_friends ) > 0 )
		{
			if ( $this->_add_friends_to_group( $add_friends, $prefs ) === FALSE )
			{
				return FALSE;
			}
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$this->message[]	= str_replace( "%group_title%", $arr['title'], lang( 'group_added' ) );

		return TRUE;
	}

	/* End create group */

	// --------------------------------------------------------------------

	/**
	 * Edit group
	 *
	 * Edit group info and members.
	 *
	 * @access		public
	 * @return		boolean
	 */

	function edit_group()
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error( lang('not_logged_in'), ee()->input->get_post('error_template') );
		}

		$this->member_id	= ee()->session->userdata['member_id'];

		//	----------------------------------------
		//	Prep array
		//	----------------------------------------

		$arr = array();

		$arr['name']					= ee()->input->get_post('friends_group_name');
		$arr['title']					= ee()->input->get_post('friends_group_title');
		$arr['description']				= ee()->input->get_post('friends_group_description');
		$arr['subject']					= ee()->input->get_post('friends_group_subject');
		$arr['message']					= ee()->input->get_post('friends_group_message');
		$arr['private']					= ee()->input->get_post('friends_group_private');
		$arr['notification_approve']	= ee()->input->get_post('friends_notification_approve');
		$arr['notification_invite']		= ee()->input->get_post('friends_notification_invite');
		$arr['notification_remove']		= ee()->input->get_post('friends_notification_remove');
		$arr['subject_approve']			= ee()->input->get_post('friends_subject_approve');
		$arr['subject_invite']			= ee()->input->get_post('friends_subject_invite');
		$arr['subject_remove']			= ee()->input->get_post('friends_subject_remove');
		$arr['error_template']			= ee()->input->get_post('friends_error_template');

		//	----------------------------------------
		//	Build add friends array
		//	----------------------------------------

		if ( isset( $_POST['friends_member_id'] ) === TRUE )
		{
			// ----------------------------------------
			// Are we in remove mode?
			// ----------------------------------------

			$mode	= 'add_friends';

			if ( $this->check_yes(ee()->input->get_post('friends_remove')) )
			{
				$mode	= 'remove_friends';
			}

			if ( is_array( $_POST['friends_member_id'] ) )
			{
				$arr[$mode]	= $_POST['friends_member_id'];
			}
			elseif ( $_POST['friends_member_id'] != '' )
			{
				$arr[$mode][]	= $_POST['friends_member_id'];
			}
		}

		//	----------------------------------------
		//	Are we notifying?
		//	----------------------------------------

		$this->notify	= ! $this->check_no( ee()->input->post('friends_notify') );

		//	----------------------------------------
		//	Do we have a group id?
		//	----------------------------------------

		if ( $this->_group_id() === FALSE )
		{
			// ----------------------------------------
			// Run create
			// ----------------------------------------

			if ( $this->_create_group( $arr ) === FALSE )
			{
				return $this->_fetch_error( $this->message, $arr['error_template'] );
			}
		}
		else
		{
			// ----------------------------------------
			// Run edit
			// ----------------------------------------

			if ( $this->_edit_group( $arr ) === FALSE )
			{
				return $this->_fetch_error( $this->message, $arr['error_template'] );
			}
		}

		//	----------------------------------------
		//	Update group stats
		//	----------------------------------------

		$this->_update_group_stats( $this->group_id );

		//	----------------------------------------
		//	Update owned group stats
		//	----------------------------------------

		$this->_update_owned_group_stats( $this->member_id );

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$data['friends_message']	= $this->_prep_message();

		//	----------------------------------------
		//	Prep return
		//	----------------------------------------

		$return	= $this->_prep_return();

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		$return	= str_replace( "%friends_group_id%", $this->group_id, $return );

		//	----------------------------------------
		//	Are we using a template?
		//	----------------------------------------

		$template	= ( ee()->input->get_post('friends_group_template') !== FALSE ) ?
						ee()->input->get_post('friends_group_template') : '';

		if ( $body = $this->_fetch_template( $template, $data ) )
		{
			return $body;
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$return	= $this->_chars_decode( $return );

		ee()->functions->redirect( $return );
	}

	/* End edit group */

	// --------------------------------------------------------------------

	/**
	 * Edit group (sub)
	 *
	 * This function takes input and edits a group assigned to the specified member. The incoming array contains this:
	 * [name]				=	name of group
	 * [title]				=	title of group
	 * [description]		=	description of group
	 * [private]			=	whether to make the group visible only to its members. Default is public.
	 * [add_friends]		=	optional array of friends to be added to the newly created group
	 * [remove_friends]	=	optional array of friends to be added to the newly created group
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _edit_group( $arr = array() )
	{
		//	----------------------------------------
		//	Validate member id
		//	----------------------------------------

		if ( $this->member_id == 0 OR
			 $this->_numeric( $this->member_id ) === FALSE )
		{
			$this->message[]	= lang('invalid_member_id');

			return FALSE;
		}

		//	----------------------------------------
		//	Group id set?
		//	----------------------------------------

		if ( $this->group_id == 0 OR $this->group_id == '' )
		{
			$this->message[]	= lang('group_id_required');

			return FALSE;
		}

		//	----------------------------------------
		//	Get member's groups
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT 	member_id, group_id, name
			 FROM 		exp_friends_groups
			 WHERE 		member_id = '" . ee()->db->escape_str( $this->member_id ) . "'"
		);

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('group_not_found');

			return FALSE;
		}

		//	----------------------------------------
		//	Create array
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			$groups[ $row['group_id'] ]	= $row['name'];
		}

		//	----------------------------------------
		//	Group does not belong to member?
		//	----------------------------------------

		if ( isset( $groups[ $this->group_id ] ) === FALSE )
		{
			$this->message[]	= lang('group_not_belongs_to_member');

			return FALSE;
		}

		//	----------------------------------------
		//	Validate name
		//	----------------------------------------

		if ( isset( $arr['name'] ) === FALSE OR
			 $arr['name'] == '' )
		{
			$this->message[]	= lang('group_name_required');

			return FALSE;
		}

		unset( $groups[ $this->group_id ] );

		if ( in_array( $arr['name'], $groups ) === TRUE )
		{
			$this->message[]	= str_replace( "%group_name%", $arr['name'], lang('group_exists') );

			return FALSE;
		}

		//	----------------------------------------
		//	Validate title
		//	----------------------------------------

		if ( isset( $arr['title'] ) === FALSE OR $arr['title'] == '' )
		{
			$arr['title']	= $arr['name'];
		}

		//	----------------------------------------
		//	Public / private
		//	----------------------------------------

		if ( isset( $arr['private'] ) === FALSE OR $arr['private'] != 'y' )
		{
			$arr['private']	= 'n';
		}

		//	----------------------------------------
		//	Extract add friends
		//	----------------------------------------

		$prefs			= $arr;

		$add_friends	= array();

		if ( isset( $arr['add_friends'] ) === TRUE AND
			 is_array( $arr['add_friends'] ) === TRUE AND
			 count( $arr['add_friends'] ) > 0 )
		{
			$add_friends	= $arr['add_friends'];
		}

		unset( $arr['add_friends'] );

		//	----------------------------------------
		//	Extract remove friends
		//	----------------------------------------

		$remove_friends	= array();

		if ( isset( $arr['remove_friends'] ) === TRUE AND
			 is_array( $arr['remove_friends'] ) === TRUE AND
			 count( $arr['remove_friends'] ) > 0 )
		{
			$remove_friends	= $arr['remove_friends'];
		}

		unset( $arr['remove_friends'] );

		//	----------------------------------------
		//	Prep array for update
		//	----------------------------------------

		$valid	= array( 'name', 'title', 'description', 'private' );

		foreach ( $arr as $key => $val )
		{
			if ( in_array( $key, $valid ) === FALSE )
			{
				unset( $arr[$key] );
			}
		}

		$arr['edit_date']	= ee()->localize->now;

		//	----------------------------------------
		//	Update group
		//	----------------------------------------

		ee()->db->query( ee()->db->update_string( 'exp_friends_groups', $arr, array( 'group_id' => $this->group_id ) ) );

		//	----------------------------------------
		//	Prep notification email prefs
		//	----------------------------------------

		foreach ( $this->group_notifications as $val )
		{
			if ( ee()->input->get_post( 'friends_notification_' . $val ) !== FALSE AND
				 ee()->input->get_post( 'friends_notification_' . $val ) != '' )
			{
				$prefs[ 'notification_' . $val ]	= ee()->input->get_post( 'friends_notification_' . $val );
			}

			if ( ee()->input->get_post( 'friends_subject_' . $val ) !== FALSE AND
				 ee()->input->get_post( 'friends_subject_' . $val ) != '' )
			{
				$prefs[ 'subject_' . $val ]			= ee()->input->get_post( 'friends_subject_' . $val );
			}
		}

		//	----------------------------------------
		//	Add friends
		//	----------------------------------------

		if ( count( $add_friends ) > 0 )
		{
			if ( $this->_add_friends_to_group( $add_friends, $prefs ) === FALSE )
			{
				return FALSE;
			}
		}

		//	----------------------------------------
		//	Remove friends
		//	----------------------------------------

		if ( count( $remove_friends ) > 0 )
		{
			if ( $this->_remove_friends_from_group( $remove_friends, $prefs ) === FALSE )
			{
				return FALSE;
			}
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$this->message[]	= str_replace( "%group_title%", $arr['title'], lang( 'group_edited' ) );

		return TRUE;
	}

	/* End edit group */


	// --------------------------------------------------------------------

	/**
	 * Edit group preferences (sub)
	 *
	 * This method modifies group prefs.
	 *
	 * @access		private
	 * @return		string
	 */

	function edit_group_preferences()
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error(
				lang('not_logged_in'),
				ee()->input->post('error_template')
			);
		}

		//	----------------------------------------
		//	Update all?
		//	----------------------------------------

		$update_all	= $this->check_yes(
			ee()->input->get_post('update_all')
		);


		//	----------------------------------------
		//	Get updates
		//	----------------------------------------

		$updates	= array();

		foreach ( $this->group_prefs as $key => $val )
		{
			if ( $this->check_yes( ee()->input->get_post($key) ) )
			{
				$updates[$key]	= 'y';
			}
			elseif ( $this->check_no( ee()->input->get_post($key) ) )
			{
				$updates[$key]	= 'n';
			}
		}

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( count( $updates ) == 0 )
		{
			return $this->_fetch_error(
				lang('no_prefs_to_update'),
				ee()->input->post('error_template')
			);
		}

		//	----------------------------------------
		//	Update main prefs
		//	----------------------------------------


		ee()->db->update(
			'exp_members',
			$updates,
			array('member_id' => ee()->session->userdata['member_id'])
		);

		//	----------------------------------------
		//	Update groups
		//	----------------------------------------

		if ( $update_all === TRUE )
		{
			$arr	= array();

			foreach ( $updates as $key => $val )
			{
				$arr[ $this->group_prefs[$key] ]	= $val;
			}

			ee()->db->update_string(
				'exp_friends_group_posts',
				$arr,
				array(
					'member_id' => ee()->session->userdata['member_id']
				)
			);
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$this->message[]	= lang( 'group_prefs_updated' );

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata	= ee()->functions->prep_conditionals(
			ee()->TMPL->tagdata,
			$cond
		);

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$message	= $this->_prep_message();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD."friends_message".RD, $message, $tagdata );
	}
	/* End edit group preferences */


	// --------------------------------------------------------------------

	/**
	 * Entries
	 *
	 * This method parses weblog entries provided through $this->entry_id.
	 *
	 * @access		private
	 * @return		string
	 */

	function _entries ( $params = array() )
	{
		//	----------------------------------------
		//	Execute?
		//	----------------------------------------

		if ( $this->entry_id == '' ) return FALSE;

		//	----------------------------------------
		//	Invoke weblog class
		//	----------------------------------------


		if ( ! class_exists('Channel') )
		{
			require PATH_MOD.'/channel/mod.channel.php';
		}

		$channel = new Channel();

		// --------------------------------------------
		//  Invoke Pagination
		// --------------------------------------------

		$channel = $this->add_pag_to_channel($channel);

		//	----------------------------------------
		//	Pass params
		//	----------------------------------------

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

		ee()->TMPL->tagparams['inclusive']	= '';

		if ( isset( $params['dynamic'] ) AND $this->check_no($params['dynamic'])  )
		{
			ee()->TMPL->tagparams['dynamic']	= 'no';
		}

		//	----------------------------------------
		//	Pre-process related data
		//	----------------------------------------

		ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );

		//	----------------------------------------
		//	Execute needed methods
		//	----------------------------------------

		$channel->fetch_custom_channel_fields();

		$channel->fetch_custom_member_fields();

		// --------------------------------------------
		//  Pagination Tags Parsed Out
		// --------------------------------------------

		$channel = $this->fetch_pagination_data($channel);

		//	----------------------------------------
		//	Grab entry data
		//	----------------------------------------

		$channel->build_sql_query();

		if( $channel->sql == '' )
		{
			return FALSE;
		}

		$channel->query = ee()->db->query($channel->sql);

		if ( isset( $channel->query ) === FALSE OR $channel->query->num_rows() == 0)
		{
			return FALSE;
		}

		ee()->load->library('typography');
		ee()->typography->initialize();
		ee()->typography->convert_curly = FALSE;

		$channel->fetch_categories();

		//	----------------------------------------
		//	Parse and return entry data
		//	----------------------------------------

		$channel->parse_channel_entries();

		$channel = $this->add_pagination_data($channel);

		//	----------------------------------------
		// 	Handle problem with pagination segments
		// 	in the url
		//	----------------------------------------

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}

		$tagdata = $channel->return_data;

		return $tagdata;
	}

	/*	End sub entries */


	// --------------------------------------------------------------------

	/**
	 * Entry notify
	 *
	 * Notify a group about a blog entry.
	 *
	 * @access		private
	 * @return		boolean
	 */

	function entry_notify( $entries = array(), $members = array(), $email = array(), $prefix = 'friends_' )
	{
		//	----------------------------------------
		//	Validate entries
		//	----------------------------------------

		if ( count( $entries ) == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Validate members
		//	----------------------------------------

		if ( count( $members ) == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Get notification template
		//	----------------------------------------

		if ( isset( $email['notification_template'] ) === FALSE OR $email['notification_template'] == '' )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Prepare entries
		//	----------------------------------------

		$data['extra']['friends_entry_id']	= implode( "|", $entries );

		//	----------------------------------------
		//	Prep email
		//	----------------------------------------

		$data['notification_template']	= $email['notification_template'];
		$data['from_email']				= ( empty( $email['from_email'] ) === FALSE ) ?
											$email['from_email'] : ee()->config->item('webmaster_email');
		$data['from_name']				= ( empty( $email['from_name'] ) === FALSE ) ?
											$email['from_name'] : ee()->config->item('webmaster_name');
		$data['subject']				= ( empty( $email['subject'] ) === FALSE ) ?
											$email['subject'] : lang('friends_entry_notification');

		//	----------------------------------------
		//	Get entry info
		//	----------------------------------------

		$fetch_entries	= array();

		foreach ( $entries as $val )
		{
			if ( isset( $this->cache['entries'][$val] ) === TRUE ) continue;
			$fetch_entries[]	= $val;
		}

		if ( count( $fetch_entries ) > 0 )
		{
			$sql	= "SELECT 	*
					   FROM 	{$this->sc->db->channel_titles}
					   WHERE 	entry_id
					   IN 		(".implode( ',', $fetch_entries ).")";

			$query	= ee()->db->query( $sql );

			foreach ( $query->result_array() as $row )
			{
				$this->cache['entries'][$row['entry_id']]	= $row;
			}
		}

		if ( empty( $this->cache['entries'] ) === FALSE )
		{
			//	We need some way to create an entries loop inside
			// our notification template so that we can show a
			// list of entries. Eesh. This is nasty.

			foreach ( $this->cache['entries'] as $key => $val )
			{
				foreach ( $val as $k => $v )
				{
					$data['extra']['entries'][$key][$prefix.$k]	= $v;
				}
			}
		}

		//	----------------------------------------
		//	Get group info
		//	----------------------------------------

		if ( empty( $email['extra']['group_id'] ) === FALSE )
		{
			if ( isset( $this->cache['groups'][$email['extra']['group_id']] ) === FALSE )
			{
				$sql	= "SELECT 	group_id,
									name 		AS {$prefix}group_name,
									title 		AS {$prefix}group_title,
									description AS {$prefix}group_description,
									private 	AS {$prefix}group_private
						   FROM 	exp_friends_groups
						   WHERE 	group_id = " . ee()->db->escape_str( $email['extra']['group_id'] ) . "
						   LIMIT 	1";

				$query	= ee()->db->query( $sql );

				if ( $query->num_rows() > 0 )
				{
					$this->cache['groups'][$query->row('group_id')]	= $query->row_array();
					$data['extra']	= array_merge( $data['extra'], $query->row_array() );
				}
			}
			else
			{
				$data['extra']	= array_merge(
					$data['extra'],
					$this->cache['groups'][$email['extra']['group_id']]
				);
			}
		}

		//	----------------------------------------
		//	Get member info
		//	----------------------------------------

		$sql	= "SELECT 		m.*, md.*
				   FROM 		exp_members m
				   LEFT JOIN 	exp_member_data md
				   ON 			md.member_id = m.member_id
				   WHERE 		m.member_id != " . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "
				   AND 			m.member_id
				   IN 			('" . implode( "','", $members ) . "')";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Loop and send
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			//	----------------------------------------
			//	Already notified?
			//	----------------------------------------
			// 	The entry_notify method can be called in a
			//	loop for as many groups as have been chosen
			//	to notify about a given entry. We want to
			//	prevent one person from receiving a
			//	notification for every group they may belong
			//	to in our list of groups. So we put a test
			//	here real quick.
			//	----------------------------------------

			if ( isset( $this->cache['entry_notifications'][
					$email['extra']['group_id']][$row['member_id']] ) === TRUE ) continue;

			$this->cache['entry_notifications'][ $email['extra']['group_id'] ][ $row['member_id'] ]	= $row['member_id'];

			$data['email']		= $row['email'];
			$data['member_id']	= $row['member_id'];

			foreach ( $row as $key => $val )
			{
				$data['extra'][$prefix.$key]	= $val;
			}

			$this->_notify( $data );
		}

		//	----------------------------------------
		//	Get out
		//	----------------------------------------

		return TRUE;
	}

	/* End entry notify */


	// --------------------------------------------------------------------

	/**
	 * Group add
	 *
	 * This method is a traffic cop. It receives a request in a URI.
	 * The possible cases that this method can handle are as follows
	 *
	 * 1. Member wants to join a group. The URI would contain a group id.
	 * We detect that they are not a member of the given group and proceed with Request mode.
	 *
	 * 2. Member wants to leave a group. The URI would contain a group id and a segment with
	 * the value of 'delete'. We detect the 'delete' command and verify that they are a member
	 * of the group. We move to remove mode.
	 *
	 * 3. A group owner is viewing a member's profile. The group owner wants to invite the
	 * member to a group. On the profile page is a list of that owner's groups. Owner clicks
	 * a link. URI contains group id and member id. We detect that the invitee is not yet a
	 * member of the group and we detect that the current user owns the group. We move to
	 * invite mode.
	 *
	 * 4. A group owner wants to remove someone from a group. Somehow we have created a link
	 * with the group id and the member's id and the 'delete' keyword in the URL. We detect
	 * that the indicated group is owned by the current user. We detect that the indicated
	 * member is a member of the group. We detect the 'delete' keyword. We remove that member
	 * from the group.
	 *
	 * @access		public
	 * @return		boolean
	 */

	function group_add()
	{
		//	----------------------------------------
		//	Set tagdata
		//	----------------------------------------

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Are we notifying?
		//	----------------------------------------

		$this->notify	= ! $this->check_no( ee()->TMPL->fetch_param('notify') ) ;

		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == 0 )
		{
			$cond['failure']	= TRUE;
			$cond['success']	= FALSE;
			$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );
			$this->message[]	= lang( 'not_logged_in' );
			return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
		}

		//	----------------------------------------
		//	Group id
		//	----------------------------------------
		// 	We'll always have a group id. Fail out if not.
		//	----------------------------------------

		//	Hardcoded to template?

		if ( ee()->TMPL->fetch_param('friends_group_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('friends_group_id') ) === TRUE )
		{
			$this->group_id	= ee()->TMPL->fetch_param('friends_group_id');
		}
		elseif ( ee()->TMPL->fetch_param('group_name') !== FALSE AND
				 ( $group_id = $this->data->get_group_id_from_group_name(
						ee()->config->item('site_id'), ee()->TMPL->fetch_param('group_name') ) ) !== FALSE )
		{
			$this->group_id	= $group_id;
			unset( $group_id );
		}

		//	Sent through POST?
		elseif ( ee()->input->post('friends_group_id') !== FALSE AND
				 is_numeric( ee()->input->post('friends_group_id') ) === TRUE )
		{
			$this->group_id	= ee()->input->post('friends_group_id');
		}

		//	Is the group indicated in the URI?

		elseif ( $this->dynamic === TRUE AND
				 ( $seg = array_search( 'group', ee()->uri->segments ) ) !== FALSE )
		{
			if ( ! empty( ee()->uri->segments[ $seg + 1 ] ) )
			{
				//	Numeric group id?

				if ( is_numeric( ee()->uri->segments[ $seg + 1 ] ) === TRUE )
				{
					$this->group_id	= ee()->uri->segments[ $seg + 1 ];
				}
				elseif ( ( $group_id = $this->data->get_group_id_from_group_name(
							ee()->config->item('site_id'), ee()->uri->segments[ $seg + 1 ] ) ) !== FALSE )
				{
					$this->group_id	= $group_id;
					unset( $group_id );
				}
			}
		}

		//	----------------------------------------
		//	Did group id get set? We can't do anything without a group id
		//	----------------------------------------

		if ( $this->group_id == 0 )
		{
			$cond['failure']	= TRUE;
			$cond['success']	= FALSE;
			$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );
			$this->message[]	= lang( 'group_id_required' );
			return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
		}

		//	----------------------------------------
		//	Check member id for later
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('member_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('member_id') ) === TRUE )
		{
			$this->member_id	= ee()->TMPL->fetch_param('member_id');
		}
		elseif ( ee()->TMPL->fetch_param('username') !== FALSE AND
				 ee()->TMPL->fetch_param('username') != '' AND
				 ( $member_id = $this->data->get_member_id_from_username(
					ee()->config->item('site_id'), ee()->TMPL->fetch_param('username') ) ) !== FALSE )
		{
			$this->member_id	= $member_id;
			unset($member_id);
		}
		elseif ( $this->dynamic === TRUE AND
				 ( $seg = array_search( 'member', ee()->uri->segments ) ) !== FALSE )
		{
			if ( ! empty( ee()->uri->segments[ $seg + 1 ] ) )
			{
				//	Numeric member id?

				if ( is_numeric( ee()->uri->segments[ $seg + 1 ] ) === TRUE )
				{
					$this->member_id	= ee()->uri->segments[ $seg + 1 ];
				}
				elseif ( ( $member_id = $this->data->get_member_id_from_username(
						ee()->config->item('site_id'), ee()->uri->segments[ $seg + 1 ] ) ) !== FALSE )
				{
					$this->member_id	= $member_id;
					unset( $member_id );
				}
			}
		}

		//	----------------------------------------
		//	Are we in delete mode?
		//	----------------------------------------

		$delete	= FALSE;

		if ( ee()->TMPL->fetch_param('delete') == 'yes' )
		{
			$delete	= TRUE;
		}
		elseif ( $this->dynamic === TRUE AND array_search( 'delete', ee()->uri->segments ) !== FALSE )
		{
			$delete	= TRUE;
		}

		//	----------------------------------------
		//	Let's figure out what we're doing
		//	----------------------------------------
		// 	1. Are we a group owner deleting a member?
		// 	2. Are we a user deleting herself from a group?
		// 	3. Are we a group owner inviting a user?
		// 	4. Are we a user requesting to join a group or accept an invitation to join a group?
		// 	----------------------------------------

		//	----------------------------------------
		//	Owner deleting a member? Must own the indicated group and must have a member id in URI.
		//	----------------------------------------

		if (
			$delete === TRUE
			AND	ee()->session->userdata('member_id') == $this->data->get_member_id_from_group_id(
					ee()->config->item('site_id'), $this->group_id )
			AND	$this->member_id != 0
			)
		{
			//	----------------------------------------
			//	Try and remove friends from the group.
			//	----------------------------------------

			$prefs	= array(
				'notification_remove'	=> ee()->TMPL->fetch_param('notification_remove'),
				'subject_remove'		=> ee()->TMPL->fetch_param('subject_remove')
			);

			if ( $this->_remove_friends_from_group( array( $this->member_id ), $prefs ) === FALSE )
			{
				$cond['failure']	= TRUE;
				$cond['success']	= FALSE;
				$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );
				return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
			}
		}

		//	----------------------------------------
		//	Member removing herself from a group
		//	----------------------------------------

		elseif (
			$delete === TRUE
			AND	(
				$this->member_id == 0
				OR ee()->session->userdata('member_id') == $this->member_id
				)
			)
		{
			//	----------------------------------------
			//	Try and remove friends from the group.
			//	----------------------------------------

			$prefs	= array(
				'notification_leave'	=> ee()->TMPL->fetch_param('notification_leave'),
				'subject_leave'			=> ee()->TMPL->fetch_param('subject_leave')
			);

			if ( $this->_remove_self_from_group( $prefs ) === FALSE )
			{
				$cond['failure']	= TRUE;
				$cond['success']	= FALSE;
				$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );
				return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
			}
		}

		//	----------------------------------------
		//	Group owner inviting or accepting a user.
		//	----------------------------------------

		elseif (
			$delete === FALSE
			AND $this->member_id != 0
			AND	ee()->session->userdata('member_id') == $this->data->get_member_id_from_group_id(
					ee()->config->item('site_id'), $this->group_id )
			)
		{
			// ----------------------------------------
			// Try and add friends to the group.
			// ----------------------------------------

			$prefs	= array(
				'notification_invite'	=> ee()->TMPL->fetch_param('notification_invite'),
				'notification_approve'	=> ee()->TMPL->fetch_param('notification_approve'),
				'subject_invite'		=> ee()->TMPL->fetch_param('subject_invite'),
				'subject_approve'		=> ee()->TMPL->fetch_param('subject_approve')
			);

			if ( $this->_add_friends_to_group( array( $this->member_id ), $prefs ) === FALSE )
			{
				$cond['failure']	= TRUE;
				$cond['success']	= FALSE;
				$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );
				return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
			}
		}

		//	----------------------------------------
		//	User requesting to join a group or accept an invite.
		//	----------------------------------------

		elseif (
			$delete === FALSE
			AND (
				$this->member_id == 0
				OR ee()->session->userdata('member_id') == $this->member_id
				)
			)
		{
			// ----------------------------------------
			// Request to join or accept invite to group.
			// ----------------------------------------

			$prefs	= array(
				'notification_request'	=> ee()->TMPL->fetch_param('notification_request'),
				'notification_accept'	=> ee()->TMPL->fetch_param('notification_accept'),
				'subject_request'		=> ee()->TMPL->fetch_param('subject_request'),
				'subject_accept'		=> ee()->TMPL->fetch_param('subject_accept'),
			);

			if ( $this->_request_membership_or_accept_invite( $prefs ) === FALSE )
			{
				$cond['failure']	= TRUE;
				$cond['success']	= FALSE;
				$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );
				return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
			}
		}

		//	----------------------------------------
		//	Fail on all conditions?
		//	----------------------------------------

		else
		{
			$this->message[]	= lang( 'group_add_fail' );
			$tagdata	= ee()->functions->prep_conditionals(
				$tagdata,
				array(
					'failure' => TRUE,
					'success' => FALSE
				)
			);
			return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
		}

		//	----------------------------------------
		//	Update group stats
		//	----------------------------------------

		$this->_update_group_stats( $this->group_id );

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $cond );

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$message	= $this->_prep_message();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD."friends_message".RD, $message, $tagdata );
	}

	/* End group add */


	// --------------------------------------------------------------------

	/**
	 * Group delete
	 *
	 * This method deletes groups.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_delete ()
	{
		$this->trigger	= 'group_name';

		//	----------------------------------------
		//	Do we have group ids?
		//	----------------------------------------

		if ( $this->_group_id() === FALSE )
		{
			if ( empty( $_POST['friends_group_id'] ) )
			{
				$data['failure']	= TRUE;
				$data['success']	= FALSE;
				$data['friends_message']	= lang( 'group_not_found' );
			}
			elseif ( is_array( $_POST['friends_group_id'] ) === TRUE )
			{
				$group_ids	= $_POST['friends_group_id'];
			}
		}
		else
		{
			$group_ids	= array( $this->group_id );
		}

		//	----------------------------------------
		//	Execute
		//	----------------------------------------

		if ( ! empty( $group_ids ) )
		{
			if ( $this->_group_delete( $group_ids ) === FALSE )
			{
				$data['failure']			= TRUE;
				$data['success']			= FALSE;
				$data['friends_message']	= $this->message[0];
			}
			else
			{
				$data['failure']			= FALSE;
				$data['success']			= TRUE;
				$data['friends_message']	= $this->message[0];
			}
		}

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $data );

		foreach ( $data as $key	=> $val )
		{
			$tagdata	= ee()->TMPL->swap_var_single( $key, $val, $tagdata );
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $tagdata;
	}

	/* End group delete */


	// --------------------------------------------------------------------

	/**
	 * Group delete (sub)
	 *
	 * This method deletes a group.
	 *
	 * @access		public
	 * @return		boolean
	 */

	function _group_delete( $group_ids = array() )
	{
		//	----------------------------------------
		//	Group id set?
		//	----------------------------------------

		$group_ids	= $this->_only_numeric( $group_ids );

		//	----------------------------------------
		//	Group id set?
		//	----------------------------------------

		if ( empty( $group_ids ) )
		{
			$this->message[]	= lang('group_id_required');

			return FALSE;
		}

		//	----------------------------------------
		//	Get group info
		//	----------------------------------------

		$sql	= "SELECT 	member_id, title, private
				   FROM 	exp_friends_groups
				   WHERE 	member_id = " . ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
				   AND 		group_id
				   IN 		(" . implode( ",", $group_ids ) . ")";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('group_not_found');

			return FALSE;
		}

		//	----------------------------------------
		//	Delete group
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM 	exp_friends_groups
			 WHERE 			group_id
			 IN 			(" . implode( ",", $group_ids ) . ")" );

		//	----------------------------------------
		//	Remove friends
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM 	exp_friends_group_posts
			 WHERE 			group_id
			 IN 			(" . implode( ",", $group_ids ) . ")"
		);

		//	----------------------------------------
		//	Update member count
		//	----------------------------------------

		$titles		= array();

		foreach ( $query->result_array() as $row )
		{
			$titles[]	= $row['title'];
		}

		//	----------------------------------------
		//	Update owned group stats
		//	----------------------------------------

		$this->_update_owned_group_stats( ee()->session->userdata('member_id') );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		if ( count( $titles ) > 1 )
		{
			$this->message[]	= str_replace(
				"%group_title%",
				implode( ", ", $titles ),
				lang( 'groups_title_deleted' )
			);
		}
		else
		{
			$this->message[]	= str_replace(
				"%group_title%",
				implode( ", ", $titles ),
				lang( 'group_title_deleted' )
			);
		}

		return TRUE;
	}

	/* End group delete */


	// --------------------------------------------------------------------

	/**
	 * Group entries
	 *
	 * This lists blog entries that have been assigned to a group.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_entries()
	{
		//	----------------------------------------
		//	Check group privacy
		//	----------------------------------------

		if ( $this->_group_id() === TRUE )
		{
			$query	= ee()->db->query(
				"SELECT 	COUNT(*) AS count
				 FROM 		exp_friends_group_posts fgp
				 LEFT JOIN 	exp_friends_groups fg
				 ON 		fgp.group_id 	= fg.group_id
				 WHERE 		fg.private 		= 'n'
				 OR 		(	fgp.member_id = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'
								AND fgp.accepted = 'y'
								AND fgp.declined = 'n'
								AND fgp.request_accepted = 'y'
								AND fgp.request_declined = 'n'
							)"
			);

			if ( $query->row('count') == 0 )
			{
				return $this->no_results('friends');
			}
		}

		//	----------------------------------------
		//	Start SQL
		//	----------------------------------------

		$sql	= "SELECT 	entry_id
				   FROM 	exp_friends_group_entry_posts
				   WHERE 	group_id != ''";

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( $this->group_id != 0 )
		{
			$sql	.= " AND group_id = '" . ee()->db->escape_str( $this->group_id ) . "'";
		}

		//	----------------------------------------
		//	Private?
		//	----------------------------------------

		if ( $this->check_yes( ee()->TMPL->fetch_param('private') ) OR
			 $this->check_no(  ee()->TMPL->fetch_param('public') ) )
		{
			$sql	.= " AND private = 'y'";
		}
		else
		{
			$sql	.= " AND private = 'n'";
		}

		//	----------------------------------------
		//	Parse friends group id real quick, if set
		//	----------------------------------------

		if ( $this->group_id != 0 )
		{
			ee()->TMPL->template	= $this->_parse_group_data( ee()->TMPL->template, $this->group_id );
		}

		//	----------------------------------------
		//	Run query
		//	----------------------------------------

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Prep
		//	----------------------------------------

		$this->entry_id	= '';

		foreach ( $query->result_array() as $row )
		{
			$this->entry_id	.= $row['entry_id'].'|';
		}

		//	----------------------------------------
		//	Parse entries
		//	----------------------------------------

		if ( ! $tagdata = $this->_entries( array('dynamic' => 'off') ) )
		{
			return $this->no_results('friends');
		}

		return $tagdata;
	}

	/* End group entries */


	// --------------------------------------------------------------------

	/**
	 * Group entries (sub)
	 *
	 * This lists blog entries that have been assigned to a group.
	 *
	 * @access		private
	 * @return		string
	 */

	function _group_entries ( $params = array() )
	{
		//	----------------------------------------
		//	Execute?
		//	----------------------------------------

		if ( $this->entry_id == '' ) return FALSE;

		//	----------------------------------------
		//	Invoke weblog class
		//	----------------------------------------

		if ( ! class_exists('Channel') )
		{
			require PATH_MOD.'/channel/mod.channel.php';
		}

		$channel = new Channel();

		// --------------------------------------------
		//  Invoke Pagination
		// --------------------------------------------

		$channel = $this->add_pag_to_channel($channel);

		//	----------------------------------------
		//	Pass params
		//	----------------------------------------

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

		ee()->TMPL->tagparams['inclusive']	= '';

		if ( isset( $params['dynamic'] ) AND $this->check_no($params['dynamic'])  )
		{
			ee()->TMPL->tagparams['dynamic']	= 'no';
		}

		//	----------------------------------------
		//	Pre-process related data
		//	----------------------------------------

		ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );

		//	----------------------------------------
		//	Execute needed methods
		//	----------------------------------------

		$channel->fetch_custom_channel_fields();

		$channel->fetch_custom_member_fields();

		// --------------------------------------------
		//  Pagination Tags Parsed Out
		// --------------------------------------------

		$channel = $this->fetch_pagination_data($channel);

		//	----------------------------------------
		//	Grab entry data
		//	----------------------------------------

		$channel->build_sql_query();

		$channel->query = ee()->db->query($channel->sql);

		if ( isset( $channel->query ) === FALSE OR
			 $channel->query->num_rows() == 0)
		{
			return FALSE;
		}

		ee()->load->library('typography');
		ee()->typography->initialize();
		ee()->typography->convert_curly = FALSE;


		$channel->fetch_categories();

		//	----------------------------------------
		//	Parse and return entry data
		//	----------------------------------------

		$channel->parse_channel_entries();

		$channel = $this->add_pagination_data($channel);

		//	----------------------------------------
		// 	Handle problem with pagination segments
		// 	in the url
		//	----------------------------------------

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}

		$tagdata = $channel->return_data;

		return $tagdata;
	}

	/* End sub group entries */


	// --------------------------------------------------------------------

	/**
	 * Group entry
	 *
	 * This method allows one to evaluate whether a given blog entry belongs to a given group.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_entry()
	{
		$cond['friends_private_group']	= FALSE;
		$cond['friends_public_group']	= TRUE;

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Entry id
		//	----------------------------------------

		if ( $this->_entry_id() === FALSE )
		{
			return ee()->functions->prep_conditionals( $tagdata, $cond );
		}

		//	----------------------------------------
		//	Get groups
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT group_id
			 FROM 	exp_friends_group_entry_posts
			 WHERE 	entry_id = '" . ee()->db->escape_str( $this->entry_id ) . "'
			 AND 	private = 'y'"
		);

		if ( $query->num_rows() == 0 )
		{
			return ee()->functions->prep_conditionals( $tagdata, $cond );
		}

		//	----------------------------------------
		//	Not logged in?
		//	----------------------------------------

		if ( ee()->session->userdata('member_id') == 0 )
		{
			$cond['friends_private_group']	= TRUE;
			$cond['friends_public_group']	= FALSE;

			return ee()->functions->prep_conditionals( $tagdata, $cond );
		}

		//	----------------------------------------
		//	Create array
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			$groups[]	= $row['group_id'];
		}

		//	----------------------------------------
		//	Get this member's permissions
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	exp_friends_group_posts
			 WHERE 	accepted = 'y'
			 AND 	declined = 'n'
			 AND 	request_accepted = 'y'
			 AND 	request_declined = 'n'
			 AND 	member_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'
			 AND 	group_id
			 IN 	('" . implode( "','", $groups ) . "')"
		);

		if ( $query->row('count') == 0 )
		{
			$cond['friends_private_group']	= TRUE;
			$cond['friends_public_group']	= FALSE;
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return ee()->functions->prep_conditionals( $tagdata, $cond );
	}

	/* End group entry */


	// --------------------------------------------------------------------

	/**
	 * Group entry add
	 *
	 * Allows members to send blog entries to members belonging to one or more friends groups.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_entry_add()
	{
		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( empty( $_POST['friends_group_id'] ) === FALSE )
		{
			if ( is_array( $_POST['friends_group_id'] ) === TRUE )
			{
				$group_ids	= $_POST['friends_group_id'];
			}
			else
			{
				$this->group_id	= $_POST['friends_group_id'];
			}
		}
		elseif ( $this->_group_id() === FALSE )
		{
			return $this->_fetch_error( lang('group_id_required') );
		}

		//	----------------------------------------
		//	Set entries array
		//	----------------------------------------

		$entries	= array();

		if ( isset( $_POST['friends_entry_id'] ) === TRUE )
		{
			if ( is_array( $_POST['friends_entry_id'] ) === TRUE )
			{
				$entries	= $_POST['friends_entry_id'];
			}
			else
			{
				$entries[]	= $_POST['friends_entry_id'];
			}
		}
		elseif ( $this->_entry_id() === TRUE )
		{
			$entries[]	= $this->entry_id;
		}

		//	----------------------------------------
		//	Loop if necessary
		//	----------------------------------------

		if ( empty( $group_ids ) === TRUE )
		{
			$group_ids	= $this->group_id;
		}

		foreach ( $group_ids as $group_id )
		{
			$this->group_id	= $group_id;

			//	----------------------------------------
			//	Delete?
			//	----------------------------------------

			if ( $this->check_yes(ee()->input->get_post('friends_remove')) )
			{
				if ( $this->_group_entry_remove( $entries ) === FALSE )
				{
					return $this->_fetch_error( $this->message );
				}
			}

			//	----------------------------------------
			//	Add entries
			//	----------------------------------------

			elseif ( $this->_group_entry_add( $entries ) === FALSE )
			{
				return $this->_fetch_error( $this->message );
			}

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
				$email['extra']['group_id']		= $group_id;

				$sql	= "SELECT 	member_id
						   FROM 	exp_friends_group_posts
						   WHERE 	group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
						   AND 		accepted = 'y'
						   AND 		declined = 'n'
						   AND 		request_accepted = 'y'
						   AND 		request_declined = 'n'
						   AND 		notify_entries = 'y'
						   GROUP BY member_id";

				$query	= ee()->db->query( $sql );

				$members	= array();

				foreach ( $query->result_array() as $row )
				{
					$members[]	= $row['member_id'];
				}

				if ( $this->entry_notify( $entries, $members, $email ) === FALSE )
				{
				}
			}
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $cond );

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$message	= $this->_prep_message();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD."friends_message".RD, $message, $tagdata );
	}

	/* End group entry add */


	// --------------------------------------------------------------------

	/**
	 * Group entry add (sub)
	 *
	 * This function takes an array of entry ids and adds them to a friends group.
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _group_entry_add( $entries = array() )
	{
		//	----------------------------------------
		//	Group id set?
		//	----------------------------------------

		if ( $this->group_id == 0 OR $this->group_id == '' )
		{
			$this->message[]	= lang('group_id_required');

			return FALSE;
		}

		//	----------------------------------------
		//	No entries?
		//	----------------------------------------

		if ( count( $entries ) == 0 )
		{
			$this->message[]	= lang('entries_required');

			return FALSE;
		}

		//	----------------------------------------
		//	Does this person belong to the group?
		//	----------------------------------------

		$sql	= "SELECT 	COUNT(*) AS count
				   FROM 	exp_friends_group_posts
				   WHERE 	accepted = 'y'
				   AND 		declined = 'n'
				   AND 		group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
				   AND 		member_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'";

		$query	= ee()->db->query( $sql );

		if ( $query->row('count') == 0 )
		{
			$this->message[]	= lang('not_group_member');

			return FALSE;
		}

		//	----------------------------------------
		//	Get valid list from DB
		//	----------------------------------------

		$sql	= "SELECT 	entry_id
				   FROM 	{$this->sc->db->channel_titles}
				   WHERE 	entry_id
				   NOT IN 	( 	SELECT 	entry_id
								FROM 	exp_friends_group_entry_posts
								WHERE 	group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
							)
				   AND 		entry_id
				   IN 		('" . implode( ',', $entries ) . "')";

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Empty
		//	----------------------------------------

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('no_valid_entries');

			return FALSE;
		}

		//	----------------------------------------
		//	Loop and insert
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			ee()->db->query(
				ee()->db->insert_string(
					'exp_friends_group_entry_posts',
					array(
						'group_id' 	=> $this->group_id,
						'entry_id'	=> $row['entry_id'],
						'member_id' => ee()->session->userdata('member_id'),
						'site_id'	=> $this->clean_site_id
					)
				)
			);
		}

		//	----------------------------------------
		//	Update total members in group
		//	----------------------------------------

		$this->_update_group_stats( $this->group_id );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$line	= ( $query->num_rows() == 1 ) ?
					lang( 'entry_added' ) :
					lang( 'entries_added' );

		$this->message[]	= str_replace( "%n%", $query->num_rows(), $line );

		return TRUE;
	}

	/* End group entry add */


	// --------------------------------------------------------------------

	/**
	 * Group entry remove
	 *
	 * This function takes an array of entry ids and removes them from a friends group.
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _group_entry_remove( $entries = array() )
	{
		//	----------------------------------------
		//	Group id set?
		//	----------------------------------------

		if ( $this->group_id == 0 OR
			 $this->group_id == '' )
		{
			$this->message[]	= lang('group_id_required');

			return FALSE;
		}

		//	----------------------------------------
		//	No entries?
		//	----------------------------------------

		if ( count( $entries ) == 0 )
		{
			$this->message[]	= lang('entries_required');

			return FALSE;
		}

		//	----------------------------------------
		//	Does this person belong to the group?
		//	----------------------------------------

		if ( ee()->session->userdata('group_id') != 1 )
		{
			$sql	= "SELECT 	COUNT(*) AS count
					   FROM 	exp_friends_group_posts
					   WHERE 	accepted = 'y'
					   AND 		declined = 'n'
					   AND 		group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
					   AND 		member_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'";

			$query	= ee()->db->query( $sql );

			if ( $query->row('count') == 0 )
			{
				$this->message[]	= lang('not_group_member');

				return FALSE;
			}
		}

		//	----------------------------------------
		//	Get valid list from DB
		//	----------------------------------------

		$entries	= $this->_only_numeric( $entries );

		$sql	= "SELECT 	entry_id, group_id, member_id, site_id
				   FROM 	exp_friends_group_entry_posts
				   WHERE 	group_id = " . ee()->db->escape_str( $this->group_id ) . "
				   AND 		entry_id
				   IN 		(" . implode( ',', $entries ) . ")
				   AND 		entry_id
				   IN 		( 	SELECT 	entry_id
								FROM 	{$this->sc->db->channel_titles}
								WHERE 	entry_id
								IN 		(" . implode( ',', $entries ) . ")
							)";

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Empty
		//	----------------------------------------

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('no_valid_entries_to_remove');

			return FALSE;
		}

		//	----------------------------------------
		//	Loop and find entries not submitted by member
		//	----------------------------------------

		$not_mine	= array();
		$mine		= array();

		if ( ee()->session->userdata('group_id') != 1 AND
			 $this->data->get_member_id_from_group_id(
				ee()->config->item('site_id'), $this->group_id ) != ee()->session->userdata('member_id') )
		{
			foreach ( $query->result_array() as $row )
			{
				if ( $row['member_id'] == ee()->session->userdata('member_id') )
				{
					$mine[]	= $row['entry_id'];
				}
				else
				{
					$not_mine[]	= $row['entry_id'];
				}
			}

			if ( count( $not_mine ) > 0 )
			{
				$this->message[]	= str_replace( '%n%', count( $not_mine ), lang('entries_not_yours') );
			}

			if ( count( $mine ) == 0 )
			{
				$this->message[]	= lang('no_owned_entries');

				return FALSE;
			}
		}

		//	----------------------------------------
		//	Loop and insert
		//	----------------------------------------

		$deletes	= 0;

		foreach ( $query->result_array() as $row )
		{
			if ( in_array( $row['entry_id'], $mine ) === FALSE AND
				 ee()->session->userdata('group_id') != 1 AND
				 $this->data->get_member_id_from_group_id( ee()->config->item('site_id'),
					$this->group_id ) != ee()->session->userdata('member_id') ) continue;

			$sql	= "DELETE FROM 	exp_friends_group_entry_posts
					   WHERE 		entry_id = " . ee()->db->escape_str( $row['entry_id'] ) . "
					   AND 			group_id = " . ee()->db->escape_str( $row['group_id'] ) . "
					   AND 			site_id = " . ee()->db->escape_str( $row['site_id'] );

			ee()->db->query( $sql );

			$deletes	+= ee()->db->affected_rows();
		}

		//	----------------------------------------
		//	Update total members in group
		//	----------------------------------------

		$this->_update_group_stats( $this->group_id );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$line	= ( count( $deletes ) == 1 ) ?
						lang( 'entry_removed' ) :
						lang( 'entries_removed' );

		$this->message[]	= str_replace( "%n%", count( $deletes ), $line );

		return TRUE;
	}

	/* End group entry remove */


	// --------------------------------------------------------------------

	/**
	 * Group Form
	 *
	 * This method creates the form that allows one to edit a group.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_form()
	{
		$act	= ee()->functions->fetch_action_id('Friends', 'edit_group');

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Force member id
		//	----------------------------------------
		// 	In the context of this form, we are assuming
		// 	that the owner of the group is reviewing
		// 	the form. So we force the member id in order
		// 	to affect the contents of the var pairs
		// 	like {members} and {friends}
		//	----------------------------------------

		$this->member_id	= ee()->session->userdata('member_id');

		//	----------------------------------------
		//	Group if present
		//	----------------------------------------

		if ( $this->_group_id() === TRUE )
		{
			$this->arr['friends_group_id']	= $this->group_id;

			//	----------------------------------------
			//	Grab group info
			//	----------------------------------------

			$query	= ee()->db->query(
				"SELECT group_id,
						name 		AS friends_group_name,
						title 		AS friends_group_title,
						description AS friends_group_description,
						private 	AS friends_group_private
				 FROM 	exp_friends_groups
				 WHERE 	group_id = '" . $this->group_id . "'
				 AND 	member_id = '" . ee()->session->userdata['member_id'] . "'"
			);

			if ( $query->num_rows() == 0 )
			{
				ee()->TMPL->tagdata = preg_replace(
					"/" . LD . "if friends_paginate" . RD . ".*?" . LD . "&#47;if" . RD . "/s",
					'',
					 ee()->TMPL->tagdata
				);

				ee()->TMPL->tagdata = preg_replace(
					"/" . LD . "friends_paginate" . RD . ".*?" . LD . "&#47;friends_paginate" . RD . "/s",
					'',
					ee()->TMPL->tagdata
				);

				return $this->no_results('friends');
			}

			ee()->TMPL->tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $query->row_array() );

			foreach ( $query->row_array() as $key => $val )
			{
				ee()->TMPL->tagdata	= str_replace( LD . $key . RD, $val, ee()->TMPL->tagdata );
			}

			//	----------------------------------------
			//	Populate group members array while we're
			//	here
			//	----------------------------------------

			$query	= ee()->db->query(
				"SELECT member_id
				 FROM 	exp_friends_group_posts
				 WHERE 	accepted = 'y'
				 AND 	declined = 'n'
				 AND 	request_accepted = 'y'
				 AND 	request_declined = 'n'
				 AND 	site_id
				 IN 	(" . implode( ',', ee()->TMPL->site_ids ) . ")
				 AND 	group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
				 AND 	member_id != " . ee()->db->escape_str( $this->member_id )
			);

			foreach ( $query->result_array() as $row )
			{
				$this->group_members[]	= $row['member_id'];
			}
		}
		else
		{
			foreach ( array('friends_group_name', 'friends_group_title', 'friends_group_description') as $val )
			{
				ee()->TMPL->tagdata = preg_replace(
					"/" . LD . "friends_paginate" . RD . ".*?" . LD . "&#47;friends_paginate" . RD . "/s",
					'',
					ee()->TMPL->tagdata
				);

				ee()->TMPL->tagdata	= str_replace( LD . $val . RD, '', ee()->TMPL->tagdata );
			}
		}

		//	----------------------------------------
		//	We're viewing the group form right now.
		// 	That form can fetch a specific group
		// 	for editing by looking for an integer
		// 	in the URI. If it finds one, the integer
		// 	is assumed to indicate a group number.
		// 	If we run these var pairs we need to set
		// 	cdynamic mode to off so that we'll ignore the URL
		//	----------------------------------------

		$this->dynamic	= FALSE;

		//	----------------------------------------
		//	Parse
		//	----------------------------------------\

		$tagdata = ee()->TMPL->tagdata;

		if ( preg_match( "/" . LD . "members" . RD . "(.*?)" .
				LD . preg_quote(T_SLASH, '/') . "members" . RD . "/s", $tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->members();

			$tagdata		= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );
		}

		if ( preg_match( "/" . LD . "friends" . RD . "(.*?)" .
				LD . preg_quote(T_SLASH, '/') . "friends" . RD . "/s", $tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->mine();

			$tagdata		= str_replace( $match['0'], $this->tagdata, $tagdata );
		}

		if ( preg_match( "/" . LD . "invites" . RD . "(.*?)" .
				LD . preg_quote(T_SLASH, '/') . "invites" . RD . "/s", $tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->invites();

			$tagdata		= str_replace( $match['0'], $this->tagdata, $tagdata);
		}

		if ( preg_match( "/" . LD . "confirmed" . RD . "(.*?)" .
				LD . preg_quote(T_SLASH, '/') . "confirmed" . RD . "/s", $tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->group_members_confirmed();

			$tagdata		= str_replace( $match['0'], $this->tagdata, $tagdata );
		}

		if ( preg_match( "/" . LD . "requests" . RD . "(.*?)" .
				LD . preg_quote(T_SLASH, '/') . "requests" . RD . "/s", $tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->group_membership_requests();

			$tagdata		= str_replace( $match['0'], $this->tagdata, $tagdata );
		}

		//	----------------------------------------
		//	Parse groups
		//	----------------------------------------
		// 	This needs to happen after the {members}
		// 	and {friends} parsing because there can
		// 	be conflicts on vars like {member_id} and stuff.
		//	----------------------------------------

		ee()->TMPL->tagdata	= $this->_groups( ee()->TMPL->tagdata );

		//	----------------------------------------
		//	Prep data
		//	----------------------------------------

		$this->arr['ACT']								= $act;

		$this->arr['RET']								= (isset($_POST['RET'])) ?
																$_POST['RET'] : ee()->functions->fetch_current_uri();

		$this->arr['form_id']							= ( ee()->TMPL->fetch_param('form_id') ) ?
																ee()->TMPL->fetch_param('form_id') : 'group_form';

		$this->arr['form_name']							= ( ee()->TMPL->fetch_param('form_name') ) ?
																ee()->TMPL->fetch_param('form_name') : 'group_form';

		$this->arr['return']							= ( ee()->TMPL->fetch_param('return') ) ?
																ee()->TMPL->fetch_param('return') : '';

		$this->arr['friends_group_template']			= ( ee()->TMPL->fetch_param('group_template') ) ?
															ee()->TMPL->fetch_param('group_template') : '';

		foreach ( $this->group_notifications as $val )
		{
			$this->arr['friends_notification_' . $val]	= ( ee()->TMPL->fetch_param('notification_'.$val) ) ?
															ee()->TMPL->fetch_param('notification_'.$val) : '';
			$this->arr['friends_subject_' . $val]			= ( ee()->TMPL->fetch_param('subject_'.$val) ) ?
															ee()->TMPL->fetch_param('subject_'.$val) : '';
		}

		//	----------------------------------------
		//	Declare form
		//	----------------------------------------

		$this->arr['tagdata']	= $tagdata;

		return $this->_form();
	}

	/* End group form */


	// --------------------------------------------------------------------

	/**
	 * Group id
	 *
	 * This method determines the friends group id of a given request, sets it as a var and returns boolean.
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _group_id()
	{
		//	----------------------------------------
		//	Have we already set the member id?
		//	----------------------------------------

		if ( $this->group_id != 0 ) return TRUE;

		//	----------------------------------------
		//	Check post
		//	----------------------------------------

		if ( ee()->input->get_post('friends_group_id') !== FALSE AND
			 is_array( ee()->input->get_post('friends_group_id') ) === FALSE AND
			 $this->_numeric( ee()->input->get_post('friends_group_id') ) === TRUE )
		{
			$this->group_id	= ee()->input->get_post('friends_group_id');

			return TRUE;
		}

		//	----------------------------------------
		//	Track down the group id?
		//	----------------------------------------

		$cat_segment	= ee()->config->item("reserved_category_word");

		if ( isset(ee()->TMPL) AND is_object(ee()->TMPL) )
		{
			if ( $this->_numeric( ee()->TMPL->fetch_param('friends_group_id') ) === TRUE )
			{
				$this->group_id	= ee()->TMPL->fetch_param('friends_group_id');

				return TRUE;
			}
			elseif ( ee()->TMPL->fetch_param('group_name') !== FALSE )
			{
				$query	= ee()->db->query(
					"SELECT group_id
					 FROM 	exp_friends_groups
					 WHERE 	name = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('group_name') ) . "'"
				);

				if ( $query->num_rows() == 1 )
				{
					$this->group_id	= $query->row('group_id');

					return TRUE;
				}
				else
				{
					return FALSE;
				}
			}
			elseif ( preg_match( "#/".$this->trigger."/(\w+)/?#", ee()->uri->uri_string, $match ) )
			{
				$sql	= "SELECT 	group_id
						   FROM 	exp_friends_groups";

				if ( is_numeric( $match['1'] ) )
				{
					$sql	.= " WHERE group_id = '" . ee()->db->escape_str( $match['1'] ) . "'";
				}
				else
				{
					$sql	.= " WHERE name = '" . ee()->db->escape_str( $match['1'] ) . "'";
				}

				$sql	.= " LIMIT 1";

				$query	= ee()->db->query( $sql );

				if ( $query->num_rows() == 1 )
				{
					$this->group_id	= $query->row('group_id');

					return TRUE;
				}
			}
		}

		//	----------------------------------------
		//	No luck so far? Let's try query string
		//	----------------------------------------

		if ( ee()->uri->query_string != '' AND
			 $this->dynamic === TRUE )
		{
			$qstring	= ee()->uri->query_string;

			// ----------------------------------------
			// Do we have a pure ID number?
			// ----------------------------------------

			if ( is_numeric( $qstring) )
			{
				$this->group_id	= $qstring;
			}
			else
			{
				//	----------------------------------------
				//	Parse day
				//	----------------------------------------

				if (preg_match("#\d{4}/\d{2}/(\d{2})#", $qstring, $match))
				{
					$partial	= substr($match['0'], 0, -3);

					$qstring	= trim_slashes(str_replace($match['0'], $partial, $qstring));
				}

				//	----------------------------------------
				//	Parse /year/month/
				//	----------------------------------------

				if (preg_match("#(\d{4}/\d{2})#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['1'], '', $qstring));
				}

				//	----------------------------------------
				//	Parse page number
				//	----------------------------------------

				if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['0'], '', $qstring));
				}

				//	----------------------------------------
				//	Parse category indicator
				//	----------------------------------------

				// Text version of the category

				if (preg_match("#^".$cat_segment."/#", $qstring, $match) AND ee()->TMPL->fetch_param('weblog'))
				{
					$qstring	= str_replace($cat_segment.'/', '', $qstring);

					$sql		= "SELECT DISTINCT 	cat_group
								   FROM 			{$this->sc->db->channels}
								   WHERE ";


					$xsql	= ee()->functions->sql_andor_string(
						ee()->TMPL->fetch_param($this->sc->channel),
						$this->sc->db->channel_name
					);

					if (substr($xsql, 0, 3) == 'AND') $xsql = substr($xsql, 3);

					$sql	.= ' '.$xsql;


					$query	= ee()->db->query($sql);

					if ($query->num_rows() == 1)
					{
						$result	= ee()->db->query(
							"SELECT cat_id
							 FROM 	exp_categories
							 WHERE 	cat_name = '" . ee()->db->escape_str($qstring) . "'
							 AND 	group_id = '" . $query->row('cat_group') . "'"
						);

						if ($result->num_rows() == 1)
						{
							$qstring	= 'C' . $result->row('cat_id');
						}
					}
				}

				// Numeric version of the category

				if (preg_match("#^C(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['0'], '', $qstring));
				}

				//	----------------------------------------
				//	Remove "N"
				//	----------------------------------------

				// The recent comments feature uses "N" as the URL indicator
				// It needs to be removed if presenst

				if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['0'], '', $qstring));
				}

				//	----------------------------------------
				//	Return if numeric
				//	----------------------------------------

				if ( is_numeric( str_replace( "/", "", $qstring) ) )
				{
					$this->group_id	= $qstring;
				}
				elseif ( preg_match( "/\/(\d+)\//s", $qstring, $match ) )
				{
					$this->group_id	= $match['1'];
				}
			}

			//	----------------------------------------
			//	Let's check the number against the DB
			//	----------------------------------------

			if ( $this->group_id != '' )
			{
				$query	= ee()->db->query(
					"SELECT group_id
					 FROM 	exp_friends_groups
					 WHERE 	group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
					 LIMIT 	1"
				);

				if ( $query->num_rows() > 0 )
				{
					$this->group_id	= $query->row('group_id');

					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/* End group id */


	// --------------------------------------------------------------------

	/**
	 * Group invite
	 *
	 * This method handles an individual's response to group invites and
	 * memberships. Members can accept invites, they can remove themselves from groups, etc.
	 *
	 * Deprecated to group_add()
	 *
	 * @access		public
	 * @return		string
	 */

	function group_invite()
	{
		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error( lang('not_logged_in'), ee()->input->post('error_template') );
		}

		//	----------------------------------------
		//	Group id?
		//	----------------------------------------

		if ( $this->_group_id() === FALSE )
		{
			return $this->_fetch_error(
				lang('group_id_required'),
				ee()->input->post('error_template')
			);
		}

		//	----------------------------------------
		//	Has the member been invited?
		//	----------------------------------------

		$sql	= "SELECT 		fgp.accepted, fg.name, fg.title,
								fg.description, m.email, m.screen_name
				   FROM 		exp_friends_group_posts fgp
				   LEFT JOIN 	exp_friends_groups fg
				   ON 			fg.group_id = fgp.group_id
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fg.member_id
				   WHERE 		fgp.group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
				   AND 			fgp.member_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->_fetch_error(
				lang('group_not_found'),
				ee()->input->post('error_template')
			);
		}

		//	----------------------------------------
		//	Has the member already accepted?
		//	----------------------------------------

		if ( $query->row('accepted') == 'y' )
		{

			$this->message[]	= str_replace(
				'%group_title%',
				$query->row('title'), lang('already_accepted_group_invitation') );

			//	----------------------------------------
			//	Prep cond
			//	----------------------------------------

			$cond['failure']	= TRUE;
			$cond['success']	= FALSE;

			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

			//	----------------------------------------
			//	Prep message
			//	----------------------------------------

			$message	= $this->_prep_message();

			//	----------------------------------------
			//	Return
			//	----------------------------------------

			return str_replace( LD."friends_message".RD, $message, $tagdata );
		}

		//	----------------------------------------
		//	Set friend template for later.
		//	----------------------------------------

		$notification_template	= ( ee()->input->get_post('notification_template') !== FALSE AND
									ee()->input->get_post('notification_template') != '' ) ?
										ee()->input->get_post('notification_template') : '';

		//	----------------------------------------
		//	Are we notifying?
		//	----------------------------------------

		$this->notify	= ! $this->check_no( ee()->input->post('notify'));

		//	----------------------------------------
		//	Are we deleting?
		//	----------------------------------------

		if ( strpos( ee()->uri->uri_string, "/delete" ) !== FALSE OR
			  $this->check_yes(ee()->input->post('delete') ) )
		{
			$this->delete	= TRUE;
		}

		//	----------------------------------------
		//	Process
		//	----------------------------------------

		if ( $this->delete === TRUE )
		{
			$data				= array(
				'accepted' 		=> 'n',
				'declined' 		=> 'y',
				'entry_date' 	=> ee()->localize->now
			);

			$email['message']	= str_replace(
				array( '%screen_name%', '%group_title%' ),
				array( ee()->session->userdata('screen_name'), $query->row('title') ),
				lang('declined_group_invitation_owner')
			);

			$email['subject']	= $email['message'];

			$this->message[]	= str_replace(
				'%group_title%',
				$query->row('title'),
				lang('declined_group_invitation')
			);
		}
		else
		{
			$data	= array(
				'accepted' 		=> 'y',
				'declined' 		=> 'n',
				'entry_date' 	=> ee()->localize->now
			);

			$email['message']	= str_replace(
				array( '%screen_name%', '%group_title%' ),
				array( ee()->session->userdata('screen_name'), $query->row('title') ),
				lang('accepted_group_invitation_owner')
			);

			$email['subject']	= $email['message'];

			$this->message[]	= str_replace(
				'%group_title%',
				$query->row('title'),
				lang('accepted_group_invitation')
			);
		}

		$sql	= ee()->db->update_string(
			'exp_friends_group_posts',
			$data,
			array(
				'group_id' 	=> $this->group_id,
				'member_id' => ee()->session->userdata('member_id')
			)
		);

		ee()->db->query( $sql );

		//	----------------------------------------
		//	Notify
		//	----------------------------------------

		if ( $this->notify === TRUE AND $notification_template != '' )
		{
			$email['notification_template']					= $notification_template;
			$email['email']									= $query->row('email');
			$email['member_id']								= ee()->session->userdata['member_id'];
			$email['from_email']							= ee()->session->userdata['email'];
			$email['from_name']								= ee()->session->userdata['screen_name'];
			$email['extra']['friend_group_id']				= $this->group_id;
			$email['extra']['friend_group_name']			= $query->row('name');
			$email['extra']['friend_group_title']			= $query->row('title');
			$email['extra']['friend_group_description']		= $query->row('description');

			$this->_notify( $email );
		}

		//	----------------------------------------
		//	Update group stats
		//	----------------------------------------

		$this->_update_group_stats( $this->group_id );

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$message	= $this->_prep_message();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD . "friends_message" . RD, $message, $tagdata );
	}

	/* End group invite */


	// --------------------------------------------------------------------

	/**
	 * Group members
	 *
	 * This method lists members in a group.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_members()
	{
		//	----------------------------------------
		//	Group id?
		//	----------------------------------------

		if ( $this->_group_id() === FALSE )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Capture pagination data if it's there?
		//	----------------------------------------

		if ( preg_match( "/" . LD . "friends_paginate" . RD . "(.+?)" . LD .
				preg_quote(T_SLASH, '/') . "friends_paginate" . RD . "/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->paginate_match	= $match;

			ee()->TMPL->tagdata	= str_replace( $this->paginate_match[0], '', ee()->TMPL->tagdata );
		}

		//	----------------------------------------
		//	Switch on type
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('type') !== FALSE AND
			 ee()->TMPL->fetch_param('type') != '' )
		{
			if ( ee()->TMPL->fetch_param('type') == 'invite' )
			{
				return $this->group_membership_invites();
			}
			elseif ( ee()->TMPL->fetch_param('type') == 'request' )
			{
				return $this->group_membership_requests();
			}
			else
			{
				return $this->group_members_confirmed();
			}
		}
		else
		{
			return $this->group_members_confirmed();
		}
	}

	/* End group members */


	// --------------------------------------------------------------------

	/**
	 * Group members confirmed
	 *
	 * Lists people that are confirmed members of a given group.
	 *
	 * @access	public
	 * @return	string
	 */

	function group_members_confirmed()
	{
		//	----------------------------------------
		//	Prep subquery
		//	----------------------------------------

		$subquery	= "SELECT 	member_id
					   FROM 	exp_friends_group_posts
					   WHERE 	site_id
					   IN 		(" . implode( ',', ee()->TMPL->site_ids ) . ")
					   AND 		group_id = " . ee()->db->escape_str( $this->group_id ) . "
					   AND 		accepted = 'y'
					   AND 		declined = 'n'
					   AND 		request_accepted = 'y'
					   AND 		request_declined = 'n'";

		//	----------------------------------------
		//	Tagdata
		//	----------------------------------------

		$this->tagdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata: $this->tagdata;

		//	----------------------------------------
		//	Run our utility method
		//	----------------------------------------

		return $this->_fetch_group_members_through_subquery( $subquery );
	}

	/* End group members confirmed */


	// --------------------------------------------------------------------

	/**
	 * Fetch group members through subquery
	 *
	 * Lists members of a site filtered by a subquery.
	 *
	 * @access	private
	 * @return	string
	 */

	function _fetch_group_members_through_subquery( $subquery = '' )
	{
		//	----------------------------------------
		//	Subquery?
		//	----------------------------------------

		if ( $subquery == '' ) return $this->no_results( 'friends' );

		//	----------------------------------------
		//	Group if present
		//	----------------------------------------

		if ( $this->_group_id() === FALSE )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	SQL
		//	----------------------------------------

		$sql	= "SELECT 	m.member_id
				   FROM 	exp_members m
				   WHERE 	m.member_id != ''";

		//	----------------------------------------
		//	Get confirmed members of the given group
		//	----------------------------------------

		$sql	.= " AND m.member_id IN (". $subquery .")";

		//	----------------------------------------
		//	Member id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('member_id') !== FALSE AND
			 ee()->TMPL->fetch_param('member_id') != '' )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('member_id'), 'm.member_id' );
		}

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param( 'member_group_id' ) )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param( 'member_group_id' ), 'm.group_id' );
		}

		//	----------------------------------------
		//	Email
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('email') )
		{
			$sql	.= " AND m.email = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('email') ) . "'";
		}

		//	----------------------------------------
		//	Days / hours
		//	----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('days') ) )
		{
			$days	= ee()->localize->NOW - ( ee()->TMPL->fetch_param('days') + 86400 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $days ) . "'";
		}
		elseif ( is_numeric( ee()->TMPL->fetch_param('hours') ) )
		{
			$hours	= ee()->localize->NOW - ( ee()->TMPL->fetch_param('hours') + 3600 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $hours ) . "'";
		}

		//	----------------------------------------
		//	Letter
		//	----------------------------------------

		if ( preg_match( "/\/(username|screen_name)\/(.+?)\//s", ee()->uri->uri_string, $match ) AND
			 $this->dynamic === TRUE )
		{
			$sql	.= ( $match['1'] == 'username' ) ? " AND m.username LIKE '": " AND m.screen_name LIKE '";
			$sql	.= ee()->db->escape_str( $match['2'] ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('username') )
		{
			$sql	.= " AND m.username LIKE '" . ee()->db->escape_str( ee()->TMPL->fetch_param('username') ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('screen_name') )
		{
			$sql	.= " AND m.screen_name LIKE '" . ee()->db->escape_str( ee()->TMPL->fetch_param('screen_name') ) . "%'";
		}

		//	----------------------------------------
		//	Group by
		//	----------------------------------------

		$sql	.= " GROUP BY m.member_id";

		//	----------------------------------------
		//	Order by
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND
			 ee()->TMPL->fetch_param('orderby') != '' AND
			 strpos( ee()->TMPL->fetch_param('orderby'), '|' ) === FALSE )
		{
			$sql	.= " ORDER BY m.".ee()->db->escape_str( ee()->TMPL->fetch_param('orderby') );
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		//	----------------------------------------
		//	Sort
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('sort') != 'desc' )
		{
			$sql	.= " ASC";
		}
		else
		{
			$sql	.= " DESC";
		}

		// ----------------------------------------
		//  Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------
		// 	For the currently logged-in member
		//	viewing the page, get their friends list.
		//	----------------------------------------

		$fsql	= "SELECT 	friend_id,
							private 	AS friends_private,
							block 		AS friends_blocked,
							reciprocal 	AS friends_reciprocal,
							entry_date 	AS friends_entry_date
				   FROM 	exp_friends
				   WHERE 	member_id = " . ee()->session->userdata['member_id'];

		$query	= ee()->db->query( $fsql );

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$this->friends[ $row['friend_id'] ]	= $row;
			}
		}

		// ----------------------------------------
		//	Fetch group member data for later parsing
		// ----------------------------------------

		$group_posts	= array();

		$gsql	= "SELECT 	member_id,
							entry_date 			AS friends_group_join_date,
							invite_or_request 	AS friends_group_invite_or_request
				   FROM 	exp_friends_group_posts
				   WHERE 	group_id = " . ee()->db->escape_str( $this->group_id );

		$query	= ee()->db->query( $gsql );

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$group_posts[ $row['member_id'] ]	= $row;
			}
		}

		//	----------------------------------------
		//	Run base query
		//	----------------------------------------

		$query	= ee()->db->query( $sql );

		$member_ids	= $this->_get_db_ids( array( 'member_id' ), $query );

		$member_ids	= array_unique( $member_ids );

		//	----------------------------------------
		//	Empty
		//	----------------------------------------

		if ( count( $member_ids ) == 0 )
		{
			return $this->no_results('friends');
		}

		$this->member_ids		= $member_ids;
		$this->total_results	= count( $member_ids );

		$r	= '';

		foreach ( $member_ids as $id )
		{
			$this->friends_count++;
			$inject	= ( isset( $group_posts[ $id ] ) === TRUE ) ? $group_posts[ $id ] : array();
			$r	.= $this->_parse_member_data( $id, $this->tagdata, 'friends_', $inject );
		}

		$this->friends_count	= 0;

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	/* End fetch group members through subquery */


	// --------------------------------------------------------------------

	/**
	 * Group membership invites
	 *
	 * Lists people that have been invited to a given group.
	 *
	 * @access	public
	 * @return	string
	 */

	function group_membership_invites()
	{
		//	----------------------------------------
		//	Prep subquery
		//	----------------------------------------

		$subquery	= "SELECT 	member_id
					   FROM 	exp_friends_group_posts
					   WHERE 	site_id
					   IN 		(" . implode( ',', ee()->TMPL->site_ids ) . ")
					   AND 		group_id = " . ee()->db->escape_str( $this->group_id ) . "
					   AND 		invite_or_request = 'invite'
					   AND 		accepted = 'n'
					   AND 		declined = 'n'
					   AND 		request_accepted = 'y'
					   AND 		request_declined = 'n'";

		//	----------------------------------------
		//	Tagdata
		//	----------------------------------------

		$this->tagdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata : $this->tagdata;

		//	----------------------------------------
		//	Run our utility method
		//	----------------------------------------

		return $this->_fetch_group_members_through_subquery( $subquery );
	}

	/* End group membership invites */


	// --------------------------------------------------------------------

	/**
	 * Group membership requests
	 *
	 * Lists people that are requesting membership to a given group.
	 *
	 * @access	public
	 * @return	string
	 */

	function group_membership_requests()
	{
		//	----------------------------------------
		//	Prep subquery
		//	----------------------------------------

		$subquery	= "SELECT 	member_id
					   FROM 	exp_friends_group_posts
					   WHERE 	site_id
					   IN  		(" . implode( ',', ee()->TMPL->site_ids ) . ")
					   AND 		group_id = " . ee()->db->escape_str( $this->group_id ) . "
					   AND 		invite_or_request = 'request'
					   AND 		accepted = 'y'
					   AND 		declined = 'n'
					   AND 		request_accepted = 'n'
					   AND 		request_declined = 'n'";

		//	----------------------------------------
		//	Tagdata
		//	----------------------------------------

		$this->tagdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata: $this->tagdata;

		//	----------------------------------------
		//	Run our utility method
		//	----------------------------------------

		return $this->_fetch_group_members_through_subquery( $subquery );
	}

	/* End group membership requests */


	// --------------------------------------------------------------------

	/**
	 * Groups
	 *
	 * This method lists groups.
	 *
	 * @access		public
	 * @return		string
	 */

	function groups ()
	{
		//	----------------------------------------
		//	Only show my groups?
		//	----------------------------------------

		$group_type	= 'confirmed';

		switch ( ee()->TMPL->fetch_param('type') )
		{
			case 'invite':
				$group_type	= 'invite';
				break;
			case 'request':
				$group_type	= 'request';
				break;
			case 'owner':
				$group_type	= 'owner';
				break;
			case 'all_groups':
				$group_type	= 'all';
				break;
			case 'all':
				$group_type	= 'all';
				break;
			default:
				$group_type	= 'confirmed';
				break;
		}

		//	----------------------------------------
		//	Execute
		//	----------------------------------------

		return $this->_groups( ee()->TMPL->tagdata, $group_type, 'return_no_results' );
	}

	/* End groups */


	// --------------------------------------------------------------------

	/**
	 * Groups (sub)
	 *
	 * This method lists groups.
	 *
	 * @access		public
	 * @return		string
	 */

	function _groups( $tagdata = '', $group_type = 'owner', $return_no_results = '' )
	{
		//	----------------------------------------
		//	Set dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Member id?
		//	----------------------------------------

		$this->trigger			= 'member_name';
		$check_uri_for_raw_id	= 'n';

		if ( $this->_member_id( $check_uri_for_raw_id ) === FALSE )
		{
			$this->member_id	= ee()->session->userdata( 'member_id' );
		}

		//	----------------------------------------
		//	Set tagdata
		//	----------------------------------------

		$tagdata	= ( $tagdata == '' ) ? ee()->TMPL->tagdata: $tagdata;

		//	----------------------------------------
		//	Capture pagination data if it's there?
		//	----------------------------------------

		if ( preg_match( "/" . LD . "friends_paginate" . RD . "(.+?)" . LD .
				preg_quote(T_SLASH, '/')."friends_paginate" . RD . "/s", $tagdata, $match ) )
		{
			$this->paginate_match	= $match;

			$tagdata	= str_replace( $this->paginate_match[0], '', $tagdata );
		}

		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			if ( $return_no_results != '' )
			{
				return $this->no_results('friends');
			}
			else
			{
				$tagdata	= ee()->functions->prep_conditionals(
					$tagdata,
					array(
						'no_groups' => TRUE,
						'groups' 	=> FALSE
					)
				);

				return $tagdata;
			}
		}

		//	----------------------------------------
		//	Set group id
		//	----------------------------------------

		$this->trigger	= 'group_name';

		$this->_group_id();

		//	----------------------------------------
		//	Get current group entries
		//	----------------------------------------

		if ( $this->_entry_id() === TRUE )
		{
			$sql	= "SELECT 	group_id
					   FROM 	exp_friends_group_entry_posts
					   WHERE 	entry_id = '" . ee()->db->escape_str( $this->entry_id ) . "'";

			if ( $this->group_id != 0 )
			{
				$sql	.= " AND group_id = '" . ee()->db->escape_str( $this->group_id ) . "'";
			}

			$query	= ee()->db->query( $sql );

			foreach ( $query->result_array() as $row )
			{
				$this->group_entries[]	= $row['group_id'];
			}
		}

		//	----------------------------------------
		//	Set privacy clause
		//	----------------------------------------
		//	If someone is viewing someone else's groups,
		//	that person is not allowed to see the person's private groups.
		//	----------------------------------------

		$privacy	= "";

		if ( $this->member_id != ee()->session->userdata('member_id') )
		{
			$privacy	= " AND fg.private = 'n'";
		}

		if ( $this->check_yes( ee()->TMPL->fetch_param('show_private_groups') ) )
		{
			$privacy	= "";
		}

		//	----------------------------------------
		//	Get groups
		//	----------------------------------------

		$sql	= "SELECT 	MDB
				   FROM 	exp_friends_groups fg";

		switch ( $group_type )
		{
			//	All
			case 'all':
				if ( $this->check_yes( ee()->TMPL->fetch_param('show_private_groups') ) )
				{
					$sql	.= " WHERE 0 = 0";
				}
				else
				{
					$sql	.= " WHERE ( fg.private = 'n' OR
										 fg.member_id = " .
									ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
								 OR    ( fg.group_id
										  IN ( 	SELECT 	group_id
												FROM 	exp_friends_group_posts
												WHERE 	member_id = " .
												  ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
												AND 	accepted = 'y'
												AND 	declined = 'n'
												AND 	request_accepted = 'y'
												AND 	request_declined = 'n'
										)
									)
								)";
				}
				break;

			//	Owner
			case 'owner':
				$sql	.= " WHERE fg.member_id = " . ee()->db->escape_str( $this->member_id );
				$sql	.= $privacy;
				break;

			//	Invite
			case 'invite':
				$sql	.= " LEFT JOIN 	exp_friends_group_posts fgp
							 ON 		fgp.group_id = fg.group_id
							 WHERE 		fgp.invite_or_request = 'invite'
							 AND 		fgp.accepted = 'n'
							 AND 		fgp.declined = 'n'
							 AND 		fgp.request_accepted = 'y'
							 AND 		fgp.request_declined = 'n'
							 $privacy
							 AND 		fgp.member_id = " . ee()->db->escape_str( $this->member_id );
				break;

			//	Request
			case 'request':
				$sql	.= " LEFT JOIN 	exp_friends_group_posts fgp
							 ON 		fgp.group_id = fg.group_id
							 WHERE 		fgp.invite_or_request = 'request'
							 AND 		fgp.accepted = 'y'
							 AND 		fgp.declined = 'n'
							 AND 		fgp.request_accepted = 'n'
							 AND 		fgp.request_declined = 'n'
							 $privacy
							 AND 		fgp.member_id = " . ee()->db->escape_str( $this->member_id );
				break;

			//	Confirmed
			default:
				$sql	.= " LEFT JOIN 	exp_friends_group_posts fgp
							 ON 		fgp.group_id = fg.group_id
							 WHERE 		fgp.accepted = 'y'
							 AND 		fgp.declined = 'n'
							 AND 		fgp.request_accepted = 'y'
							 AND 		fgp.request_declined = 'n'
							 $privacy
							 AND 		fgp.member_id = " . ee()->db->escape_str( $this->member_id );
				break;
		}

		//	----------------------------------------
		//	Site id
		//	----------------------------------------

		$sql	.= " AND fg.site_id IN (" . implode( ',', ee()->TMPL->site_ids ) . ")";

		//	----------------------------------------
		//	Hide mine
		//	----------------------------------------

		if ( $this->check_yes( ee()->TMPL->fetch_param('hide_mine') ) )
		{
			$sql	.= " AND fg.member_id != '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'";
		}

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( $this->group_id != 0 )
		{
			$sql	.= " AND fg.group_id = '".ee()->db->escape_str( $this->group_id )."'";
		}

		//	----------------------------------------
		//	Group by
		//	----------------------------------------

		$sql	.= " GROUP BY fg.group_id";

		//	----------------------------------------
		//	Set order by
		//	----------------------------------------

		$order	= ' ORDER BY fg.title';

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND
			 ee()->TMPL->fetch_param('orderby') != '' )
		{
			foreach ( array( 'title' => 'fg.title', 'entry_date' => 'fg.entry_date', 'random' => 'rand()' ) as $key => $val )
			{
				if ( ee()->TMPL->fetch_param('orderby') == $key )
				{
					$order	= " ORDER BY " . $val;
				}
			}
		}

		$sql	.= $order;

		//	----------------------------------------
		//	Set sort
		//	----------------------------------------

		if ( strtolower(ee()->TMPL->fetch_param('sort')) != 'asc' )
		{
			$sql	.= " DESC";
		}
		else
		{
			$sql	.= " ASC";
		}

		//	----------------------------------------
		//	Get count
		//	----------------------------------------

		$query		= ee()->db->query( str_replace( "MDB", "COUNT(*) AS count", $sql ) );

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( $return_no_results != '' AND ( $query->num_rows() == 0 OR $query->row('count') == 0 ) )
		{
			return $this->no_results('friends');
		}

		//confusing php var name, corrected with correct EE var, though
		$count		= $query->num_rows();

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'friends_absolute_results' => $count ) );

		$tagdata	= ee()->TMPL->swap_var_single( "friends_absolute_results", $count, $tagdata );

		// ----------------------------------------
		//  Prep pagination
		// ----------------------------------------

		$sql = str_replace(
			"MDB",
			"fg.group_id,
			 fg.group_id 		AS friends_group_id,
			 fg.member_id 		AS friends_group_owner_id,
			 fg.name 			AS friends_group_name,
			 fg.title 			AS friends_group_title,
			 fg.description 	AS friends_group_description,
			 fg.entry_date 		AS friends_group_entry_date,
			 fg.edit_date 		AS friends_group_edit_date,
			 fg.private 		AS friends_group_private,
			 fg.total_members 	AS friends_group_total_members,
			 fg.total_entries 	AS friends_group_total_entries",
			$sql );

		$sql_p	= $this->_prep_pagination( $sql );

		$query	= ee()->db->query( $sql_p);

		$paginated_past = FALSE;

		$row_count = $query->num_rows();

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'friends_total_results' => $row_count ) );

		$tagdata	= ee()->TMPL->swap_var_single( "friends_total_results", $row_count, $tagdata );

		if ( $query->num_rows() == 0 )
		{

			// We have no return data.
			// This may be because we've paginated passed the end.
			// In that case, run the query again, without the pagination
			// data, so we can recover a moducum of respect.

			$query	= ee()->db->query( $sql );

			if( $query->num_rows() == 0 )
			{

				$tagdata	= ee()->functions->prep_conditionals(
					$tagdata,
					array( 'no_groups' => TRUE, 'groups' => FALSE )
				);

				return $tagdata;
			}
			else
			{
				// We do have group data, but have broken pagination
				// Set a marker so we know
				$paginated_past = TRUE;
			}
		}

		//	----------------------------------------
		//	Get the currently logged in member's groups
		//	----------------------------------------

		$joined_groups	= $this->data->get_joined_groups_from_member_id(
						ee()->config->item( 'site_id' ), ee()->session->userdata( 'member_id' ) );

		//	----------------------------------------
		//	Parse groups
		//	----------------------------------------

		$r				= '';
		$prefix			= 'friends_';
		$friends_count 	= 1;

		foreach ( $query->result_array() as $row )
		{
			$out	= $tagdata;

			//	----------------------------------------
			//	Create new vars
			//	----------------------------------------

			if ( isset( $joined_groups[ $row['group_id'] ] ) === TRUE )
			{
				$row[$prefix . 'group_join_date']	= $joined_groups[ $row['group_id'] ][ 'group_join_date' ];
			}

			//	----------------------------------------
			//	Handle group members
			//	----------------------------------------

			if ( in_array( $row['group_id'], $this->group_entries ) )
			{
				$row['friends_group_entry']		= 'y';
			}
			else
			{
				$row['friends_group_entry']		= 'n';
			}

			//row count
			$row['friends_count'] = $friends_count++;

			//	----------------------------------------
			//	Conditionals
			//	----------------------------------------

			$out	= ee()->functions->prep_conditionals( $out, $row );
			$out	= $this->_parse_switch( $out );

			//	----------------------------------------
			//	Parse dates
			//	----------------------------------------

			$dates	= array(
				'group_entry_date', 'group_edit_date', 'group_join_date'
				);

			foreach ( $dates as $val )
			{
				$val	= $prefix.$val;

				if ( strpos( $out, LD.$val ) !== FALSE AND
					 isset( $row[$val] ) === TRUE AND
					 preg_match_all("/" . LD . $val."\s+format=([\"'])([^\\1]*?)\\1" . RD . "/s", $out, $matches ))
				{
					for($i=0, $s=sizeof($matches[2]); $i < $s; ++$i)
					{
						$out	= str_replace( $matches[0][$i], $this->_parse_date( $matches[2][$i], $row[$val] ), $out );
					}
				}
			}

			//	----------------------------------------
			//	Parse regular vars
			//	----------------------------------------

			foreach ( $row as $key => $val )
			{
				$out	= str_replace( LD.$key.RD, $val, $out );
			}

			$r	.=	trim( $out );
		}

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	/* End parse groups */


	// --------------------------------------------------------------------

	/**
	 * Member of group
	 *
	 * Is the currently logged in member a member of a given group?
	 *
	 * @access	public
	 * @return	string
	 */

	function member_of_group ()
	{
		$cond['member_of_group']	= 'n';

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Group id
		//	----------------------------------------
		// 	We'll always have a group id. Fail out if not.
		//	----------------------------------------

		//	Hardcoded to template?

		if ( ee()->TMPL->fetch_param('friends_group_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('friends_group_id') ) === TRUE )
		{
			$this->group_id	= ee()->TMPL->fetch_param('friends_group_id');
		}
		elseif ( ee()->TMPL->fetch_param('group_name') !== FALSE AND
				( $group_id = $this->data->get_group_id_from_group_name(
					ee()->config->item('site_id'),
					ee()->TMPL->fetch_param('group_name') ) ) !== FALSE )
		{
			$this->group_id	= $group_id;
			unset( $group_id );
		}

		//	Sent through POST?

		elseif ( ee()->input->post('friends_group_id') !== FALSE AND
				 is_numeric( ee()->input->post('friends_group_id') ) === TRUE )
		{
			$this->group_id	= ee()->input->post('friends_group_id');
		}

		//	Is the group indicated in the URI?

		elseif ( $this->dynamic === TRUE AND ( $seg = array_search( 'group', ee()->uri->segments ) ) !== FALSE )
		{
			if ( ! empty( ee()->uri->segments[ $seg + 1 ] ) )
			{
				//	Numeric group id?

				if ( is_numeric( ee()->uri->segments[ $seg + 1 ] ) === TRUE )
				{
					$this->group_id	= ee()->uri->segments[ $seg + 1 ];
				}
				elseif ( ( $group_id = $this->data->get_group_id_from_group_name(
							ee()->config->item('site_id'), ee()->uri->segments[ $seg + 1 ] ) ) !== FALSE )
				{
					$this->group_id	= $group_id;
					unset( $group_id );
				}
			}
		}

		//	----------------------------------------
		//	Did group id get set? We can't do anything without a group id
		//	----------------------------------------

		if ( $this->group_id == 0 )
		{
			return $tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );
		}

		//	----------------------------------------
		//	Check member id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('member_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('member_id') ) === TRUE )
		{
			$this->member_id	= ee()->TMPL->fetch_param('member_id');
		}
		elseif ( ee()->TMPL->fetch_param('username') !== FALSE AND
				 ee()->TMPL->fetch_param('username') != '' AND
				 ( $member_id = $this->data->get_member_id_from_username(
						ee()->config->item('site_id'), ee()->TMPL->fetch_param('username') ) ) !== FALSE )
		{
			$this->member_id	= $member_id;
			unset($member_id);
		}

		//	Sent through POST?

		elseif ( ee()->input->post('friends_member_id') !== FALSE AND
				 is_numeric( ee()->input->post('friends_member_id') ) === TRUE )
		{
			$this->member_id	= ee()->input->post('friends_member_id');
		}
		elseif ( $this->dynamic === TRUE AND ( $seg = array_search( 'member', ee()->uri->segments ) ) !== FALSE )
		{
			if ( ! empty( ee()->uri->segments[ $seg + 1 ] ) )
			{
				//	Numeric member id?

				if ( is_numeric( ee()->uri->segments[ $seg + 1 ] ) === TRUE )
				{
					$this->member_id	= ee()->uri->segments[ $seg + 1 ];
				}
				elseif ( ( $member_id = $this->data->get_member_id_from_username(
						ee()->config->item('site_id'), ee()->uri->segments[ $seg + 1 ] ) ) !== FALSE )
				{
					$this->member_id	= $member_id;
					unset( $member_id );
				}
			}
		}
		elseif ( ee()->session->userdata('member_id') != 0 )
		{
			$this->member_id	= ee()->session->userdata('member_id');
		}

		//	----------------------------------------
		//	Did member id get set? We can't do anything without a member id
		//	----------------------------------------

		if ( $this->member_id == 0 )
		{
			return $tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );
		}

		//	----------------------------------------
		//	So we have a group id and a member id, let's test.
		//	----------------------------------------

		if ( $this->data->member_of_group( ee()->config->item('site_id'), $this->member_id, $this->group_id ) === FALSE )
		{
			return $tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );
		}
		else
		{
			$cond['member_of_group']	= 'y';
			return $tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );
		}
	}

	/* End member of group */


	// --------------------------------------------------------------------

	/**
	 * My groups
	 *
	 * This method is an alias to groups() with a trigger to show only the current member's groups.
	 *
	 * @access	public
	 * @return	string
	 */

	function my_groups ()
	{
		//	----------------------------------------
		//	Execute
		//	----------------------------------------

		return $this->_groups( '', TRUE, 'return_no_results' );
	}

	/* End my groups */


	// --------------------------------------------------------------------

	/**
	 * Parse group data
	 *
	 * This parses group variables on ee()->TMPL->template
	 *
	 * @access	private
	 * @return	string
	 */

	function _parse_group_data( $tagdata = '', $group_id = '', $prefix = 'friends_group_' )
	{
		$vars	= array(
			'friends_group_id'			=> '',
			'friends_group_name'		=> '',
			'friends_group_title'		=> '',
			'friends_group_description'	=> ''
		);

		if ( $group_id != '' )
		{
			if ( isset( $this->cache['groups'][$group_id] ) === TRUE )
			{
				$vars	= $this->cache['groups'][$group_id];
			}
			else
			{
				$query	= ee()->db->query(
					"SELECT *
					 FROM 	exp_friends_groups
					 WHERE 	site_id
					 IN 	(" . implode( ',', ee()->TMPL->site_ids ) . ")
					 AND 	group_id = " . ee()->db->escape_str( $group_id )
				);

				if ( $query->num_rows() > 0 )
				{
					foreach ( $query->row_array() as $key => $val )
					{
						$vars[ $prefix.$key ]	= $val;
					}
				}

				$this->cache['groups'][$group_id]	= $vars;
			}
		}

		foreach ( $vars as $key => $val )
		{
			if ( strpos( $tagdata, LD.$key ) === FALSE ) continue;

			$tagdata	= str_replace( LD.$key.RD, $val, $tagdata );
		}

		return $tagdata;
	}

	/* End parse group data */


	// --------------------------------------------------------------------

	/**
	 * Recount groups
	 *
	 * This recounts stats pertaining to a given group.
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _recount_groups()
	{
		//	----------------------------------------
		//	Group id?
		//	----------------------------------------

		if ( $this->group_id == '' ) return FALSE;

		//	----------------------------------------
		//	Get DB count
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	exp_friends_group_posts
			 WHERE 	group_id = '" . $this->group_id . "'"
		);

		//	----------------------------------------
		//	Update
		//	----------------------------------------

		ee()->db->query(
			ee()->db->update_string(
				'exp_friends_groups',
				array( 'total_members' => $query->row('count') ),
				array( 'group_id' => $this->group_id )
			)
		);

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $query->row('count');
	}

	/* End recount groups */


	// --------------------------------------------------------------------

	/**
	 * Request membership or accept invite
	 *
	 * This method allows a user to request membership to a group or accept an invite to a group.
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _request_membership_or_accept_invite( $prefs = array() )
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			$this->message[]	= lang('not_logged_in');
			return FALSE;
		}

		//	----------------------------------------
		//	Group id valid?
		//	----------------------------------------

		if ( $this->group_id == 0 OR ( $group_data = $this->data->get_group_data_from_group_id(
				ee()->config->item('site_id'), $this->group_id ) ) === FALSE )
		{
			$this->message[]	= lang('group_id_required');
			return FALSE;
		}

		//	----------------------------------------
		//	Has the member been invited?
		//	----------------------------------------

		$sql	= "SELECT 		fgp.accepted, fgp.request_accepted, fgp.invite_or_request, m.email, m.screen_name
				   FROM 		exp_friends_group_posts fgp
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fgp.member_id
				   WHERE 		fgp.group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
				   AND 			fgp.member_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			//	----------------------------------------
			//	No record of user, so this is a request to join.
			//	----------------------------------------

			if ( $group_data['private'] == 'y' )
			{
				$this->message[]	= str_replace(
					'%friends_group_title%',
					$group_data['title'],
					lang('membership_request_decline_group_is_private')
				);
				return FALSE;
			}
			else
			{
				$data	= array(
					'group_id'			=> $this->group_id,
					'member_id'			=> ee()->session->userdata('member_id'),
					'site_id'			=> $this->clean_site_id,
					'entry_date'		=> ee()->localize->now,
					'accepted'			=> 'y',
					'declined'			=> 'n',
					'request_accepted'	=> 'n',
					'request_declined'	=> 'n',
					'invite_or_request'	=> 'request'
				);

				$sql	= ee()->db->insert_string( 'exp_friends_group_posts', $data );

				ee()->db->query( $sql );

				$this->message[]	= str_replace(
					'%friends_group_title%',
					$group_data['title'],
					lang('request_group_membership')
				);

				//	----------------------------------------
				//	Set email data
				//	----------------------------------------

				$email['notification_template']		= ( ! empty( $prefs['notification_request'] ) ) ?
														$prefs['notification_request'] : '';

				$email['subject']					= ( ! empty( $prefs['subject_request'] ) ) ?
														$prefs['subject_request'] :
														lang('membership_request_email_subject');
			}
		}
		else
		{
			//	----------------------------------------
			//	Has the member already accepted?
			//	----------------------------------------

			if ( $query->row('accepted') == 'y' )
			{
				if ( $query->row('invite_or_request') == 'request' AND
					 $query->row('request_accepted') == 'n' )
				{
					$this->message[]	= str_replace(
						'%friends_group_title%',
						$group_data['title'],
						lang('already_requested_group_invitation')
					);
					return FALSE;
				}
				else
				{
					$this->message[]	= str_replace(
						'%friends_group_title%',
						$group_data['title'],
						lang('already_accepted_group_invitation')
					);
					return FALSE;
				}
			}
			else
			{
				$data	= array(
					'accepted' 		=> 'y',
					'declined' 		=> 'n',
					'entry_date' 	=> ee()->localize->now
				);

				$sql	= ee()->db->update_string(
					'exp_friends_group_posts',
					$data,
					array(
						'group_id' 	=> $this->group_id,
						'member_id' => ee()->session->userdata('member_id')
					)
				);

				ee()->db->query( $sql );

				$this->message[]	= str_replace(
					'%friends_group_title%',
					$group_data['title'],
					lang('accepted_group_invitation')
				);

				//	----------------------------------------
				//	Set email data
				//	----------------------------------------

				$email['notification_template']		= ( ! empty( $prefs['notification_accept'] ) ) ?
														$prefs['notification_accept'] : '';

				$email['subject']					= ( ! empty( $prefs['subject_accept'] ) ) ?
														$prefs['subject_accept'] :
														lang('membership_accept_group_invitation_email_subject');
			}
		}

		//	----------------------------------------
		//	Notify
		//	----------------------------------------

		if ( $this->notify === TRUE AND $email['notification_template'] != '' )
		{
			$email['email']									= $group_data['owner_email'];
			$email['member_id']								= ee()->session->userdata['member_id'];
			$email['from_email']							= ee()->session->userdata['email'];
			$email['from_name']								= ee()->session->userdata['screen_name'];
			$email['extra']['friends_group_id']				= $this->group_id;
			$email['extra']['friends_group_name']			= $group_data['name'];
			$email['extra']['friends_group_title']			= $group_data['title'];
			$email['extra']['friends_group_description']	= $group_data['description'];
			$email['extra']['friends_owner_screen_name']	= $group_data['owner_screen_name'];
			$email['extra']['friends_owner_username']		= $group_data['owner_username'];
			$email['extra']['friends_owner_member_id']		= $group_data['owner_member_id'];
			$email['extra']['friends_user_screen_name']		= ee()->session->userdata['screen_name'];
			$email['extra']['friends_user_username']		= ee()->session->userdata['username'];
			$email['extra']['friends_user_member_id']		= ee()->session->userdata['member_id'];


			//	----------------------------------------
			//	Let's quickly parse some vars just for sanity
			//	----------------------------------------

			foreach ( $email['extra'] as $key => $val )
			{
				$email['subject']	= str_replace(
					array( '%'.$key.'%', LD.$key.RD ),
					array( $val, $val ),
					$email['subject']
				);
			}

			$this->_notify( $email );
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return TRUE;
	}

	/* End request membership or accept invite */


	// --------------------------------------------------------------------

	/**
	 * Remove friends from group
	 *
	 * This method takes an array of member ids to be removed from a given group. It is used by the owner of a group.
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _remove_friends_from_group( $friends = array(), $prefs = array() )
	{
		//	----------------------------------------
		//	No friends?
		//	----------------------------------------

		if ( count( $friends ) == 0 )
		{
			$this->message[]	= lang('friends_required');
			return FALSE;
		}

		//	----------------------------------------
		//	Group id valid?
		//	----------------------------------------

		if ( $this->group_id == 0 OR
			 ( $group_data = $this->data->get_group_data_from_group_id(
					ee()->config->item('site_id'), $this->group_id ) ) === FALSE )
		{
			$this->message[]	= lang('group_id_required');
			return FALSE;
		}

		//	----------------------------------------
		//	Filter out the owner
		//	----------------------------------------
		//  We don't want to remove the owner of the
		//  group from the group
		//	----------------------------------------

		$owner_id = $this->data->get_member_id_from_group_id(ee()->config->item('site_id'), $this->group_id );

		//	----------------------------------------
		//	Remove owner id
		//	----------------------------------------

		if ( $owner_id !== FALSE )
		{
			$friends	= array_diff( $friends, array( $owner_id ) );
		}

		//	----------------------------------------
		//	Get a list of the members for notifications
		//	----------------------------------------

		$sql	= "SELECT 		fgp.member_id, m.email, m.username, m.screen_name
				   FROM 		exp_friends_group_posts fgp
				   LEFT JOIN 	exp_members m
				   ON 			fgp.member_id = m.member_id
				   WHERE 		fgp.member_id
				   IN 			('" . implode( ',', $friends ) . "')
				   AND 			fgp.group_id = " . ee()->db->escape_str( $this->group_id );

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Update DB
		//	----------------------------------------

		$sql	= "UPDATE 	exp_friends_group_posts
				   SET 		request_accepted = 'n',
							request_declined = 'y',
							entry_date = '" . ee()->localize->now . "'
				   WHERE 	group_id = '" . ee()->db->escape_str( $this->group_id )  ."'
				   AND 		member_id
				   IN 		('" . implode( ',', $friends ) . "')";

		ee()->db->query( $sql );

		//	----------------------------------------
		//	Notify
		//	----------------------------------------

		if ( $this->notify === TRUE AND ! empty( $prefs['notification_remove'] ) AND $prefs['notification_remove'] != '' )
		{
			foreach ( $query->result_array() as $row )
			{
				unset( $email );
				$email['notification_template']	   				= $prefs['notification_remove'];
				$email['email']					   				= $row['email'];
				$email['member_id']				   				= $row['member_id'];
				$email['from_email']			   				= ee()->session->userdata['email'];
				$email['from_name']				   				= ee()->session->userdata['screen_name'];
				$email['subject']				   				= ( ! empty( $prefs['subject_remove'] ) ) ?
																	$prefs['subject_remove'] :
																	lang('subject_remove_email');
				$email['extra']['friends_group_id']				= $this->group_id;
				$email['extra']['friends_screen_name']			= $group_data['owner_screen_name'];
				$email['extra']['friends_group_name']			= $group_data['name'];
				$email['extra']['friends_group_title']			= $group_data['title'];
				$email['extra']['friends_group_description']	= $group_data['description'];
				$email['extra']['friends_owner_screen_name']	= $group_data['owner_screen_name'];
				$email['extra']['friends_owner_username']		= $group_data['owner_username'];
				$email['extra']['friends_owner_member_id']		= $group_data['owner_member_id'];
				$email['extra']['friends_user_screen_name']		= $row['screen_name'];
				$email['extra']['friends_user_username']		= $row['username'];
				$email['extra']['friends_user_member_id']		= $row['member_id'];

				//	----------------------------------------
				//	Let's quickly parse some vars just for sanity
				//	----------------------------------------

				foreach ( $email['extra'] as $key => $val )
				{
					$email['subject']	= str_replace(
						array( '%'.$key.'%', LD.$key.RD ),
						array( $val, $val ),
						$email['subject']
					);
				}

				$this->_notify( $email );
			}
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$line	= ( count( $friends ) == 1 ) ?
					lang( 'friend_removed' ) :
					lang( 'friends_removed' );

		$this->message[]	= str_replace( "%n%", ( count( $friends ) ), $line );

		return TRUE;
	}

	/* End remove friends from group */


	// --------------------------------------------------------------------

	/**
	 * Remove self from group
	 *
	 * This method allows a user to remove themselves from a group.
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _remove_self_from_group( $prefs = array() )
	{
		//	----------------------------------------
		//	Group id valid?
		//	----------------------------------------

		if ( $this->group_id == 0 OR
			 ( $group_data = $this->data->get_group_data_from_group_id(
				ee()->config->item('site_id'), $this->group_id ) ) === FALSE )
		{
			$this->message[]	= lang('group_id_required');
			return FALSE;
		}

		//	----------------------------------------
		//	Owners can't delete themselves
		//	----------------------------------------

		if ( $group_data['member_id'] == ee()->session->userdata('member_id') )
		{
			$this->message[]	= lang('cant_delete_self');
			return FALSE;
		}

		//	----------------------------------------
		//	Delete
		//	----------------------------------------

		$sql	= "DELETE FROM 	exp_friends_group_posts
				   WHERE 		group_id = '" . ee()->db->escape_str( $this->group_id ) . "'
				   AND 			member_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') );

		ee()->db->query( $sql );

		//	----------------------------------------
		//	Notify
		//	----------------------------------------

		if ( $this->notify === TRUE AND
			 ee()->db->affected_rows() > 0 AND
			 ! empty( $prefs['notification_leave'] ) AND
			 $prefs['notification_leave'] != '' )
		{
			$email['notification_template']					= $prefs['notification_leave'];
			$email['email']									= $group_data['owner_email'];
			$email['member_id']								= $group_data['member_id'];
			$email['from_email']							= ee()->session->userdata['email'];
			$email['from_name']								= ee()->session->userdata['screen_name'];
			$email['subject']								= ( ! empty( $prefs['subject_leave'] ) ) ?
																$prefs['subject_leave'] :
																lang('subject_leave_email');
			$email['extra']['friends_group_id']				= $this->group_id;
			$email['extra']['friends_screen_name']			= ee()->session->userdata['screen_name'];
			$email['extra']['friends_group_name']			= $group_data['name'];
			$email['extra']['friends_group_title']			= $group_data['title'];
			$email['extra']['friends_group_description']	= $group_data['description'];
			$email['extra']['friends_owner_screen_name']	= $group_data['owner_screen_name'];
			$email['extra']['friends_owner_username']		= $group_data['owner_username'];
			$email['extra']['friends_owner_member_id']		= $group_data['owner_member_id'];
			$email['extra']['friends_user_screen_name']		= ee()->session->userdata['screen_name'];
			$email['extra']['friends_user_username']		= ee()->session->userdata['username'];
			$email['extra']['friends_user_member_id']		= ee()->session->userdata['member_id'];

			// ----------------------------------------
			// Let's quickly parse some vars just for sanity
			// ----------------------------------------

			foreach ( $email['extra'] as $key => $val )
			{
				$email['subject']	= str_replace(
					array( '%'.$key.'%', LD.$key.RD ),
					array( $val, $val ),
					$email['subject']
				);
			}

			$this->_notify( $email );
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$this->message[]	= str_replace(
			"%friends_group_title%",
			$group_data['title'],
			lang( 'self_removed' )
		);

		return TRUE;
	}

	/* End remove self from group */


	// --------------------------------------------------------------------

	/**
	 * Update group stats
	 *
	 * Takes a group id and recounts its stats
	 *
	 * @access		private
	 * @return		boolean
	 */

	 function _update_group_stats( $group_id = 0 )
	 {
		if ( $group_id == 0 ) return FALSE;

		//	----------------------------------------
		//	Get member stats
		//	----------------------------------------

		$sql	= "SELECT 	COUNT(*) AS count
				   FROM 	exp_friends_group_posts
				   WHERE 	accepted = 'y'
				   AND 		declined = 'n'
				   AND 		request_accepted = 'y'
				   AND 		request_declined = 'n'
				   AND 		group_id = " . ee()->db->escape_str( $group_id );

		$query	= ee()->db->query( $sql );

		$members	= 0;

		if ( $query->num_rows() > 0 AND $query->row('count') > 0 )
		{
			$members	= $query->row('count');
		}

		//	----------------------------------------
		//	Get entries stats
		//	----------------------------------------

		$sql	= "SELECT 	COUNT(*) AS count
				   FROM 	exp_friends_group_entry_posts
				   WHERE 	group_id = " . ee()->db->escape_str( $group_id );

		$query	= ee()->db->query( $sql );

		$entries	= 0;

		if ( $query->num_rows() > 0 AND $query->row('count') > 0 )
		{
			$entries	= $query->row('count');
		}

		//	----------------------------------------
		//	Update
		//	----------------------------------------

		$sql	= ee()->db->update_string(
			'exp_friends_groups',
			array( 'total_members' => $members, 'total_entries' => $entries ),
			array( 'group_id' => $group_id )
		);

		ee()->db->query( $sql );

		return TRUE;
	 }

	 /* End update group stats */


	// --------------------------------------------------------------------

	/**
	 * Update owned group stats
	 *
	 * Takes a member's id and recounts that member's public and privately owned groups
	 *
	 * @access		private
	 * @return		boolean
	 */

	 function _update_owned_group_stats( $member_id = 0 )
	 {
		if ( $member_id == 0 ) return FALSE;

		//	----------------------------------------
		//	Get member stats
		//	----------------------------------------

		$sql	= "SELECT 	private
				   FROM 	exp_friends_groups
				   WHERE 	member_id = " . ee()->db->escape_str( $member_id );

		$query	= ee()->db->query( $sql );

		$public		= 0;
		$private	= 0;

		foreach ( $query->result_array() as $row )
		{
			if ( $row['private'] == 'y' )
			{
				$private++;
			}
			else
			{
				$public++;
			}
		}

		//	----------------------------------------
		//	Update
		//	----------------------------------------

		$sql	= ee()->db->update_string(
			'exp_members',
			array( 'friends_groups_public' => $public, 'friends_groups_private' => $private ),
			array( 'member_id' => $member_id )
		);

		ee()->db->query( $sql );

		return TRUE;
	 }

	 /* End update owned group stats */
}

/* End class */
?>
