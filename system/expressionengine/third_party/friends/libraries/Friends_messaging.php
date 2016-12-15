<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Messaging Class
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @filesource	friends/libraries/Friends_messaging.php
 */

require_once realpath(rtrim(dirname(__FILE__), '/') . '/../mod.friends.php');

class Friends_messaging extends Friends
{
	// --------------------------------------------------------------------

	/**
	 * Message delete
	 *
	 * Marks private messages as deleted.
	 *
	 * @access		public
	 * @return		string
	 */

	function message_delete()
	{
		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Ajax mode?
		//	----------------------------------------

		if ( $this->check_yes(ee()->input->post('friends_ajax')) )
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
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Message id?
		//	----------------------------------------

		$message_id	= array();

		if ( ee()->TMPL->fetch_param('message_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('message_id') ) === TRUE )
		{
			$message_id[]	= ee()->TMPL->fetch_param('message_id');
		}
		elseif ( preg_match( "/\/(\d+)/s", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			$message_id[]	= $match['1'];
		}
		elseif ( empty( $_POST['friends_message_id'] ) === FALSE )
		{
			$message_id	= $_POST['friends_message_id'];
		}

		//	----------------------------------------
		//	Delete now
		//	----------------------------------------

		if ( $this->_message_delete( $message_id ) === FALSE )
		{
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'failure' => TRUE, 'success' => FALSE ) );
			return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
	}

	/* End message delete */

	// --------------------------------------------------------------------

	/**
	 * Message delete sub
	 *
	 * Marks private messages as deleted.
	 *
	 * @access		private
	 * @return		string
	 */

	function _message_delete( $message_id = array() )
	{
		//	----------------------------------------
		//	Valid messages?
		//	----------------------------------------

		if ( ( $message_id = $this->_only_numeric( $message_id ) ) === FALSE OR empty( $message_id ) === TRUE )
		{
			$this->message[]	= lang('no_message_ids_for_deletion');

			return FALSE;
		}

		//	----------------------------------------
		//	Check against DB
		//	----------------------------------------

		$sql	= 'SELECT copy_id, message_id, message_folder, message_deleted FROM exp_message_copies WHERE recipient_id = '.ee()->db->escape_str( ee()->session->userdata('member_id') ).' AND message_id IN ('.implode( ',', $message_id ).')';

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('no_message_ids_from_db_for_deletion');

			return FALSE;
		}

		//	----------------------------------------
		//	Get valid copy ids
		//	----------------------------------------

		$copy_id	= array();
		$deleted	= 0;

		foreach ( $query->result_array() as $row )
		{
			if ( $row['message_deleted'] == 'y' AND $row['message_folder'] != 0 )
			{
				$deleted++;
			}
			else
			{
				$copy_id[]	= $row['copy_id'];
			}
		}

		//	----------------------------------------
		//	Any already deleted?
		//	----------------------------------------

		if ( $deleted === 1 AND count( $copy_id ) === 0 )
		{
			$this->message[]	= lang('your_message_already_deleted');

			return FALSE;
		}
		elseif ( $deleted === 1 )
		{
			$this->message[]	= lang('message_already_deleted');
		}
		elseif ( $deleted > 1 )
		{
			$this->message[]	= str_replace( '%count%', $deleted, lang('messages_already_deleted') );

			if ( count( $copy_id ) === 0 ) return FALSE;
		}

		//	----------------------------------------
		//	Delete the remainder
		//	----------------------------------------

		if ( count( $copy_id ) === 0 )
		{
			$this->message[]	= lang('no_message_ids_remain_for_deletion');

			return FALSE;
		}

		$sql	= 'DELETE FROM 	exp_message_copies
				   WHERE 		copy_id
				   IN 			('.implode( ',', $copy_id ).')';

		ee()->db->query( $sql );

		if ( ee()->db->affected_rows() === 1 )
		{
			$this->message[]	= lang('message_deleted');
		}
		else
		{
			$this->message[]	= str_replace( '%count%', ee()->db->affected_rows(), lang('messages_deleted') );
		}

		//	----------------------------------------
		//	Delete messages with no copies
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM 	exp_message_data
			 WHERE 			message_id
			 NOT IN 		( SELECT message_id FROM exp_message_copies )"
		);

		return TRUE;
	}

	//	End message delete sub

	// --------------------------------------------------------------------

	/**
	 * Message folder edit
	 *
	 * Edits message folders
	 *
	 * @access		public
	 * @return		string
	 */

	function message_folder_edit()
	{


		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error( lang('not_logged_in') );
		}

		//	----------------------------------------
		//	Build folders array
		//	----------------------------------------

		if ( isset( $_POST['friends_message_folder_id'] ) )
		{
			if ( is_array( $_POST['friends_message_folder_id'] ) )
			{
				$folders['post']	= $_POST['friends_message_folder_id'];
			}
		}

		if ( empty( $folders['post'] ) === TRUE )
		{
			return $this->_fetch_error( lang('no_folders_provided') );
		}

		//	----------------------------------------
		//	Get current folders
		//	----------------------------------------

		$folders['current']	= $this->data->get_message_folders_for_member( ee()->session->userdata('member_id') );
		// This array of folders is not altered, just makes folder name available to us.
		$folders['all']		= $folders['current'];

		unset( $folders['current'][0], $folders['current'][1], $folders['current'][2] );

		//	----------------------------------------
		//	Determine changes
		//	----------------------------------------

		$folders['deleted']		= array();
		$folders['disallowed']	= array();
		$folders['duplicates']	= array();
		$folders['edits']		= array();
		$folders['new']			= array();
		$folders['used']		= array();

		foreach ( $folders['post'] as $key => $val )
		{
			$val = ee()->security->xss_clean($val);

			if ( $val != '' AND ( in_array( strtolower( $val ), array( 'inbox', 'sent', 'trash' ) ) === TRUE ) )
			{
				$folders['disallowed'][]	= $val;	// Not allowed
			}
			elseif ( isset( $folders['current'][$key] ) === TRUE AND $val != '' AND $val != $folders['current'][$key] )
			{
				$folders['edits'][$key]	= $val;	// Edit a folder
				unset( $folders['current'][$key] );
			}
			elseif ( $val != '' AND isset( $folders['current'][$key] ) === FALSE )
			{
				$folders['new'][$key]	= $val;	// New folder
			}
		}

		foreach ( $folders['current'] as $key => $val )
		{
			if ( isset( $folders['post'][$key] ) === TRUE AND $folders['post'][$key] == '' )
			{
				$folders['deleted'][$key]	= $val;
				unset( $folders['current'][$key] );
			}
		}

		//	----------------------------------------
		//	Check duplicates?
		//	----------------------------------------

		foreach ( array( 'current', 'new', 'edits' ) as $arr )
		{
			foreach ( $folders[ $arr ] as $val )
			{
				if ( in_array( $val, $folders['used'] ) === FALSE )
				{
					$folders['used'][]			= $val;
				}
				else
				{
					$folders['duplicates'][]	= $val;
				}
			}
		}

		if ( count( $folders['duplicates'] ) > 0 )
		{
			return $this->_fetch_error(
				 str_replace(
					'%folders%',
					implode( ', ', $folders['duplicates'] ),
					lang( 'folders_duplicated' )
				)
			);
		}

		//	----------------------------------------
		//	Limit exceeded?
		//	----------------------------------------

		if ( ( count( $folders['new'] ) + count( $folders['edits'] ) ) > 7 )
		{
			return $this->_fetch_error( lang('folder_limit_exceeded') );
		}

		//	----------------------------------------
		//	Check Form Hash
		//	----------------------------------------

		$this->_check_form_hash();

		//	----------------------------------------
		//	Any disallowed?
		//	----------------------------------------

		if ( empty( $folders['disallowed'] ) === FALSE )
		{
			$this->message[]	= str_replace(
				'%folders%',
				implode( ', ', $folders['disallowed'] ),
				lang( 'folders_not_allowed' )
			);
		}

		//	----------------------------------------
		//	Any new?
		//	----------------------------------------

		if ( empty( $folders['new'] ) === FALSE )
		{
			foreach ( $folders['new'] as $key => $val )
			{
				if ( $key > 10 OR $key === 0 OR $key === 1 OR $key === 2 ) continue;
				$creates[ 'folder' . $key . '_name' ]	= $val;
			}

			if ( empty( $creates ) === FALSE )
			{
				ee()->db->query(
					ee()->db->update_string(
						'exp_message_folders',
						$creates,
						array( 'member_id' => ee()->session->userdata('member_id') )
					)
				);

				$this->message[]	= str_replace(
					'%folders%',
					implode( ', ', $folders['new'] ),
					lang( 'folders_created' )
				);
			}
		}

		//	----------------------------------------
		//	Any edits?
		//	----------------------------------------

		if ( empty( $folders['edits'] ) === FALSE  )
		{
			$renames	= array();

			foreach ( $folders['edits'] as $key => $val )
			{
				if ( $key > 10 OR $key === 0 OR $key === 1 OR $key === 2 ) continue;
				$edits[ 'folder' . $key . '_name' ]	= $val;
				$renames[]	= $folders['all'][$key] . lang('was_renamed_to') . $val;
			}

			if ( empty( $edits ) === FALSE )
			{
				ee()->db->query(
					ee()->db->update_string(
						'exp_message_folders',
						$edits,
						array( 'member_id' => ee()->session->userdata('member_id') )
					)
				);
			}

			$this->message[]	= str_replace(
				'%folders%',
				implode( lang(' and '), $renames ),
				lang( 'folders_renamed' )
			);
		}
		else
		{
			$this->message[]	= lang( 'no_folders_renamed' );
		}

		//	----------------------------------------
		//	Any deletes?
		//	----------------------------------------

		if ( empty( $folders['deleted'] ) === FALSE  )
		{
			// ----------------------------------------
			// Check the DB for folders still containing messages
			// ----------------------------------------

			$query	= ee()->db->query(
				"SELECT message_folder, message_deleted
				 FROM 	exp_message_copies
				 WHERE 	message_deleted = 'n'
				 AND 	recipient_id = '" . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "'
				 AND 	message_folder
				 NOT IN (1,2)
				 AND 	message_folder
				 IN 	(" . implode( ',', array_keys( $folders['deleted'] ) ) . ")"
			);

			if ( $query->num_rows() > 0 )
			{
				foreach ( $query->result_array() as $row )
				{
					$folders['db'][$row['message_folder']][]	= 1;
				}

				// ----------------------------------------
				// Prepare message
				// ----------------------------------------

				$full	= '';

				foreach ( $folders['db'] as $key => $val )
				{
					$full	.= $folders['all'][$key] . ' (' . count( $val ) . ')' . ',';
					unset( $folders['deleted'][$key] );
				}

				$full	= substr( $full, 0, -1 );

				$this->message[]	= str_replace( '%folders%', $full, lang( 'folders_contain_messages' ) );
			}

			// ----------------------------------------
			// Delete folders and copies if necessary
			// ----------------------------------------

			if ( empty( $folders['deleted'] ) === FALSE )
			{
				ee()->db->query(
					"DELETE FROM 	exp_message_copies
					 WHERE 			recipient_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "
					 AND 			message_deleted = 'y'
					 AND 			message_folder
					 IN 			(" . implode( ',', array_keys( $folders['deleted'] ) ) . ")"
				);

				foreach ( array_keys( $folders['deleted'] ) as $key )
				{
					$deletes[ 'folder' . $key . '_name' ]	= '';
				}

				if ( empty( $deletes ) === FALSE )
				{
					ee()->db->query(
						ee()->db->update_string(
							'exp_message_folders',
							$deletes,
							array( 'member_id' => ee()->session->userdata('member_id') )
						)
					);

					$this->message[]	= str_replace(
						'%folders%',
						implode( ', ', $folders['deleted'] ),
						lang( 'folders_deleted' )
					);
				}
			}
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
					'title' => lang('success'),
					'heading' => lang('success'),
					'link' => array( $return, lang('continue') ),
					'content' => $data['message']
				)
			);
		}
		else
		{
			return $body;
		}
	}

	/*	End message folder edit */

	// --------------------------------------------------------------------

	/**
	 * Message folder form
	 *
	 * Creates a form for editing message folders
	 *
	 * @access		public
	 * @return		string
	 */

	function message_folder_form()
	{
		$act		= ee()->functions->fetch_action_id('Friends', 'message_folder_edit');

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Logged in?
		//	----------------------------------------

		if ( ee()->session->userdata('member_id') == 0 )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Get folders
		//	----------------------------------------

		$folders	= $this->data->get_message_folders_for_member( ee()->session->userdata('member_id') );

		//	----------------------------------------
		//	Remove fixed folders
		//	----------------------------------------

		unset( $folders[0], $folders[1], $folders[2] );

		//	----------------------------------------
		//	Are we editing only one folder?
		//	----------------------------------------

		if ( $this->dynamic === TRUE AND preg_match( "/\/(\d+)/s", ee()->uri->uri_string, $match ) )
		{
			if ( isset( $folders[ $match['1'] ] ) === FALSE )
			{
				return $this->no_results('friends');
			}
			else
			{
				$temp[ $match['1'] ]	= $folders[ $match['1'] ];
				$folders	= $temp;
			}
		}

		if ( preg_match( "/".LD."friends_message_folders".RD."(.*?)".
						LD.preg_quote(T_SLASH, '/')."friends_message_folders".RD."/s", $tagdata, $match ) )
		{
			// ----------------------------------------
			// Get the first open folder position
			// ----------------------------------------

			$position	= '';

			$query	= ee()->db->query(
				"SELECT *
				 FROM 	exp_message_folders
				 WHERE 	member_id = ".ee()->db->escape_str( ee()->session->userdata('member_id') )
			);

			unset( $query->row['member_id'] );

			foreach ( $query->row_array() as $key => $val )
			{
				if ( $position != '' ) continue;

				if ( $val == '' )
				{
					$position	= str_replace( array( 'folder', '_name' ), '', $key );
					$folders[$position]	= '';
					continue;
				}
			}

			$r	= '';

			foreach ( $folders as $key => $val )
			{
				$tdata	= $match['1'];
				$tdata	= $this->_parse_switch( $tdata );

				$tdata	= str_replace(
					array(
						LD.'friends_message_folder_id'.RD,
						LD.'friends_message_folder'.RD
					),
					array( $key, $val ),
					$tdata
				);

				$r	.= $tdata;
			}

			$tagdata	= str_replace( $match['0'], $r, $tagdata );
		}

		//	----------------------------------------
		//	Prep data
		//	----------------------------------------

		$this->arr['ACT']				= $act;

		$this->arr['RET']				= (isset($_POST['RET'])) ? $_POST['RET'] :
											ee()->functions->fetch_current_uri();

		$this->arr['form_id']			= ( ee()->TMPL->fetch_param('form_id') ) ?
											ee()->TMPL->fetch_param('form_id') : 'friends_message_folder_form';

		$this->arr['form_name']			= ( ee()->TMPL->fetch_param('form_name') ) ?
											ee()->TMPL->fetch_param('form_name') : 'friends_message_folder_form';

		$this->arr['return']			= ( ee()->TMPL->fetch_param('return') ) ? ee()->TMPL->fetch_param('return'): '';

		//	----------------------------------------
		//	Declare form
		//	----------------------------------------

		$this->arr['tagdata']	= $tagdata;

		return $this->_form();
	}

	/* End message folder form */

	// --------------------------------------------------------------------

	/**
	 * Message folder name
	 *
	 * This shows the message folder name based on the folder id provided.
	 *
	 * @variables	{friends_message_folder_name}
	 *
	 * @access		public
	 * @return		string
	 */

	function message_folder_name()
	{


		$uri		= ee()->uri->uri_string;
		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Get folders for later
		//	----------------------------------------

		$folders	= $this->data->get_message_folders_for_member( ee()->session->userdata('member_id') );

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Folder id?
		//	----------------------------------------

		if ( $this->dynamic === TRUE AND preg_match( "/\/folder\/(\d+\/*)/s", $uri, $match ) )
		{
			$uri_folder_id	= rtrim( $match['1'], '/' );
			$uri			= str_replace( $match['0'], '', $uri );
		}

		//	----------------------------------------
		//	Force for 'inbox'
		//	----------------------------------------

		if ( $this->dynamic === TRUE AND preg_match( "/\/folder\/inbox/s", $uri, $match ) )
		{
			$uri_folder_id	= 1;
			$uri			= str_replace( $match['0'], '', $uri );
		}

		//	----------------------------------------
		//	Folder id?
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('message_folder_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('message_id') ) === TRUE )
		{
			$folder_id	= ee()->TMPL->fetch_param('message_folder_id');
		}
		elseif ( ee()->TMPL->fetch_param('message_folder') !== FALSE AND
				 ee()->TMPL->fetch_param('message_folder') != '' )
		{
			$temp	= explode( "|", strtolower( ee()->TMPL->fetch_param('message_folder') ) );

			foreach ( $folders as $key => $val )
			{
				if ( in_array( strtolower( $val ), $temp ) === TRUE )
				{
					$folder_id	= $key;
				}
			}
		}
		elseif ( isset( $uri_folder_id ) === TRUE )
		{
			$folder_id	= $uri_folder_id;
		}

		//	----------------------------------------
		//	Does this folder id exist?
		//	----------------------------------------

		if ( isset( $folder_id ) === FALSE OR
			 empty( $folders[ $folder_id ] ) ) return $this->no_results('friends');

		//	----------------------------------------
		//	Does this folder id exist?
		//	----------------------------------------

		$cond['friends_message_folder_name']	= $folders[ $folder_id ];

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

		$tagdata	= str_replace( LD.'friends_message_folder_name'.RD, $folders[ $folder_id ], $tagdata );

		return $tagdata;
	}

	/*	End message folder name */

	// --------------------------------------------------------------------

	/**
	 * Message folders
	 *
	 * This presents message folders.
	 *
	 * @variables	{friends_folder}, {friends_folder_id},
	 * {friends_folder_read_count}, {friends_folder_total}, {friends_folder_unread_count}
	 *
	 * @access		public
	 * @return		string
	 */

	function message_folders()
	{
		//	----------------------------------------
		//	Logged in?
		//	----------------------------------------

		if ( ee()->session->userdata('member_id') == 0 )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Get folders
		//	----------------------------------------

		$temp	= $this->data->get_message_folders_for_member( ee()->session->userdata('member_id') );

		foreach ( $temp as $key => $val )
		{
			$folders[ $key ]	= array(
				'friends_message_folder_id'		=> $key,
				'friends_message_folder'		=> $val
			);
		}

		//	----------------------------------------
		//	Get folder stats from DB
		//	----------------------------------------

		$sql	= "SELECT 	message_folder, message_read, message_deleted
				   FROM 	exp_message_copies
				   WHERE 	recipient_id = ".ee()->db->escape_str( ee()->session->userdata('member_id') )."
				   AND 		message_folder
				   IN 		( ".implode( ',', array_keys( $temp ) )." )";

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Load array
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			$message_folder	= ( $row['message_deleted'] == 'y' ) ? 0: $row['message_folder'];

			if ( $row['message_read'] == 'n' )
			{
				$folders[ $message_folder ]['friends_message_folder_unread_count'][]	= $row['message_read'];
			}
			else
			{
				$folders[ $message_folder ]['friends_message_folder_read_count'][]		= $row['message_read'];
			}

			$folders[ $message_folder ]['friends_message_folder_total'][]	= $row['message_read'];
			$folders[ $message_folder ]['friends_message_folder_id']	= $message_folder;
			$folders[ $message_folder ]['friends_message_folder']	= $temp[ $message_folder ];
		}

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		$r	= '';

		foreach ( $folders as $row )
		{

			$row['friends_message_folder_read_count']		= ( isset( $row['friends_message_folder_read_count'] ) === TRUE ) ? count( $row['friends_message_folder_read_count'] ): 0;
			$row['friends_message_folder_total']			= ( isset( $row['friends_message_folder_total'] ) === TRUE ) ? count( $row['friends_message_folder_total'] ): 0;
			$row['friends_message_folder_unread_count']	= ( isset( $row['friends_message_folder_unread_count'] ) === TRUE ) ? count( $row['friends_message_folder_unread_count'] ): 0;

			$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $row );
			$tagdata	= $this->_parse_switch( $tagdata );

			foreach ( $row as $key => $val )
			{
				if ( strpos( $tagdata, LD.$key ) === FALSE ) continue;

				$tagdata	= str_replace( LD.$key.RD, $val, $tagdata );
			}

			$r	.= $tagdata;
		}

		return $r;
	}

	/*	End message folders */

	// --------------------------------------------------------------------

	/**
	 * Message form
	 *
	 * @access	public
	 * @return	string
	 */

	function message_form()
	{
		$act		= ee()->functions->fetch_action_id('Friends', 'send_message');

		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Reply mode
		//	----------------------------------------

		$reply_mode		= $this->check_yes( ee()->TMPL->fetch_param('reply_mode') );

		//	----------------------------------------
		//	Reply mode
		//	----------------------------------------

		$forward_mode	= $this->check_yes( ee()->TMPL->fetch_param('forward_mode') );

		//	----------------------------------------
		//	Direct message to friend?
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('friend_id') !== FALSE AND
			 ( is_numeric( ee()->TMPL->fetch_param('friend_id') ) === TRUE ) )
		{
			$member_id	= ee()->TMPL->fetch_param('friend_id');
		}
		elseif ( ee()->TMPL->fetch_param('message_id') !== FALSE AND
				( is_numeric( ee()->TMPL->fetch_param('message_id') ) === TRUE ) )
		{
			$message_id	= ee()->TMPL->fetch_param('message_id');
		}
		elseif ( preg_match( "/\/(\d+)/s", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			// ----------------------------------------
			// In reply mode, the number in the URI is assumed to be a message id
			// ----------------------------------------

			if ( $reply_mode OR $forward_mode )
			{
				$message_id	= $match['1'];
			}
			else
			{
				$member_id	= $match['1'];
			}
		}

		//	----------------------------------------
		//	Parse friends
		//	----------------------------------------

		if ( preg_match( "/".LD."members".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."members".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->members();

			$tagdata	= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );
		}
		elseif ( preg_match( "/".LD."friends".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."friends".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->mine();

			$tagdata	= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );
		}
		elseif ( preg_match( "/".LD."invites".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."invites".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->invites();

			$tagdata	= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );
		}
		elseif ( preg_match( "/".LD."confirmed".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."confirmed".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->group_members_confirmed();

			$tagdata	= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );
		}
		elseif ( preg_match( "/".LD."requests".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."requests".RD."/s", ee()->TMPL->tagdata, $match ) )
		{
			$this->tagdata	= $match['1'];

			$this->tagdata	= $this->group_membership_requests();

			$tagdata	= str_replace( $match['0'], $this->tagdata, ee()->TMPL->tagdata );
		}
		else
		{
			$tagdata	= ee()->TMPL->tagdata;
		}

		//	----------------------------------------
		//	Parse groups
		//	----------------------------------------

		if ( preg_match( "/".LD."friends_groups".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."friends_groups".RD."/s", $tagdata, $match ) )
		{
			$friends	= TRUE;

			$this->tagdata	= $this->_groups( $match['1'], FALSE );	// Show groups to which this member belongs

			$tagdata	= str_replace( $match['0'], $this->tagdata, $tagdata );
		}

		//	----------------------------------------
		//	If we have a message id, show only that message, limit 1
		//	----------------------------------------

		if ( ! empty( $message_id )  )
		{
			$sql	= "SELECT 		md.message_date			AS friends_message_date,
									md.message_subject 		AS friends_message_subject,
									md.message_body 		AS friends_message,
									mc.message_id 			AS friends_message_id,
									mc.copy_id,
									mc.sender_id,
									mc.message_read 		AS friends_message_read,
									mc.message_time_read 	AS friends_message_time_read,
									mc.message_folder 		AS friends_message_folder,
									mc.message_status 		AS friends_message_status
					   FROM		 	exp_message_data md
					   LEFT JOIN 	exp_message_copies mc
					   ON 			md.message_id = mc.message_id
					   LEFT JOIN 	exp_members m
					   ON 			m.member_id = md.sender_id
					   WHERE 		mc.message_deleted = 'n'
					   AND 			mc.recipient_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "
					   AND 			mc.message_id = " . ee()->db->escape_str( $message_id ) . "
					   LIMIT 		1";

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				return $this->no_results('friends');
			}

			$sender_id	= $query->row('sender_id');

			$tagdata	= $this->_parse_message_data( $query->row_array(), $tagdata );
		}

		//	----------------------------------------
		//	Prep data
		//	----------------------------------------

		$this->arr['ACT']				= $act;

		$this->arr['RET']				= (isset($_POST['RET'])) ?
											$_POST['RET'] : ee()->functions->fetch_current_uri();

		$this->arr['form_id']			= ( ee()->TMPL->fetch_param('form_id') ) ?
											ee()->TMPL->fetch_param('form_id'): 'message_form';

		$this->arr['form_name']			= ( ee()->TMPL->fetch_param('form_name') ) ?
											ee()->TMPL->fetch_param('form_name'): 'message_form';

		$this->arr['return']			= ( ee()->TMPL->fetch_param('return') ) ? ee()->TMPL->fetch_param('return'): '';

		$this->arr['link']				= ( ee()->TMPL->fetch_param('link') ) ? ee()->TMPL->fetch_param('link'): '';

		$this->arr['notification_template']	= ( ee()->TMPL->fetch_param('notification_template') ) ?
											ee()->TMPL->fetch_param('notification_template'): '';

		if (ee()->TMPL->fetch_param('reply_email'))
		{
		    $param_id = $this->_insert_params(
		        array(
		            'reply_email' => ee()->TMPL->fetch_param('reply_email')
		            ));

		    if ($param_id !== FALSE)
		    {
		        $this->arr['params_id'] = $param_id;
		    }
		}


		if ( ! $forward_mode AND ! empty( $sender_id ) )
		{
			$this->arr['friend[]']	= $sender_id;
		}
		else if ( ! $forward_mode AND ! empty( $member_id ) )
		{
			$this->arr['friend[]']	= $member_id;
		}

		//	----------------------------------------
		//	Declare form
		//	----------------------------------------

		$this->arr['tagdata']	= $tagdata;

		return $this->_form();
	}

	/* End message form */

	// --------------------------------------------------------------------

	/**
	 * Message move
	 *
	 * Marks private messages as deleted.
	 *
	 * @access		public
	 * @return		string
	 */

	function message_move()
	{
		$tagdata	= ee()->TMPL->tagdata;

		//	----------------------------------------
		//	Ajax mode?
		//	----------------------------------------

		if ( ee()->input->post('friends_ajax') !== FALSE AND ee()->input->post('friends_ajax') == 'yes' )
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
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Message id?
		//	----------------------------------------

		$message_id	= array();

		if ( ee()->TMPL->fetch_param('message_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('message_id') ) === TRUE )
		{
			$message_id[]	= ee()->TMPL->fetch_param('message_id');
		}
		elseif ( preg_match( "/\/(\d+)/s", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			$message_id[]	= $match['1'];
		}
		elseif ( empty( $_POST['friends_message_id'] ) === FALSE )
		{
			$message_id	= $_POST['friends_message_id'];
		}

		//	----------------------------------------
		//	Folder id?
		//	----------------------------------------

		$folder_id	= '';

		if ( ee()->TMPL->fetch_param('message_folder_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('message_folder_id') ) === TRUE )
		{
			$folder_id	= ee()->TMPL->fetch_param('message_folder_id');
		}
		elseif ( preg_match( "/\/message_folder(\d+)/s", ee()->uri->uri_string, $match ) AND $this->dynamic === TRUE )
		{
			$folder_id	= $match['1'];
		}
		elseif ( isset( $_POST['friends_message_folder_id'] ) === TRUE )
		{
			$folder_id	= $_POST['friends_message_folder_id'];
		}
		else
		{
			$tagdata	= ee()->functions->prep_conditionals(
				$tagdata,
				array( 'failure' => TRUE, 'success' => FALSE )
			);
			return str_replace( LD."friends_message".RD, $this->_prep_message( lang('no_folder_id') ), $tagdata );
		}

		//	----------------------------------------
		//	Move now
		//	----------------------------------------

		if ( $this->_message_move( $message_id, $folder_id ) === FALSE )
		{
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, array( 'failure' => TRUE, 'success' => FALSE ) );
			return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
		}

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$cond['failure']	= FALSE;
		$cond['success']	= TRUE;

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return str_replace( LD."friends_message".RD, $this->_prep_message(), $tagdata );
	}

	/* End message move */

	// --------------------------------------------------------------------

	/**
	 * Message move sub
	 *
	 * Marks private messages as deleted.
	 *
	 * @access		private
	 * @return		string
	 */

	function _message_move( $message_id = array(), $folder_id = '' )
	{
		//	----------------------------------------
		//	Valid messages?
		//	----------------------------------------

		if ( ( $message_id = $this->_only_numeric( $message_id ) ) === FALSE OR
			 empty( $message_id ) === TRUE )
		{
			$this->message[]	= lang('no_message_ids_for_move');

			return FALSE;
		}

		//	----------------------------------------
		//	Get message folders
		//	----------------------------------------

		$folders	= $this->data->get_message_folders_for_member( ee()->session->userdata('member_id') );

		if ( empty( $folders[$folder_id] ) === TRUE )
		{
			$this->message[]	= lang('folder_not_exists');

			return FALSE;
		}
		else
		{
			$folder	= $folders[$folder_id];
		}

		//	----------------------------------------
		//	Check against DB
		//	----------------------------------------

		$sql	= 'SELECT 	copy_id, message_id, message_deleted, message_folder
				   FROM 	exp_message_copies
				   WHERE 	recipient_id = ' . ee()->db->escape_str( ee()->session->userdata('member_id') ) . '
				   AND 		message_id
				   IN 		('.implode( ',', $message_id ).')';

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			$this->message[]	= lang('no_message_ids_from_db_for_move');

			return FALSE;
		}

		//	----------------------------------------
		//	Get valid copy ids
		//	----------------------------------------

		$copy_id	= array();
		$moved		= 0;
		$deleted	= 0;

		foreach ( $query->result_array() as $row )
		{
			// ----------------------------------------
			// Count the number of messages already in the trash
			// ----------------------------------------

			if ( $folder_id == 0 AND $row['message_deleted'] == 'y' )
			{
				$deleted++;
			}
			elseif ( $row['message_folder'] == $folder_id )
			{
				$moved++;
			}
			else
			{
				$copy_id[]	= $row['copy_id'];
			}
		}

		//	----------------------------------------
		//	Any already deleted?
		//	----------------------------------------

		if ( ( $deleted === 1 OR $moved === 1 ) AND count( $copy_id ) === 0 )
		{
			$this->message[]	= str_replace( '%folder%', $folder, lang('your_message_already_moved') );

			return FALSE;
		}
		elseif ( $deleted === 1 OR $moved === 1 )
		{
			$this->message[]	= str_replace( '%folder%', $folder, lang('message_already_moved') );
		}
		elseif ( $deleted > 1 )
		{
			$this->message[]	= str_replace(
				array( '%count%', '%folder%' ),
				array( $deleted, $folder ),
				lang('messages_already_moved')
			);

			if ( count( $copy_id ) === 0 ) return FALSE;
		}
		elseif ( $moved > 1 )
		{
			$this->message[]	= str_replace(
				array( '%count%', '%folder%' ),
				array( $moved, $folder ),
				lang('messages_already_moved')
			);

			if ( count( $copy_id ) === 0 ) return FALSE;
		}

		//	----------------------------------------
		//	Move the remainder
		//	----------------------------------------

		if ( count( $copy_id ) === 0 )
		{
			$this->message[]	= lang('no_message_ids_remain_for_move');

			return FALSE;
		}

		$message_deleted	= ( $folder_id == 0 ) ? 'y': 'n';

		$sql	= 'UPDATE 	exp_message_copies
				   SET 		message_deleted = \''.$message_deleted.'\',
							message_folder = '.ee()->db->escape_str( $folder_id ).'
				   WHERE 	copy_id
				   IN 		('.implode( ',', $copy_id ).')';

		ee()->db->query( $sql );

		if ( ee()->db->affected_rows() === 1 )
		{
			$this->message[]	= str_replace( '%folder%', $folder, lang('message_moved') );
		}
		else
		{
			$this->message[]	= str_replace(
				array( '%count%', '%folder%' ),
				array( ee()->db->affected_rows(), $folder ),
				lang('messages_moved')
			);
		}

		return TRUE;
	}

	/* End message move sub */

	// --------------------------------------------------------------------

	/**
	 * Messages
	 *
	 * This presents private messages.
	 *
	 * @variables	{friends_message_date}, {friends_message_subject}, {friends_message}, {friends_message_id},
	 * {friends_message_read}, {friends_message_time_read}, {friends_message_folder}, {friends_message_status}
	 *
	 * @access		public
	 * @return		string
	 */

	function messages()
	{
		$uri	= ee()->uri->uri_string;

		//	----------------------------------------
		//	Get folders for later
		//	----------------------------------------

		$folders	= $this->data->get_message_folders_for_member( ee()->session->userdata('member_id') );

		//	----------------------------------------
		//	Dynamic
		//	----------------------------------------

		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Folder id?
		//	----------------------------------------

		if ( $this->dynamic === TRUE AND preg_match( "/\/folder\/(\d+\/*)/s", $uri, $match ) )
		{
			$uri_folder_id	= rtrim( $match['1'], '/' );
			$uri			= str_replace( $match['0'], '', $uri );
		}

		//	----------------------------------------
		//	Force for 'inbox'
		//	----------------------------------------

		if ( $this->dynamic === TRUE AND preg_match( "/\/folder\/inbox/s", $uri, $match ) )
		{
			$uri_folder_id	= 1;
			$uri			= str_replace( $match['0'], '', $uri );
		}

		//	----------------------------------------
		//	Folder id?
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('message_folder_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('message_id') ) === TRUE )
		{
			$folder_id[]	= ee()->TMPL->fetch_param('message_folder_id');
		}
		elseif ( ee()->TMPL->fetch_param('message_folder') !== FALSE AND
				 ee()->TMPL->fetch_param('message_folder') != '' )
		{
			$temp	= explode( "|", strtolower( ee()->TMPL->fetch_param('message_folder') ) );

			foreach ( $folders as $key => $val )
			{
				if ( in_array( strtolower( $val ), $temp ) === TRUE )
				{
					$folder_id[]	= $key;
				}
			}
		}
		elseif ( isset( $uri_folder_id ) === TRUE )
		{
			$folder_id[]	= $uri_folder_id;
		}

		//	----------------------------------------
		//	Parse folders
		//	----------------------------------------

		if ( preg_match_all( "/".LD."friends_message_folders".RD."(.*?)".LD.
							preg_quote(T_SLASH, '/')."friends_message_folders".RD."/s", ee()->TMPL->template, $matches ) )
		{
			foreach ( array_keys( $matches[0] ) as $m )
			{
				$r	= '';

				foreach ( $folders as $key => $val )
				{
					$tdata	= $matches['1'][$m];

					$selected	= ( isset( $folder_id ) === TRUE AND
									in_array( $key, $folder_id ) === TRUE ) ? 'selected="selected"' : '';

					$tdata	= ee()->functions->prep_conditionals(
						$tdata,
						array(
							'friends_message_folder_selected' 	=> $selected,
							'friends_message_folder_id' 		=> $key,
							'friends_message_folder' 			=> $val
						)
					);

					$tdata	= str_replace(
						array(
							LD.'friends_message_folder_id'.RD,
							LD.'friends_message_folder'.RD,
							LD.'friends_message_folder_selected'.RD
						),
						array( $key, $val, $selected ),
						$tdata
					);

					$r	.= $tdata;
				}

				ee()->TMPL->template	= str_replace( $matches['0'][$m], $r, ee()->TMPL->template );
			}
		}

		//	----------------------------------------
		//	Logged in?
		//	----------------------------------------

		if ( ee()->session->userdata('member_id') == 0 )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Message id?
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param('message_id') !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param('message_id') ) === TRUE )
		{
			$message_id	= ee()->TMPL->fetch_param('message_id');
		}
		elseif ( $this->dynamic === TRUE AND preg_match( "/\/(\d+)/s", $uri, $match ) )
		{
			$message_id	= $match['1'];
		}

		//	----------------------------------------
		//	If we have a message id, show only that message, limit 1
		//	----------------------------------------

		if ( ! empty( $message_id ))
		{
			$sql	= "SELECT		md.message_date AS friends_message_date,
									md.message_subject AS friends_message_subject,
									md.message_body AS friends_message,
									mc.message_id AS friends_message_id,
									mc.copy_id,
									mc.sender_id,
									mc.message_read AS friends_message_read,
									mc.message_time_read AS friends_message_time_read,
									mc.message_folder AS friends_message_folder,
									mc.message_status AS friends_message_status
					   FROM 		exp_message_data md
					   LEFT JOIN 	exp_message_copies mc
					   ON 			md.message_id = mc.message_id
					   LEFT JOIN 	exp_members m ON m.member_id = md.sender_id
					   WHERE 		mc.message_id != 0
					   AND 			mc.recipient_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') ) . "
					   AND 			mc.message_id = " . ee()->db->escape_str( $message_id ) . "
					   LIMIT 		1";

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				return $this->no_results('friends');
			}

			$r	= '';

			foreach ( $query->result_array() as $row )
			{
				if ( $row['friends_message_time_read'] == 0 )
				{
					$row['friends_message_time_read']	= ee()->localize->now;
					ee()->db->query(
						ee()->db->update_string(
							'exp_message_copies',
							array(
								'message_received'	=> 'y',
								'message_read' 		=> 'y',
								'message_time_read' => ee()->localize->now
							),
							array(
								'copy_id' => $row['copy_id']
							)
						)
					);
				}

				$r	.= $this->_parse_message_data( $row, ee()->TMPL->tagdata );
			}

			return $r;
		}

		//	----------------------------------------
		//	Did we end up with folder id?
		//	----------------------------------------

		if ( isset( $folder_id ) === FALSE OR count( $folder_id ) == 0 ) return $this->no_results('friends');

		//	----------------------------------------
		//	Get messages
		//	----------------------------------------

		$sql	= "SELECT 		md.message_id 			AS friends_message_id,
								md.message_date 		AS friends_message_date,
								md.message_subject 		AS friends_message_subject,
								md.message_body 		AS friends_message,
								mc.copy_id,
								mc.sender_id,
								mc.recipient_id,
								mc.message_read 		AS friends_message_read,
								mc.message_time_read 	AS friends_message_time_read,
								mc.message_folder 		AS friends_message_folder,
								mc.message_status 		AS friends_message_status
				   FROM 		exp_message_data md
				   LEFT JOIN 	exp_message_copies mc
				   ON 			md.message_id = mc.message_id
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = md.sender_id
				   WHERE 		mc.message_deleted != ''
				   AND 			mc.recipient_id = " . ee()->db->escape_str( ee()->session->userdata('member_id') );

		if ( $this->check_no(ee()->TMPL->fetch_param('show_read')) )
		{
			$sql	.= " AND mc.message_read = 'n'";
		}

		//	----------------------------------------
		//	Show sent messages?
		//	----------------------------------------
		// 	Don't show sent messages unless the
		//	template says to or we're looking at the
		//	sent folder, which is folder #2.
		//	----------------------------------------

		if ( $this->check_yes(ee()->TMPL->fetch_param('show_sent')) )
		{
			// Do nothing. Don't filter out any folders.
		}
		elseif ( empty( $folder_id ) === TRUE OR
				 in_array( 2, $folder_id ) === FALSE )
		{
			$sql	.= " AND mc.message_folder != 2";
		}

		if ( isset( $folder_id ) === FALSE OR
			 ( $trash = array_search( 0, $folder_id ) ) === FALSE )
		{
			$sql	.= " AND mc.message_deleted = 'n'";
		}

		if ( empty( $folder_id ) === FALSE )
		{
			$sql	.= " AND mc.message_folder IN (".implode( ',', $folder_id ).")";
		}

		$sql	.= " ORDER BY message_date DESC";

		// ----------------------------------------
		//	Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		// ----------------------------------------
		//	Run query
		// ----------------------------------------

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results('friends');
		}

		//	----------------------------------------
		//	Amass the member ids so that we can later grab all of their data from the DB at once and then parse as necessary
		//	----------------------------------------

		$this->member_ids	= $this->_get_db_ids( array( 'sender_id', 'recipient_id' ), $query );

		$r	= '';
		$i = 0;

		foreach ( $query->result_array() as $row )
		{
			if ( empty( $folders[ $row['friends_message_folder'] ] ) === FALSE )
			{
				$row['friends_message_folder']	= $folders[ $row['friends_message_folder'] ];
			}

			$i++;

			$row['friends_count']			= $i;
			$row['friends_total_results']	= $query->num_rows();

			$r	.= $this->_parse_message_data( $row, ee()->TMPL->tagdata );
		}

		// ----------------------------------------
		//	Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	/*	End messages */

	// --------------------------------------------------------------------

	/**
	 * Parse message data
	 *
	 * This parses a row of message data for messages().
	 *
	 * @access	private
	 * @return	string
	 */

	function _parse_message_data( $row = array(), $tagdata = '' )
	{
		if ( $tagdata == '' OR count( $row ) == 0 ) return '';

		//	----------------------------------------
		//	Prep typography
		//	----------------------------------------

		if ( strpos( $tagdata, LD.'friends_message'.RD ) !== FALSE )
		{
			if ( is_object( $this->TYPE ) === FALSE )
			{

				ee()->load->library('typography');
				ee()->typography->initialize();
				ee()->typography->convert_curly = FALSE;

				$this->TYPE =& ee()->typography;

				$this->TYPE->smileys		= FALSE;
				$this->TYPE->highlight_code	= TRUE;
			}

			$formatting['html_format']		= 'all';
			$formatting['auto_links']		= 'n';
			$formatting['allow_img_url']	= 'y';
			$formatting['text_format']		= ( ee()->TMPL->fetch_param('text_format') != '' ) ?
												ee()->TMPL->fetch_param('text_format') : 'none';
		}

		//	----------------------------------------
		//	Parse conditionals
		//	----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $row );

		$i	= 0;

		foreach ( ee()->TMPL->var_single as $key => $val )
		{
			$i++;

			if ( strpos( $key, 'format=' ) !== FALSE )
			{
				$full	= $key;
				$key	= preg_replace( "/(.*?)\s+format=[\"'](.*?)[\"']/s", '\1', $key );
				$dates[$key][$i]['format']	= $val;
				$dates[$key][$i]['full']	= $full;
			}

			if ( strpos( $key, 'switch=' ) === 0 )
			{
				$switch[]	= $val;
			}
		}

		foreach ( $row as $key => $val )
		{
			if ( strpos( $tagdata, LD.$key ) === FALSE ) continue;

			if ( empty( $dates ) === FALSE )
			{
				foreach ( $dates as $field => $date )
				{
					foreach ( $date as $k => $v )
					{
						if ( isset( $row[$field] ) === TRUE AND is_numeric( $row[$field] ) === TRUE )
						{
							$tagdata	= str_replace(
								LD.$v['full'].RD,
								$this->_parse_date( $v['format'],
									$row[$field]
								),
								$tagdata
							);
						}
					}
				}
			}

			if ( $key == 'friends_message' AND strpos( $tagdata, LD.'friends_message'.RD ) !== FALSE )
			{
				$val	= $this->TYPE->parse_type( $val, $formatting );
			}

			$tagdata	= str_replace( LD.$key.RD, $val, $tagdata );
		}

		//	----------------------------------------
		//	Parse sender info
		//	----------------------------------------

		unset( $this->params['backspace'] );

		if ( ( $member_data = $this->_parse_member_data( $row['sender_id'], $tagdata, 'friends_message_sender_' ) ) !== FALSE )
		{
			$tagdata	= $member_data;
		}

		//	----------------------------------------
		//	Parse recipient info
		//	----------------------------------------

		if ( preg_match_all( "/".LD."friends_message_recipients(.*?)".RD."(.*?)".LD.
								preg_quote(T_SLASH, '/')."friends_message_recipients".RD."/s", $tagdata, $matches ) )
		{
			if ( isset( $row['friends_message_id'] ) === FALSE )
			{
				foreach ( array_keys( $matches[0] ) as $key )
				{
					$tagdata	= str_replace( $matches['0'][$key], '', $tagdata );
				}

				return $tagdata;
			}

			$sql	= 'SELECT 	recipient_id
					   FROM 	exp_message_copies
					   WHERE 	sender_id != recipient_id
					   AND 		message_id = ' . ee()->db->escape_str( $row['friends_message_id'] );

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				foreach ( array_keys( $matches[0] ) as $key )
				{
					$tagdata	= str_replace( $matches['0'][$key], '', $tagdata );
				}

				return $tagdata;
			}

			$this->member_ids	= $this->_get_db_ids( array( 'recipient_id' ), $query );

			foreach ( array_keys( $matches[0] ) as $key )
			{
				$this->params		= ee()->functions->assign_parameters( $matches['1'][$key] );

				if ( ( $member_data = $this->_parse_member_data(
						$this->member_ids,
						$matches['2'][$key],
						'friends_message_recipient_' ) ) !== FALSE )
				{
					$tagdata	= str_replace( $matches['0'][$key], $member_data, $tagdata );
				}
				else
				{
					$tagdata	= str_replace( $matches['0'][$key], '', $tagdata );
				}
			}
		}

		//	----------------------------------------
		//	Parse sender info
		//	----------------------------------------

		if ( preg_match_all( "/".LD."friends_message_sender(.*?)".RD."(.*?)".LD.
				preg_quote(T_SLASH, '/')."friends_message_sender".RD."/s", $tagdata, $matches ) )
		{
			if ( isset( $row['friends_message_id'] ) === FALSE )
			{
				foreach ( array_keys( $matches[0] ) as $key )
				{
					$tagdata	= str_replace( $matches['0'][$key], '', $tagdata );
				}

				return $tagdata;
			}

			$this->member_ids	= array( $row['sender_id'] );

			foreach ( array_keys( $matches[0] ) as $key )
			{
				$this->params		= ee()->functions->assign_parameters( $matches['1'][$key] );

				if ( ( $member_data = $this->_parse_member_data(
						$this->member_ids, $matches['2'][$key], 'friends_message_sender_' ) ) !== FALSE )
				{
					$tagdata	= str_replace( $matches['0'][$key], $member_data, $tagdata );
				}
				else
				{
					$tagdata	= str_replace( $matches['0'][$key], '', $tagdata );
				}
			}
		}

		return $tagdata;
	}

	/*	End parse message data */

	// --------------------------------------------------------------------

	/**
	 * Recount private messages
	 *
	 * This accepts an array of member ids and recounts their private messages column.
	 *
	 * @access	private
	 * @return	boolean
	 */

	function _recount_private_messages( $members = array() )
	{
		if ( empty( $members ) ) return FALSE;

		$sql	= "SELECT 	recipient_id, COUNT(*) AS count
				   FROM 	exp_message_copies
				   WHERE 	message_read = 'n'
				   AND 		message_deleted = 'n'
				   AND 		recipient_id != sender_id
				   AND 		recipient_id
				   IN 		(" . implode( ',', $members ) . ")
				   GROUP BY recipient_id";

		$query	= ee()->db->query( $sql );

		$counts	= array();

		foreach ( $query->result_array() as $row )
		{
			$counts[ $row['recipient_id'] ]	= $row['count'];

			ee()->db->query(
				ee()->db->update_string(
					'exp_members',
					array(
						'private_messages'	=> $row['count']
					),
					array(
						'member_id'			=> $row['recipient_id']
					)
				)
			);
		}

		foreach ( $members as $id )
		{
			$count	= ( isset( $counts[$id] ) === TRUE ) ? $counts[$id] : 0;

			ee()->db->query(
				ee()->db->update_string(
					'exp_members',
					array(
						'private_messages'	=> $count
					),
					array(
						'member_id'			=> $id
					)
				)
			);
		}
	}

	/*	End recount private messages */

	// --------------------------------------------------------------------

	/**
	 * Send message
	 *
	 * @access	public
	 * @return	boolean
	 */

	function send_message()
	{
		//	----------------------------------------
		//	Not logged in?  Fail out gracefully.
		//	----------------------------------------

		if ( ee()->session->userdata['member_id'] == '0' )
		{
			return $this->_fetch_error( lang('not_logged_in') );
		}

		//	----------------------------------------
		//	General security
		//	----------------------------------------

		if ( $this->_security() === FALSE )
		{
			return;
		}

		//	----------------------------------------
		//	Member waiting period check
		//	----------------------------------------

		if ( ( $message_waiting_period = $this->data->get_preference_from_site_id(
				ee()->config->item( 'site_id' ), 'message_waiting_period' ) ) !== FALSE )
		{
			if ( ee()->session->userdata['join_date'] > ( ee()->localize->now - ( $message_waiting_period * 60 * 60 ) ) )
			{
				return $this->_fetch_error( lang('message_waiting_period_fail') );
			}
		}

		//	----------------------------------------
		//	Message throttling check
		//	----------------------------------------

		if ( ( $message_throttling = $this->data->get_preference_from_site_id( ee()->config->item( 'site_id' ), 'message_throttling' ) ) !== FALSE )
		{
			$query	= ee()->db->query(
				"SELECT COUNT(*) AS count
				 FROM 	exp_message_data
				 WHERE 	sender_id = " . ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
				 AND 	message_date >= " . ( ee()->localize->now - $message_throttling ) );

			if ( $query->row('count') > 0 )
			{
				return $this->_fetch_error(
					str_replace(
						'%seconds%',
						$message_throttling,
						lang('message_throttling_fail')
					)
				);
			}
		}

		//	----------------------------------------
		//	Message?
		//	----------------------------------------

		if ( empty( $_POST['friends_message'] ) === TRUE )
		{
			return $this->_fetch_error( lang('message_required') );
		}

		//	----------------------------------------
		//	Max message char check
		//	----------------------------------------

		if ( ( $max_message_chars = $this->data->get_preference_from_site_id(
						ee()->config->item( 'site_id' ), 'max_message_chars' ) ) !== FALSE )
		{
			if ( strlen( $_POST['friends_message'] ) > $max_message_chars )
			{
				return $this->_fetch_error( lang('max_message_chars_fail') );
			}
		}

		$message	= ee()->security->xss_clean( $_POST['friends_message'] );

		//	----------------------------------------
		//	Subject?
		//	----------------------------------------

		if ( empty( $_POST['friends_subject'] ) === TRUE )
		{
			$subject	= str_replace(
				'%screen_name%',
				ee()->session->userdata('screen_name'),
				lang( 'message_subject' )
			);
		}
		else
		{
			$subject	= ee()->security->xss_clean( $_POST['friends_subject'] );
		}

		//	----------------------------------------
		//	Build groups array
		//	----------------------------------------

		$groups	= array();

		if ( isset( $_POST['friends_group'] ) === TRUE )
		{
			if ( is_array( $_POST['friends_group'] ) === TRUE )
			{
				$groups	= $_POST['friends_group'];
			}
			else
			{
				$groups[]	= $_POST['friends_group'];
			}
		}

		//	----------------------------------------
		//	Build friends array
		//	----------------------------------------

		$friends	= array();

		if ( isset( $_POST['friend'] ) === TRUE )
		{
			if ( is_array( $_POST['friend'] ) === TRUE )
			{
				$friends	= $_POST['friend'];
			}
			else
			{
				$friends[]	= $_POST['friend'];
			}
		}

		if ( count( $friends ) == 0 AND count( $groups ) == 0 )
		{
			return $this->_fetch_error( lang('recipients_required') );
		}

		//	----------------------------------------
		//	Message day limit check
		//	----------------------------------------

		if ( ( $message_day_limit = $this->data->get_preference_from_site_id(
				ee()->config->item( 'site_id' ), 'message_day_limit' ) ) !== FALSE )
		{
			$query	= ee()->db->query(
				"SELECT 	COUNT(*) AS count
				 FROM 		exp_message_copies mc
				 LEFT JOIN 	exp_message_data md
				 ON 		md.message_id = mc.message_id
				 WHERE 		md.sender_id = " . ee()->db->escape_str( ee()->session->userdata( 'member_id' ) ) . "
				 AND 		message_date >= " . ( ee()->localize->now - 86400 ) );

			if ( $query->num_rows() > 0 AND $query->row('count') >= $message_day_limit )
			{
				return $this->_fetch_error( lang('message_day_limit_fail') );
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
		//	Gather and verify data from DB for friends
		//	----------------------------------------

		$temp	= array();

		if ( count( $friends ) > 0 )
		{
			//no idea why these are both there? this first one never gets to exist -gf
			/*$sql	= "SELECT 		m.*
					   FROM 		exp_members m
					   LEFT JOIN 	exp_friends f
					   ON 			f.friend_id = m.member_id
					   WHERE 		f.site_id = " . ee()->db->escape_str( ee()->config->item('site_id') ) . "
					   AND 			m.accept_messages = 'y'
					   AND 			f.block = 'n'
					   AND 			m.friends_opt_out = 'n'
					   AND 			m.member_id
					   IN 			('".implode( "','", $friends )."')";*/

			$sql	= "SELECT 	m.*
					   FROM 	exp_members m
					   WHERE 	m.accept_messages = 'y'
					   AND 		m.friends_opt_out = 'n'
					   AND 		m.member_id
					   IN 		('" . implode( "','", $friends ) . "')";

			$query	= ee()->db->query( $sql );

			foreach ( $query->result_array() as $row )
			{
				$temp[ $row['member_id'] ]	= $row;
			}
		}

		$friends	= $temp; unset( $temp );

		//	----------------------------------------
		//	Gather and verify data from DB for groups
		//	----------------------------------------

		if ( count( $groups ) > 0 )
		{
			$sql	= "SELECT 		m.*
					   FROM 		exp_members m
					   LEFT JOIN 	exp_friends_group_posts fgp
					   ON 			fgp.member_id = m.member_id
					   WHERE 		fgp.site_id = " . ee()->db->escape_str( ee()->config->item('site_id') ) . "
					   AND 			m.accept_messages = 'y'
					   AND 			fgp.declined = 'n'
					   AND 			m.friends_opt_out = 'n'
					   AND 			fgp.group_id
					   IN 			('" . implode( "','", $groups ) . "')";

			$query	= ee()->db->query( $sql );

			foreach ( $query->result_array() as $row )
			{
				$friends[ $row['member_id'] ]	= $row;
			}
		}

		//	----------------------------------------
		//	Remove the sender as a recipient
		//	----------------------------------------

		unset( $friends[ ee()->session->userdata('member_id') ] );

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

		if ( count( $friends ) == 0 )
		{
			return $this->_fetch_error( lang('no_valid_recipients') );
		}

		//	----------------------------------------
		//	Max recipients per message check
		//	----------------------------------------

		if ( ( $max_recipients_per_message = $this->data->get_preference_from_site_id(
				ee()->config->item( 'site_id' ), 'max_recipients_per_message' ) ) !== FALSE )
		{
			if ( count( $friends ) > $max_recipients_per_message )
			{
				return $this->_fetch_error( lang('max_recipients_per_message_fail') );
			}
		}

		//	----------------------------------------
		//	Make sure all recipients have message folders
		//	----------------------------------------

		$this->data->create_message_folders_for_members( array_keys( $friends ) );

		//	----------------------------------------
		//	Create primary message record
		//	----------------------------------------

		$data	= array(
			'sender_id'				=> ee()->session->userdata( 'member_id' ),
			'message_date'			=> ee()->localize->now,
			'message_subject'		=> $subject,
			'message_body'			=> $message,
			'message_recipients'	=> implode( '|', array_keys( $friends ) ),
			'total_recipients'		=> count( $friends ),
			'message_sent_copy'		=> 'y',
			'message_status'		=> 'sent'
		);

		ee()->db->query( ee()->db->insert_string( 'exp_message_data', $data ) );

		$message_id	= ee()->db->insert_id();

		//	----------------------------------------
		//	Create message copies
		//	----------------------------------------

		$data	= array(
			'message_id'			=> $message_id,
			'sender_id'				=> ee()->session->userdata( 'member_id' ),
			'message_authcode'		=> ee()->functions->random('alpha', 10)
		);

		foreach ( $friends as $id => $row )
		{
			$data['recipient_id']	= $id;

			ee()->db->query( ee()->db->insert_string( 'exp_message_copies', $data ) );
		}

		//	----------------------------------------
		//	Save a copy for sender
		//	----------------------------------------

		$data['recipient_id']		= ee()->session->userdata( 'member_id' );
		$data['message_folder']		= 2;
		$data['message_received']	= 'y';
		$data['message_read']		= 'y';
		$data['message_time_read']	= ee()->localize->now;

		ee()->db->query( ee()->db->insert_string( 'exp_message_copies', $data ) );

		//	----------------------------------------
		//	Send notifications
		//	----------------------------------------

		$reply_email = ($this->_param('reply_email')) ? $this->_param('reply_email') : ee()->session->userdata['email'];

		$data	= array();

		if ( empty( $_POST['notification_template'] ) === FALSE )
		{
			$_POST['notification_template']	= ee()->security->xss_clean( $_POST['notification_template'] );

			foreach ( $friends as $id => $row )
			{
				$data['email']							= $row['email'];
				$data['notification_template']			= $_POST['notification_template'];
				$data['from_email']						= $reply_email;
				$data['from_name']						= ee()->session->userdata['screen_name'];
				$data['subject']						= $subject;
				$data['message']						= $message;
				$data['link']							= ( empty( $_POST['link'] ) === FALSE ) ?
															ee()->security->xss_clean( $_POST['link'] ) : '';
				$data['member_id']						= $id;
				$data['extra']['friends_message_id']	= $message_id;

				$this->_notify( $data );
			}
		}

		// ----------------------------------
		// Increment exp_members.private_messages
		// ----------------------------------

		$this->_recount_private_messages(
			array_merge(
				array_keys( $friends ),
				array( ee()->session->userdata('member_id') )
			)
		);

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Compose return message
		//	----------------------------------------

		if ( count( $friends ) > 1 )
		{
			$this->message[]	= str_replace( '%count%', count( $friends ), lang('messages_sent') );
		}
		else
		{
			$this->message[]	= str_replace( '%count%', count( $friends ), lang('message_sent') );
		}

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
	/* End send message */
}

/* End class */
