<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - User Side
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @version		1.6.5
 * @filesource	friends/mod.friends.php
 */

require_once 'addon_builder/module_builder.php';

class Friends extends Module_builder_friends
{
	public $disabled				= FALSE;

	public $TYPE;
	public $query;

	public $ajax					= FALSE;
	public $delete					= FALSE;
	public $dynamic					= TRUE;
	public $multipart				= FALSE;
	public $notify					= TRUE;

	public $entry_id				= 0;
	public $friends_count			= 0;
	public $member_id				= 0;
	public $group_id				= 0;

	public $cat_request				= '';
	public $reserved_cat_segment	= '';
	public $return_data				= '';
	public $tagdata					= '';
	public $trigger					= 'group_name';

	public $params_tbl				= 'exp_friends_params';

	public $arr						= array();
	public $friends					= array();
	public $group_entries			= array();
	public $group_members			= array();
	public $member_ids				= array();
	public $message					= array();
	public $mfields					= array();
	public $params					= array();

	public $group_prefs				= array(
		'friends_group_entries_notify' 		=> 'notify_entries',
		'friends_group_comments_notify'		=> 'notify_comments',
		'friends_group_joins_notify' 		=> 'notify_joins',
		'friends_group_favorites_notify'	=> 'notify_favorites',
		'friends_group_ratings_notify' 		=> 'notify_ratings'
	);

	public $basepath				= '';
	public $cur_page				= 0;
	public $current_page			= 0;
	public $limit					= 100;
	public $total_pages				= 0;
	public $total_results			= 0;
	public $page_count				= '';
	public $page_next				= '';
	public $page_previous			= '';
	public $pager					= '';
	public $paginate				= FALSE;
	public $paginate_match			= array();
	public $paginate_data			= '';
	public $res_page				= '';


	/**
	 * Moved Library functions
	 * @var	array
	 * @see	__call()
	 */
	protected $lib_functions		= array(
		'friends_groups'	=> array(
			'_add_friends_to_group',
			'_group_entry_add',
			'_groups',
			'_parse_message_data',
			'_update_group_stats',
			'comment_notify',
			'edit_group',
			'edit_group_preferences',
			'entry_notify',
			'group_add',
			'group_delete',
			'group_entries',
			'group_entry',
			'group_entry_add',
			'group_entry_remove',
			'group_form',
			'group_invite',
			'group_members',
			'group_members_confirmed',
			'group_membership_invites',
			'group_membership_requests',
			'groups',
			'member_of_group',
			'my_groups',
			'subscribe',
		),
		'friends_hugs'		=> array(
			'hug',
			'hugs',
		),
		'friends_messaging'	=> array(
			'_create_message_folders',
			'message_delete',
			'message_folder_edit',
			'message_folder_form',
			'message_folder_name',
			'message_folders',
			'message_form',
			'message_move',
			'messages',
			'send_message',
		),
		'friends_status'	=> array(
			'status',
			'status_delete',
			'status_form',
			'status_update'
		),
	);
	//END $lib_functions

	// -------------------------------------
	//	EE's libraries/Actions.php use method_exists
	//	which means our __call method isn't used for
	//	front end action requests. Annoying.
	// -------------------------------------

	public function edit_group()
	{
		$args = func_get_args();
		return $this->__call(__FUNCTION__, $args);
	}

	public function message_folder_edit()
	{
		$args = func_get_args();
		return $this->__call(__FUNCTION__, $args);
	}

	public function send_message()
	{
		$args = func_get_args();
		return $this->__call(__FUNCTION__, $args);
	}

	public function status_update()
	{
		$args = func_get_args();
		return $this->__call(__FUNCTION__, $args);
	}


	// --------------------------------------------------------------------

	/**
	 * Magic Call function! *.☆.* Fairy Dust *.☆.*
	 *
	 * In the before time, there were lots of heavy handed
	 * functions that loaded new instances of these class methdods
	 * and used them just once to call functions. Converted
	 * to libs and this magic caller.
	 *
	 * @access	public
	 * @param	string	$method	method to call!
	 * @param	array	$args	Arrrrrguments
	 * @return	mixed			lib method result
	 */

	public function __call($method = '', $args = array())
	{
		foreach ($this->lib_functions as $lib => $methods)
		{
			if (in_array($method, $methods))
			{
				ee()->load->library($lib);
				return call_user_func_array(
					array(ee()->{$lib}, $method),
					$args
				);
			}
		}
	}
	//END __call


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

		//	----------------------------------------
		//	Set trigger
		//	----------------------------------------

		if ( isset(ee()->TMPL) AND
			 is_object(ee()->TMPL) AND
			 ee()->TMPL->fetch_param('trigger') != '' )
		{
			$this->trigger	= ee()->TMPL->fetch_param('trigger');
		}
		elseif ( empty( ee()->uri->segments['2'] ) === FALSE )
		{
			$this->trigger	= ee()->uri->segments['2'];
		}

		//sets the member ID if it is there and set to current_user
		if ( isset(ee()->TMPL) AND
			 isset(ee()->TMPL) AND is_object(ee()->TMPL) AND
			 ee()->TMPL->fetch_param('member_id') === 'CURRENT_USER' AND
			 ( isset(ee()->session) AND
			   is_object(ee()->session) AND
			   isset(ee()->session->userdata))
		   )
		{
			ee()->TMPL->tagparams['member_id'] = ee()->session->userdata['member_id'];
		}

		//keeps us from calling this a bajallion times
		$this->clean_site_id = ee()->db->escape_str( ee()->config->item( 'site_id' ) );

		ee()->load->helper(array('string','text'));
	}
	// END Friends()


	// --------------------------------------------------------------------

	/**
	 * Theme Folder URL
	 *
	 * Mainly used for codepack
	 *
	 * @access	public
	 * @return	string	theme folder url with ending slash
	 */

	public function theme_folder_url()
	{
		return $this->sc->addon_theme_url;
	}
	//END theme_folder_url


	// --------------------------------------------------------------------

	/**
	 * Add a Friend
	 *
	 * This allows someone to add an EE member as a friend.
	 *
	 * @access	public
	 * @return	string
	 */

	function add( $local = FALSE )
	{
		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Ajax mode?
		//	----------------------------------------

		if ( ee()->input->post('ajax') !== FALSE AND ee()->input->post('ajax') == 'yes' )
		{
			$this->ajax	= TRUE;
		}

		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'failure' => TRUE, 'success' => FALSE ) );
			return str_replace( LD."friends_message".RD, $this->_prep_message( lang('not_logged_in') ), $tagdata );
		}

		//	----------------------------------------
		//	Are we notifying?
		//	----------------------------------------

		$this->notify	= ! $this->check_no( ee()->input->post('friends_notify'));

		//	----------------------------------------
		//	Build members array
		//	----------------------------------------

		$members	= array();

		if ( ee()->input->post('friends_member_id') !== FALSE )
		{
			if ( is_array( ee()->input->post('friends_member_id') ) === TRUE )
			{
				$members	= ee()->input->post('friends_member_id');
			}
			else
			{
				$members[]	= ee()->input->post('friends_member_id');
			}
		}

		if ( preg_match( "#/"."username"."/(\w+)/?#", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			$members[]	= $this->data->get_member_id_from_username( ee()->config->item( 'site_id' ), $match[1] );
		}

		//security first

		$clean = array();

		foreach ($members as $id)
		{
			if (is_numeric($id) AND $id > 0)
			{
				$clean[] = $id;
			}
		}

		$members = $clean;

		//	----------------------------------------
		//	Are we blocking?
		//	----------------------------------------

		if ( strpos( ee()->uri->uri_string, "/block" ) !== FALSE )
		{
			if ( $this->_block( $members ) === FALSE )
			{
				$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'failure' => TRUE, 'success' => FALSE ) );
				return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
			}
		}

		//	----------------------------------------
		//	Are we deleting?
		//	----------------------------------------

		elseif ( strpos( ee()->uri->uri_string, "/delete" ) !== FALSE )
		{
			if ( $this->_delete( $members ) === FALSE )
			{
				$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'failure' => TRUE, 'success' => FALSE ) );
				return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
			}
		}

		//	----------------------------------------
		//	Add members
		//	----------------------------------------

		elseif ( $this->_add( $members ) === FALSE )
		{
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'failure' => TRUE, 'success' => FALSE ) );
			return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
		}

		//	----------------------------------------
		//	Is this function being called as a utility?
		//	----------------------------------------

		if ( $local === TRUE )
		{
			return TRUE;
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata			= ee()->functions->prep_conditionals( $tagdata, $cond );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
	}

	// End add


	// --------------------------------------------------------------------

	/**
	 * Add a Friend (subroutine)
	 *
	 * This supports methods for adding someone as a friend.
	 *
	 * @access	private
	 * @return	string
	 */

	function _add( $members = array() )
	{
		// An array of members who have already marked the current member as a friend.
		// They will receive a confirmation notification instead of a request notification.
		$confirms	= array();

		// An array of members who have already marked the current member as a blocked friend.
		$blocks		= array();

		$notification_data	= array(
			'link_confirm',
			'link_request',
			'message_confirm',
			'message_request',
			'notification_confirm',
			'notification_request',
			'subject_confirm',
			'subject_request'
		);

		//	----------------------------------------
		//	Get notifications ready
		//	----------------------------------------

		$notification_confirm	= '';
		$notification_request	= '';

		foreach ( $notification_data as $val )
		{
			if ( ee()->input->get_post( 'friends_' . $val ) !== FALSE AND
				 ee()->input->get_post( 'friends_' . $val ) != '' )
			{
				$$val	= ee()->input->get_post( 'friends_' . $val );
			}
			elseif ( isset(ee()->TMPL) AND is_object(ee()->TMPL) AND
					 ee()->TMPL->fetch_param( $val ) !== FALSE AND
					 ee()->TMPL->fetch_param( $val ) != '' )
			{
				$$val	= ee()->TMPL->fetch_param( $val );
			}
			else
			{
				$$val	= '';
			}
		}

		//	----------------------------------------
		//	Multiple or single?
		//	----------------------------------------

		if ( count( $members ) == 0 )
		{
			//	----------------------------------------
			//	Do we have a valid ID number?
			//	----------------------------------------

			if ( $this->_member_id() === FALSE )
			{
				$this->message[]	= lang('no_member_id');

				return FALSE;
			}

			//	----------------------------------------
			//	Are you your own friend?
			//	----------------------------------------

			if ( $this->member_id == ee()->session->userdata['member_id'] )
			{
				$this->message[]	= lang('your_own_friend');

				return FALSE;
			}

			//	----------------------------------------
			//	First, fail out if friend has already
			//	been recorded for member.
			//	----------------------------------------

			$previously_blocked	= array();

			$query = ee()->db->query(
				"SELECT entry_id, friend_id, block
				 FROM 	exp_friends
				 WHERE 	site_id 	= {$this->clean_site_id}
				 AND 	friend_id 	= '" . ee()->db->escape_str($this->member_id) . "'
				 AND 	member_id 	= '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'"
			);

			if ( $query->num_rows() > 0 )
			{
				if ( $query->row('block') == 'n' )
				{
					$this->message[]	= lang('duplicate_friend');
					return FALSE;
				}
				else
				{
					$previously_blocked[ $query->row('friend_id') ]	= $query->row('entry_id');
				}
			}

			//	----------------------------------------
			//	Fail out if friend not exists or has opted
			//	out.
			//	----------------------------------------
			//	Otherwise we'll use this query to loop and
			//	invite.
			//	----------------------------------------

			$query		= ee()->db->query(
				"SELECT member_id, friends_opt_out, email
				 FROM 	exp_members
				 WHERE 	member_id = '".ee()->db->escape_str($this->member_id) . "'"
			);

			if ( $query->num_rows() == 0 )
			{
				$this->message[]	= lang('member_not_found');

				return FALSE;
			}

			if ( $query->row('friends_opt_out') == 'y' )
			{
				$this->message[]	= lang('member_opted_out');

				return FALSE;
			}

			//	----------------------------------------
			//	Does this person already have us as a friend? If so, we're in confirmation mode.
			//	----------------------------------------

			$confirmq	= ee()->db->query(
				"SELECT block
				 FROM 	exp_friends
				 WHERE 	site_id = {$this->clean_site_id}
				 AND 	member_id = " . ee()->db->escape_str( $this->member_id ) . "
				 AND 	friend_id = " . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "
				 LIMIT 	1"
			);

			if ( $confirmq->num_rows() > 0 )
			{
				if ( $confirmq->row('block') == 'n' )
				{
					$confirms[]	= $this->member_id;
				}
				else
				{
					$blocks[]	= $this->member_id;
				}
			}
		}
		else
		{
			//	----------------------------------------
			//	Get list of existing friends.
			//	----------------------------------------

			$mems	= implode( "','", $members );

			$previously_blocked	= array();
			$duplicates			= array();

			$query	= ee()->db->query(
				"SELECT 	entry_id, friend_id, block
				 FROM 		exp_friends
				 WHERE 		site_id 	= {$this->clean_site_id}
				 AND 		friend_id
				 IN 		('" . $mems . "')
				 AND 		member_id 	= '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'"
			);

			if ( $query->num_rows() > 0 )
			{
				foreach( $query->result_array() as $row )
				{
					if ( $row['block'] == 'n' )
					{
						$duplicates[]	= $row['friend_id'];
					}
					else
					{
						$previously_blocked[ $row['friend_id'] ]	= $row['entry_id'];
					}
				}

				if ( count( $duplicates ) > 0 )
				{
					$this->message[]	= str_replace(
						"%count%",
						count( $duplicates ),
						lang('duplicate_friends')
					);
				}
			}

			//	----------------------------------------
			//	Fail out if no members exist
			//	----------------------------------------

			$query	= ee()->db->query(
				"SELECT 	member_id, friends_opt_out, email
				 FROM 		exp_members
				 WHERE 		member_id
				 IN 		('$mems')
				 AND 		member_id
				 NOT IN		('".implode( "','", $duplicates )."')
				 AND 		member_id != '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'"
			);

			if ( $query->num_rows() == 0 )
			{
				$this->message[]	= ( count( $duplicates ) > 0 ) ?
										lang('remaining_members_not_found') :
										lang('members_not_found');
				return FALSE;
			}

			//	----------------------------------------
			//	Capture opted-out members
			//	----------------------------------------

			$optouts	= array();

			foreach ( $query->result_array() as $row )
			{
				if ( $row['friends_opt_out'] == 'y' )
				{
					$optouts[]	= $row['member_id'];
				}
			}

			if ( count( $optouts ) > 0 )
			{
				$this->message[]	= str_replace(
					"%count%",
					count( $optouts ),
					lang('members_opted_out')
				);
			}

			//	----------------------------------------
			//	Anyone left?
			//	----------------------------------------

			if ( $query->num_rows()  == count( $optouts ) )
			{
				$this->message[]	= lang('no_members_left');
				return FALSE;
			}

			//	----------------------------------------
			//	Do we have any confirms?
			//	----------------------------------------

			$confirmq	= ee()->db->query(
				"SELECT member_id, block
				 FROM 	exp_friends
				 WHERE 	site_id = {$this->clean_site_id}
				 AND 	member_id
				 IN 	('$mems')
				 AND 	friend_id = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'"
			);

			if ( $confirmq->num_rows() > 0 )
			{
				foreach ( $confirmq->result_array() as $row )
				{
					if ( $row['block'] == 'n' )
					{
						$confirms[]	= $row['member_id'];
					}
					else
					{
						$blocks[]	= $row['member_id'];
					}
				}
			}
		}

		//	----------------------------------------
		//	Private?
		//	----------------------------------------

		$private	= 'n';

		if ( strpos( ee()->uri->uri_string, "/private" ) !== FALSE OR ee()->input->get_post('private') == 'y' )
		{
			$private	= 'y';
		}

		//	----------------------------------------
		//	Loop and add
		//	----------------------------------------

		$invite_count	= 0;
		$confirm_count	= 0;
		$block_count	= 0;

		$members	= array();

		foreach ( $query->result_array() as $row )
		{
			if ( $row['friends_opt_out'] != 'y' )
			{
				$members[]	= $row['member_id'];

				//	----------------------------------------
				//	New or previously blocked?
				//	----------------------------------------

				if ( isset( $previously_blocked[ $row['member_id'] ] ) === TRUE )
				{
					//	----------------------------------------
					//	Prepare update
					//	----------------------------------------

					$data	= array(
						'email'			=> $row['email'],
						'block'			=> 'n'
					);

					//	----------------------------------------
					//	Insert
					//	----------------------------------------

					ee()->db->query(
						ee()->db->update_string(
							'exp_friends',
							$data,
							array( 'entry_id' => $previously_blocked[ $row['member_id'] ] )
						)
					);
				}
				else
				{
					//	----------------------------------------
					//	Prepare insert
					//	----------------------------------------

					$data	= array(
						'friend_id'		=> $row['member_id'],
						'email'			=> $row['email'],
						'member_id'		=> ee()->session->userdata['member_id'],
						'entry_date'	=> ee()->localize->now,
						'private'		=> $private,
						'block'			=> 'n',
						'site_id'		=> $this->clean_site_id
					);

					//	----------------------------------------
					//	Insert
					//	----------------------------------------

					ee()->db->query( ee()->db->insert_string('exp_friends', $data) );
				}

				//	----------------------------------------
				//	Notify
				//	----------------------------------------

				if ( in_array( $row['member_id'], $blocks ) === TRUE )
				{
					$block_count++;
					continue;
				}

				if ( in_array( $row['member_id'], $confirms ) === TRUE )
				{
					$confirm_count++;

					//	----------------------------------------
					//	We run an update statement just in case we previously rejected this person's invitation to friendship.
					//	----------------------------------------

					ee()->db->query(
						ee()->db->update_string(
							'exp_friends',
							array(
								'block' => 'n'
							),
							array(
								'member_id' => $row['member_id'],
								'friend_id' => ee()->session->userdata['member_id']
							)
						)
					);

					//	----------------------------------------
					//	Notify
					//	----------------------------------------

					if ( $notification_confirm != '' )
					{
						$data['notification_template']	= $notification_confirm;
						$data['from_email']				= ee()->session->userdata['email'];
						$data['from_name']				= ee()->session->userdata['screen_name'];
						$data['subject']				= $subject_confirm;
						$data['message']				= $message_confirm;
						$data['link']					= $link_confirm;
						$data['member_id']				= $row['member_id'];

						$this->_notify( $data );
					}
				}
				else
				{
					$invite_count++;

					if ( $notification_request != '' )
					{
						$data['notification_template']	= $notification_request;
						$data['from_email']				= ee()->session->userdata['email'];
						$data['from_name']				= ee()->session->userdata['screen_name'];
						$data['subject']				= $subject_request;
						$data['message']				= $message_request;
						$data['link']					= $link_request;
						$data['member_id']				= $row['member_id'];

						$this->_notify( $data );
					}
				}
			}
		}

		//	----------------------------------------
		//	Update reciprocals
		//	----------------------------------------

		$this->_reciprocal( $members );

		//	----------------------------------------
		//	Adjust our counts.
		//	----------------------------------------

		$this->_update_stats();

		//	----------------------------------------
		//	Prep invite message
		//	----------------------------------------
		//	Our current approach to blocking friends
		//	and trying to add someone as a friend who
		//	has blocked you says that when you have
		//	been blocked, you barely know about it.
		//	So we treat blocks and invites the same here.
		//	----------------------------------------

		$invite_count	= $invite_count + $block_count;

		if ( $invite_count > 1 )
		{
			$this->message[]	= str_replace( "%count%", $invite_count, lang('friends_added') );
		}
		elseif ( $invite_count == 1 )
		{
			$this->message[]	= str_replace( "%count%", $invite_count, lang('friend_added') );
		}

		//	----------------------------------------
		//	Prep confirm message
		//	----------------------------------------

		if ( $confirm_count > 1 )
		{
			$this->message[]	= str_replace( "%count%", $confirm_count, lang('friends_confirmed') );
		}
		elseif ( $confirm_count == 1 )
		{
			$this->message[]	= str_replace( "%count%", $confirm_count, lang('friend_confirmed') );
		}

		return TRUE;
	}
	// End add


	// --------------------------------------------------------------------

	/**
	 * Block a Friend
	 *
	 * You don't have to be friends with everyone. You can block friend requests.
	 *
	 * @access	private
	 * @return	string
	 */

	function _block( $members = array() )
	{
		//	----------------------------------------
		//	Multiple or single?
		//	----------------------------------------

		if ( count( $members ) == 0 )
		{
			$count	= 1;

			//	----------------------------------------
			//	Do we have a valid ID number?
			//	----------------------------------------

			if ( $this->_member_id() === FALSE )
			{
				$this->message[]	= lang('no_member_id_to_block');
				return FALSE;
			}

			$members[]	= $this->member_id;

			//	----------------------------------------
			//	Is this person already in the friend's DB?
			//	----------------------------------------

			$query	= ee()->db->query(
				"SELECT entry_id
				 FROM 	exp_friends
				 WHERE 	site_id = {$this->clean_site_id}
				 AND 	member_id = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'
				 AND 	friend_id = '" . ee()->db->escape_str( $this->member_id ) . "'
				 LIMIT 	1"
			);

			//	----------------------------------------
			//	Set block flag
			//	----------------------------------------

			if ( $query->num_rows() > 0 )
			{
				ee()->db->query(
					ee()->db->update_string(
						'exp_friends',
						array(
							'reciprocal' 	=> 'n',
							'block' 		=> 'y'
						),
						array( 'entry_id' => $query->row('entry_id') )
					)
				);

				$ended_friendships	= ee()->db->affected_rows();
			}
			else
			{
				ee()->db->query(
					ee()->db->insert_string(
						'exp_friends',
						array(
							'member_id' 	=> ee()->session->userdata['member_id'],
							'friend_id' 	=> $this->member_id,
							'site_id' 		=> $this->clean_site_id,
							'entry_date' 	=> ee()->localize->now,
							'reciprocal' 	=> 'n',
							'block' 		=> 'y'
						)
					)
				);
			}
		}
		else
		{
			$count	= count( $members );

			//	----------------------------------------
			//	Verify members
			//	----------------------------------------

			$db_members	= array();

			$query = ee()->db->query(
				"SELECT entry_id, friend_id
				 FROM 	exp_friends
				 WHERE 	site_id = {$this->clean_site_id}
				 AND 	friend_id
				 IN 	('" . implode( "','", $members ) . "')
				 AND 	member_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'"
			);

			foreach ( $query->result_array() as $row )
			{
				$db_members[ $row['friend_id'] ]	= $row['entry_id'];
			}

			foreach ( $members as $member_id )
			{
				if ( isset( $db_members[ $member_id ] ) === TRUE )
				{
					ee()->db->query(
						ee()->db->update_string(
							'exp_friends',
							array(
								'reciprocal' 	=> 'n',
								'block' 		=> 'y'
							),
							array( 'entry_id' => $db_members[ $member_id ] )
						)
					);
				}
				else
				{
					ee()->db->query(
						ee()->db->insert_string(
							'exp_friends',
							array(
								'member_id' 	=> ee()->session->userdata['member_id'],
								'friend_id' 	=> $member_id,
								'site_id' 		=> $this->clean_site_id,
								'entry_date' 	=> ee()->localize->now,
								'reciprocal' 	=> 'n',
								'block' 		=> 'y'
							)
						)
					);
				}
			}

			$ended_friendships	= count( $db_members );
		}

		//	----------------------------------------
		//	Were any existing friendships ended?
		//	----------------------------------------

		if ( empty( $ended_friendships ) === FALSE )
		{
			$count = $count - $ended_friendships;

			if ( $ended_friendships > 1 )
			{
				$this->message[]	= str_replace( "%count%", $ended_friendships, lang('friendships_ended') );
			}
			else
			{
				$this->message[]	= str_replace( "%count%", $ended_friendships, lang('friendship_ended') );
			}
		}

		//	----------------------------------------
		//	Update reciprocals
		//	----------------------------------------

		$this->_reciprocal( $members );

		//	----------------------------------------
		//	Adjust our counts.
		//	----------------------------------------

		$this->_update_stats();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		if ( $count > 1 )
		{
			$this->message[]	= str_replace( "%count%", $count, lang('friends_blocked') );
		}
		elseif ( $count == 1 )
		{
			$this->message[]	= str_replace( "%count%", $count, lang('friend_blocked') );
		}

		return TRUE;
	}

	// End block


	// --------------------------------------------------------------------

	/**
	 * Chars decode
	 *
	 * This little routine preps chars for forms
	 *
	 * @access	private
	 * @return	string
	 */

	function _chars_decode( $str = '' )
	{
		if ( $str == '' ) return;

		if ( function_exists( 'htmlspecialchars_decode' ) === TRUE )
		{
			$str	= htmlspecialchars_decode( $str );
		}

		if ( function_exists( 'html_entity_decode' ) === TRUE )
		{
			$str	= html_entity_decode( $str );
		}

		$str	= str_replace( array( '&amp;', '&#47;', '&#39;', '\'' ), array( '&', '/', '', '' ), $str );

		$str	= stripslashes( $str );

		return $str;
	}

	// End chars decode


	// --------------------------------------------------------------------

	/**
	 * Check form hash
	 *
	 * Makes sure that a valid XID is present in the form POST
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _check_form_hash()
	{
		//hash is checked by EE on all posts everywhere in EE 2.7
		if (version_compare($this->ee_version, '2.7', '<') &&
		 ! $this->check_secure_forms())
		{
			return $this->_fetch_error(
				lang('not_authorized'),
				ee()->input->get_post('template')
			);
		}

		return TRUE;
	}
	// End check form hash


	// --------------------------------------------------------------------

	/**
	 * Delete a Friend
	 *
	 * @access	public
	 * @return	string
	 */

	function delete()
	{
		$this->delete	= TRUE;

		return $this->add();
	}

	// End delete


	// --------------------------------------------------------------------

	/**
	 * Delete a Friend (sub)
	 *
	 * @access	public
	 * @return	string
	 */

	function _delete( $members = array() )
	{
		//	----------------------------------------
		//	Multiple or single?
		//	----------------------------------------

		$count	= 1;

		if ( count( $members ) == 0 )
		{
			//	----------------------------------------
			//	Do we have a valid ID number?
			//	----------------------------------------

			if ( ! $this->_member_id() )
			{
				$this->message[]	= lang('no_member_id');
				return FALSE;
			}

			//	----------------------------------------
			//	Fail out if friend does not exist
			//	for member.
			//	----------------------------------------

			$query = ee()->db->query(
				"SELECT COUNT(*) AS count
				 FROM 	exp_friends
				 WHERE 	friend_id = '".ee()->db->escape_str($this->member_id)."'
				 AND 	member_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'
				 LIMIT  1"
			);

			if ( $query->row('count') == 0 )
			{
				$this->message[]	= lang('friend_not_exists');
				return FALSE;
			}

			ee()->db->query(
				"DELETE FROM exp_friends
				 WHERE 		 friend_id = '".ee()->db->escape_str($this->member_id)."'
				 AND 		 member_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'
				 LIMIT 		 1"
			);

			$members[]	= $this->member_id;
		}
		else
		{
			//	----------------------------------------
			//	Verify members
			//	----------------------------------------

			$query		= ee()->db->query(
				"SELECT entry_id
				 FROM 	exp_friends
				 WHERE 	friend_id
				 IN 	('" . implode( "','", ee()->db->escape_str($members) ) . "')
				 AND 	member_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'"
			);

			if ( $query->num_rows() == 0 )
			{
				$this->message[]	= lang('friends_not_exist');
				return FALSE;
			}

			$count	= $query->num_rows();

			foreach ( $query->result_array() as $row )
			{
				ee()->db->query(
					"DELETE FROM 	exp_friends
					 WHERE 			entry_id = '" . $row['entry_id'] . "'"
				);
			}
		}

		//	----------------------------------------
		//	Update reciprocals
		//	----------------------------------------

		$this->_reciprocal( $members, 'delete' );

		//	----------------------------------------
		//	Adjust our counts.
		//	----------------------------------------

		$this->_update_stats();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		if ( $count > 1 )
		{
			$this->message[]	= str_replace( "%count%", $count, lang('friends_deleted') );
		}
		else
		{
			$this->message[]	= str_replace( "%count%", $count, lang('friend_deleted') );
		}

		return TRUE;
	}

	// End delete



	// --------------------------------------------------------------------

	/**
	 * Entry ID
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _entry_id( $type = 'entry_id' )
	{
		$cat_segment	= ee()->config->item("reserved_category_word");

		if ( $this->_numeric( trim( ee()->TMPL->fetch_param( $type ) ) ) === TRUE )
		{
			$this->$type	= trim( ee()->TMPL->fetch_param( $type ) );

			return TRUE;
		}
		elseif ( ee()->uri->query_string != '' OR
				 ( isset( ee()->uri->page_query_string ) === TRUE AND
				   ee()->uri->page_query_string != '' AND
				   $type == 'entry_id' )
			   )
		{
			$qstring	= ( ee()->uri->page_query_string != '' ) ? ee()->uri->page_query_string : ee()->uri->query_string;

			//	----------------------------------------
			//	Do we have a pure ID number?
			//	----------------------------------------

			if ( $this->_numeric( $qstring ) === TRUE )
			{
				$this->$type	= $qstring;

				return TRUE;
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

				if (preg_match("#^".$cat_segment."/#", $qstring, $match) AND ee()->TMPL->fetch_param($this->sc->channel))
				{
					$qstring	= str_replace($cat_segment.'/', '', $qstring);

					$sql		= "SELECT DISTINCT 	cat_group
								   FROM 			{$this->sc->db->channels}
								   WHERE ";

					if ( defined('USER_BLOG') AND defined('UB_BLOG_ID') AND USER_BLOG !== FALSE)
					{
						$sql	.= " {$this->sc->db->id} ='" . UB_BLOG_ID . "'";
					}
					else
					{
						$xsql	= ee()->functions->sql_andor_string(
								ee()->TMPL->fetch_param($this->sc->channel),
								$this->sc->db->channel_name
						);

						if (substr($xsql, 0, 3) == 'AND') $xsql = substr($xsql, 3);

						$sql	.= ' '.$xsql;
					}

					$query	= ee()->db->query($sql);

					if ($query->num_rows() == 1)
					{
						$result	= ee()->db->query(
							"SELECT cat_id
							 FROM 	exp_categories
							 WHERE 	cat_name='" . ee()->db->escape_str($qstring) . "'
							 AND 	group_id='".$query->row('cat_group') . "'"
						);

						if ($result->num_rows() == 1)
						{
							$qstring	= 'C' . $result->row('cat_id');
						}
					}
				}

				//	----------------------------------------
				//	Numeric version of the category
				//	----------------------------------------

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
				//	Remove 'delete' and 'private'
				//	----------------------------------------

				$qstring	= trim_slashes( str_replace( array('delete', 'private'), array( '','' ), $qstring) );

				//	----------------------------------------
				//	Try numeric id again
				//	----------------------------------------

				if ( preg_match( "/(\d+)/", $qstring, $match ) )
				{
					$this->$type	= $match['1'];

					return TRUE;
				}

				//	----------------------------------------
				//	Parse URL title or username
				//	----------------------------------------

				if ( $type == 'member_id' )
				{
					//	----------------------------------------
					//	Parse username
					//	----------------------------------------

					if (strstr($qstring, '/'))
					{
						$xe			= explode('/', $qstring);
						$qstring	= current($xe);
					}

					$sql	= "SELECT 	member_id
							   FROM 	exp_members
							   WHERE 	username = '" . ee()->db->escape_str( $qstring ) . "'";

					$query	= ee()->db->query($sql);

					if ( $query->num_rows() > 0 )
					{
						$this->member_id = $query->row('member_id');

						return TRUE;
					}
				}
				else
				{
					//	----------------------------------------
					//	Parse url_title
					//	----------------------------------------

					if (strstr($qstring, '/'))
					{
						$xe			= explode('/', $qstring);
						$qstring	= current($xe);
					}

					$sql	= "SELECT {$this->sc->db->titles}.entry_id
							   FROM   {$this->sc->db->titles}, {$this->sc->db->channels}
							   WHERE  {$this->sc->db->titles}.{$this->sc->db->id} = {$this->sc->db->channels}.{$this->sc->db->id}
							   AND    {$this->sc->db->titles}.url_title = '" . ee()->db->escape_str($qstring) . "'";

					$query	= ee()->db->query($sql);

					if ( $query->num_rows() > 0 )
					{
						$this->entry_id = $query->row('entry_id');

						return TRUE;
					}
				}
			}
		}

		return FALSE;
	}

	// End entry id


	// --------------------------------------------------------------------

	/**
	 * Fetch error
	 *
	 * @access	private
	 * @return	string
	 */

	function _fetch_error( $error, $template = '' )
	{
		$content  = '';

		if ( $this->ajax === FALSE )
		{
			$content  = '<ul>';

			if ( is_array($error) === FALSE )
			{
				$content	.= "<li>".$error."</li>\n";
			}
			else
			{
				foreach ($error as $val)
				{
					$content	.= "<li>".$val."</li>\n";
				}
			}

			$content .= "</ul>";
		}
		else
		{
			if ( is_array($error) === FALSE )
			{
				$content	.= $error;
			}
			else
			{
				foreach ($error as $val)
				{
					$content	.= $val."\n";
				}
			}
		}


		$data	= array(
			'failure'	=> TRUE,
			'success'	=> FALSE,
			'message'	=> $content
		);

		if ( ! $body = $this->_fetch_template( $template, $data ) )
		{
			return $this->show_error($error);
		}

		return $body;
	}

	// End fetch error


	// --------------------------------------------------------------------

	/**
	 * Fetch members through subquery
	 *
	 * Lists members of a site filtered by a subquery.
	 *
	 * @access	private
	 * @return	string
	 */

	function _fetch_members_through_subquery( $subquery1 = '', $subquery2 = '' )
	{
		//	----------------------------------------
		//	Subquery?
		//	----------------------------------------

		if ( $subquery1 == '' ) return $this->no_results( 'friends' );

		//	----------------------------------------
		//	SQL
		//	----------------------------------------

		$sql	= "SELECT 	m.member_id
				   FROM 	exp_members m
				   WHERE 	m.member_id != ''";

		//	----------------------------------------
		//	Add subquery 1
		//	----------------------------------------

		$sql	.= " AND m.member_id IN (". $subquery1 .")";

		//	----------------------------------------
		//	Add subquery 2
		//	----------------------------------------

		$sql	.= " AND m.member_id IN (". $subquery2 .")";

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param( 'member_group_id' ) )
		{
			$sql	.= ee()->functions->sql_andor_string(
				ee()->TMPL->fetch_param( 'member_group_id' ),
				'm.group_id'
			);
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
			$days	= ee()->localize->now - ( ee()->TMPL->fetch_param('days') + 86400 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $days ) . "'";
		}
		elseif ( is_numeric( ee()->TMPL->fetch_param('hours') ) )
		{
			$hours	= ee()->localize->now - ( ee()->TMPL->fetch_param('hours') + 3600 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $hours ) . "'";
		}

		//	----------------------------------------
		//	Letter
		//	----------------------------------------

		if ( preg_match( "/\/(username|screen_name)\/(.+?)\//s", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			$sql	.= ( $match['1'] == 'username' ) ? " AND m.username LIKE '": " AND m.screen_name LIKE '";
			$sql	.= ee()->db->escape_str( $match['2'] ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('search_username') )
		{
			$sql	.= " AND m.username LIKE '" . ee()->db->escape_str( ee()->TMPL->fetch_param('search_username') ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('search_screen_name') )
		{
			$sql	.= " AND m.screen_name LIKE '" . ee()->db->escape_str( ee()->TMPL->fetch_param('search_screen_name') ) . "%'";
		}

		//	----------------------------------------
		//	Group by
		//	----------------------------------------

		$sql	.= " GROUP BY m.member_id";

		//	----------------------------------------
		//	Order by
		//	----------------------------------------

		$is_random = FALSE;

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND
			 ee()->TMPL->fetch_param('orderby') != '' AND
			 strpos( ee()->TMPL->fetch_param('orderby'), '|' ) === FALSE )
		{
			if( strtolower(ee()->TMPL->fetch_param('orderby')) == 'random' )
			{
				$is_random = TRUE;
				$sql .= " ORDER BY RAND()";
			}
			else
			{
				$sql	.= " ORDER BY m.".ee()->db->escape_str( ee()->TMPL->fetch_param('orderby') );
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}


		//	----------------------------------------
		//	Sort
		//	----------------------------------------

		if ( ! $is_random)
		{
			if ( ee()->TMPL->fetch_param('sort') != 'desc' )
			{
				$sql	.= " ASC";
			}
			else
			{
				$sql	.= " DESC";
			}
		}

		// ----------------------------------------
		//  Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------
		//	For the currently logged-in member viewing the page, get their friends list.
		//	----------------------------------------

		$fsql = "SELECT friend_id,
						private 	AS friends_private,
						block 		AS friends_blocked,
						reciprocal 	AS friends_reciprocal,
						entry_date 	AS friends_entry_date
				 FROM 	exp_friends
				 WHERE 	member_id = " . ee()->db->escape_str(ee()->session->userdata['member_id']);

		$query	= ee()->db->query( $fsql );

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$this->friends[ $row['friend_id'] ]	= $row;
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
			$r	.= $this->_parse_member_data( $id, ee()->TMPL->tagdata );
		}

		$this->friends_count	= 0;

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	// End fetch members through subquery


	// --------------------------------------------------------------------

	/**
	 * Fetch template
	 *
	 * @access	private
	 * @return	string
	 */

	function _fetch_template( $tmp = '', $data = array(), $settings = array() )
	{
		if ( $tmp == '' )
		{
			if ( ! $tmp = ee()->input->get_post('template') )
			{
				if ( ! (isset(ee()->TMPL) AND is_object(ee()->TMPL)) OR ! $tmp = ee()->TMPL->fetch_param('template') )
				{
					return FALSE;
				}
			}
		}

		$tmp		= str_replace( '&amp;', '&', $tmp );

		$template	= preg_split( "/\/|".preg_quote(T_SLASH, '/')."/", trim( $tmp, "/" ) );

		if ( isset( $template['1'] ) === FALSE ) return FALSE;

		$query		= ee()->db->query(
			"SELECT t.template_type, t.template_data
			 FROM 	exp_templates 		AS t
			 JOIN 	exp_template_groups AS tg
			 ON 	tg.group_id = t.group_id
			 WHERE 	tg.group_name = '" . $template['0'] . "'
			 AND 	t.template_name = '" . $template['1'] . "'
			 LIMIT 	1"
		);

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Prevent an infinite loop if this
		//	function is being called inside the
		//	template that we are calling.
		//	----------------------------------------

		if ( stristr( $query->row('template_data'), "exp:friends" ) )
		{
			return $this->show_error(array(lang('template_loop')));
		}

		//	----------------------------------------
		//	Instantiate template class
		//	----------------------------------------

		require_once 'addon_builder/parser.addon_builder.php';
		$TEMPL = $GLOBALS['TMPL'] = new Addon_builder_parser_friends();

		//	----------------------------------------
		//	Set some values
		//	----------------------------------------

		$TEMPL->encode_email		= FALSE;

		$TEMPL->disable_caching		= TRUE;

		$TEMPL->global_vars			= ( isset( $TEMPL->global_vars )) ? $TEMPL->global_vars: array();

		$TEMPL->global_vars			= array_merge( $TEMPL->global_vars, $data );

		$body = $GLOBALS['TMPL']->process_string_as_template($query->row('template_data'));

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $body;
	}

	// End fetch template


	// --------------------------------------------------------------------

	/**
	 * Form
	 *
	 * This method creates the form that allows you to add or remove friends.
	 *
	 * @access	public
	 * @return	string
	 */

	function form()
	{
		$act	= ee()->functions->fetch_action_id('Friends', 'update');

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		if ( preg_match( "/".LD."members".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."members".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];
		}
		elseif ( preg_match( "/".LD."invites".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."invites".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$invite_mode	= TRUE;
			$this->tagdata	= $match['1'];
		}
		else
		{
			return $this->_fetch_error( lang('members_var_pair_required') );
		}

		//	----------------------------------------
		//	Which type of member list?
		//	----------------------------------------

		if ( empty( $invite_mode ) === FALSE )
		{
			$this->tagdata	= $this->invites();
		}
		elseif ( ee()->TMPL->fetch_param('type') !== FALSE AND ee()->TMPL->fetch_param('type') == 'mine' )
		{
			$this->tagdata	= $this->mine();
		}
		else
		{
			$this->tagdata	= $this->members();
		}

		//	----------------------------------------
		//	Swap out the new tagdata
		//	----------------------------------------

		$tagdata	= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );

		//	----------------------------------------
		//	Prep data
		//	----------------------------------------

		$this->arr['ACT']							= $act;

		$this->arr['RET']							= ee()->input->post('RET') ?
														ee()->input->post('RET') :
														ee()->functions->fetch_current_uri();

		$this->arr['form_id']						= ee()->TMPL->fetch_param('form_id') ?
														ee()->TMPL->fetch_param('form_id') :
														'friends_form';

		$this->arr['form_name']						= ee()->TMPL->fetch_param('form_name') ?
														ee()->TMPL->fetch_param('form_name') :
														'friends_form';
		$this->arr['return']						= ee()->TMPL->fetch_param('return') ?
														ee()->TMPL->fetch_param('return') : '';

		$fetch = array(
			'link_confirm',
			'link_request',
			'message_confirm',
			'message_request',
			'subject_confirm',
			'subject_request',
			'template',
			'notification_confirm',
			'notification_request',
			'notify'
		);

		foreach( $fetch as $item )
		{
			$this->arr['friends_' . $item]			= ee()->TMPL->fetch_param($item) ?
															ee()->TMPL->fetch_param($item) : '';
		}

		//	----------------------------------------
		//	Declare form
		//	----------------------------------------

		$this->arr['tagdata']	= $tagdata;

		return $this->_form();
	}

	// End form


	// ----------------------------------------
	// Form
	// ----------------------------------------

	function _wall_form( $data = array() )
	{
		if ( count( $data ) == 0 AND ! isset( $this->hdata ) ) return '';

		if ( ! isset( $this->hdata['tagdata'] ) OR $this->hdata['tagdata'] == '' )
		{
			$tagdata	=	ee()->TMPL->tagdata;
		}
		else
		{
			$tagdata	= $this->hdata['tagdata'];
			unset( $this->hdata['tagdata'] );
		}

		//	----------------------------------------
		//	Insert params
		//	----------------------------------------

		if ( ! $this->params_id = $this->_insert_params() )
		{
			$this->params_id	= 0;
		}

		$this->hdata['params_id']	= $this->params_id;

		//	----------------------------------------
		//	Generate form
		//	----------------------------------------

		$arr	= array(
			'hidden_fields'	=> $this->hdata,
			'action'		=> ee()->functions->fetch_site_index(),
			'id'			=> $this->hdata['id'],
			'name'			=> $this->hdata['form_name'],
			'enctype'		=> ( $this->multipart ) ? 'multi': '',
			'onsubmit'		=> ( ee()->TMPL->fetch_param('onsubmit') ) ?
									ee()->TMPL->fetch_param('onsubmit') : ''
		);

		if ( ee()->TMPL->fetch_param('name') !== FALSE )
		{
			$arr['name']	= ee()->TMPL->fetch_param('name');
		}

		// --------------------------------------------
		//  HTTPS URLs?
		// --------------------------------------------

		if ($this->check_yes(ee()->TMPL->fetch_param('secure_action')))
		{
			if (isset($arr['action']))
			{
				$arr['action'] = str_replace('http://', 'https://', $arr['action']);
			}
		}

		if ($this->check_yes(ee()->TMPL->fetch_param('secure_return')))
		{
			foreach(array('return', 'RET') as $return_field)
			{
				if (isset($arr['hidden_fields'][$return_field]))
				{
					if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $arr['hidden_fields'][$return_field], $match ) > 0 )
					{
						$arr['hidden_fields'][$return_field] = ee()->functions->create_url( $match['1'] );
					}
					elseif ( stristr( $arr['hidden_fields'][$return_field], "http://" ) === FALSE )
					{
						$arr['hidden_fields'][$return_field] = ee()->functions->create_url(
							$arr['hidden_fields'][$return_field]
						);
					}

					$arr['hidden_fields'][$return_field] = str_replace(
						'http://',
						'https://',
						$arr['hidden_fields'][$return_field]
					);
				}
			}
		}

		// --------------------------------------------
		//  Create and Return Form
		// --------------------------------------------

		$r		= ee()->functions->form_declaration( $arr );

		$r	.= stripslashes($tagdata);

		$r	.= "</form>";


		//return $this->_chars_decode($r);
		return $r;
	}

	//	End form



	// --------------------------------------------------------------------

	/**
	 * Form (sub)
	 *
	 * This method receives form config info and returns a properly formated EE form.
	 *
	 * @access	private
	 * @return	string
	 */

	function _form()
	{
		if ( ! isset( $this->arr ) ) return '';

		if ( ! isset( $this->arr['tagdata'] ) OR $this->arr['tagdata'] == '' )
		{
			$tagdata	=	ee()->TMPL->tagdata;
		}
		else
		{
			$tagdata	= $this->arr['tagdata'];
			unset( $this->arr['tagdata'] );
		}

		//	----------------------------------------
		//	Generate form
		//	----------------------------------------
		$form_class = ( isset( $this->arr['form_class'] ) ) ? $this->arr['form_class'] : '';

		$r	= ee()->functions->form_declaration(
			array(
				'hidden_fields'	=> $this->arr,
				'action'		=> $this->arr['RET'],
				'name'			=> $this->arr['form_name'],
				'id'			=> $this->arr['form_id'],
				'class'			=> $form_class,
				'enctype'		=> ( $this->multipart ) ? 'multi': '',
				'onsubmit'		=> ( ee()->TMPL->fetch_param('onsubmit') ) ?
										ee()->TMPL->fetch_param('onsubmit') : ''
			)
		);

		$r	.= stripslashes($tagdata);

		$r	.= "</form>";

		return $r;
	}

	// End form


	// --------------------------------------------------------------------

	/**
	 * Get DB ids
	 *
	 * I was sicking of building loops through DB results. This method
	 * takes an array of column names and loops through the DB results.
	 * All of the values of all the column names are merged into one array.
	 * We use this most often when preparing member data for template parsing.
	 * We want to know about all the members relevant to a given template
	 * and grab their member data in one swoop.
	 *
	 * @access		private
	 * @return		array
	 */

	function _get_db_ids( $columns = array(), $query )
	{
		$array	= array();

		if ( count( $columns ) == 0 OR $query->num_rows() == 0 ) return $array;

		//	Nested foreach loops might end up slow

		foreach ( $query->result_array() as $row )
		{
			foreach ( $columns as $id )
			{
				if ( empty( $row[$id] ) === TRUE ) continue;
				$array[]	= $row[$id];
			}
		}

		$array	= array_unique( $array ); // sort( $array );

		return $array;
	}

	// End get DB ids


	// --------------------------------------------------------------------

	/**
	 * Invite form
	 *
	 * This creates a form that allows site members to invite non-members to the site.
	 *
	 * @access	public
	 * @return	string
	 */

	function invite_form()
	{
		$act	= ee()->functions->fetch_action_id('Friends', 'invite_friends');

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Set tagdata
		//	----------------------------------------

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Parse group variables
		//	----------------------------------------

		if ( preg_match( "/".LD."groups".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."groups".RD."/s", $tagdata, $match ) )
		{
			$match['1']	= $this->_groups( $match['1'], 'owner', 'return_no_results' );

			$tagdata	= str_replace( $match['0'], $match['1'], $tagdata );
		}

		//	----------------------------------------
		//	Prep data
		//	----------------------------------------

		$this->arr['ACT'] = $act;

		$this->arr['RET'] =  ee()->functions->fetch_current_uri();

		foreach ( array(
			'form_id',
			'form_name',
			'return'
			) as $val )
		{
			$this->arr[ $val ] = ( ee()->TMPL->fetch_param( $val ) ) ? ee()->TMPL->fetch_param( $val ) : '';
		}

		foreach ( array(
			'notification_invite',
			'notification_confirm',
			'notification_request',
			'subject_invite',
			'subject_confirm',
			'subject_request',
			'notification_group_invite',
			'notification_group_subject',
			'template',
			'notify'
			) as $val )
		{
			$this->arr[ 'friends_' . $val ]	= ( ee()->TMPL->fetch_param( $val ) ) ? ee()->TMPL->fetch_param( $val ): '';
		}

		//	----------------------------------------
		//	Declare form
		//	----------------------------------------

		$this->arr['tagdata']	= $tagdata;

		return $this->_form();
	}

	// End invite form


	// --------------------------------------------------------------------

	/**
	 * Invite Friends
	 *
	 * This handles the submission from the invite form
	 *
	 * @access	public
	 * @return	string
	 */

	function invite_friends()
	{		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error( lang('not_logged_in') );
		}

		//	----------------------------------------
		//	Run security
		//	----------------------------------------

		$this->_security();

		//	----------------------------------------
		//	Check secure forms
		//	----------------------------------------

		$this->_check_form_hash();

		//	----------------------------------------
		//	Are we inviting to a group?
		//	----------------------------------------
		//	We can either create a group at the time
		//	of inviting people or call upon an
		//	existing group. We give preference to
		//	group creation and edit. If the necessary
		//	fields are not present, we check if a raw
		//	group id has been provided.
		//	----------------------------------------

		if ( ee()->input->post('friends_group_id') !== FALSE AND
			 is_numeric( ee()->input->post('friends_group_id') ) === TRUE )
		{
			$this->group_id	= ee()->input->post('friends_group_id');
		}

		//	----------------------------------------
		//	Are we notifying?
		//	----------------------------------------

		$this->notify	= ! $this->check_no(ee()->input->get_post('friends_notify'));

		//	----------------------------------------
		//	Invite
		//	----------------------------------------

		if ( ! $this->_invite_friends() )
		{
			return $this->_fetch_error( $this->message );
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$data['friends_message']	= $this->_prep_message();

		//	----------------------------------------
		//	Are we using a template?
		//	----------------------------------------

		$template	= ( ee()->input->get_post('friends_template') !== FALSE ) ?
						ee()->input->get_post('friends_template') : '';

		if ( $body = $this->_fetch_template( $template, $data ) )
		{
			return $body;
		}

		//	----------------------------------------
		//	Prep return
		//	----------------------------------------

		$return	= $this->_prep_return();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$return	= $this->_chars_decode( $return );

		ee()->functions->redirect( $return );

		exit();
	}

	// End invite friends


	// --------------------------------------------------------------------

	/**
	 * Invite friends subroutine
	 *
	 * @access	public
	 * @return	string
	 */

	function _invite_friends ()
	{		//	----------------------------------------
		//	Do we have a list of emails?
		//	----------------------------------------

		if ( ! $email = ee()->input->post('friends_emails') )
		{
			$this->message[]	= lang('no_emails');
			return FALSE;
		}

		//	----------------------------------------
		//	Prep email string
		//	----------------------------------------

		$email	= ee()->security->xss_clean( $email );
		$email	= trim( $email );
		$email	= preg_replace("/[,|\|]/", " ", $email);
		$email	= preg_replace("/[\r\n|\r|\n]/", " ", $email);
		$email	= preg_replace("/\t+/", " ", $email);
		$email	= preg_replace("/\s+/", " ", $email);
		$temp	= explode(" ", $email);

		//	----------------------------------------
		//	Clean emails
		//	----------------------------------------

		foreach( $temp as $addr )
		{
			if ( preg_match('/\<(.*)\>/s', $addr, $match) )
			{
				$addr = $match['1'];
			}

			ee()->load->helper('email');

			if ( valid_email($addr) )
			{
				$emails[]	= $addr;
			}
		}

		//	----------------------------------------
		//	Any valid emails?
		//	----------------------------------------

		if ( empty( $emails ) )
		{
			$this->message[]	= lang('no_valid_emails');
			return FALSE;
		}

		//	----------------------------------------
		//	How many of the people being invited are already members?
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT member_id, email
			 FROM 	exp_members
			 WHERE 	email
			 IN 	('" . implode( "','", $emails ) . "')"
		);

		$members	= array();

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$members[ $row['member_id'] ]	= $row['email'];
			}
		}

		//	----------------------------------------
		//	Remove the existing members from the new
		//	----------------------------------------

		$emails		= array_diff( $emails, $members );

		//	----------------------------------------
		//	Switch keys with vals in members array for
		//	later. Note that if two members have the
		//	same email address, this flip will cause
		//	only one to be invited, but so it goes. I
		//	want it that way.
		//	----------------------------------------

		$members	= array_flip( $members );

		//	----------------------------------------
		//	Handle existing members
		//	----------------------------------------

		if ( count( $members ) > 0 )
		{
			$this->_add( $members );

			if ( $this->group_id != 0 )
			{
				$prefs	= array();

				foreach ( array(
					'friends_notification_group_invite' => 'notification_invite',
					'friends_subject_group_invite' 		=> 'subject_invite'
				  ) as $key => $val )
				{
					if ( ee()->input->get_post($key) !== FALSE AND ee()->input->get_post($key) != '' )
					{
						$prefs[$val]	= ee()->input->get_post($key);
					}
				}

				$this->_add_friends_to_group(
					$members,
					$prefs,
					ee()->session->userdata('member_id'),
					$this->group_id
				);
			}
		}

		//	----------------------------------------
		//	Of the new people, find out who has already been invited by this person and remove them
		//	----------------------------------------

		$query = ee()->db->query(
			"SELECT email
			 FROM 	exp_friends
			 WHERE  friend_id = 0
			 AND 	site_id = {$this->clean_site_id}
			 AND   	member_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "
			 AND 	email
			 IN 	('" . implode( "','", $emails ) . "')"
		);

		if ( $query->num_rows() > 0 )
		{
			$emails	= array_diff( $emails, $this->_get_db_ids( array( 'email' ), $query ) );
		}
		else
		{
			// Error message that no one was left to invite
		}

		//	----------------------------------------
		//	Now handle the new people
		//	----------------------------------------

		$count	= 0;

		if ( count( $emails ) > 0 )
		{
			//	----------------------------------------
			//	Make sure notification is on
			//	----------------------------------------

			$this->notify	= TRUE;

			//	----------------------------------------
			//	Loop and go
			//	----------------------------------------

			foreach ( $emails as $email )
			{
				$count++;

				//	----------------------------------------
				//	Prepare insert
				//	----------------------------------------
				//	We create a friend record. The person who
				// is doing the inviting gets a record for the
				// friend that they will soon have. Once that
				// person comes and joins the site, we look for
				// their email in the exp_friends DB table. For
				// whomever invited them, we will find this
				// record and go from there.
				//	----------------------------------------

				$data	= array(
					// They are not a friend yet, they have no member id to use. But soon...
					'friend_id'		=> 0,
					'email'			=> $email,
					'member_id'     => ee()->session->userdata['member_id'],
					'referrer_id'	=> ee()->session->userdata['member_id'],
					'group_id'		=> $this->group_id,
					'entry_date'	=> ee()->localize->now,
					'private'		=> 'n',
					'site_id'		=> $this->clean_site_id
				);

				//	----------------------------------------
				//	Insert
				//	----------------------------------------

				ee()->db->query( ee()->db->insert_string('exp_friends', $data) );

				//	----------------------------------------
				//	Set invite template
				//	----------------------------------------

				$notification_template	= ee()->input->get_post('friends_notification_invite') ?
											ee()->input->get_post('friends_notification_invite') : '';

				//	----------------------------------------
				//	Notify
				//	----------------------------------------

				if ( $notification_template != '' )
				{
					unset( $data );
					$data['email']					= $email;
					$data['notification_template']	= $notification_template;
					$data['from_email']				= ee()->session->userdata['email'];
					$data['from_name']				= ee()->session->userdata['screen_name'];
					$data['subject']				= ee()->input->post('friends_subject_invite') ?
														ee()->input->post('friends_subject_invite') : '';
					$data['member_id']				= ee()->session->userdata('member_id');

					$this->_notify( $data );
				}
			}
		}

		//	----------------------------------------
		//	Update reciprocals
		//	----------------------------------------

		$this->_reciprocal( $members );

		//	----------------------------------------
		//	Adjust our counts.
		//	----------------------------------------

		$this->_update_stats();

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		if ( $count > 1 )
		{
			$this->message[]	= str_replace( "%count%", $count, lang('friends_invited') );
		}
		else
		{
			$this->message[]	= str_replace( "%count%", $count, lang('friend_invited') );
		}

		return TRUE;
	}

	// End invite friends


	// --------------------------------------------------------------------

	/**
	 * Invites
	 *
	 * This method shows invitations that a member has received.
	 *
	 * @access	public
	 * @return	string
	 */

	function invites()
	{
		//	----------------------------------------
		//	Invite type
		//	----------------------------------------

		$invite_type	= 'incoming';
		$sql			= 'SELECT f.member_id AS id';
		$join_column	= 'f.member_id';
		$column			= 'f.friend_id';

		if ( ee()->TMPL->fetch_param('invite_type') !== FALSE AND
			 ee()->TMPL->fetch_param('invite_type') != '' AND
			 ee()->TMPL->fetch_param('invite_type') != 'incoming' )
		{
			$invite_type	= 'outgoing';
			$sql			= 'SELECT f.friend_id AS id';
			$join_column	= 'f.friend_id';
			$column			= 'f.member_id';
		}

		//	----------------------------------------
		//	SQL
		//	----------------------------------------

		$sql	.= " FROM 		exp_friends f
					 LEFT JOIN 	exp_members m
					 ON 		m.member_id = $join_column
					 WHERE 		f.site_id
					 IN 		('" . implode("','", ee()->TMPL->site_ids) . "')";

		//	----------------------------------------
		//	Member id
		//	----------------------------------------

		if ( $this->_member_id() === TRUE )
		{
			$sql	.= " AND $column = '" . ee()->db->escape_str( $this->member_id ) . "'";
		}
		elseif ( ee()->session->userdata['member_id'] != 0 )
		{
			$sql	.= " AND $column = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'";
		}

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('member_group_id') !== FALSE AND
			 ee()->TMPL->fetch_param('member_group_id') != '' )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param( 'member_group_id' ), 'm.group_id' );
		}

		//	----------------------------------------
		//	Email
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('email') !== FALSE AND ee()->TMPL->fetch_param('email') != '' )
		{
			$sql	.= " AND m.email = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('email') ) . "'";
		}

		//	----------------------------------------
		//	Days / hours
		//	----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('days') ) )
		{
			$days	= ee()->localize->now - ( ee()->TMPL->fetch_param('days') + 86400 );

			$sql	.= " AND f.entry_date >= '" . ee()->db->escape_str( $days ) . "'";
		}
		elseif ( is_numeric( ee()->TMPL->fetch_param('hours') ) )
		{
			$hours	= ee()->localize->now - ( ee()->TMPL->fetch_param('hours') + 3600 );

			$sql	.= " AND f.entry_date >= '" . ee()->db->escape_str( $hours ) . "'";
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
		elseif ( ee()->TMPL->fetch_param('search_username') )
		{
			$sql	.= " AND m.username LIKE '" .
						ee()->db->escape_str( ee()->TMPL->fetch_param('search_username') ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('search_screen_name') )
		{
			$sql	.= " AND m.screen_name LIKE '" .
						ee()->db->escape_str( ee()->TMPL->fetch_param('search_screen_name') ) . "%'";
		}

		//	----------------------------------------
		//	Reciprocal
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('reciprocal') !== FALSE AND
			 ee()->TMPL->fetch_param('reciprocal') != '' )
		{
			if ( strpos( ee()->TMPL->fetch_param('reciprocal'), 'n' ) === FALSE )
			{
				$sql	.= " AND f.reciprocal = 'y'";
			}
			else
			{
				$sql	.= " AND f.reciprocal = 'n'";
			}
		}
		else
		{
			$sql	.= " AND f.reciprocal = 'n'";
		}

		//	----------------------------------------
		//	Blocked
		//	----------------------------------------
		//	The default changes depending on incoming versus outgoing mode
		//	----------------------------------------

		if ( $invite_type == 'outgoing' )
		{
			if ( ee()->TMPL->fetch_param('show_blocked') === FALSE OR
				 ! $this->check_yes(ee()->TMPL->fetch_param('show_blocked')) )
			{
				$sql	.= " AND f.block = 'n'";
			}
		}
		elseif ( ee()->TMPL->fetch_param('show_blocked') === FALSE OR
				 ! $this->check_yes(ee()->TMPL->fetch_param('show_blocked')) )
		{
			//	----------------------------------------
			//	When viewing incoming invites, we want to
			//	ignore invites from people that we have blocked.
			//	----------------------------------------

			//they blocked you
			$sql	.= " AND 	f.member_id
						 NOT IN ( SELECT 	member_id
								  FROM 		exp_friends
								  WHERE 	site_id
								  IN 		('".implode("','", ee()->TMPL->site_ids)."')
								  AND 		block = 'y'
								  AND 		friend_id = " . ee()->db->escape_str(
										ee()->session->userdata( 'member_id' ) ) . "
								)";

			//you blocked them
			$sql	.= " AND 	f.member_id
						 NOT IN ( SELECT 	friend_id
								  FROM 		exp_friends
								  WHERE 	site_id
								  IN 		('".implode("','", ee()->TMPL->site_ids)."')
								  AND 		block = 'y'
								  AND 		member_id = " . ee()->db->escape_str(
										ee()->session->userdata( 'member_id' ) ) . "
								)";

		}

		//	----------------------------------------
		//	Order by
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND
			 ee()->TMPL->fetch_param('orderby') != '' 	  AND
			 strpos( ee()->TMPL->fetch_param('orderby'), '|' ) === FALSE )
		{
			if ( in_array( ee()->TMPL->fetch_param('orderby'), array( 'entry_date' ) ) === TRUE )
			{
				$sql	.= " ORDER BY f.".ee()->db->escape_str( ee()->TMPL->fetch_param('orderby') );
			}
			elseif ( ee()->TMPL->fetch_param('orderby') == 'random' )
			{
				$sql	.= " ORDER BY RAND() ";
			}
			else
			{
				$sql	.= " ORDER BY m.".ee()->db->escape_str( ee()->TMPL->fetch_param('orderby') );
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		//	----------------------------------------
		//	Sort
		//	----------------------------------------

		if ( strtolower(ee()->TMPL->fetch_param('sort')) != 'desc' )
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
		//	Run base query
		//	----------------------------------------

		$query	= ee()->db->query( $sql );

		$member_ids	= $this->_get_db_ids( array( 'id' ), $query );

		if ( count( $member_ids ) == 0 )
		{
			return $this->no_results('friends');
		}

		$member_ids	= array_merge( $member_ids, $this->group_members );

		$this->member_ids		= $member_ids;
		$this->total_results	= count( $member_ids );

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------
		//	For the currently logged-in member viewing the page, get their friends list.
		//	----------------------------------------

		$sql	= "SELECT 	f.friend_id,
							f.member_id,
							private AS friends_private,
							f.block AS friends_blocked,
							f.reciprocal AS friends_reciprocal,
							f.entry_date AS friends_entry_date
				   FROM 	exp_friends f
				   WHERE 	f.site_id
				   IN 		(".implode( ',', ee()->TMPL->site_ids ).")";

		if ( $this->_member_id() === TRUE )
		{
			$sql	.= " AND ".$column." = '" . ee()->db->escape_str( $this->member_id ) . "'";
		}
		elseif ( ee()->session->userdata['member_id'] != 0 )
		{
			$sql	.= " AND ".$column." = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'";
		}

		$query	= ee()->db->query( $sql );

		$index	= ( $column == 'f.member_id' ) ? 'friend_id': 'member_id';

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$this->friends[ $row[ $index ] ]	= $row;
			}
		}

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		$r	= '';

		$tdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata: $this->tagdata;

		foreach ( $member_ids as $id )
		{
			$this->friends_count++;
			$r	.= $this->_parse_member_data( $id, $tdata );
		}

		$this->friends_count	= 0;

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	// End invites


	// --------------------------------------------------------------------

	/**
	 * Log
	 *
	 * Log data to the EE CP log.
	 *
	 * @access	private
	 * @return	string
	 */

	function _log( $msg )
	{
		if ( $msg == '' )
		{
			return FALSE;
		}

		$data = array(
			'id'         => '',
			'member_id'  => '1',
			'username'   => 'Friends Module',
			'ip_address' => ee()->input->ip_address(),
			'act_date'   => ee()->localize->now,
			'action'     => $msg,
			'site_id'	 => $this->clean_site_id
		);

		ee()->db->query(ee()->db->insert_string('exp_cp_log', $data));

		//return;
	}

	// End log


	// --------------------------------------------------------------------

	/**
	 * Members
	 *
	 * List members.
	 *
	 * @access	public
	 * @return	string
	 */

	function members()
	{
		//	----------------------------------------
		//	SQL
		//	----------------------------------------

		$sql	= "SELECT 	m.member_id
				   FROM 	exp_members m
				   WHERE 	m.member_id != ''";

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Member id
		//	----------------------------------------

		// The group_form method forces $this->member_id to be the
		// currently logged in user. In this context we don't want that.
		$this->member_id	= 0;

		if ( $this->_member_id() !== FALSE )
		{
			$sql	.= " AND m.member_id = '" . ee()->db->escape_str( $this->member_id ) . "'";
		}
		elseif ( $this->_member_id() === FALSE && ee()->TMPL->fetch_param('username') != '')
		{
			// If username="" is used but no members found, $this->_member_id() is FALSE.
			// Trigger no_results
			$sql	.= " AND m.member_id = 0";
		}

		//	----------------------------------------
		//	Friend id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('friend_id') and is_numeric(ee()->TMPL->fetch_param('friend_id')))
		{
			if ( ee()->TMPL->fetch_param('friend_id') == 'CURRENT_USER' )
			{
				$sql	.= ee()->functions->sql_andor_string( ee()->session->userdata['member_id'], 'm.member_id' );
			}
			else
			{
				$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('friend_id'), 'm.member_id' );
			}
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
			$sql	.= " AND m.email = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('email') ) ."'";
		}

		//	----------------------------------------
		//	Days / hours
		//	----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('days') ) )
		{
			$days	= ee()->localize->now - ( ee()->TMPL->fetch_param('days') + 86400 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $days ) . "'";
		}
		elseif ( is_numeric( ee()->TMPL->fetch_param('hours') ) )
		{
			$hours	= ee()->localize->now - ( ee()->TMPL->fetch_param('hours') + 3600 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $hours ) . "'";
		}

		//	----------------------------------------
		//	Letter
		//	----------------------------------------

		if ( preg_match( "/\/(username|screen_name)\/([^\/\?#]+)/s", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			$sql	.= ( $match['1'] == 'username' ) ? " AND m.username LIKE '": " AND m.screen_name LIKE '";
			$sql	.= ee()->db->escape_str( $match['2'] ) ."%'";
		}
		elseif ( ee()->TMPL->fetch_param('search_username') )
		{
			$sql	.= " AND m.username LIKE '" . ee()->db->escape_str( ee()->TMPL->fetch_param('search_username') ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('search_screen_name') )
		{
			$sql	.= " AND m.screen_name LIKE '" . ee()->db->escape_str( ee()->TMPL->fetch_param('search_screen_name') ) . "%'";
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
			 strpos( ee()->TMPL->fetch_param('orderby'), '|' ) === FALSE)
		{
			if( ee()->TMPL->fetch_param('orderby') == 'random' )
			{
				$sql	.= " ORDER BY RAND() ";
			}
			else
			{
				$sql	.= " ORDER BY m." . ee()->db->escape_str( ee()->TMPL->fetch_param('orderby') );
			}
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

		// Do we want pagination? If we've been passed a member_id, probably not.
		if( $this->member_id == 0 )
		{
			$sql	= $this->_prep_pagination( $sql );
		}

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------
		//	For the currently logged-in member viewing the page, get their friends list.
		//	----------------------------------------

		$fsql = "SELECT friend_id,
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

		//	----------------------------------------
		//	Run base query
		//	----------------------------------------

		$query	= ee()->db->query( $sql );

		$member_ids	= $this->_get_db_ids( array( 'member_id' ), $query );

		$member_ids	= array_merge( $member_ids, $this->group_members );

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

		$tdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata: $this->tagdata;

		foreach ( $member_ids as $id )
		{
			$this->friends_count++;
			$r	.= $this->_parse_member_data( $id, $tdata );
		}

		$this->friends_count	= 0;

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	// End member list


	// --------------------------------------------------------------------

	/**
	 * Member id
	 *
	 * Find and set a member id.
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _member_id( $check_uri_for_raw_id = 'y' )
	{
		$cat_segment	= ee()->config->item("reserved_category_word");

		//	----------------------------------------
		//	Have we already set the member id?
		//	----------------------------------------

		if ( $this->member_id != 0 ) return TRUE;

		//	----------------------------------------
		//	Track down the member id?
		//	----------------------------------------

		if ( isset(ee()->TMPL) AND is_object(ee()->TMPL) AND $this->_numeric( ee()->TMPL->fetch_param('member_id') ) === TRUE )
		{
			$this->member_id	= ee()->TMPL->fetch_param('member_id');

			return TRUE;
		}
		elseif ( isset(ee()->TMPL) AND is_object(ee()->TMPL) AND
				 ee()->TMPL->fetch_param('member_id') !== FALSE AND
				 ee()->TMPL->fetch_param('member_id') == 'CURRENT_USER' AND
				 ee()->session->userdata('member_id') != 0 )
		{
			$this->member_id	= ee()->session->userdata('member_id');

			return TRUE;
		}
		elseif ( isset(ee()->TMPL) AND is_object(ee()->TMPL) AND
				 ee()->TMPL->fetch_param('username') !== FALSE AND
				 ee()->TMPL->fetch_param('username') != '' )
		{
			if ( ee()->TMPL->fetch_param('username') == 'CURRENT_USER' AND
				 ee()->session->userdata('member_id') != 0 )
			{
				$this->member_id	= ee()->session->userdata('member_id');

				return TRUE;

			}
			else
			{
				$query	= ee()->db->query(
					"SELECT member_id
					 FROM 	exp_members
					 WHERE 	username = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('username') ) . "'"
				);

				if ( $query->num_rows() == 1 )
				{
					$this->member_id	= $query->row('member_id');

					return TRUE;
				}
				else
				{
					return FALSE;
				}
			}
		}
		elseif ( isset(ee()->TMPL) AND is_object(ee()->TMPL) AND
				 ee()->TMPL->fetch_param('friend_username') !== FALSE AND
				 ee()->TMPL->fetch_param('friend_username') != '' )
		{
			if ( ee()->TMPL->fetch_param('friend_username') == 'CURRENT_USER' AND
				 ee()->session->userdata('member_id') != 0 )
			{
				$this->member_id	= ee()->session->userdata('member_id');

				return TRUE;

			}
			else
			{
				$query	= ee()->db->query(
					"SELECT member_id
					 FROM 	exp_members
					 WHERE 	username = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('friend_username') ) . "'"
				);

				if ( $query->num_rows() == 1 )
				{
					$this->member_id	= $query->row('member_id');

					return TRUE;
				}
				else
				{
					return FALSE;
				}
			}
		}
		elseif ( preg_match( "#/".$this->trigger."/(\w+)/?#", ee()->uri->uri_string, $match ) AND
				 $this->dynamic === TRUE )
		{
			$sql	= "SELECT 	member_id
					   FROM 	exp_members";

			if ( is_numeric( $match['1'] ) )
			{
				$sql	.= " WHERE member_id = '" . ee()->db->escape_str( $match['1'] ) . "'";
			}
			else
			{
				$sql	.= " WHERE username = '" . ee()->db->escape_str( $match['1'] ) . "'";
			}

			$sql	.= " LIMIT 1";

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 1 )
			{
				$this->member_id	= $query->row('member_id');

				return TRUE;
			}
		}

		//	----------------------------------------
		//	Check URI for raw id?
		//	----------------------------------------

		if ( $check_uri_for_raw_id != 'y' ) return FALSE;

		//	----------------------------------------
		//	No luck so far? Let's try query string
		//	----------------------------------------

		if ( ee()->uri->query_string != '' AND $this->dynamic === TRUE )
		{
			$qstring	= ee()->uri->query_string;

			//	----------------------------------------
			//	Do we have a pure ID number?
			//	----------------------------------------

			if ( is_numeric( $qstring) )
			{
				$this->member_id	= $qstring;
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

				if (preg_match("#^".$cat_segment."/#", $qstring, $match) AND ee()->TMPL->fetch_param($this->sc->channel))
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
					$qstring	= trim_slashes( str_replace($match['0'], '', $qstring) );
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
				//	Remove "delete"
				//	----------------------------------------

				$qstring	= str_replace( "delete", "", $qstring );

				//	----------------------------------------
				//	Remove "delete"
				//	----------------------------------------

				$qstring	= str_replace( "private", "", $qstring );

				//	----------------------------------------
				//	Remove "private"
				//	----------------------------------------

				$qstring	= str_replace( "block", "", $qstring );

				//	----------------------------------------
				//	Return if numeric
				//	----------------------------------------

				if ( is_numeric( str_replace( "/", "", $qstring) ) )
				{
					$this->member_id	= $qstring;
				}
				elseif ( preg_match( "/\/(\d+)\//s", $qstring, $match ) )
				{
					$this->member_id	= $match['1'];
				}
			}

			//	----------------------------------------
			//	Let's check the number against the DB
			//	----------------------------------------

			if ( $this->member_id != '' )
			{
				$query	= ee()->db->query(
					"SELECT member_id
					 FROM 	exp_members
					 WHERE  member_id = '" . ee()->db->escape_str( $this->member_id ) . "'
					 LIMIT 	1"
				);

				if ( $query->num_rows() > 0 )
				{
					$this->member_id	= $query->row('member_id');

					return TRUE;
				}
			}
		}

		return FALSE;
	}

	// End member id



















	// End message folders








	// --------------------------------------------------------------------

	/**
	 * mfields
	 *
	 * Fetch custom member field IDs
	 *
	 * @access	private
	 * @return	array
	 */

	function _mfields()
	{
		if ( count( $this->mfields ) > 0 ) return $this->mfields;

		if ( isset( $this->cache['mfields'] ) === TRUE )
		{
			$this->mfields	=& $this->cache['mfields'];
		}

		$query = ee()->db->query(
			"SELECT m_field_id, m_field_name, m_field_label, m_field_type,
					m_field_list_items, m_field_required, m_field_public, m_field_fmt
			 FROM exp_member_fields"
		);

		foreach ($query->result_array() as $row)
		{
			$this->mfields[$row['m_field_name']] = array(
				'id'		=> $row['m_field_id'],
				'name'		=> $row['m_field_name'],
				'label'		=> $row['m_field_label'],
				'type'		=> $row['m_field_type'],
				'list'		=> $row['m_field_list_items'],
				'required'	=> $row['m_field_required'],
				'public'	=> $row['m_field_public'],
				'format'	=> $row['m_field_fmt']
			);
		}

		$this->cache['mfields']	= $this->mfields;

		return $this->mfields;
	}

	// End


	// --------------------------------------------------------------------

	/**
	 * Mine
	 *
	 * Is this member my friend?
	 *
	 * @access	public
	 * @return	boolean
	 */

	function mine( $forced_friends = array() )
	{
		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	SQL
		//	----------------------------------------

		$sql	= "SELECT 		f.friend_id AS member_id,
								f.friend_id AS friends_member_id
				   FROM 		exp_friends f
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = f.friend_id
				   WHERE 		f.friend_id != ''";

		//	----------------------------------------
		//	Member id
		//	----------------------------------------

		if ( $this->_member_id() AND count( $forced_friends ) == 0 )
		{
			$sql	.= " AND f.member_id = '" . ee()->db->escape_str( $this->member_id ) . "'";
		}
		elseif ( ee()->session->userdata['member_id'] != 0 )
		{
			$sql	.= " AND f.member_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'";
			$this->member_id	= ee()->session->userdata['member_id'];
		}
		else
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Friend id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('friend_id') !== FALSE AND ee()->TMPL->fetch_param('friend_id') != '' )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('friend_id'), 'f.friend_id' );
		}

		if ( count( $forced_friends ) > 0 )
		{
			$sql	.= " AND f.friend_id IN (".implode( ',', $this->_only_numeric( $forced_friends ) ).")";
		}

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('member_group_id') !== FALSE AND ee()->TMPL->fetch_param('member_group_id') != '' )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param( 'member_group_id' ), 'm.group_id' );
		}

		//	----------------------------------------
		//	Reciprocal
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('reciprocal') !== FALSE AND ee()->TMPL->fetch_param('reciprocal') != '' )
		{
			if ( strpos( ee()->TMPL->fetch_param('reciprocal'), 'n' ) !== FALSE )
			{
				$sql	.= " AND f.reciprocal = 'n'";
			}
			else
			{
				$sql	.= " AND f.reciprocal = 'y'";
			}
		}

		//	----------------------------------------
		//	Blocked
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('show_blocked') !== FALSE AND
			 $this->check_yes(ee()->TMPL->fetch_param('show_blocked')) )
		{
			//????
		}
		else
		{
			$sql	.= " AND f.block = 'n'";
		}

		//	----------------------------------------
		//	Public
		//	----------------------------------------

		if ( $this->check_no(ee()->TMPL->fetch_param('public')) 	OR
			 $this->check_yes(ee()->TMPL->fetch_param('private'))  	)
		{
			$sql	.= " AND f.private = 'y'";
		}

		//	----------------------------------------
		//	Email
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('email') !== FALSE AND ee()->TMPL->fetch_param('email') != '' )
		{
			$sql	.= " AND m.email = '" . ee()->db->escape_str( ee()->TMPL->fetch_param('email') ) . "'";
		}

		//	----------------------------------------
		//	Days / hours
		//	----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('days') ) )
		{
			$days	= ee()->localize->now - ( ee()->TMPL->fetch_param('days') + 86400 );

			$sql	.= " AND f.entry_date >= '" . ee()->db->escape_str( $days ) . "'";
		}
		elseif ( is_numeric( ee()->TMPL->fetch_param('hours') ) )
		{
			$hours	= ee()->localize->now - ( ee()->TMPL->fetch_param('hours') + 3600 );

			$sql	.= " AND f.entry_date >= '" . ee()->db->escape_str( $hours ) . "'";
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
		elseif ( ee()->TMPL->fetch_param('search_username') )
		{
			$sql	.= " AND 	m.username
						 LIKE 	'" . ee()->db->escape_str( ee()->TMPL->fetch_param('search_username') ) . "%'";
		}
		elseif ( ee()->TMPL->fetch_param('search_screen_name') )
		{
			$sql	.= " AND 	m.screen_name
						 LIKE 	'" . ee()->db->escape_str( ee()->TMPL->fetch_param('search_screen_name') ) . "%'";
		}

		//	----------------------------------------
		//	Birthday range
		//	----------------------------------------
		//	We receive a range in days
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('birthday_range') !== FALSE AND
			 ee()->TMPL->fetch_param('birthday_range') != '' )
		{
			if ( ee()->TMPL->fetch_param('birthday_range') == '0' OR
				 ee()->TMPL->fetch_param('birthday_range') == 'today' )
			{
				//EE 2.6+
				if (is_callable(array(ee()->localize, 'format_date')))
				{
					$sql	.= " AND m.bday_m = " . ee()->localize->format_date('%n');
					$sql	.= " AND m.bday_d = " . ee()->localize->format_date('%j');

				}
				//EE 2.5.5 and below
				else
				{
					$sql	.= " AND m.bday_m = " . date( 'n', ee()->localize->set_localized_time() );
					$sql	.= " AND m.bday_d = " . date( 'j', ee()->localize->set_localized_time() );
				}


			}
			else
			{
				$valid_dates	= array();

				$now	= ee()->localize->now;

				$valid_dates[]	= $now;

				for ( $i=0; $i<=ee()->TMPL->fetch_param('birthday_range'); $i++ )
				{
					$valid_dates[]	= date( 'nj', ( $now + ( 86400 * $i ) ) );
				}

				$sql	.= " AND CONCAT( bday_m, bday_d ) IN ('".implode( "','", $valid_dates )."')";
			}
		}

		//	----------------------------------------
		//	Order by
		//	----------------------------------------

		$is_random = FALSE;

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND
			 ee()->TMPL->fetch_param('orderby') != '' AND
			 strpos( ee()->TMPL->fetch_param('orderby'), '|' ) === FALSE )
		{
			if ( ee()->TMPL->fetch_param('orderby') == 'birthday' )
			{
				$sql	.= " ORDER BY birthday";
			}
			else if (strtolower(ee()->TMPL->fetch_param('orderby')) == 'random')
			{
				$is_random = TRUE;
				$sql	.= " ORDER BY RAND()";
			}
			else
			{
				$sql	.= " ORDER BY m." . ee()->db->escape_str( ee()->TMPL->fetch_param('orderby') );
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		//	----------------------------------------
		//	Sort
		//	----------------------------------------

		if ( ! $is_random)
		{
			if ( ee()->TMPL->fetch_param('sort') != 'desc' )
			{
				$sql	.= " ASC";
			}
			else
			{
				$sql	.= " DESC";
			}
		}

		// ----------------------------------------
		//  Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		//	----------------------------------------
		//	Run base query
		//	----------------------------------------

		$member_ids	= array();

		$query	= ee()->db->query( $sql );

		foreach ( $query->result_array() as $row )
		{
			$member_ids[]	= $row['member_id'];
		}

		$member_ids	= array_merge( $member_ids, $this->group_members );

		$member_ids	= array_unique( $member_ids );

		//	----------------------------------------
		//	Empty
		//	----------------------------------------

		if ( count( $member_ids ) == 0 )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------
		//	Directly above we have gathered the friends
		//  of the currently logged in member or the member
		//	specified in the URL or template param.
		//	We now get more information about those
		//	friends in this stupid query.
		//	----------------------------------------

		$query = ee()->db->query(
			"SELECT friend_id,
					private 	AS friends_private,
					block 		AS friends_blocked,
					reciprocal 	AS friends_reciprocal,
					entry_date 	AS friends_entry_date
			 FROM 	exp_friends
			 WHERE 	member_id = " . $this->member_id
		);

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$this->friends[ $row['friend_id'] ]	= $row;
			}
		}

		$this->member_ids		= $member_ids;
		$this->total_results	= count( $member_ids );

		$r	= '';

		$tdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata : $this->tagdata;

		foreach ( $member_ids as $id )
		{
			$this->friends_count++;
			$r	.= $this->_parse_member_data( $id, $tdata );
		}

		$this->friends_count	= 0;

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	// End mine


	// --------------------------------------------------------------------

	/**
	 * Mutual friends
	 *
	 * For a given member and a given friend, show mutual friends.
	 *
	 * @access	public
	 * @return	string
	 */

	function mutual_friends()
	{
		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Member id?
		//	----------------------------------------

		$this->trigger	= 'member_name';

		if ( $this->_member_id() === FALSE )
		{
			$this->member_id	= ee()->session->userdata( 'member_id' );
		}

		//	----------------------------------------
		//	Friend id?
		//	----------------------------------------

		$friend_id	= 0;

		if ( ee()->TMPL->fetch_param( 'friend_id' ) !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param( 'friend_id' ) ) === TRUE )
		{
			$friend_id	= ee()->TMPL->fetch_param( 'friend_id' );
		}

		elseif ( ee()->TMPL->fetch_param( 'friend_username' ) !== FALSE AND
				 ee()->TMPL->fetch_param( 'friend_username' ) != '' )
		{
			$friend_id	= $this->data->get_member_id_from_username(
				ee()->config->item( 'site_id' ),
				ee()->TMPL->fetch_param( 'friend_username' )
			);
		}
		elseif ( preg_match( "#/"."friend_name"."/(\w+)/?#", ee()->uri->uri_string, $match ) AND
				 $this->dynamic === TRUE )
		{
			$friend_id	= $this->data->get_member_id_from_username( ee()->config->item( 'site_id' ), $match[1] );
		}

		if ( $friend_id == 0 )
		{
			return $this->no_results( 'friends' );
		}

		//	----------------------------------------
		//	Reciprocal?
		//	----------------------------------------

		if ( $this->check_yes(ee()->TMPL->fetch_param('reciprocal')) )
		{
			$reciprocal	= 'y';
		}

		if ( $this->check_no(ee()->TMPL->fetch_param('reciprocal')) )
		{
			$reciprocal	= 'n';
		}

		//	----------------------------------------
		//	Prep subquery 1
		//	----------------------------------------

		$subquery1	= "SELECT 	friend_id
					   FROM 	exp_friends
					   WHERE 	block = 'n'";

		//	----------------------------------------
		//	If I am looking at my own list I should be able to view my private friends.
		//	----------------------------------------

		if ( ee()->session->userdata( 'member_id' ) != $this->member_id )
		{
			$subquery1	.= " AND private = 'n'";
		}


		$subquery1	.= " AND 	site_id
						 IN 	('".implode("','", ee()->TMPL->site_ids)."')
						 AND 	member_id = " . ee()->db->escape_str( $this->member_id );

		//	----------------------------------------
		//	Prep subquery 2
		//	----------------------------------------

		$subquery2	= "SELECT 	friend_id
					   FROM 	exp_friends
					   WHERE 	block = 'n'
					   AND 		private = 'n'
					   AND 		site_id
					   IN	 	('" . implode("','", ee()->TMPL->site_ids) . "')
					   AND 		member_id = " . ee()->db->escape_str( $friend_id );

		//	----------------------------------------
		//	Reciprocal flag?
		//	----------------------------------------

		if ( ! empty( $reciprocal ) )
		{
			$subquery1	.= " AND reciprocal = '" . $reciprocal . "'";
			$subquery2	.= " AND reciprocal = '" . $reciprocal . "'";
		}

		//	----------------------------------------
		//	Run our utility method
		//	----------------------------------------

		return $this->_fetch_members_through_subquery( $subquery1, $subquery2 );
	}

	// End mutual friends





	// --------------------------------------------------------------------

	/**
	 * Notify
	 *
	 * Send an email.
	 *
	 * @access		private
	 * @return		string
	 */

	function _notify( $data = array() )
	{
		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( count( $data ) == 0 OR $this->notify === FALSE )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Email?
		//	----------------------------------------

		if ( isset( $data['email'] ) === FALSE )
		{
			$this->message[]	= lang('no_email');
			return FALSE;
		}

		//	----------------------------------------
		//	Template?
		//	----------------------------------------

		if ( isset( $data['notification_template'] ) === FALSE )
		{
			$this->message[]	= lang('no_notification_template');
			return FALSE;
		}

		//	----------------------------------------
		//	Instantiate
		//	----------------------------------------

		if ( isset( $data['extra'] ) === TRUE AND is_array( $data['extra'] ) === TRUE )
		{
			//	----------------------------------------
			//	Extract blog entry data for later
			//	----------------------------------------

			if ( empty( $data['extra']['entries'] ) === FALSE AND
				 is_array( $data['extra']['entries'] ) === TRUE )
			{
				$entries	= $data['extra']['entries'];
				unset( $data['extra']['entries'] );
			}

			foreach ( $data['extra'] as $key => $val )
			{
				$vars[$key]	= $val;

				//	----------------------------------------
				//	Parse subject vars
				//	----------------------------------------

				if ( empty( $data['subject'] ) === FALSE AND
					 strpos( $data['subject'], LD.$key ) !== FALSE )
				{
					$data['subject']	= str_replace( LD.$key.RD, $val, $data['subject'] );
				}
			}
		}

		//	----------------------------------------
		//	Prep main vars
		//	----------------------------------------

		$vars['recipient']			= $data['email'];
		$vars['from_email']			= ( $data['from_email'] ) ?
											$data['from_email'] : ee()->config->item('webmaster_email');
		$vars['from_name']			= ( $data['from_name'] ) ?
											$data['from_name']  : ee()->config->item('webmaster_name');
		$vars['friends_subject']	= ( isset( $data['subject'] ) === TRUE ) ?
										$data['subject'] :
										'Someone has added you as a friend at '.ee()->config->item('site_name');
		$vars['friends_message']	= ( isset( $data['message'] ) === TRUE ) ? $data['message']: '';
		$vars['friends_link']		= ( isset( $data['link'] ) === TRUE ) ? $data['link']: '';
		$vars['wordwrap']			= ( isset( $data['wordwrap'] ) === TRUE ) ? $data['wordwrap']: '';
		$vars['html']				= ( isset( $data['html'] ) === TRUE ) ? $data['html']: '';

		//	----------------------------------------
		//	No template in DB?
		//	----------------------------------------

		if ( ( $body = $this->_fetch_template( $data['notification_template'], $vars ) ) === FALSE )
		{
			$this->message[]	= lang('no_notification_template_found');
			return FALSE;
		}

		//	----------------------------------------
		//	Parse member data?
		//	----------------------------------------

		if ( isset( $data['member_id'] ) === TRUE AND
			( strpos( $vars['friends_subject'], 'screen_name' ) !== FALSE OR
			  strpos( $vars['friends_message'], 'screen_name' ) !== FALSE OR
				( strpos( $vars['friends_subject'], 'username' ) !== FALSE OR
				  strpos( $vars['friends_message'], 'username' ) !== FALSE ) OR
			  strpos( $body, 'screen_name' ) !== FALSE
			)
		 )
		{
			//should we parse everything?
			$parse_custom_fields = $this->data->get_preference_from_site_id(
										ee()->config->item( 'site_id' ), 'notify_parse_all' ) == 'y';

			// There was a bug with not having a preference for this, so double check our setting
			if( ! $parse_custom_fields )
			{
				if( ! ($this->data->get_preference_from_site_id( ee()->config->item( 'site_id' ), 'notify_parse_all' ) == 'n' ) )
				{
					// Oh noes! the preference wasn't set after all!
					// Default to true
					$parse_custom_fields = TRUE;
				}
			}

			//	----------------------------------------
			//	Fetch it
			//	----------------------------------------

			if ( empty( $this->cache['members'][$data['member_id']] ) === TRUE )
			{
				$query	= ee()->db->query(
					"SELECT *
					 FROM 	exp_members
					 WHERE 	member_id = " . ee()->db->escape_str( $data['member_id'] )
				);

				$row   = $query->row_array();

				if ( $parse_custom_fields AND count( $this->_mfields() ) > 0 )
				{
					$member_data_query	= ee()->db->query(
						"SELECT *
						 FROM 	exp_member_data
						 WHERE 	member_id = " . ee()->db->escape_str( $data['member_id'] )
					);

					$member_data_row   = $member_data_query->row_array();

					foreach ( $this->mfields as $key => $val )
					{
						$row[ $key ] = $member_data_row['m_field_id_' . $val['id'] ];
					}
				}

				$this->cache['members'][$data['member_id']] = $row;
			}

			//	----------------------------------------
			//	Parse
			//	----------------------------------------

			foreach ( $this->cache['members'][ $data['member_id'] ] as $key => $val )
			{
				foreach ( array( 'friends_subject', 'friends_message' ) as $item )
				{
					//	----------------------------------------
					//	For invites
					//	----------------------------------------

					if ( strpos( $vars[$item], LD . 'friends_invitee_' . $key . RD ) !== FALSE )
					{
						$vars[$item]	= str_replace( LD . 'friends_invitee_' . $key . RD, $val, $vars[$item] );
					}

					if ( strpos( $vars[$item], LD . 'friends_invitee_photo_url' . RD ) !== FALSE )
					{
						$vars[$item]	= str_replace( LD . 'friends_invitee_photo_url' . RD, ee()->config->item('photo_url'), $vars[$item] );
					}

					if ( strpos( $body, LD . 'friends_invitee_' . $key . RD ) !== FALSE )
					{
						$body	= str_replace( LD . 'friends_invitee_' . $key . RD, $val, $body );
					}

					if ( strpos( $body, LD . 'friends_invitee_photo_url' . RD ) !== FALSE )
					{
						$body	= str_replace( LD . 'friends_invitee_photo_url' . RD, ee()->config->item('photo_url'), $body );
					}

					//	----------------------------------------
					//	For messaging
					//	----------------------------------------

					if ( strpos( $vars[$item], LD . 'friends_recipient_' . $key . RD ) !== FALSE )
					{
						$vars[$item]	= str_replace( LD . 'friends_recipient_' . $key . RD, $val, $vars[$item] );
					}

					if ( strpos( $vars[$item], LD . 'friends_recipient_photo_url' . RD ) !== FALSE )
					{
						$vars[$item]	= str_replace( LD . 'friends_recipient_photo_url' . RD, ee()->config->item('photo_url'), $vars[$item] );
					}

					if ( strpos( $body, LD . 'friends_recipient_' . $key . RD ) !== FALSE )
					{
						$body	= str_replace( LD . 'friends_recipient_' . $key . RD, $val, $body );
					}

					if ( strpos( $body, LD . 'friends_recipient_photo_url' . RD ) !== FALSE )
					{
						$body	= str_replace( LD . 'friends_recipient_photo_url' . RD, ee()->config->item('photo_url'), $body );
					}
				}
			}

			//	----------------------------------------
			//	Fetch it
			//	----------------------------------------

			if ( empty( $this->cache['members'][ ee()->session->userdata('member_id') ] ) === TRUE )
			{
				$query	= ee()->db->query(
					"SELECT *
					 FROM 	exp_members
					 WHERE 	member_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') )
				);

				$row   = $query->row_array();

				if ( $parse_custom_fields AND count( $this->_mfields() ) > 0 )
				{
					$member_data_query	= ee()->db->query(
						"SELECT *
						 FROM 	exp_member_data
						 WHERE 	member_id = ".ee()->db->escape_str( ee()->session->userdata('member_id') )
					);

					$member_data_row   = $member_data_query->row_array();

					foreach ( $this->mfields as $key => $val )
					{
						$row[ $key ] = $member_data_row['m_field_id_' . $val['id'] ];
					}
				}

				$this->cache['members'][ ee()->session->userdata('member_id') ]	= $row;
			}

			//	----------------------------------------
			//	Parse
			//	----------------------------------------

			foreach ( $this->cache['members'][ ee()->session->userdata('member_id') ] as $key => $val )
			{
				foreach ( array( 'friends_subject', 'friends_message' ) as $item )
				{
					//	----------------------------------------
					//	For invites
					//	----------------------------------------

					if ( strpos( $vars[$item], LD . 'friends_inviter_' . $key . RD ) !== FALSE )
					{
						$vars[$item]	= str_replace( LD . 'friends_inviter_' . $key . RD, $val, $vars[$item] );
					}

					if ( strpos( $vars[$item], LD . 'friends_inviter_photo_url' . RD ) !== FALSE )
					{
						$vars[$item] 	= str_replace( LD . 'friends_inviter_photo_url' . RD, ee()->config->item('photo_url'), $vars[$item] );
					}

					if ( strpos( $body, LD . 'friends_inviter_' . $key . RD ) !== FALSE )
					{
						$body	= str_replace( LD . 'friends_inviter_' . $key . RD, $val, $body );
					}

					if ( strpos( $body, LD . 'friends_inviter_photo_url' . RD ) !== FALSE )
					{
						$body 	= str_replace( LD . 'friends_inviter_photo_url' . RD, ee()->config->item('photo_url'), $body );
					}

					//	----------------------------------------
					//	For messaging
					//	----------------------------------------

					if ( strpos( $vars[$item], LD . 'friends_sender_' . $key . RD ) !== FALSE )
					{
						$vars[$item]	= str_replace( LD . 'friends_sender_' . $key . RD, $val, $vars[$item] );
					}

					if ( strpos( $vars[$item], LD . 'friends_sender_photo_url' . RD ) !== FALSE )
					{
						$vars[$item] 	= str_replace( LD . 'friends_sender_photo_url' . RD, ee()->config->item('photo_url'), $vars[$item] );
					}

					if ( strpos( $body, LD . 'friends_sender_' . $key . RD ) !== FALSE )
					{
						$body	= str_replace( LD . 'friends_sender_' . $key . RD, $val, $body );
					}

					if ( strpos( $body, LD . 'friends_sender_photo_url' . RD ) !== FALSE )
					{
						$body 	= str_replace( LD . 'friends_sender_photo_url' . RD, ee()->config->item('photo_url'), $body );
					}
				}
			}

			//	----------------------------------------
			//	Save in the session for use elsewhere,
			//	most likely by the function that called $this->_notify()
			//	----------------------------------------

			$this->cache['email_notifications'][ $data['member_id'] ]	= $vars;
		}

		//	----------------------------------------
		//	Parse weblog entry data?
		//	----------------------------------------
		//	We can receive an array of weblog entry data.
		//	When we do, we can parse a variable pair called
		//	{entries} and return a line for each entry.
		//	----------------------------------------

		if ( strpos( $body, LD.'entries' ) !== FALSE AND empty( $entries ) === FALSE )
		{
			if ( preg_match( "/{entries}(.*?){\/entries}/s", $body, $match ) )
			{
				$r	= '';

				foreach ( $entries as $key => $entry )
				{
					$tagdata	= $match['1'];

					foreach ( $entry as $k => $v )
					{
						if ( strpos( $tagdata, LD.$k ) === FALSE ) continue;

						$tagdata	= str_replace( LD.$k.RD, $v, $tagdata );
					}

					$r	.= $tagdata;
				}

				$body	= str_replace( $match['0'], $tagdata, $body );
			}
		}

		//	----------------------------------------
		//	Send email
		//	----------------------------------------

		ee()->load->library('email');
		ee()->email->wordwrap	= $this->check_yes($vars['wordwrap']);
		ee()->email->mailtype	= $this->check_yes($vars['html']) ? 'html': 'text';

		ee()->email->initialize();
		ee()->email->from( 		$vars['from_email'], $vars['from_name'] );
		ee()->email->to( 		$vars['recipient'] );
		ee()->email->subject( 	$vars['friends_subject'] );
		ee()->email->message( 	entities_to_ascii( $body ) );
		ee()->email->send();

		//	----------------------------------------
		//	Cache the email
		//	----------------------------------------

		$data	= array(
			'entry_date' 	=> ee()->localize->now,
			'total_sent' 	=> 1,
			'from_name'		=> $vars['from_name'],
			'from_email'	=> $vars['from_email'],
			'recipient'		=> $vars['recipient'],
			'subject'		=> $vars['friends_subject'],
			'message'		=> $vars['friends_message'],
			'mailtype'		=> ee()->email->mailtype,
			'wordwrap'		=> $vars['wordwrap']
		);

		ee()->db->query( ee()->db->insert_string( 'exp_friends_notification_log', $data ) );
	}

	// End notify


	// --------------------------------------------------------------------

	/**
	 * Numeric
	 *
	 * Is a value numeric?
	 *
	 * @access		public
	 * @return		string
	 */

	function _numeric ( $str = '' )
	{
		if ( $str == '' OR preg_match( '/[^0-9]/', $str ) != 0 )
		{
			return FALSE;
		}

		return TRUE;
	}

	// End numeric


	// --------------------------------------------------------------------

	/**
	 * Only numeric
	 *
	 * Returns an array containing only numeric values
	 *
	 * @access		private
	 * @return		array
	 */

	function _only_numeric( $array )
	{
		if ( empty( $array ) === TRUE ) return FALSE;

		if ( is_array( $array ) === FALSE )
		{
			$array	= array( $array );
		}

		foreach ( $array as $key => $val )
		{
			if ( preg_match( '/[^0-9]/', $val ) != 0 ) unset( $array[$key] );
		}

		if ( empty( $array ) === TRUE ) return FALSE;

		return $array;
	}

	// End only numeric


	// --------------------------------------------------------------------

	/**
	 * Parse
	 *
	 * Parse a list of members. This method has been bettered and replaced by _parse_member_data(). Not that the replacement is not one to one. Some extra coding is needed.
	 *
	 * @access		public
	 * @return		string
	 */

	function _parse( $inject = array() )
	{
		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( $this->query->num_rows() == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Disable
		//	----------------------------------------

		$disable_member_fields	= FALSE;
		$disable_date_fields	= FALSE;

		if ( ee()->TMPL->fetch_param('disable') AND stristr( ee()->TMPL->fetch_param('disable'), 'member_fields' ) )
		{
			$disable_member_fields	= TRUE;
		}

		if ( ee()->TMPL->fetch_param('disable') AND stristr( ee()->TMPL->fetch_param('disable'), 'date_fields' ) )
		{
			$disable_date_fields	= TRUE;
		}

		//	----------------------------------------
		//	Base member image stuff
		//	----------------------------------------

		$avatar_url		= ee()->config->item('avatar_url');
		$photo_url		= ee()->config->item('photo_url');
		$signature_url	= ee()->config->item('signature_url');

		//	----------------------------------------
		//	Set dates
		//	----------------------------------------

		$dates	= array(
			'friends_entry_date',
			'friends_edit_date',
			'friends_join_date',
			'friends_last_bulletin_date',
			'friends_last_visit',
			'friends_last_activity',
			'friends_last_entry_date',
			'friends_last_rating_date',
			'friends_last_comment_date',
			'friends_last_forum_post_date',
			'friends_last_email_date'
		);

		//	----------------------------------------
		//	Set typography
		//	----------------------------------------

		if ( ! $disable_member_fields )
		{

			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;

			$this->TYPE =& ee()->typography;
		}

		//	----------------------------------------
		//	Loop
		//	----------------------------------------

		$return		= '';
		$position	= 0;

		foreach ( $this->query->result_array() as $row )
		{
			//	----------------------------------------
			//	Add vars
			//	----------------------------------------

			$row['avatar_url']		= $avatar_url;
			$row['photo_url']		= $photo_url;
			$row['signature_url']	= $signature_url;

			//	----------------------------------------
			//	Inject
			//	----------------------------------------

			if ( count( $inject ) > 0 AND isset( $inject[$row['member_id']] ) )
			{
				$row	= array_merge( $row, $inject[$row['member_id']] );
			}

			//	----------------------------------------
			//	Handle friends
			//	----------------------------------------
			//	The $this->friends array should contain
			//  basic info about the list of friends for
			//  current member viewing the page or
			//	invoked in the URL / template param.
			//	----------------------------------------

			$row['friend']				= 'n';
			$row['friends_reciprocal']	= 'n';
			$row['friends_blocked']		= 'n';
			$row['friends_private']		= 'n';
			$row['friends_public']		= 'y';
			$row['friends_entry_date']	= 0;

			if ( isset( $this->friends[ $row['member_id'] ] ) === TRUE )
			{
				$row['friend']				= 'y';
				$row['friends_reciprocal']	= $this->friends[ $row['member_id'] ]['friends_reciprocal'];
				$row['friends_blocked']		= $this->friends[ $row['member_id'] ]['friends_blocked'];
				$row['friends_private']		= $this->friends[ $row['member_id'] ]['friends_private'];
				$row['friends_entry_date']	= $this->friends[ $row['member_id'] ]['friends_entry_date'];
				$row['friends_public']		= ( $this->friends[ $row['member_id'] ]['friends_private'] == 'n' ) ? 'y': 'n';
			}

			//	----------------------------------------
			//	Handle group members
			//	----------------------------------------

			if ( in_array( $row['member_id'], $this->group_members ) )
			{
				$row['friends_group_member']	= 'y';
			}
			else
			{
				$row['friends_group_member']	= 'n';
			}

			//	----------------------------------------
			//	Conditionals
			//	----------------------------------------

			$tagdata	= ( $this->tagdata == '' ) ? ee()->TMPL->tagdata: $this->tagdata;

			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $row );

			//	----------------------------------------
			//	Parse Switch
			//	----------------------------------------

			if ( preg_match( "/" . LD . "(switch\s*=.+?)" . RD . "/is", $tagdata, $match ) > 0 )
			{
				$sparam = ee()->functions->assign_parameters($match['1']);

				$sw = '';

				if ( isset( $sparam['switch'] ) !== FALSE )
				{
					$sopt = explode("|", $sparam['switch']);

					$sw = $sopt[($position + count($sopt)) % count($sopt)];
				}

				$tagdata = ee()->TMPL->swap_var_single($match['1'], $sw, $tagdata);
			}

			//	----------------------------------------
			//	Parse dates
			//	----------------------------------------

			if ( $disable_date_fields === FALSE )
			{
				foreach ($dates as $val)
				{
					if ( isset( $row[$val] ) === TRUE AND
						 preg_match(
							"/".LD.$val."\s+format=[\"'](.*?)[\"']".RD."/s",
							$tagdata,
							$match
						 )
					   )
					{
						$str	= $match['1'];

						$codes	= $this->fetch_date_params( $match['1'] );

						foreach ( $codes as $code )
						{
							$str	= str_replace(
								$code,
								$this->convert_timestamp( $code, $row[$val], TRUE ),
								$str
							);
						}

						$tagdata	= str_replace( $match['0'], $str, $tagdata );
					}
				}
			}

			//	----------------------------------------
			//	Parse custom member vars
			//	----------------------------------------

			if ( isset( $row['friends_member_id'] ) AND
				 $row['friends_member_id'] != '0' 	AND
				 count( $this->_mfields() ) > 0 	AND
				 $disable_member_fields === FALSE )
			{
				foreach ( $this->mfields as $key => $val )
				{
					//	----------------------------------------
					//	Conditionals
					//	----------------------------------------
					//	This is going to be a slow way to parse
					//  custom member field conditionals.
					//	There's a better way somewhere.
					//	----------------------------------------

					$cond[ $val['name'] ]	= $row['m_field_id_'.$val['id']];
					$tagdata				= ee()->functions->prep_conditionals( $tagdata, $cond );

					//	----------------------------------------
					//	Parse select
					//	----------------------------------------

					if ( preg_match(
							"/" . LD . "select_" . $key . RD . "(.*?)" . LD . preg_quote(T_SLASH, '/') . "select_" . $key . RD . "/s",
							$tagdata,
							$match ) )
					{
						$data		= ee()->TMPL->fetch_data_between_var_pairs( $tagdata, 'select_' . $key );

						$tagdata	= preg_replace(
							"/" . LD . 'select_' . $key . RD . "(.*?)" . LD . preg_quote(T_SLASH, '/') . 'select_' . $key . RD . "/s",
							$this->_parse_select( $key, $row, $data ),
							$tagdata
						);
					}

					//	----------------------------------------
					//	Parse abbreviated
					//	----------------------------------------

					$tagdata = ee()->TMPL->swap_var_single(
						'abbr_'.$key,
						$this->TYPE->parse_type(
							substr( $row['m_field_id_' . $val['id']], 0, 1 ) . '.',
							array(
								'text_format'   => $this->mfields[$key]['format'],
								'html_format'   => 'safe',
								'auto_links'    => 'n',
								'allow_img_url' => 'n'
							)
						),
						$tagdata
					);

					//	----------------------------------------
					//	Parse singles
					//	----------------------------------------

					$tagdata = ee()->TMPL->swap_var_single(
						$key,
						$this->TYPE->parse_type(
							$row['m_field_id_'.$val['id']],
							array(
								'text_format'   => $this->mfields[$key]['format'],
								'html_format'   => 'safe',
								'auto_links'    => 'n',
								'allow_img_url' => 'n'
							)
						),
						$tagdata
					);
				}
			}

			//	----------------------------------------
			//	Parse friends vars
			//	----------------------------------------

			foreach ( $row as $key => $val )
			{
				if ( strpos( $tagdata, LD . $key . RD ) === FALSE ) continue;

				$tagdata	= str_replace( LD . $key . RD, $val, $tagdata );
			}

			$this->return_data	.= $tagdata;
		}

		return TRUE;
	}

	// End list


	// --------------------------------------------------------------------

	/**
	 * Parse date
	 *
	 * Parses an EE date string.
	 *
	 * @access	private
	 * @return	str
	 */

	function _parse_date( $format = '', $date = 0 )
	{
		if ( $format == '' OR $date == 0 ) return '';

		// -------------------------------------
		//	strftime is much faster, but we have to convert date codes from what EE users expect to use
		// -------------------------------------

		// return strftime( $format, $date );

		// -------------------------------------
		//	EE's built in date parser is slow, but for now we'll use it
		// -------------------------------------

		$codes	= $this->fetch_date_params( $format );

		foreach ( $codes as $code )
		{
			$format	= str_replace( $code, $this->convert_timestamp( $code, $date, TRUE ), $format );
		}

		return $format;
	}

	// End parse date


	// --------------------------------------------------------------------

	/**
	 * Parse member data
	 *
	 * This parses a block of member data whether one member or many
	 *
	 * @access	private
	 * @return	string
	 */

	function _parse_member_data( $member_id = '', $tdata = '', $prefix = 'friends_', $inject = array(), $include_counts = TRUE )
	{
		if ( empty( $member_id ) === TRUE OR $tdata == '' ) return FALSE;

		//	----------------------------------------
		//	Do we already have our data?
		//	----------------------------------------

		if ( is_array( $member_id ) === FALSE AND
			isset( $this->cache['member_data'][$prefix.$member_id] ) === TRUE )
		{
			$member_data[$member_id]	= $this->cache['member_data'][$prefix.$member_id];
		}
		else
		{
			$sql	=  "SELECT
						m.member_id 				AS {$prefix}member_id,
						m.group_id 					AS {$prefix}member_group_id,
						m.username 					AS {$prefix}username,
						m.screen_name 				AS {$prefix}screen_name,
						m.email 					AS {$prefix}email,
						m.url 						AS {$prefix}url,
						m.location 					AS {$prefix}location,
						m.occupation 				AS {$prefix}occupation,
						m.interests 				AS {$prefix}interests,
						m.bday_d 					AS {$prefix}bday_d,
						m.bday_m 					AS {$prefix}bday_m,
						m.bday_y 					AS {$prefix}bday_y,
						m.aol_im 					AS {$prefix}aol_im,
						m.yahoo_im 					AS {$prefix}yahoo_im,
						m.msn_im 					AS {$prefix}msn_im,
						m.icq 						AS {$prefix}icq,
						m.bio 						AS {$prefix}bio,
						m.signature 				AS {$prefix}signature,
						m.avatar_filename 			AS {$prefix}avatar_filename,
						m.avatar_width 				AS {$prefix}avatar_width,
						m.avatar_height 			AS {$prefix}avatar_height,
						m.photo_filename 			AS {$prefix}photo_filename,
						m.photo_width 				AS {$prefix}photo_width,
						m.photo_height 				AS {$prefix}photo_height,
						m.sig_img_filename 			AS {$prefix}signature_image_filename,
						m.sig_img_width 			AS {$prefix}signature_image_width,
						m.sig_img_height 			AS {$prefix}signature_image_height,
						m.ignore_list				AS {$prefix}ignore_list,
						m.private_messages			AS {$prefix}private_messages,
						m.accept_messages			AS {$prefix}accept_messages,
						m.last_view_bulletins		AS {$prefix}last_view_bulletins,
						m.last_bulletin_date		AS {$prefix}last_bulletin_date,
						m.ip_address				AS {$prefix}ip_address,
						m.join_date 				AS {$prefix}join_date,
						m.last_visit 				AS {$prefix}last_visit,
						m.last_activity 			AS {$prefix}last_activity,
						m.total_entries 			AS {$prefix}total_entries,
						m.total_comments 			AS {$prefix}total_comments,
						m.total_forum_topics 		AS {$prefix}total_forum_topics,
						m.total_forum_posts 		AS {$prefix}total_forum_posts,
						m.total_friends 			AS {$prefix}total_friends,
						m.total_reciprocal_friends 	AS {$prefix}total_reciprocal_friends,
						m.total_blocked_friends 	AS {$prefix}total_blocked_friends,
						m.friends_groups_private 	AS {$prefix}groups_private,
						m.friends_groups_public 	AS {$prefix}groups_public,
						m.friends_total_hugs 		AS {$prefix}total_hugs,
						m.last_entry_date 			AS {$prefix}last_entry_date,
						m.last_comment_date 		AS {$prefix}last_comment_date,
						m.last_forum_post_date 		AS {$prefix}last_forum_post_date,
						m.last_email_date 			AS {$prefix}last_email_date,
						m.in_authorlist				AS {$prefix}in_authorlist,
						m.accept_admin_email		AS {$prefix}accept_admin_email,
						m.accept_user_email			AS {$prefix}accept_user_email,
						m.notify_by_default			AS {$prefix}notify_by_default,
						m.notify_of_pm				AS {$prefix}notify_of_pm,
						m.display_avatars			AS {$prefix}display_avatars,
						m.display_signatures		AS {$prefix}display_signatures,
						m.language 					AS {$prefix}language,
						m.timezone 					AS {$prefix}timezone,";

			$sql .=		"mg.group_title 			AS {$prefix}member_group_title, ";

			// Is User installed?
			if ( $this->column_exists( 'profile_views', 'exp_members' ) === TRUE )
			{
				$sql .= " m.profile_views 			AS {$prefix}profile_views, ";
			}


			$sql  	.= " md.*
						FROM 		exp_members AS m
						LEFT JOIN	exp_member_groups mg
						ON			m.group_id = mg.group_id
						LEFT JOIN 	exp_member_data md
						ON 			m.member_id = md.member_id";

			if ( count( $this->member_ids ) > 0 )
			{
				$sql	.= ' WHERE m.member_id IN ('.implode( ',', $this->member_ids ).')';
			}
			else
			{
				$sql	.= ' WHERE m.member_id = '.ee()->db->escape_str( $member_id );
			}

			$sql	.= ' AND site_id = \'' . ee()->db->escape_str(ee()->config->item('site_id')) .
						'\' ORDER BY m.screen_name ASC';

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				$this->cache['member_data'][$prefix.$member_id]	= array();

				return FALSE;
			}

			$member_data	=& $query->result_array();
		}

		//	----------------------------------------
		//	Parse through our tagdata
		//	----------------------------------------

		$disable_member_fields	= $this->check_yes(ee()->TMPL->fetch_param('disable_member_fields'));

		//	----------------------------------------
		//	Set typography
		//	----------------------------------------

		if ( $disable_member_fields === FALSE )
		{

			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;

			$this->TYPE =& ee()->typography;

		}

		//	----------------------------------------
		//	Add additional values to $query->row
		//	----------------------------------------

		$photo_url		= ee()->config->slash_item('photo_url');
		$avatar_url		= ee()->config->slash_item('avatar_url');
		$sig_img_url	= ee()->config->slash_item('sig_img_url');

		//	----------------------------------------
		//	Commencez
		//	----------------------------------------

		$r = '';

		foreach ( $member_data as $row )
		{
			if ( is_array( $row ) === FALSE ) continue;

			$tagdata	= $tdata;

			unset( $inject['member_id'] );

			$row	= array_merge( $row, $inject );

			if( ! isset( $row['member_id'] ) ) continue;

			$this->cache['member_data'][$prefix.$row['member_id']] = $row;

			if ( is_array( $member_id ) === FALSE AND $row['member_id'] != $member_id ) continue;

			//	----------------------------------------
			//	Add counts
			//	----------------------------------------

			if( $include_counts )
			{

				$row['friends_count']			= $this->friends_count;
				$row['friends_total_results']	= $this->total_results;

			}

			//	----------------------------------------
			//	Total Combined Posts
			//	----------------------------------------

			$row['friends_total_combined_posts'] = 0;

			if (isset($row['friends_total_entries']))
			{
				$row['friends_total_combined_posts'] = $row['friends_total_entries'] + $row['friends_total_comments'] + $row['friends_total_forum_posts'] + $row['friends_total_forum_topics'];
			}



			//	----------------------------------------
			//	Add fix for conditional parser problem on no_results
			//	----------------------------------------

			$row['friends_no_results']	= '';

			//	----------------------------------------
			//	Handle friends
			//	----------------------------------------
			//	The $this->friends array should contain
			//	basic info about the list of friends for
			//	current member viewing the page or invoked
			//	in the URL / template param.
			//	----------------------------------------

			$row['friend']				= 'n';
			$row['friends_reciprocal']	= 'n';
			$row['friends_blocked']		= 'n';
			$row['friends_private']		= 'n';
			$row['friends_public']		= 'y';
			$row['friends_entry_date']	= 0;

			if ( isset( $this->friends[ $row['member_id'] ] ) === TRUE )
			{
				$row['friends_reciprocal']	= $this->friends[ $row['member_id'] ]['friends_reciprocal'];
				$row['friends_blocked']		= $this->friends[ $row['member_id'] ]['friends_blocked'];
				$row['friends_private']		= $this->friends[ $row['member_id'] ]['friends_private'];
				$row['friends_entry_date']	= $this->friends[ $row['member_id'] ]['friends_entry_date'];
				$row['friends_public']		= ( $this->friends[ $row['member_id'] ]['friends_private'] == 'n' ) ? 'y': 'n';
				$row['friend']				= ( $this->friends[ $row['member_id'] ]['friends_blocked'] == 'n' ) ? 'y': 'n';
			}

			//	----------------------------------------
			//	Handle group members
			//	----------------------------------------

			if ( in_array( $row['member_id'], $this->group_members ) )
			{
				$row['friends_group_member']	= 'y';
			}
			else
			{
				$row['friends_group_member']	= 'n';
			}

			//	----------------------------------------
			//	Handle extra urls
			//	----------------------------------------

			if ( $row[ $prefix . 'avatar_filename' ] != '' )
			{
				$row[ $prefix . 'avatar_url']	= $avatar_url . $row[ $prefix . 'avatar_filename' ];
			}
			else
			{
				$row[ $prefix . 'avatar_url']	= '';
			}

			if ( $row[ $prefix . 'photo_filename' ] != '' )
			{
				$row[ $prefix . 'photo_url']	= $photo_url . $row[ $prefix . 'photo_filename' ];
			}
			else
			{
				$row[ $prefix . 'photo_url']	= '';
			}

			if ( $row[ $prefix . 'signature_image_filename' ] != '' )
			{
				$row[ $prefix . 'signature_image_url']	= $sig_img_url .
															$row[ $prefix . 'signature_image_filename' ];
			}
			else
			{
				$row[ $prefix . 'signature_image_url']	= '';
			}

			//	----------------------------------------
			//	Create a birthday timestamp
			//	----------------------------------------

			$row[ $prefix . 'birthday' ]	= mktime(
				12,
				0,
				0,
				$row[ $prefix . 'bday_m'],
				$row[ $prefix . 'bday_d'],
				$row[ $prefix . 'bday_y']
			);

			//	----------------------------------------
			//	Parse conditionals
			//	----------------------------------------

			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $row );
			$tagdata	= $this->_parse_switch( $tagdata );

			//	----------------------------------------
			//	Parse dates
			//	----------------------------------------

			$dates	= array(
				'birthday',
				'entry_date',
				'group_join_date',
				'join_date',
				'last_bulletin_date',
				'last_visit',
				'last_activity',
				'last_entry_date',
				'last_rating_date',
				'last_comment_date',
				'last_forum_post_date',
				'last_email_date'
			);

			foreach ( $dates as $val )
			{
				$val	= $prefix.$val;

				if ( strpos( $tagdata, LD.$val ) !== FALSE AND
					 isset( $row[$val] ) === TRUE AND
					 preg_match_all(
						"/".LD.$val."\s+format=([\"'])([^\\1]*?)\\1".RD."/s",
						$tagdata,
						$matches
					 )
				   )
				{
					for($i = 0, $s = count($matches[2]); $i < $s; $i++)
					{
						$tagdata	= str_replace(
							$matches[0][$i],
							$this->_parse_date( $matches[2][$i], $row[$val] ),
							$tagdata
						);
					}
				}
			}

			//	----------------------------------------
			//	Parse bio field
			//	----------------------------------------

			if ( strpos( $tagdata, LD . $prefix . 'bio' . RD ) !== FALSE AND
				 isset( $row[$prefix . 'bio'] ) === TRUE )
			{
				$tagdata = ee()->TMPL->swap_var_single(
					$prefix . 'bio',
					$this->TYPE->parse_type(
						$row[$prefix.'bio'],
						array(
							'text_format'   => 'xhtml',
							'html_format'   => 'safe',
							'auto_links'    => 'n',
							'allow_img_url' => 'n'
						)
					),
					$tagdata
				);
			}

			//	----------------------------------------
			//	Parse main variables
			//	----------------------------------------

			foreach ( $row as $key => $val )
			{
				if ( strpos( $tagdata, LD . $key . RD ) === FALSE ) continue;
				$tagdata	= str_replace( LD . $key . RD, $val, $tagdata );
			}

			//	----------------------------------------
			//	Parse custom member vars
			//	----------------------------------------

			if ( count( $this->_mfields() ) > 0 AND $disable_member_fields === FALSE )
			{
				foreach ( $this->mfields as $key => $val )
				{
					//	----------------------------------------
					//	Conditionals
					//	----------------------------------------
					//	This is going to be a slow way to parse
					//  custom member field conditionals. There's
					//	a better way somewhere.
					//	----------------------------------------

					$cond[ $prefix.$val['name'] ]	= $row['m_field_id_' . $val['id']];
					$tagdata						= ee()->functions->prep_conditionals( $tagdata, $cond );

					//	----------------------------------------
					//	Parse select
					//	----------------------------------------

					if ( strpos( $tagdata, LD . "select_" . $key . RD ) !== FALSE AND
						 preg_match(
							"/" . LD . "select_" . $key . RD . "(.*?)" . LD . preg_quote(T_SLASH, '/') . "select_" . $key . RD . "/s",
							$tagdata,
							$match
						 )
						)
					{
						$data		= ee()->TMPL->fetch_data_between_var_pairs( $tagdata, 'select_' . $key );

						$tagdata	= preg_replace(
							"/" . LD . 'select_' . $key . RD . "(.*?)" . LD . preg_quote(T_SLASH, '/') . 'select_' . $key . RD . "/s",
							$this->_parse_select( $key, $row, $data ),
							$tagdata
						);
					}

					//	----------------------------------------
					//	Parse abbreviated
					//	----------------------------------------

					if ( strpos( $tagdata, LD . $prefix . 'abbr_' . $key . RD ) !== FALSE )
					{
						$tagdata = ee()->TMPL->swap_var_single(
							$prefix . 'abbr_' . $key,
							$this->TYPE->parse_type(
								substr( $row['m_field_id_'.$val['id']], 0, 1 ).'.',
								array(
									'text_format'   => $this->mfields[$key]['format'],
									'html_format'   => 'safe',
									'auto_links'    => 'n',
									'allow_img_url' => 'n'
								)
							),
							$tagdata
						);
					}

					//	----------------------------------------
					//	Parse singles
					//	----------------------------------------

					if ( strpos( $tagdata, LD . $prefix . $key . RD ) !== FALSE )
					{
						$tagdata = ee()->TMPL->swap_var_single(
							$prefix.$key,
							$this->TYPE->parse_type(
								$row['m_field_id_'.$val['id']],
								array(
									'text_format'   => $this->mfields[$key]['format'],
									'html_format'   => 'safe',
									'auto_links'    => 'n',
									'allow_img_url' => 'n'
								)
							),
							$tagdata
						);
					}
				}
			}

			$r	.= $tagdata;
		}

		$r	= ( empty( $this->params['backspace'] ) === FALSE AND
				is_numeric( $this->params['backspace'] ) === TRUE ) ?
					substr( $r, 0, - ( $this->params['backspace'] ) ) : $r;

		if ( $r == '' )
		{
			return $tdata;
		}

		return $r;
	}

	// End parse member data





	// --------------------------------------------------------------------

	/**
	 * Parse pagination
	 *
	 * @access	private
	 * @return	string
	 */

	function _parse_pagination( $return = '' )
	{
		return $this->parse_pagination(array(
			'tagdata' 	=> $return,
			'prefix'	=> 'friends'
		));
	}
	// End parse pagination


	// --------------------------------------------------------------------

	/**
	 * Parse select
	 *
	 * Parse a select field
	 *
	 * @access		private
	 * @return		string
	 */

	function _parse_select( $key = '', $row = array(), $data = '' )
	{
		//	----------------------------------------
		//	Fail?
		//	----------------------------------------

		if ( $key == '' OR $data == '' )
		{
			return '';
		}

		//	----------------------------------------
		//	Are there list items present?
		//	----------------------------------------

		if ( ! isset( $this->mfields[$key]['list'] ) OR $this->mfields[$key]['list'] == '' )
		{
			return '';
		}

		//	----------------------------------------
		//	Do we have a value?
		//	----------------------------------------

		if ( isset( $row['m_field_id_'.$this->mfields[$key]['id']] ) )
		{
			$value	= $row['m_field_id_'.$this->mfields[$key]['id']];
		}
		else
		{
			$value	= '';
		}

		//	----------------------------------------
		//	Create an array from value
		//	----------------------------------------

		$arr	= preg_split( "/\r|\n/", $value );

		//	----------------------------------------
		//	Loop
		//	----------------------------------------

		$return	= '';

		foreach ( preg_split( "/\r|\n/", $this->mfields[$key]['list'] ) as $val )
		{
			$out		= $data;
			$selected	= ( in_array( $val, $arr ) ) ? 'selected="selected"': '';
			$checked	= ( in_array( $val, $arr ) ) ? 'checked="checked"': '';
			$out		= str_replace( LD . "selected" . RD, $selected, $out );
			$out		= str_replace( LD . "checked" . RD, $checked, $out );
			$out		= str_replace( LD . "value" . RD, $val, $out );
			$return		.= trim( $out )."\n";
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $return;
	}

	// End parse select


	// --------------------------------------------------------------------

	/**
	 * Parse switch
	 *
	 * Parses the friends_switch variable so that admins can create zebra stripe UI's.
	 *
	 * @access	private
	 * @return	str
	 */

	function _parse_switch( $tagdata = '' )
	{
		if ( $tagdata == '' ) return '';

		//	----------------------------------------
		//	Parse Switch
		//	----------------------------------------

		if ( preg_match( "/".LD."(friends_switch\s*=(.+?))".RD."/is", $tagdata, $match ) > 0 )
		{
			$val	= $this->cycle( explode( '|', str_replace( array( '"', "'" ), '', $match['2'] ) ) );

			$tagdata = str_replace( $match['0'], $val, $tagdata );
		}

		return $tagdata;
	}

	// End parse date


	// --------------------------------------------------------------------

	/**
	 * Preferences
	 *
	 * @access		public
	 * @return		string
	 */

	function preferences()
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return;
		}

		//	----------------------------------------
		//	Exclude
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND
			 ee()->TMPL->fetch_param('exclude') != '' )
		{
			$exclude	= explode( "|", ee()->TMPL->fetch_param('exclude') );

			$this->group_prefs	= array_flip( $this->group_prefs );

			foreach( $exclude as $val )
			{
				unset( $this->group_prefs[$val] );
			}

			$this->group_prefs	= array_flip( $this->group_prefs );
		}

		//	----------------------------------------
		//	Grab prefs
		//	----------------------------------------

		$sql = "SELECT 	" . implode( ",", array_flip( $this->group_prefs ) ) . "
				FROM 	exp_members
				WHERE 	member_id = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'";

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Loop and parse
		//	----------------------------------------

		$r	= '';

		foreach ( $query->row_array() as $key => $val )
		{
			$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, array( 'preference_value' => $val ) );

			$tagdata	= str_replace(  array( LD.'preference_name'.RD, LD.'preference_title'.RD ),
										array( $key, lang( $key ) ),
										$tagdata );

			$r			.= $tagdata;
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $r;
	}

	// End preferences


	// --------------------------------------------------------------------

	/**
	 * Prep message
	 *
	 * @access		private
	 * @return		string
	 */

	function _prep_message( $message = '' )
	{
		$content	= '';

		if ( $message != '' )
		{
			return $message;
		}
		elseif ( count( $this->message ) == 1 )
		{
			return $this->message['0'];
		}
		elseif ( count( $this->message ) > 1 )
		{
			$this->message	= array_unique( $this->message );
			$content	= implode( "\r\n", $this->message );
		}

		return $content;
	}

	// End prep message


	// --------------------------------------------------------------------

	/**
	 * Prep pagination
	 *
	 * This is a temporary routine. We'll replace with pagination from Hermes when it's ready.
	 *
	 * @access	private
	 * @return	string
	 */

	function _prep_pagination( $sql, $url_suffix = '' )
	{
		// ----------------------------------------
		//	Limit
		// ----------------------------------------

		if ( ee()->TMPL->fetch_param('limit') !== FALSE AND
			 $this->_numeric( ee()->TMPL->fetch_param('limit') ) !== FALSE )
		{
			$this->limit	= ee()->TMPL->fetch_param('limit');
		}

		$offset = ( ! ee()->TMPL->fetch_param('offset') OR
					! $this->_numeric(ee()->TMPL->fetch_param('offset'))) ?
						'0' : ee()->TMPL->fetch_param('offset');

		$query = ee()->db->query($sql);

		if ($query->num_rows() == 0)
		{
			$this->current_page	= 1;
			$this->total_pages	= 1;
			return $sql;
		}

		$total_results	= $query->num_rows();

		//some functions remove pagination before parsing
		if (isset($this->paginate_match) AND isset($this->paginate_match[0]))
		{
			ee()->TMPL->tagdata .= $this->paginate_match[0];
		}

		// We only want to engage full pagination if we have a
		// paginate block in the tagdata
		if ( strpos( ee()->TMPL->tagdata, 'paginate' . RD ) !== FALSE )
		{
			//get pagination info
			$pagination_data = $this->universal_pagination(array(
				'sql'					=> $sql,
				'url_suffix' 			=> $url_suffix,
				'total_results'			=> $total_results,
				'tagdata'				=> ee()->TMPL->tagdata,
				'limit'					=> $this->limit,
				'uri_string'			=> ee()->uri->uri_string,
				'paginate_prefix'		=> 'friends_',
				'offset'				=> $offset,
				'auto_paginate'			=> TRUE
			));

			//if we paginated, sort the data
			if ($pagination_data['paginate'] === TRUE)
			{
				ee()->TMPL->tagdata		= $pagination_data['tagdata'];
			}

			return $pagination_data['sql'];
		}
		else
		{
			$sql .= ' LIMIT ' . ee()->db->escape_str($offset) . ',' . ee()->db->escape_str($this->limit);

			return $sql;
		}
	}

	//	End prep pagination


	// --------------------------------------------------------------------

	/**
	 * Prep return
	 *
	 * @access		private
	 * @return		string
	 */

	function _prep_return( $return = '' )
	{
		if ( ee()->input->get_post('return') !== FALSE AND ee()->input->get_post('return') != '' )
		{
			$return	= ee()->input->get_post('return');
		}
		elseif ( ee()->input->get_post('RET') !== FALSE AND ee()->input->get_post('RET') != '' )
		{
			$return	= ee()->input->get_post('RET');
		}
		else
		{
			$return = ee()->functions->fetch_current_uri();
		}

		if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $return, $match ) )
		{
			$return	= ee()->functions->create_url( $match['1'] );
		}
		elseif ( stristr( $return, "http://" ) === FALSE && stristr( $return, "https://" ) === FALSE )
		{
			$return	= ee()->functions->create_url( $return );
		}

		return $return;
	}

	// End prep return



	// --------------------------------------------------------------------

	/**
	 * Reciprocal
	 *
	 * This updates the flag for a member that indicates where they have
	 * a reciprocal friendship with someone. We like to update statistic
	 * type stuff like this on the input side rather than in an output
	 * function because we want output functions to be fast. People normally
	 * expect form submissions and the like to be a little slower
	 * than regular page loads.
	 *
	 * Completely re-written feb 11, 2011.
	 * this now runs each member one at a time through _reciprocal_single
	 * and makes sure that the logged in member is in the array
	 *
	 * @access		private
	 * @return		boolean
	 */

	public function _reciprocal( $members = array() )
	{
		//--------------------------------------------
		//	make sure its an array or something
		//--------------------------------------------

		if ( ! is_array($members))
		{
			//is it a number?
			if (is_numeric($members))
			{
				$members = array($members);
			}
			//let this pass through and the logged in member can do something
			else
			{
				$members = array();
			}
		}

		if (ee()->session->userdata('member_id') != 0 AND
			! in_array(ee()->session->userdata('member_id'), $members))
		{
			$members[] = ee()->session->userdata('member_id');
		}

		//send to instance so we can do some checks
		$this->members_to_update = array_unique($members);

		//--------------------------------------------
		//	one last check
		//--------------------------------------------

		if ( empty($this->members_to_update) ) return FALSE;

		//--------------------------------------------
		//	send it all to the rechecker
		//--------------------------------------------

		foreach ($this->members_to_update as $member)
		{
			if ( is_numeric($member) )
			{
				$this->_reciprocal_single($member);
			}
		}

		return TRUE;
	}
	//END reciprocal


	// --------------------------------------------------------------------

	/**
	 * Reciprocal single
	 *
	 * Completely re-written feb 11, 2011.
	 * this gets called from _reciprocal for each member in the passed array
	 *
	 * @access		private
	 * @return		boolean
	 */

	private function _reciprocal_single($member)
	{
		//--------------------------------------------
		//	find everything to do with member
		//--------------------------------------------

		$sql = "SELECT 	member_id, friend_id, entry_id
				FROM	exp_friends
				WHERE	block = 'n'
				AND 	(member_id = '" . ee()->db->escape_str($member) ."'
						 OR friend_id = '" . ee()->db->escape_str($member) ."')";


		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}

		//--------------------------------------------
		//	split data into members friends, and people
		//	who are friends with the member
		//--------------------------------------------

		$members_friends 	= array();
		$friends_of_member	= array();

		foreach ($query->result_array() as $row)
		{
			//my friends
			if ($row['member_id'] == $member)
			{
				$members_friends[$row['friend_id']] = $row;
			}
			//members who friended me
			else
			{
				$friends_of_member[$row['member_id']] = $row;
			}
		}

		//--------------------------------------------
		//	check for reciprocal and seperate
		//--------------------------------------------

		$mutual_friends 	= array();
		$set_to_yes			= array();
		$set_to_no			= array();

		foreach ($members_friends as $member_id => $data)
		{
			//are we friends with each other?
			if (isset($friends_of_member[$member_id]))
			{
				$mutual_friends[] = $member_id;

				//lets set these to reciprocol = yes for both of us
				$set_to_yes[] = $friends_of_member[$member_id]['entry_id'];
				$set_to_yes[] = $data['entry_id'];

				//remove from each list so we have proper leftovers
				unset($members_friends[$member_id]);
				unset($friends_of_member[$member_id]);
			}
		}

		//magic beans
		$total_reciprocol = count($mutual_friends);

		//--------------------------------------------
		//	process leftovers as not friends
		//--------------------------------------------

		foreach ($members_friends as $member_id => $data)
		{
			$set_to_no[] = $data['entry_id'];
		}

		foreach ($friends_of_member as $member_id => $data)
		{
			$set_to_no[] = $data['entry_id'];
		}

		//we dont want to do any double work, though this shouldn't be neccessary
		$set_to_yes = array_unique($set_to_yes);
		$set_to_no 	= array_unique($set_to_no);

		//--------------------------------------------
		//	set new data
		//--------------------------------------------

		//total friends
		ee()->db->query(
			ee()->db->update_string(
				'exp_members',
				array('total_reciprocal_friends' 	=> $total_reciprocol),
				array('member_id' 					=> $member)
			)
		);

		//we not friends :(
		if ( ! empty($set_to_no))
		{
			ee()->db->query(
				ee()->db->update_string(
					'exp_friends',
					array('reciprocal' 	=> 'n'),
					"entry_id IN (" . implode(",", $set_to_no) . ")"
				)
			);
		}

		//we be friends :D
		//as an extra extra extra precaution, run this last
		//just in case there were any duplicates in the no section
		if ( ! empty($set_to_yes))
		{
			ee()->db->query(
				ee()->db->update_string(
					'exp_friends',
					array('reciprocal' 	=> 'y'),
					"entry_id IN (" . implode(",", $set_to_yes) . ")"
				)
			);
		}

		//not even sure this is neccessary now
		return TRUE;
	}
	//END _reciprocal_single


	// --------------------------------------------------------------------

	/**
	 * Recursive referral check
	 *
	 * This is a recursive function.
	 * It adds a row in the DB for every person responsible
	 * for bringing the primary member to the site.
	 *
	 * @access		private
	 * @return		string
	 */

	function _recursive_referral_check ( $primary_id, $referrer_id = 0 )
	{
		//	----------------------------------------
		//	If referrer_id is 0 we're at the
		//	beginning, use primary id rather than
		//	referrer id.
		//	----------------------------------------

		$friend_id	= ( $referrer_id == 0 ) ? $primary_id : $referrer_id;

		$query	= ee()->db->query(
			"SELECT referrer_id
			 FROM 	exp_friends
			 WHERE 	referrer_id != '0'
			 AND 	friend_id = '" . ee()->db->escape_str( $friend_id ) . "'
			 LIMIT 	1"
		);

		//	----------------------------------------
		//	Somehow if no one referred this person, we
		//	shouldn't be here in the first place. Bail
		//	out.
		//	----------------------------------------

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}
		else
		{
			$referrer_id	= $query->row('referrer_id');
		}

		//	----------------------------------------
		//	Have we already been here?
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT COUNT(*) AS count
			 FROM 	exp_friends_referrals
			 WHERE 	member_id = '" . ee()->db->escape_str( $primary_id ) . "'
			 AND 	referrer_id = '" . $referrer_id . "'"
		);

		if ( $query->row('count') > 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Insert a referral
		//	----------------------------------------

		ee()->db->query(
			ee()->db->insert_string(
				'exp_friends_referrals',
				array(
					'member_id' 	=> $primary_id,
					'referrer_id' 	=> $referrer_id,
					'site_id'		=> $this->clean_site_id
				)
			)
		);

		//	----------------------------------------
		//	Go again
		//	----------------------------------------

		$this->_recursive_referral_check( $primary_id, $referrer_id );
	}

	// End recursive referral check


	// --------------------------------------------------------------------

	/**
	 * Referral check
	 *
	 * This function processes referrals. It checks the DB and to determine if any friend invites
	 * of non-members should be processed. It runs them and sends notifications as appropriate.
	 *
	 * @access		public
	 * @return		string
	 */

	function referral_check ()
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata('member_id') == '0' )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Set interval
		//	----------------------------------------

		$interval	= 10;

		if ( ee()->TMPL->fetch_param( 'interval' ) !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param( 'interval' ) ) === TRUE )
		{
			$interval	= ee()->TMPL->fetch_param( 'interval' );
		}

		//	----------------------------------------
		//	Is it time to do another check on the inviter side?
		//	----------------------------------------

		if ( $this->data->time_to_check_referrals_for_inviter(
				ee()->config->item('site_id'),
				ee()->session->userdata('member_id'),
				$interval ) !== FALSE )
		{
			//	----------------------------------------
			//	For the currently logged in member, have any of their invitations joined the site?
			//	----------------------------------------

			$sql	= "SELECT 		m.member_id, m.email, f.entry_id, f.group_id
					   FROM 		exp_friends f
					   LEFT JOIN 	exp_members m ON f.email = m.email
					   WHERE 		f.site_id = {$this->clean_site_id}
					   AND 			f.friend_id = 0
					   AND 			f.referrer_id != 0
					   AND 			m.member_id != ''
					   AND 			f.email != ''
					   AND 			f.member_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "
					   AND 			f.referrer_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') );

			$query	= ee()->db->query( $sql );

			//	----------------------------------------
			//	Update the friend_id in the friends records and send email notifications if necessary
			//	----------------------------------------

			$groups	= array();

			foreach ( $query->result_array() as $row )
			{
				//	----------------------------------------
				//	DB update
				//	----------------------------------------

				ee()->db->query(
					ee()->db->update_string(
						'exp_friends',
						array(
							'friend_id'	=> $row['member_id'],
							'edit_date'	=> ee()->localize->now
						),
						array(
							'entry_id'	=> $row['entry_id']
						)
					)
				);

				//	----------------------------------------
				//	Assemble array of group invitations so that we can invite multiple members to the same group effeciently
				//	----------------------------------------

				if ( $row['group_id'] !== 0 )
				{
					$groups[ $row['group_id'] ][ $row['member_id'] ]	= $row['member_id'];
				}

				//	----------------------------------------
				//	Send friend request notifications
				//	----------------------------------------
			}

			//	----------------------------------------
			//	Record group invitation and send notifications if appropriate
			//	----------------------------------------

			foreach ( $groups as $group_id => $member_ids )
			{
				$this->_add_friends_to_group( $member_ids, array(), ee()->session->userdata('member_id'), $group_id );
			}

			//	----------------------------------------
			//	Record the fact that we're done
			//	----------------------------------------

			ee()->db->query(
				"INSERT INTO `exp_friends_automations` (
					`action`,
					`entry_date`,
					`site_id`,
					`member_id`
				)
				VALUES (
					'inviter_referral_check',
					UNIX_TIMESTAMP(),
					" . ee()->db->escape_str( ee()->config->item('site_id') ) . ",
					" . ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
				)"
			);
		}

		//	----------------------------------------
		//	Have we done a check on the invitee side yet? We need only do it once.
		//	----------------------------------------

		if ( $this->data->time_to_check_referrals_for_invitee(
				ee()->config->item('site_id'),
				ee()->session->userdata('member_id') ) !== FALSE
			)
		{
			//	----------------------------------------
			//	Check to see if the currently logged in member was invited into the site and run those routines. I wanted to separate these two routines as I think that they may get confusing in the future.
			//	----------------------------------------

			$sql = "SELECT 		m.member_id, m.email, f.entry_id, f.group_id, f.referrer_id
					FROM 		exp_friends f
					LEFT JOIN 	exp_members m ON f.email = m.email
					WHERE 		f.site_id = {$this->clean_site_id}
					AND 		f.friend_id = 0
					AND 		f.referrer_id != 0
					AND 		m.member_id != ''
					AND 		f.email = '" . ee()->db->escape_str( ee()->session->userdata('email') ) . "'";

			$query	= ee()->db->query( $sql );

			//	----------------------------------------
			//	Update the friend_id in the friends records and send email notifications if necessary
			//	----------------------------------------

			$groups	= array();

			foreach ( $query->result_array() as $row )
			{
				//	----------------------------------------
				//	DB update
				//	----------------------------------------

				ee()->db->query(
					ee()->db->update_string(
						'exp_friends',
						array(
							'friend_id'	=> ee()->session->userdata('member_id'),
							'edit_date'	=> ee()->localize->now
						),
						array(
							'entry_id'	=> $row['entry_id']
						)
					)
				);

				//	----------------------------------------
				//	Assemble array of group invitations so that we can invite
				//  multiple members to the same group efficiently
				//	----------------------------------------

				if ( $row['group_id'] != 0 )
				{
					$groups[ $row['group_id'] ]	= $row['referrer_id'];
				}

				//	----------------------------------------
				//	Send friend request notifications
				//	----------------------------------------
			}

			//	----------------------------------------
			//	Record group invitation and send notifications if appropriate
			//	----------------------------------------

			foreach ( $groups as $group_id => $owner_id )
			{
				$this->_add_friends_to_group( array(
					 ee()->session->userdata('member_id') ),
					array(),
					$owner_id,
					$group_id
				);
			}

			//	----------------------------------------
			//	Record the fact that we're done
			//	----------------------------------------

			ee()->db->query(
				"INSERT INTO `exp_friends_automations` (
					`action`,
					`entry_date`,
					`site_id`,
					`member_id`
				)
				VALUES (
					'invitee_referral_check',
					UNIX_TIMESTAMP(),
					" . ee()->db->escape_str( ee()->config->item('site_id') ) . ",
					" . ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
				)"
			);

			//	----------------------------------------
			//	Count exponential referrals
			//	----------------------------------------
			//	Only one referring friend gets the
			//	exponential credit. Let's not be crazy.
			//	----------------------------------------

			if ( ! $this->check_no(ee()->TMPL->fetch_param( 'process_referrals' )) )
			{
				$this->_recursive_referral_check( ee()->session->userdata('member_id') );
			}
		}
	}

	// End referral check


	// --------------------------------------------------------------------

	/**
	 * Referral count
	 *
	 * List one's referral count.
	 *
	 * @access		public
	 * @return		string
	 */

	function referral_count ()
	{
		//	----------------------------------------
		//	Tagdata
		//	----------------------------------------

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Member id
		//	----------------------------------------

		if ( $this->_member_id() === FALSE )
		{
			return str_replace( LD.'friends_referral_count'.RD, '0', $tagdata );
		}

		//	----------------------------------------
		//	Grab it
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT COUNT(*) AS friends_referral_count
			 FROM 	exp_friends_referrals
			 WHERE 	referrer_id = '" . ee()->db->escape_str( $this->member_id ) . "'"
		);

		//	----------------------------------------
		//	Conditionals
		//	----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals(
			$tagdata,
			array( 'friends_referral_count' => $query->row('friends_referral_count') )
		);

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		return str_replace( LD . 'friends_referral_count' . RD, $query->row('friends_referral_count'), $tagdata );
	}

	// End referral count


	// --------------------------------------------------------------------

	/**
	 * Search
	 *
	 * @access		public
	 * @return		string
	 */

	function search()
	{
		//	----------------------------------------
		//	SQL
		//	----------------------------------------

		$sql	= "SELECT 		m.*, md.*
				   FROM 		exp_members m
				   LEFT JOIN 	exp_member_data md
				   ON 			md.member_id = m.member_id
				   WHERE 		m.friends_opt_out != 'y'";

		//	----------------------------------------
		//	Member id
		//	----------------------------------------

		if ( $this->_member_id() === TRUE )
		{
			$sql	.= " AND m.member_id = '" . ee()->db->escape_str( $this->member_id ) . "'";
		}

		//	----------------------------------------
		//	Friend id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('friend_id') )
		{
			if ( ee()->TMPL->fetch_param('friend_id') == 'CURRENT_USER' )
			{
				$sql	.= ee()->functions->sql_andor_string( ee()->session->userdata['member_id'], 'f.friend_id' );
			}
			else
			{
				$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('friend_id'), 'f.friend_id' );
			}
		}

		//	----------------------------------------
		//	Group id
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param( 'member_group_id' ) )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param( 'member_group_id' ), 'm.group_id' );
		}

		//	----------------------------------------
		//	Days / hours
		//	----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('days') ) )
		{
			$days	= ee()->localize->now - ( ee()->TMPL->fetch_param('days') + 86400 );

			$sql	.= " AND m.join_date >= '" . ee()->db->escape_str( $days ) . "'";
		}
		elseif ( is_numeric( ee()->TMPL->fetch_param('hours') ) )
		{
			$hours	= ee()->localize->now - ( ee()->TMPL->fetch_param('hours') + 3600 );

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
		//	Keywords?
		//	----------------------------------------

		if ( ee()->input->get_post('keywords') !== FALSE AND
			 ee()->input->get_post('keywords') != '' )
		{
			$sql		.= " AND (";

			$keywords	= ee()->input->get_post('keywords');

			$sqls		= array();

			if ( ee()->TMPL->fetch_param('custom_fields') !== FALSE AND
				 ee()->TMPL->fetch_param('custom_fields') != '' )
			{
				$custom_fields	= preg_split( "/,|\|/", ee()->TMPL->fetch_param('custom_fields') );

				$this->_mfields();

				foreach ( $custom_fields as $field )
				{
					if ( isset( $this->mfields[$field] ) === TRUE )
					{
						$sqls[]	= "`md.m_field_id_".$this->mfields[$field]['id']."` LIKE '%" .
									ee()->db->escape_str( $keywords ) . "%'";
					}
				}
			}

			$sqls[]	= "m.screen_name LIKE '%" . ee()->db->escape_str( $keywords ) . "%'";

			$sql	.= implode( " OR ", $sqls );

			$sql	.= ")";
		}

		//	----------------------------------------
		//	Exclude?
		//	----------------------------------------

		$exclude	= '';

		if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
		{
			$exclude	.= str_replace( ",", "|", ee()->TMPL->fetch_param('exclude') );
		}

		if ( ee()->input->get_post('exclude') !== FALSE AND ee()->input->get_post('exclude') != '' )
		{
			$exclude	.= str_replace( ",", "|", ee()->input->get_post('exclude') );
		}

		$exclude	= explode( "|", $exclude );

		foreach ( $exclude as $key => $val )
		{
			if ( $this->_numeric( $val ) === FALSE )
			{
				unset( $exclude[$key] );
			}
		}

		$sql	.= " AND m.member_id NOT IN ('".implode( "','", $exclude )."')";

		//	----------------------------------------
		//	Group by
		//	----------------------------------------

		$sql	.= " GROUP BY m.member_id";

		//	----------------------------------------
		//	Order by
		//	----------------------------------------

		$is_random = FALSE;

		if ( ee()->TMPL->fetch_param('orderby') )
		{
			if( ee()->TMPL->fetch_param('orderby') == 'random' )
			{
				$sql	.= " ORDER BY RAND() ";
				$is_random = TRUE;
			}
			else
			{
				$sql	.= " ORDER BY ".str_replace( "|", ",", ee()->db->escape_str( ee()->TMPL->fetch_param('orderby')  ) );
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		//	----------------------------------------
		//	Sort
		//	----------------------------------------

		if( !$is_random )
		{
			if ( ee()->TMPL->fetch_param('sort') != 'desc' )
			{
				$sql	.= " ASC";
			}
			else
			{
				$sql	.= " DESC";
			}
		}

		//	----------------------------------------
		//	Limit
		//	----------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('limit') ) )
		{
			$sql	.= " LIMIT " . ee()->db->escape_str( ee()->TMPL->fetch_param('limit') );
		}

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT member_id, friend_id, private, block, reciprocal, entry_date
			 FROM 	exp_friends
			 WHERE 	member_id = '" . ee()->db->escape_str( ee()->session->userdata['member_id'] ) . "'"
			);

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$this->friends[ $row['member_id'] ]	= $row;
			}
		}

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$this->query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		if ( $this->_parse() === FALSE )
		{
			return $this->no_results('friends');
		}
		else
		{
			return $this->return_data;
		}
	}

	// End search


	// --------------------------------------------------------------------

	/**
	 * Security
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _security()
	{
		// ----------------------------------------
		//	Is the user banned?
		// ----------------------------------------

		if ( ee()->session->userdata['is_banned'] === TRUE )
		{
			return $this->_fetch_error( lang('not_authorized') );
		}

		// ----------------------------------------
		//	Is the IP address and User Agent required?
		// ----------------------------------------

		if ( ee()->config->item('require_ip_for_posting') == 'y' )
		{
			if ( ( ee()->input->ip_address() == '0.0.0.0' OR
				   ee()->session->userdata['user_agent'] == '' ) AND
				   ee()->session->userdata['group_id'] != 1 )
			{
				return $this->_fetch_error( lang('not_authorized') );
			}
		}

		// ----------------------------------------
		//	Is the nation of the user banned?
		// ----------------------------------------

		if ($this->check_yes(ee()->config->item('ip2nation')) AND
			ee()->db->table_exists('ip2nation'))
		{
			ee()->session->nation_ban_check();
		}

		// ----------------------------------------
		//	Blacklist / Whitelist Check
		// ----------------------------------------

		if ( $this->check_yes(ee()->blacklist->blacklisted) AND
			 $this->check_no(ee()->blacklist->whitelisted) )
		{
			return $this->_fetch_error( lang('not_authorized') );
		}

		// ----------------------------------------
		//	Return
		// ----------------------------------------

		return TRUE;
	}

	// End security



	// --------------------------------------------------------------------

	/**
	 * Show Profile Comment Wall
	 *
	 * @access		public
	 * @return		string
	 */

	function profile_wall()
	{
		//$member_id = $this->member_id;

		$friend_id	= 0;

		if ( ee()->TMPL->fetch_param( 'friend_id' ) !== FALSE AND
			 ( is_numeric( ee()->TMPL->fetch_param( 'friend_id' ) ) === TRUE
				OR ee()->TMPL->fetch_param('friend_id') === 'CURRENT_USER' ) )
		{
			$friend_id	= ee()->TMPL->fetch_param( 'friend_id' );

			//current user, and logged in?
			if ($friend_id === 'CURRENT_USER')
			{
				$friend_id = ee()->session->userdata['member_id'];
			}
		}
		elseif ( ee()->TMPL->fetch_param( 'friend_username' ) !== FALSE AND
				 ee()->TMPL->fetch_param( 'friend_username' ) != '' )
		{
			$friend_id	= $this->data->get_member_id_from_username(
				ee()->config->item( 'site_id' ),
				ee()->TMPL->fetch_param( 'friend_username' )
			);
		}
		elseif ( preg_match( "#/"."friend_username"."/(\w+)/?#", ee()->uri->uri_string, $match ) AND
				 $this->dynamic === TRUE )
		{
			$friend_id	= $this->data->get_member_id_from_username( ee()->config->item( 'site_id' ), $match[1] );
		}

		if ($friend_id === 0)
		{
			return $this->no_results();
		}

		$orderby			= ( ! in_array(ee()->TMPL->fetch_param('orderby'), array(FALSE, '')) AND
								$this->column_exists(ee()->TMPL->fetch_param('orderby'),
								 'exp_friends_profile_comments')) ?
										ee()->TMPL->fetch_param('orderby') : 'entry_date';

		$sort				= (strtolower(ee()->TMPL->fetch_param('orderby')) === 'asc') ? 'ASC' : 'DESC';

		//	----------------------------------------
		//	build sql
		//	----------------------------------------

		$sql = "SELECT 	fpc.*
				FROM 	exp_friends_profile_comments AS fpc
				WHERE 	friend_id = '" . ee()->db->escape_str($friend_id) . "'";


		if (	 ee()->TMPL->fetch_param( 'author_id' ) !== FALSE AND
				 is_numeric( ee()->TMPL->fetch_param( 'author_id' ) ) === TRUE)
		{
			$sql .= " AND author_id = '" . ee()->db->escape_str(ee()->TMPL->fetch_param( 'author_id' )) . "'";
		}

		$sql .=	" AND site_id = '" . ee()->db->escape_str( ee()->config->item('site_id') ) . "'";

		if ($orderby !== FALSE)
		{
			$sql .= ' ORDER BY ' . ee()->db->escape_str($orderby) . " $sort";
		}

		// ----------------------------------------
		//	Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		$tagdata = ee()->TMPL->tagdata;

		//	----------------------------------------
		//	parse template data
		//	----------------------------------------

		$return_data = '';

		//get data
		$query = ee()->db->query($sql);

		//just an extra check
		if ($query->num_rows() == 0)
		{
			return $this->no_results();
		}

		//extra vars for parsing
		$total_count 	= $query->num_rows();
		$count 			= 0;

		$author_ids 	= array();

		foreach ($query->result_array() as $row)
		{
			$author_ids[] = $row['author_id'];
		}

		$screen_names	= array();
		$usernames		= array();

		$mquery = ee()->db->query(
			"SELECT screen_name, member_id, username
			 FROM 	exp_members
			 WHERE 	member_id
			 IN 	('".implode("','", array_unique($author_ids))."')"
		);

		foreach($mquery->result_array() as $row)
		{
			$screen_names[$row['member_id']] 	= $row['screen_name'];
			$usernames[$row['member_id']] 		= $row['username'];
		}

		foreach ($query->result_array() as $row)
		{
			// Our member may have been deleted
			if( ! isset( $screen_names[ $row['author_id'] ] ) )
			{
				$row['screen_name'] = lang('screen_name_deleted');
				$row['username'] 	= lang('username_deleted');
			}
			else
			{
				$row['screen_name'] = $screen_names[$row['author_id']];
				$row['username'] 	= $usernames[$row['author_id']];
			}

			foreach($row as $k => $v)
			{
				$row['friends_' . $k] = $v;
			}

			$row['friends_member_id'] = $row['author_id'];

			$row['member_id'] = $row['CURRENT_USER'] = ee()->session->userdata['member_id'];

			$td = $tagdata;

			//normal goodies :D
			$td = ee()->functions->prep_conditionals(
				$td,
				array(
					'total_count' 	=> $total_count,
					'count' 		=> ++$count
				)
			);

			//  Parse "single" variables
			foreach (ee()->TMPL->var_single as $key => $val)
			{
				//is this a date key?
				$is_date = FALSE;

				//we store the entire key for full replacement later
				if (stristr($key, 'format'))
				{
					$is_date 		= TRUE;
					$date_format	= $val;
					$full_key		= $key;
					$key 			= trim(substr($key, 0, strpos($key, ' ')));
				}

				if(isset($row[$key]))
				{
					if ($is_date)
					{
						$func = (is_callable(array(ee()->localize, 'format_date'))) ?
								'format_date' :
								'decode_date';
						$row[$key] = ee()->localize->$func($date_format, $row[$key]);

						//have to swap out the real, full key
						$td = ee()->TMPL->swap_var_single($full_key, $row[$key], $td);
					}

					if (stristr($key, 'comment'))
					{
						$row[$key] =  str_replace(
							array('{', '}'),
							array('&#123;', '&#125;'),
							$row[$key]
						);
					}

					//normal prep
					$td = ee()->functions->prep_conditionals($td, array($key => $row[$key]));

					$td = ee()->TMPL->swap_var_single($key, $row[$key], $td);
				}
			}//end foreach var_single

			$return_data .= $td;
		}

		return  $this->_parse_pagination( $return_data );
	}
	//end profile_wall()


	// --------------------------------------------------------------------

	/**
	 * Show Group Comment Wall
	 *
	 * @access		public
	 * @return		string
	 */

	function group_wall()
	{
		//	----------------------------------------
		//	Are we using a template?
		//	----------------------------------------

		$friends_group_id	= ( ! in_array(ee()->TMPL->fetch_param('friends_group_id'), array(FALSE, '')) AND
								is_numeric(ee()->TMPL->fetch_param('friends_group_id'))
							  ) ? ee()->TMPL->fetch_param('friends_group_id') : FALSE;

		$friends_group_name	= ( ! in_array(ee()->TMPL->fetch_param('friends_group_name'), array(FALSE, ''))) ?
								ee()->TMPL->fetch_param('friends_group_name') : FALSE;

		//got to have one of these
		if ( $friends_group_id == FALSE AND $friends_group_name == FALSE )
		{
			return $this->no_results();
		}

		$member_id			= ( ! in_array(ee()->TMPL->fetch_param('member_id'), array(FALSE, '')) AND
								is_numeric(ee()->TMPL->fetch_param('member_id'))
							  ) ? ee()->TMPL->fetch_param('member_id') : FALSE;

		//current user, and logged in?
		if ($member_id === 'CURRENT_USER')
		{
			$member_id = ee()->session->userdata['member_id'];

			if ($member_id === 0)
			{
				$member_id = FALSE;
			}
		}

		$username			= ( ! in_array(ee()->TMPL->fetch_param('username'), array(FALSE, ''))) ?
								ee()->TMPL->fetch_param('username') : FALSE;


		$orderby			= ( ! in_array(ee()->TMPL->fetch_param('orderby'), array(FALSE, '')) AND
								$this->column_exists(ee()->TMPL->fetch_param('orderby'),
								 'exp_friends_group_comments')) ?
										ee()->TMPL->fetch_param('orderby') : 'entry_date';

		$sort				= (strtolower(ee()->TMPL->fetch_param('orderby')) === 'asc') ? 'ASC' : 'DESC';

		//	----------------------------------------
		//	build sql
		//	----------------------------------------

		$sql = 'SELECT 	fgc.*
				FROM 	exp_friends_group_comments as fgc';

		//we have to have one or the other
		if ($friends_group_id !== FALSE)
		{
			$sql .= " WHERE group_id = '" . ee()->db->escape_str($friends_group_id) . "'
					  AND	site_id  = '" . ee()->db->escape_str( ee()->config->item('site_id') ). "'";
		}
		else
		{
			$sql .= " WHERE group_id IN (
						SELECT 	group_id
						FROM	exp_friends_groups
						WHERE	group_name = '" . ee()->db->escape_str($friends_group_name) . "'
						AND		site_id		= '" . ee()->db->escape_str( ee()->config->item('site_id') ) . "'
						LIMIT 	1
					  )";
		}

		//limiting to a user?
		if ($member_id !== FALSE AND $member_id !== 0)
		{
			$sql .= " AND author_id = '" . ee()->db->escape_str($member_id) . "'";
		}
		elseif ($username !== FALSE)
		{
			$sql .= " AND author_id IN (
						SELECT 	member_id
						FROM	exp_members
						WHERE	username = '" . ee()->db->escape_str($username) . "'
						LIMIT 	1
					  )";
		}

		if ($orderby !== FALSE)
		{
			$sql .= ' ORDER BY ' . ee()->db->escape_str($orderby) . " $sort";
		}

		// ----------------------------------------
		//	Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		$tagdata = ee()->TMPL->tagdata;

		//	----------------------------------------
		//	parse template data
		//	----------------------------------------

		$return_data = '';

		//get data
		$query = ee()->db->query($sql);

		//just an extra check
		if ($query->num_rows() == 0)
		{
			return $this->no_results();
		}

		//extra vars for parsing
		$total_count 	= $query->num_rows();
		$count 			= 0;

		$author_ids 	= array();

		foreach ($query->result_array() as $row)
		{
			$author_ids[] = $row['author_id'];
		}

		$screen_names	= array();
		$usernames		= array();

		$mquery = ee()->db->query(
			"SELECT screen_name, member_id, username
			 FROM 	exp_members
			 WHERE 	member_id
			 IN 	('".implode("','", array_unique($author_ids))."')"
		);

		foreach($mquery->result_array() as $row)
		{
			$screen_names[$row['member_id']] 	= $row['screen_name'];
			$usernames[$row['member_id']] 		= $row['username'];
		}

		foreach ($query->result_array() as $row)
		{
			// Our member may have been deleted
			if( ! isset( $screen_names[ $row['author_id'] ] ) )
			{
				$row['screen_name'] = lang('screen_name_deleted');
				$row['username'] 	= lang('username_deleted');
			}
			else
			{
				$row['screen_name'] = $screen_names[$row['author_id']];
				$row['username'] 	= $usernames[$row['author_id']];
			}

			foreach($row as $k => $v)
			{
				$row['friends_' . $k] = $v;
			}

			$row['friends_member_id'] = $row['author_id'];

			$td = $tagdata;

			//normal goodies :D
			$td = ee()->functions->prep_conditionals(
				$td,
				array(
					'total_count' 	=> $total_count,
					'count' 		=> ++$count
				)
			);

			//  Parse "single" variables
			foreach (ee()->TMPL->var_single as $key => $val)
			{
				//is this a date key?
				$is_date = FALSE;

				//we store the entire key for full replacement later
				if (stristr($key, 'format'))
				{
					$is_date 		= TRUE;
					$date_format	= $val;
					$full_key		= $key;
					$key 			= trim(substr($key, 0, strpos($key, ' ')));
				}

				if(isset($row[$key]))
				{
					if ($is_date)
					{
						$func = (is_callable(array(ee()->localize, 'format_date'))) ?
								'format_date' :
								'decode_date';

						$row[$key] = ee()->localize->$func($date_format, $row[$key]);

						//have to swap out the real, full key
						$td = ee()->TMPL->swap_var_single($full_key, $row[$key], $td);
					}

					if (stristr($key, 'comment'))
					{
						$row[$key] =  str_replace(
							array('{', '}', '/'),
							array('&#123;', '&#125;', '&#47;'),
							$row[$key]
						);
					}

					//normal prep
					$td = ee()->functions->prep_conditionals($td, array($key => $row[$key]));

					$td = ee()->TMPL->swap_var_single($key, $row[$key], $td);
				}
			}//end foreach var_single

			$return_data .= $td;
		}

		return  $this->_parse_pagination( $return_data );
	}
	//end group_wall()



	// --------------------------------------------------------------------

	/**
	 * Post Group Comment Wall
	 *
	 * @access		public
	 * @return		string
	 */

	function group_wall_form()
	{
		$this->params['require_captcha']	= 'no';

		//	----------------------------------------
		//	Grab our tag data
		//	----------------------------------------

		$tagdata						= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Do we require IP address?
		//	----------------------------------------

		$this->params['require_ip']		= (ee()->TMPL->fetch_param('require_ip')) ? ee()->TMPL->fetch_param('require_ip') : '';

		//	----------------------------------------
		//	Are we establishing any required fields?
		//	----------------------------------------

		$this->params['ee_required']	= (ee()->TMPL->fetch_param('required')) ? ee()->TMPL->fetch_param('required') : '' ;

		//	----------------------------------------
		//	Are we notifying anyone?
		//	----------------------------------------

		$this->params['ee_notify']		= (ee()->TMPL->fetch_param('notify')) ? ee()->TMPL->fetch_param('notify') : '' ;

		//	----------------------------------------
		//	Are we using a notification template?
		//	----------------------------------------

		$this->params['template']		= (ee()->TMPL->fetch_param('template')) ?
											str_replace(SLASH, '/', ee()->TMPL->fetch_param('template')) :
											'default_template' ;


		$this->params['prevent_duplicates'] = $this->check_yes(ee()->TMPL->fetch_param('prevent_duplicates')) ? 'yes' : 'no' ;

		// ----------------------------------------
		//	Parse conditional pairs
		// ----------------------------------------

		$cond['captcha']				= ( $this->check_yes(ee()->config->item('captcha_require_members'))  OR
											($this->check_no(ee()->config->item('captcha_require_members')) AND
											 ee()->session->userdata('member_id') == 0) ) ?
												TRUE: FALSE;

		$tagdata						= ee()->functions->prep_conditionals( $tagdata, $cond );

		$return 						= (ee()->TMPL->fetch_param('return')) ? ee()->TMPL->fetch_param('return') : '' ;

		// ----------------------------------------
		//	Create form
		// ----------------------------------------

		$hidden = array(
			'ACT'					=> ee()->functions->fetch_action_id('Friends', 'insert_group_wall_comment'),
			'URI'					=> (ee()->uri->uri_string == '') ? 'index' : ee()->uri->uri_string,
			//form declaration takes care of this already
			//'XID'					=> ( ! isset($_POST['XID'])) ? '' : $_POST['XID'],
			'return'				=> $this->_chars_decode(str_replace(SLASH, '/', $return)),
			'friends_group_id'		=> ee()->TMPL->fetch_param('friends_group_id')
		);

		//	----------------------------------------
		//	Create form
		//	----------------------------------------

		$this->hdata					= $hidden;

		$this->hdata['RET']			= (isset($_POST['RET'])) ? $_POST['RET'] : ee()->functions->fetch_current_uri();

		$this->hdata['form_name']	= ( ee()->TMPL->fetch_param('form_name') ) ?
										ee()->TMPL->fetch_param('form_name') : 'group_wall_form';

		$this->hdata['id']			= ( ee()->TMPL->fetch_param('form_id') ) ?
										ee()->TMPL->fetch_param('form_id') : 'group_wall_form';

		$this->hdata['tagdata']		= $tagdata;

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$r	= $this->_wall_form();

		//	----------------------------------------
		//	Add class
		//	----------------------------------------

		if ( $class = ee()->TMPL->fetch_param('form_class') )
		{
			$r	= str_replace( "<form", "<form class=\"".$class."\"", $r );
		}

		//	----------------------------------------
		//	Add title
		//	----------------------------------------

		if ( $form_title = ee()->TMPL->fetch_param('form_title') )
		{
			$r	= str_replace( "<form", "<form title=\"".htmlspecialchars($form_title)."\"", $r );
		}

		if (ee()->extensions->active_hook('friends_group_wall_form_end') === TRUE)
		{
			$r = ee()->extensions->universal_call('friends_group_wall_form_end', $r);
			if (ee()->extensions->end_script === TRUE) return;
		}

		return $r;
	}

   // --------------------------------------------------------------------

	/**
	 * Post profile Comment Wall
	 *
	 * @access		public
	 * @return		string
	 */

	function profile_wall_form()
	{
		$this->params['require_captcha']	= 'no';

		//	----------------------------------------
		//	Grab our tag data
		//	----------------------------------------

		$tagdata						= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Do we require IP address?
		//	----------------------------------------

		$this->params['require_ip']		= (ee()->TMPL->fetch_param('require_ip')) ? ee()->TMPL->fetch_param('require_ip') : '';

		//	----------------------------------------
		//	Are we establishing any required fields?
		//	----------------------------------------

		$this->params['ee_required']	= (ee()->TMPL->fetch_param('required')) ? ee()->TMPL->fetch_param('required') : '' ;

		//	----------------------------------------
		//	Are we notifying anyone?
		//	----------------------------------------

		$this->params['ee_notify']		= (ee()->TMPL->fetch_param('notify')) ? ee()->TMPL->fetch_param('notify') : '' ;

		//	----------------------------------------
		//	Are we using a notification template?
		//	----------------------------------------

		$this->params['template']		= (ee()->TMPL->fetch_param('template')) ?
											str_replace(SLASH, '/', ee()->TMPL->fetch_param('template')) :
											'default_template' ;


		$this->params['prevent_duplicates'] = $this->check_yes(ee()->TMPL->fetch_param('prevent_duplicates')) ? 'yes' : 'no' ;

		// ----------------------------------------
		//	Parse conditional pairs
		// ----------------------------------------

		$cond['captcha']				= ( $this->check_yes(ee()->config->item('captcha_require_members'))  OR
											($this->check_no(ee()->config->item('captcha_require_members')) AND
											 ee()->session->userdata('member_id') == 0) ) ?
												TRUE: FALSE;

		$tagdata						= ee()->functions->prep_conditionals( $tagdata, $cond );

		$return 						= (ee()->TMPL->fetch_param('return')) ? ee()->TMPL->fetch_param('return') : '' ;

		$friend_id						= 0;

		$param_friend_id				= ee()->TMPL->fetch_param('friend_id');

		if ($param_friend_id == 'CURRENT_USER' OR $param_friend_id == '{logged_in_member_id}')
		{
			$friend_id = ee()->session->userdata('member_id');
		}
		else if ($param_friend_id !== FALSE AND is_numeric($param_friend_id))
		{
			$friend_id = $param_friend_id;
		}
		else if ($this->_member_id())
		{
			$friend_id = $this->member_id;
		}

		// ----------------------------------------
		//	Create form
		// ----------------------------------------

		$hidden = array(
			'ACT'					=> ee()->functions->fetch_action_id('Friends', 'insert_profile_wall_comment'),
			'URI'					=> (ee()->uri->uri_string == '') ? 'index' : ee()->uri->uri_string,
			//form decleration takes care of this already
			//'XID'					=> ( ! isset($_POST['XID'])) ? '' : $_POST['XID'],
			'return'				=> $this->_chars_decode(str_replace(SLASH, '/', $return)),
			'friend_id'				=> $friend_id
		);

		//	----------------------------------------
		//	Create form
		//	----------------------------------------

		$this->hdata					= $hidden;

		$this->hdata['RET']			= (isset($_POST['RET'])) ? $_POST['RET'] : ee()->functions->fetch_current_uri();

		$this->hdata['form_name']	= ( ee()->TMPL->fetch_param('form_name') ) ?
										ee()->TMPL->fetch_param('form_name') : 'profile_wall_form';

		$this->hdata['id']			= ( ee()->TMPL->fetch_param('form_id') ) ?
										ee()->TMPL->fetch_param('form_id') : 'profile_wall_form';

		$this->hdata['tagdata']		= $tagdata;

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		$r	= $this->_wall_form();

		//	----------------------------------------
		//	Add class
		//	----------------------------------------

		if ( $class = ee()->TMPL->fetch_param('form_class') )
		{
			$r	= str_replace( "<form", "<form class=\"".$class."\"", $r );
		}

		//	----------------------------------------
		//	Add title
		//	----------------------------------------

		if ( $form_title = ee()->TMPL->fetch_param('form_title') )
		{
			$r	= str_replace( "<form", "<form title=\"".htmlspecialchars($form_title)."\"", $r );
		}

		if (ee()->extensions->active_hook('friends_profile_wall_form_end') === TRUE)
		{
			$r = ee()->extensions->universal_call('friends_profile_wall_form_end', $r);
			if (ee()->extensions->end_script === TRUE) return;
		}

		return $r;
	}

	//	----------------------------------------
	//	Insert new entry
	//	----------------------------------------

	function insert_group_wall_comment()
	{
		//	----------------------------------------
		//	Fetch the freeform language pack
		//	----------------------------------------

		ee()->lang->loadfile('friends');

		//	----------------------------------------
		//	Is the user logged in?
		//	----------------------------------------

		$this->member_id = ee()->session->userdata('member_id');

		if ( $this->member_id == 0)
		{
			return $this->show_error(array(lang('not_logged_in')));
		}

		//	----------------------------------------
		//	check for group_id
		//	----------------------------------------

		ee()->load->library('friends_groups');

		if ( ! ee()->friends_groups->_group_id())
		{
			return $this->show_error(array(lang('group_not_found')));
		}

		$this->group_id = ee()->friends_groups->group_id;

		//	----------------------------------------
		//	check for bans, etc
		//	----------------------------------------

		$this->_security();

		//	----------------------------------------
		//	check if is a member of this group
		//	----------------------------------------

		if( ! $this->data->member_of_group( ee()->config->item( 'site_id' ), $this->member_id, $this->group_id ))
		{
			return $this->show_error(array(lang('not_group_member')));
		}

		//	----------------------------------------
		//	Start error trapping on required fields
		//	----------------------------------------


		$errors				= array();

		$required_fields 	= array('friends_wall_comment');

		if ( $this->_param('ee_required') != '' )
		{
			$required_fields	 = array_merge($required_fields,
									preg_split("/,|\|/" , $this->_param('ee_required'), -1, PREG_SPLIT_NO_EMPTY));
		}

		foreach ($required_fields as $required_field)
		{
			if ( ! ee()->input->post($required_field))
			{
				$errors[] = str_replace('%field%', $required_field, lang('missing_required_field'));
			}
		}

		if ( count($errors) > 0 )
		{
			return $this->show_error($errors);
		}

		//	----------------------------------------
		//	Check duplicates
		//	----------------------------------------

		$wall_comment =  trim(ee()->security->xss_clean(ee()->input->post('friends_wall_comment')));

		if ($this->check_yes($this->_param('prevent_duplicates')))
		{
			$dupe_sql 	= "SELECT 	author_id, comment
						   FROM 	exp_friends_group_comments
						   WHERE 	author_id = '" . ee()->db->escape_str($this->member_id) . "'
						   AND		comment   = '" . ee()->db->escape_str($wall_comment) . "'";

			$dupe_q 	= ee()->db->query($dupe_sql);

			if ($dupe_q->num_rows() > 0)
			{
				return $this->show_error(array(lang('duplicate_comment')));
			}
		}

		//	----------------------------------------
		//	Do we have errors to display?
		//	----------------------------------------

		if (count($errors) > 0)
		{
		   return $this->show_error($errors);
		}

		//	----------------------------------------
		//	Do we require captcha?
		//	----------------------------------------

		if ( $this->_param('require_captcha') AND $this->check_yes($this->_param('require_captcha')))
		{
			if ($this->check_yes(ee()->config->item('captcha_require_members'))  OR
				( $this->check_no(ee()->config->item('captcha_require_members')) AND
				  ee()->session->userdata('member_id') == 0))
			{
				if ( ! isset($_POST['captcha']) || $_POST['captcha'] == '')
				{
					return $this->show_error(lang('captcha_required'));
				}
				else
				{
					$res = ee()->db->query(
						"SELECT COUNT(*) AS count
						 FROM 	exp_captcha
						 WHERE 	word = '" . ee()->db->escape_str($_POST['captcha']) . "'
						 AND 	ip_address = '" . ee()->db->escape_str(ee()->input->ip_address()) . "'
						 AND 	date > UNIX_TIMESTAMP()-7200"
					);

					if ($res->row('count') == 0)
					{
						return $this->show_error(lang('captcha_incorrect'));
					}

					ee()->db->query(
						"DELETE FROM 	exp_captcha
						 WHERE 		 	(word = '" . ee()->db->escape_str($_POST['captcha']) . "'
											AND 	ip_address = '".ee()->db->escape_str(ee()->input->ip_address())."')
						 OR 		 	date < UNIX_TIMESTAMP()-7200"
					);
				}
			}
		}

		//	----------------------------------------
		//	Check Form Hash
		//	----------------------------------------

		//hash is checked by EE on all posts everywhere in EE 2.7
		if (version_compare($this->ee_version, '2.7', '<') &&
		! $this->check_secure_forms())
		{
			return $this->show_error(lang('not_authorized'));
		}

		//	----------------------------------------
		//	Build the data array
		//	----------------------------------------

		$data		= array(
			'author_id'				=> $this->member_id,
			'group_id'				=> $this->group_id,
			'entry_date'			=> ee()->localize->now,
			'comment'				=> $wall_comment,
			'site_id'				=> $this->clean_site_id
		);

		//	----------------------------------------
		//	Submit data into DB
		//	----------------------------------------

		$sql			= ee()->db->insert_string( 'exp_friends_group_comments', $data );

		$query			= ee()->db->query( $sql );

		$this->entry_id	= ee()->db->insert_id();


		//	----------------------------------------
		//	Send notifications
		//	----------------------------------------



		//	----------------------------------------
		//	Set return
		//	----------------------------------------

		if ( ! $return = ee()->input->get_post('return') )
		{
			$return	= ee()->input->get_post('RET');
		}

		if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $return, $match ) > 0 )
		{
			$return	= ee()->functions->create_url( $match['1'] );
		}
		elseif ( stristr( $return, "http://" ) === FALSE && stristr( $return, "https://" ) === FALSE )
		{
			$return	= ee()->functions->create_url( $return );
		}

		$return	= str_replace( "%%entry_id%%", $this->entry_id, $return );

		$return	= $this->_chars_decode( $return );

		//	----------------------------------------
		//	Return the user
		//	----------------------------------------

		if ( $return != '' )
		{
			ee()->functions->redirect( $return );
		}
		else
		{
			ee()->functions->redirect( ee()->functions->fetch_site_index() );
		}

		exit;
	}
	//	End insert


	//	----------------------------------------
	//	Insert new entry
	//	----------------------------------------

	function insert_profile_wall_comment()
	{
		//	----------------------------------------
		//	Fetch the freeform language pack
		//	----------------------------------------

		ee()->lang->loadfile('friends');

		//	----------------------------------------
		//	Is the user logged in?
		//	----------------------------------------

		$this->member_id = ee()->session->userdata('member_id');

		if ( $this->member_id == 0)
		{
			return $this->show_error(array(lang('not_logged_in')));
		}

		$friend_id = ee()->input->post('friend_id');

		if ( ! is_numeric($friend_id) OR $friend_id <= 0)
		{
			return $this->show_error(array(lang('member_not_found')));
		}

		//	----------------------------------------
		//	check for bans, etc
		//	----------------------------------------

		$this->_security();

		//	----------------------------------------
		//	check if these are friends
		//	----------------------------------------

		$members_friends = $this->data->get_friend_ids_from_member_id( ee()->config->item( 'site_id' ), $friend_id );

		if( $this->member_id != $friend_id AND ! in_array($this->member_id, $members_friends))
		{
			return $this->show_error(array(lang('not_friends')));
		}

		//	----------------------------------------
		//	Start error trapping on required fields
		//	----------------------------------------


		$errors				= array();

		$required_fields 	= array('friends_wall_comment');

		if ( $this->_param('ee_required') != '' )
		{
			$required_fields	 = array_merge($required_fields,
									preg_split("/,|\|/" , $this->_param('ee_required'), -1, PREG_SPLIT_NO_EMPTY));
		}

		foreach ($required_fields as $required_field)
		{
			if ( ! ee()->input->post($required_field))
			{
				$errors[] = str_replace('%field%', $required_field, lang('missing_required_field'));
			}
		}

		if ( count($errors) > 0 )
		{
			return $this->show_error($errors);
		}


		//	----------------------------------------
		//	Check duplicates
		//	----------------------------------------

		$wall_comment =  trim(ee()->security->xss_clean(ee()->input->post('friends_wall_comment')));

		if ( $this->check_yes($this->_param('prevent_duplicates')))
		{
			$dupe_sql 	= "SELECT 	author_id, comment
						   FROM 	exp_friends_profile_comments
						   WHERE 	author_id = '" . ee()->db->escape_str($this->member_id) . "'
						   AND		friend_id   = '" . ee()->db->escape_str($friend_id) . "'
						   AND		comment   = '" . ee()->db->escape_str($wall_comment) . "'";

			$dupe_q 	= ee()->db->query($dupe_sql);

			if ($dupe_q->num_rows() > 0)
			{
				return $this->show_error(array(lang('duplicate_comment')));
			}
		}

		//	----------------------------------------
		//	Do we have errors to display?
		//	----------------------------------------

		if (count($errors) > 0)
		{
		   return $this->show_error($errors);
		}

		//	----------------------------------------
		//	Do we require captcha?
		//	----------------------------------------

		if ( $this->_param('require_captcha') AND $this->check_yes($this->_param('require_captcha')))
		{
			if ($this->check_yes(ee()->config->item('captcha_require_members'))  OR
				( $this->check_no(ee()->config->item('captcha_require_members')) AND
				  ee()->session->userdata('member_id') == 0))
			{
				if ( ! isset($_POST['captcha']) || $_POST['captcha'] == '')
				{
					return $this->show_error(lang('captcha_required'));
				}
				else
				{
					$res = ee()->db->query(
						"SELECT COUNT(*) AS count
						 FROM 	exp_captcha
						 WHERE 	word = '" . ee()->db->escape_str($_POST['captcha']) . "'
						 AND 	ip_address = '" . ee()->db->escape_str(ee()->input->ip_address()) . "'
						 AND 	date > UNIX_TIMESTAMP()-7200"
					);

					if ($res->row('count') == 0)
					{
						return $this->show_error(lang('captcha_incorrect'));
					}

					ee()->db->query(
						"DELETE FROM 	exp_captcha
						 WHERE 		 	(word = '" . ee()->db->escape_str($_POST['captcha']) . "'
											AND 	ip_address = '".ee()->db->escape_str(ee()->input->ip_address())."')
						 OR 		 	date < UNIX_TIMESTAMP()-7200"
					);
				}
			}
		}

		//	----------------------------------------
		//	Check Form Hash
		//	----------------------------------------

		//hash is checked by EE on all posts everywhere in EE 2.7
		if (version_compare($this->ee_version, '2.7', '<') &&
		 ! $this->check_secure_forms() )
		{
			return $this->show_error(array(lang('not_authorized')));
		}

		//	----------------------------------------
		//	Build the data array
		//	----------------------------------------

		$data		= array(
			'author_id'				=> $this->member_id,
			'friend_id'				=> $friend_id,
			'entry_date'			=> ee()->localize->now,
			'comment'				=> $wall_comment,
			'site_id'				=> $this->clean_site_id
		);

		//	----------------------------------------
		//	Submit data into DB
		//	----------------------------------------

		$sql			= ee()->db->insert_string( 'exp_friends_profile_comments', $data );

		$query			= ee()->db->query( $sql );

		$this->entry_id	= ee()->db->insert_id();


		//	----------------------------------------
		//	Send notifications
		//	----------------------------------------



		//	----------------------------------------
		//	Set return
		//	----------------------------------------

		if ( ! $return = ee()->input->get_post('return') )
		{
			$return	= ee()->input->get_post('RET');
		}

		if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $return, $match ) > 0 )
		{
			$return	= ee()->functions->create_url( $match['1'] );
		}
		elseif ( stristr( $return, "http://" ) === FALSE && stristr( $return, "https://" ) === FALSE )
		{
			$return	= ee()->functions->create_url( $return );
		}

		$return	= str_replace( "%%entry_id%%", $this->entry_id, $return );

		$return	= $this->_chars_decode( $return );

		//	----------------------------------------
		//	Return the user
		//	----------------------------------------

		if ( $return != '' )
		{
			ee()->functions->redirect( $return );
		}
		else
		{
			ee()->functions->redirect( ee()->functions->fetch_site_index() );
		}

		exit;
	}
	//	End insert


	// --------------------------------------------------------------------

	/**
	 * profile wall comment delete
	 *
	 * Delete a comment as long as it's yours.
	 *
	 * @access		public
	 * @return		string
	 */

	function profile_comment_delete()
	{
		//	----------------------------------------
		//	Logged in?
		//	----------------------------------------

		if ( ( $this->member_id = ee()->session->userdata('member_id') ) == 0 )
		{
			return $this->_fetch_error( lang('not_logged_in') );
		}

		//	----------------------------------------
		//	Security
		//	----------------------------------------

		$this->_security();

		//	----------------------------------------
		//	Status id present?
		//	----------------------------------------

		$comment_id	= '';

		if ( ee()->TMPL->fetch_param( 'comment_id' ) !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param( 'comment_id' ) ) === TRUE )
		{
			$comment_id	= ee()->TMPL->fetch_param( 'comment_id' );
		}
		elseif ( preg_match( '/\/(\d+)/s', ee()->uri->uri_string, $match ) )
		{
			$comment_id	= $match['1'];
		}
		else
		{
			return $this->_fetch_error( lang('no_comment_id') );
		}

		//	----------------------------------------
		//	Ownership?
		//	----------------------------------------

		$is_admin 		= (ee()->session->userdata('group_id') == 1);
		$comment_data 	= $this->data->get_data_from_profile_comment_id( ee()->config->item('site_id'), $comment_id );

		if ( ! $is_admin AND
			   $comment_data['author_id'] != $this->member_id AND
			   $comment_data['friend_id'] != $this->member_id )
		{
			return $this->_fetch_error( lang('not_your_comment') );
		}

		// ----------------------------------------
		//	Extension
		// ----------------------------------------

		if (ee()->extensions->active_hook('friends_profile_comment_delete') === TRUE)
		{
			$data	= ee()->extensions->universal_call( 'friends_profile_comment_delete', $this, $data );
			if ( ee()->extensions->end_script === TRUE ) exit();
		}

		//	----------------------------------------
		//	Delete
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM exp_friends_profile_comments
			 WHERE 		 comment_id = " . ee()->db->escape_str( $comment_id )
		);

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$this->message[]			= lang('comment_deleted');
		$data['friends_message']	= $this->_prep_message();

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $data );
		return str_replace( LD . 'friends_message' . RD, $this->message[0], $tagdata );
	}

	//	End profile_comment_delete



	// --------------------------------------------------------------------

	/**
	 * group wall comment delete
	 *
	 * Delete a comment as long as it's yours.
	 *
	 * @access		public
	 * @return		string
	 */

	function group_comment_delete()
	{
		//	----------------------------------------
		//	Logged in?
		//	----------------------------------------

		if ( ( $this->member_id = ee()->session->userdata('member_id') ) == 0 )
		{
			return $this->_fetch_error( lang('not_logged_in') );
		}

		//	----------------------------------------
		//	Security
		//	----------------------------------------

		$this->_security();

		//	----------------------------------------
		//	Status id present?
		//	----------------------------------------

		$comment_id	= '';

		if ( ee()->TMPL->fetch_param( 'comment_id' ) !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param( 'comment_id' ) ) === TRUE )
		{
			$comment_id	= ee()->TMPL->fetch_param( 'comment_id' );
		}
		elseif ( preg_match( '/\/(\d+)/s', ee()->uri->uri_string, $match ) )
		{
			$comment_id	= $match['1'];
		}
		else
		{
			return $this->_fetch_error( lang('no_comment_id') );
		}

		//	----------------------------------------
		//	Ownership?
		//	----------------------------------------


		$is_admin 		= (ee()->session->userdata('group_id') == 1);
		$comment_data 	= $this->data->get_data_from_group_comment_id( ee()->config->item('site_id'), $comment_id );

		if ( ! $is_admin AND
			$comment_data['author_id'] != $this->member_id AND
			$comment_data['friend_id'] != $this->member_id )
		{
			return $this->_fetch_error( lang('not_your_comment') );
		}

		// ----------------------------------------
		//	Extension
		// ----------------------------------------

		if (ee()->extensions->active_hook('friends_group_comment_delete') === TRUE)
		{
			$data	= ee()->extensions->universal_call( 'friends_group_comment_delete', $this, $data );
			if ( ee()->extensions->end_script === TRUE ) exit();
		}

		//	----------------------------------------
		//	Delete
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM exp_friends_group_comments
			 WHERE 		 comment_id = " . ee()->db->escape_str( $comment_id )
		);

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$this->message[]			= lang('comment_deleted');
		$data['friends_message']	= $this->_prep_message();

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $data );
		return str_replace( LD . 'friends_message' . RD, $this->message[0], $tagdata );
	}

	//	End group_comment_delete



















	// --------------------------------------------------------------------

	/**
	 * Update friends
	 *
	 * @access		public
	 * @return		string
	 */

	function update()
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error( lang('not_logged_in') );
		}

		//	----------------------------------------
		//	Check Form Hash
		//	----------------------------------------

		$this->_check_form_hash();

		//	----------------------------------------
		//	Are we deleting?
		//	----------------------------------------

		$this->delete	= $this->check_yes(ee()->input->post('friends_delete'));

		//	----------------------------------------
		//	Are we deleting?
		//	----------------------------------------

		$block_mode		= $this->check_yes(ee()->input->post('friends_block'));

		//	----------------------------------------
		//	Are we notifying?
		//	----------------------------------------

		$this->notify	= $this->check_yes(ee()->input->post('friends_notify'));

		//	----------------------------------------
		//	Build members array
		//	----------------------------------------

		$members	= array();

		if ( isset( $_POST['friends_member_id'] ) )
		{
			if ( is_array( $_POST['friends_member_id'] ) )
			{
				$members	= $_POST['friends_member_id'];
			}
			else
			{
				$members[]	= $_POST['friends_member_id'];
			}
		}

		//	----------------------------------------
		//	Are we deleting?
		//	----------------------------------------

		if ( strpos( ee()->uri->uri_string, "/delete" ) !== FALSE OR $this->delete === TRUE )
		{
			if ( ! $this->_delete( $members ) )
			{
				return $this->_fetch_error( $this->message );
			}
		}

		//	----------------------------------------
		//	Blocking?
		//	----------------------------------------

		elseif ( strpos( ee()->uri->uri_string, "/block" ) !== FALSE OR $block_mode === TRUE )
		{
			if ( $this->_block( $members ) === FALSE )
			{
				return $this->_fetch_error( $this->message );
			}
		}

		//	----------------------------------------
		//	Standard add
		//	----------------------------------------

		elseif ( ! $this->_add( $members ) )
		{
			return $this->_fetch_error( $this->message );
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$data['message']	= $this->_prep_message();

		//	----------------------------------------
		//	Prep return
		//	----------------------------------------

		$return	= $this->_prep_return();

		//	----------------------------------------
		//	Are we using a template?
		//	----------------------------------------

		if ( ! $body = $this->_fetch_template( '', $data ) )
		{
			return ee()->output->show_message(
				array(
					'title' 	=> lang('success'),
					'heading' 	=> lang('success'),
					'link' 		=> array( $return, lang('continue') ),
					'content' 	=> $data['message']
				)
			);
		}
		else
		{
			return $body;
		}
	}

	// End update





	// --------------------------------------------------------------------

	/**
	 * Update stats
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _update_stats( $members = array() )
	{
		//	----------------------------------------
		//	Make sure that I'm in the list
		//	----------------------------------------

		$members[]	= ee()->session->userdata['member_id'];

		$mems		= implode( "','", $members );

		//	----------------------------------------
		//	Verify list of members
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT member_id
			 FROM 	exp_members
			 WHERE 	member_id
			 IN 	('".ee()->db->escape_str( $mems )."')"
		);

		if ( $query->num_rows() == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Loop
		//	----------------------------------------

		$members	= array();

		foreach ( $query->result_array() as $row )
		{
			$members[]	= $row['member_id'];
		}

		$mems		= implode( "','", $members );

		//	----------------------------------------
		//	Zero out the totals
		//	----------------------------------------
		//	Reciprocal friends is counted in $this->_reciprocal()
		//	----------------------------------------

		ee()->db->query(
			"UPDATE exp_members
			 SET 	total_friends = '0',
					total_blocked_friends = '0'
			 WHERE 	member_id
			 IN 	('" . ee()->db->escape_str( $mems ) . "')"
		);

		$sql	= array();

		//	----------------------------------------
		//	Update total friends
		//	----------------------------------------

		$query = ee()->db->query(
			"SELECT 	member_id, COUNT(*) AS count
			 FROM 		exp_friends
			 WHERE 		block = 'n'
			 AND 		friend_id != 0
			 AND 		member_id
			 IN 		('".ee()->db->escape_str( $mems )."')
			 GROUP BY 	member_id"
		);

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$sql[] = ee()->db->update_string(
					'exp_members',
					array( 'total_friends' => $row['count'] ),
					array( 'member_id' => $row['member_id'] )
				);
			}
		}

		//	----------------------------------------
		//	Update total blocked friends
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT member_id, COUNT(*) AS count
			 FROM 	exp_friends
			 WHERE 	block = 'y'
			 AND 	friend_id != 0
			 AND 	member_id
			 IN 	('".ee()->db->escape_str( $mems )."')
			 GROUP BY member_id"
		);

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$sql[] = ee()->db->update_string(
					'exp_members',
					array( 'total_blocked_friends' => $row['count'] ),
					array( 'member_id' => $row['member_id'] )
				);
			}
		}

		//	----------------------------------------
		//	Loop
		//	----------------------------------------

		if ( count( $sql ) > 0 )
		{
			foreach( $sql as $q )
			{
				ee()->db->query( $q );
			}
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return TRUE;
	}

	// End update stats


	/**
	 * Update stats from the CP
	 *
	 * @access		private
	 * @return		boolean
	 */

	function _update_stats_cp( $member_ids = array() )
	{
		$mems		= implode( "','", $member_ids );

		//	----------------------------------------
		//	Zero out the totals
		//	----------------------------------------
		//	Reciprocal friends is counted in $this->_reciprocal()
		//	----------------------------------------

		ee()->db->query(
			"UPDATE exp_members
			 SET 	total_friends = '0',
					total_blocked_friends = '0'
			 WHERE 	member_id
			 IN 	('" . ee()->db->escape_str( $mems ) . "')"
		);

		$sql	= array();

		//	----------------------------------------
		//	Update total friends
		//	----------------------------------------

		$query = ee()->db->query(
			"SELECT 	member_id, COUNT(*) AS count
			 FROM 		exp_friends
			 WHERE 		block = 'n'
			 AND 		friend_id != 0
			 AND 		member_id
			 IN 		('".ee()->db->escape_str( $mems )."')
			 GROUP BY 	member_id"
		);

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$sql[] = ee()->db->update_string(
					'exp_members',
					array( 'total_friends' => $row['count'] ),
					array( 'member_id' => $row['member_id'] )
				);
			}
		}

		//	----------------------------------------
		//	Update total blocked friends
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT member_id, COUNT(*) AS count
			 FROM 	exp_friends
			 WHERE 	block = 'y'
			 AND 	friend_id != 0
			 AND 	member_id
			 IN 	('".ee()->db->escape_str( $mems )."')
			 GROUP BY member_id"
		);

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$sql[] = ee()->db->update_string(
					'exp_members',
					array( 'total_blocked_friends' => $row['count'] ),
					array( 'member_id' => $row['member_id'] )
				);
			}
		}

		//	----------------------------------------
		//	Loop
		//	----------------------------------------

		if ( count( $sql ) > 0 )
		{
			foreach( $sql as $q )
			{
				ee()->db->query( $q );
			}
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return TRUE;
	}

	// End update stats


	// ----------------------------------------
	// Params
	// ----------------------------------------

	function _param( $which = '', $type = 'all' )
	{
		//	----------------------------------------
		//	Which?
		//	----------------------------------------

		if ( $which == '' ) return FALSE;

		//	----------------------------------------
		//	Params set?
		//	----------------------------------------

		if ( count( $this->params ) == 0 )
		{
			// ----------------------------------------
			// Empty id?
			// ----------------------------------------

			if ( ! $this->params_id = ee()->input->get_post('params_id') )
			{
				return FALSE;
			}

			// ----------------------------------------
			// Select from DB
			// ----------------------------------------

			$query	= ee()->db->query(
				"SELECT data
				 FROM 	{$this->params_tbl}
				 WHERE 	params_id = '" . ee()->db->escape_str( $this->params_id ) . "'"
			);

			// ----------------------------------------
			// Empty?
			// ----------------------------------------

			if ( $query->num_rows() == 0 ) return FALSE;

			// ----------------------------------------
			// Unserialize
			// ----------------------------------------

			$this->params			= unserialize( $query->row('data') );
			$this->params['set']	= TRUE;

			// ----------------------------------------
			// Delete
			// ----------------------------------------

			ee()->db->query(
				"DELETE FROM 	{$this->params_tbl}
				 WHERE 			entry_date < " . ee()->db->escape_str( (ee()->localize->now - 7200) )
			);
		}

		//	----------------------------------------
		//	Fetch from params array
		//	----------------------------------------

		if ( isset( $this->params[$which] ) )
		{
			$return	= str_replace( "&#47;", "/", $this->params[$which] );

			return $return;
		}

		//	----------------------------------------
		//	Fetch TMPL
		//	----------------------------------------

		if ( isset(ee()->TMPL) AND is_object(ee()->TMPL) AND ee()->TMPL->fetch_param($which) )
		{
			return ee()->TMPL->fetch_param($which);
		}

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return TRUE;
	}

	// ----------------------------------------
	// Insert params
	// ----------------------------------------

	function _insert_params( $params = array() )
	{
		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( count( $params ) > 0 )
		{
			$this->params	= $params;
		}
		elseif ( ! isset( $this->params ) OR count( $this->params ) == 0 )
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Serialize
		//	----------------------------------------

		$this->params	= serialize( $this->params );

		//	----------------------------------------
		//	Delete excess when older than 2 hours
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM 	{$this->params_tbl}
			 WHERE 			entry_date < " . ee()->db->escape_str( (ee()->localize->now - 7200) )
		);

		//	----------------------------------------
		//	Insert
		//	----------------------------------------

		ee()->db->query(
			ee()->db->insert_string(
				$this->params_tbl,
				array(
					'entry_date' 	=> ee()->localize->now,
					'data' 			=> $this->params
				)
			)
		);

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return ee()->db->insert_id();
	}

	//	End insert params
}
// END CLASS Friends
