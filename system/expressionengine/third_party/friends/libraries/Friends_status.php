<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Status Class
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @filesource	friends/libraries/Friends_status.php
 */

require_once realpath(rtrim(dirname(__FILE__), '/') . '/../mod.friends.php');

class Friends_status extends Friends
{
	public $status_length	= 255;


	// --------------------------------------------------------------------

	/**
	 * Status
	 *
	 * This method returns all of the statuses posted by a given member
	 * if a member is specified or it returns all statuses of my friends
	 * when no member is specified.
	 *
	 * @access		public
	 * @return		string
	 */

	function status()
	{
		$this->dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		//	----------------------------------------
		//	Do we have a valid ID number?
		//	----------------------------------------

		$show_friends	= FALSE;

		if ( $this->_member_id() === FALSE )
		{
			if ( ( $this->member_id = ee()->session->userdata('member_id') ) == 0 )
			{
				return $this->no_results( 'friends' );
			}
			else
			{
				// We are logged in, but we haven't specified that we're
				// viewing ourself with username="CURRENT_USER"
				// so we assume we want to see our friends' statuses.
				$show_friends	= TRUE;
			}
		}

		//	----------------------------------------
		//	Get friends
		//	----------------------------------------
		// 	For the currently logged-in member viewing
		//	the page, get their friends list.
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

		if ( ee()->session->userdata['member_id'] != 0 )
		{
			$sql	.= " AND f.member_id = ".ee()->db->escape_str( ee()->session->userdata['member_id'] );
		}
		else
		{
			$sql	.= " AND f.member_id = ".ee()->db->escape_str( $this->member_id );
		}

		$query	= ee()->db->query( $sql );

		$reciprocal_friends	= array();

		if ( $query->num_rows() > 0 )
		{
			foreach( $query->result_array() as $row )
			{
				$this->friends[ $row['friend_id'] ]	= $row;

				if ( $row['friends_reciprocal'] == 'y' )
				{
					$reciprocal_friends[ $row['friend_id'] ]	= $row;
				}
			}
		}

		//	----------------------------------------
		//	Get statuses
		//	----------------------------------------

		$sql	= "SELECT 	fs.status_id 	AS friends_status_id,
							fs.member_id 	AS friends_member_id,
							fs.status 		AS friends_status,
							fs.status_date 	AS friends_status_date,
							private 		AS friends_private
				   FROM 	exp_friends_status fs
				   WHERE 	fs.site_id
				   IN 		(".implode( ',', ee()->TMPL->site_ids ).")";

		//	----------------------------------------
		//	Get statuses of friends or just the member?
		//	----------------------------------------

		if ( empty( $show_friends ) === TRUE )
		{
			$sql	.= " AND fs.member_id = ".ee()->db->escape_str( $this->member_id );
		}
		else
		{
			$friend_ids	= $this->data->get_friend_ids_from_member_id( ee()->TMPL->site_ids, $this->member_id );

			if ( count( $friend_ids ) == 0 )
			{
				return $this->no_results( 'friends' );
			}

			$sql	.= " AND fs.member_id IN ( " . implode( ",", $friend_ids ) . " )";
		}

		//	----------------------------------------
		//	Privacy
		//	----------------------------------------
		// 	If we're looking at someone else's statuses,
		//	we need to only view their public statuses
		//	unless they are our reciprocal friends
		//	----------------------------------------

		if ( $show_friends === TRUE OR
			 $this->member_id != ee()->session->userdata('member_id') )
		{
			$friends_check	= "";

			if ( count( $reciprocal_friends ) > 0 )
			{
				$friends_check	= " OR fs.member_id IN (".implode( ',', array_keys( $reciprocal_friends ) ).") ";
			}

			$sql	.= " AND ( fs.private = 'n'".$friends_check." )";
		}

		//	----------------------------------------
		//	Order
		//	----------------------------------------

		$sql	.= " ORDER BY status_date DESC";

		// ----------------------------------------
		//  Prep pagination
		// ----------------------------------------

		$sql	= $this->_prep_pagination( $sql );

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$query	= ee()->db->query( $sql );

		$member_ids	= $this->_get_db_ids( array( 'friends_member_id' ), $query );

		if ( count( $member_ids ) == 0 )
		{
			return $this->no_results('friends');
		}

		$this->member_ids	= $member_ids;

		//	----------------------------------------
		//	Loop and parse
		//	----------------------------------------

		$r		= '';
		$count	= 0;

		foreach ( $query->result_array() as $row )
		{
			$count++;

			$row['friends_count'] = $count;

			$tagdata	= ee()->TMPL->tagdata;

			$tagdata	= $this->_parse_member_data( $row['friends_member_id'], $tagdata, 'friends_', array(), FALSE );

			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $row );
			$tagdata	= $this->_parse_switch( $tagdata );

			foreach ( ee()->TMPL->var_single as $key => $val )
			{
				// ----------------------------------------
				//	Parse status date variable
				// ----------------------------------------

				if ( isset( $row['friends_status_date'] ) === TRUE AND
					 strpos( $tagdata, 'format=' ) !== FALSE AND
					 strpos( $key, 'friends_status_date' ) !== FALSE )
				{
					$tagdata	= str_replace( LD.$key.RD, $this->_parse_date( $val, $row['friends_status_date'] ), $tagdata );
				}

				// ----------------------------------------
				//	Parse all
				// ----------------------------------------

				if ( isset( $row[$key] ) === TRUE )
				{
					$tagdata	= str_replace( LD.$key.RD, $row[$key], $tagdata );
				}
			}

			$r	.= $tagdata;
		}

		// ----------------------------------------
		// Parse pagination
		// ----------------------------------------

		return $this->_parse_pagination( $r );
	}

	//	End status

	// --------------------------------------------------------------------

	/**
	 * Status delete
	 *
	 * Delete a status as long as it's yours.
	 *
	 * @access		public
	 * @return		string
	 */

	function status_delete()
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

		$status_id	= '';

		if ( ee()->TMPL->fetch_param( 'status_id' ) !== FALSE AND
			 is_numeric( ee()->TMPL->fetch_param( 'status_id' ) ) === TRUE )
		{
			$status_id	= ee()->TMPL->fetch_param( 'status_id' );
		}
		elseif ( preg_match( '/\/(\d+)/s', ee()->uri->uri_string, $match ) )
		{
			$status_id	= $match['1'];
		}
		else
		{
			return $this->_fetch_error( lang('no_status_id') );
		}

		//	----------------------------------------
		//	Ownership?
		//	----------------------------------------

		if ( $this->data->get_member_id_from_status_id($status_id) != ee()->session->userdata('member_id') )
		{
			return $this->_fetch_error( lang('not_your_status') );
		}

		// ----------------------------------------
		//	Extension
		// ----------------------------------------

		if (ee()->extensions->active_hook('friends_status_delete_status') === TRUE)
		{
			$data	= ee()->extensions->universal_call( 'friends_status_delete_status', $this, $data );
			if ( ee()->extensions->end_script === TRUE ) exit();
		}

		//	----------------------------------------
		//	Delete
		//	----------------------------------------

		ee()->db->query(
			"DELETE FROM 	exp_friends_status
			 WHERE 			status_id = " . ee()->db->escape_str( $status_id )
		);

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$this->message[]			= lang('status_deleted');
		$data['friends_message']	= $this->_prep_message();

		//	----------------------------------------
		//	Parse
		//	----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $data );
		return str_replace( LD . 'friends_message' . RD, $this->message[0], $tagdata );
	}

	/*	End status delete */

	// --------------------------------------------------------------------

	/**
	 * Status form
	 *
	 * This method creates a form that can be used to post a status update to one's profile.
	 *
	 * @access		public
	 * @return		string
	 */

	function status_form()
	{
		$act	= ee()->functions->fetch_action_id('Friends', 'status_update');

		//	----------------------------------------
		//	Logged in?
		//	----------------------------------------

		if ( ( $this->member_id = ee()->session->userdata('member_id') ) == 0 )
		{
			return $this->no_results( 'friends' );
		}

		//	----------------------------------------
		//	Prep data
		//	----------------------------------------

		$this->arr['ACT']				= $act;

		$this->arr['RET']				= (isset($_POST['RET'])) ?
											$_POST['RET'] : ee()->functions->fetch_current_uri();

		$this->arr['form_id']			= ( ee()->TMPL->fetch_param('form_id') ) ?
											ee()->TMPL->fetch_param('form_id') : 'friends_status_form';

		$this->arr['form_class']		= ( ee()->TMPL->fetch_param('form_class') ) ?
											ee()->TMPL->fetch_param('form_class') : '';

		$this->arr['form_name']			= ( ee()->TMPL->fetch_param('form_name') ) ?
											ee()->TMPL->fetch_param('form_name') : 'friends_status_form';

		$this->arr['return']			= ( ee()->TMPL->fetch_param('return') ) ?
											ee()->TMPL->fetch_param('return') : '';

		$this->arr['template']			= ( ee()->TMPL->fetch_param('template') ) ?
											ee()->TMPL->fetch_param('template') : '';

		//	----------------------------------------
		//	Parse member data
		//	----------------------------------------

		$tagdata	= $this->_parse_member_data( $this->member_id, ee()->TMPL->tagdata );

		//	----------------------------------------
		//	Declare form
		//	----------------------------------------

		$this->arr['tagdata']	= $tagdata;

		return  $this->_form();
	}

	//	End status form

	// --------------------------------------------------------------------

	/**
	 * Status update
	 *
	 * This method processes the submission that comes from the status form.
	 *
	 * @access		public
	 * @return		string
	 */

	function status_update()
	{
		$data	= array();

		$_POST	= ee()->security->xss_clean( $_POST );

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
		//	Status present?
		//	----------------------------------------

		if ( empty( $_POST['friends_status'] ) === TRUE )
		{
			return $this->_fetch_error( lang('no_status') );
		}

		//	----------------------------------------
		//	Status length?
		//	----------------------------------------

		if ( strlen( $_POST['friends_status'] ) > $this->status_length )
		{
			return $this->_fetch_error(
				str_replace(
					'%count%',
					$this->status_length,
					lang('status_too_long')
				)
			);
		}

		$data['status']	= $_POST['friends_status'];

		//	----------------------------------------
		//	Private?
		//	----------------------------------------

		$data['private']	= 'n';

		if ( empty( $_POST['friends_status_private'] ) === FALSE AND
					$this->check_yes($_POST['friends_status_private']) )
		{
			$data['private']	= 'y';
		}

		//	----------------------------------------
		//	Prepare insert
		//	----------------------------------------

		$data['status_date']	= ee()->localize->now;
		$data['member_id']		= $this->member_id;
		$data['group_id']		= ee()->session->userdata('group_id');
		$data['site_id']		= ee()->config->item('site_id');

		// ----------------------------------------
		//	Extension
		// ----------------------------------------

		if (ee()->extensions->active_hook('friends_status_update_status') === TRUE)
		{
			$data	= ee()->extensions->universal_call( 'friends_status_update_status', $this, $data );
			if ( ee()->extensions->end_script === TRUE ) exit();
		}

		//	----------------------------------------
		//	Insert
		//	----------------------------------------

		ee()->db->query( ee()->db->insert_string( 'exp_friends_status', $data ) );

		//	----------------------------------------
		//	Prep cond
		//	----------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;

		//	----------------------------------------
		//	Prep message
		//	----------------------------------------

		$this->message[]			= lang('status_update_submitted');
		$data['friends_message']	= $this->_prep_message();

		//	----------------------------------------
		//	Prep return
		//	----------------------------------------

		$return	= $this->_prep_return();

		//	----------------------------------------
		//	Are we using a template?
		//	----------------------------------------

		if ( ! $body = $this->_fetch_template( '', $data ) )
		{
			// ----------------------------------------
			// Return
			// ----------------------------------------

			$return	= $this->_chars_decode( $return );

			ee()->functions->redirect( $return );

			exit();
		}
		else
		{
			return $body;
		}
	}

	/*	End status update */
}

/*	End class */
?>