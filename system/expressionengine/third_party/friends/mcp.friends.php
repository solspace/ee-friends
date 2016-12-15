<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Friends - Control Panel
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @version		1.6.5
 * @filesource	friends/mcp.friends.php
 */

require_once 'addon_builder/module_builder.php';

class Friends_mcp extends Module_builder_friends
{

	private $row_limit = 50;

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	bool		Enable calling of methods based on URI string
	 * @return	string
	 */

	public function __construct( $switch = TRUE )
	{
		parent::__construct();

		//keeps us from calling this a bajallion times
		$this->clean_site_id = ee()->db->escape_str( ee()->config->item( 'site_id' ) );

		if ((bool) $switch === FALSE) return; // Install or Uninstall Request

		// --------------------------------------------
		//  Module Menu Items
		// --------------------------------------------

		$menu	= array(
			'module_home'			=> array(
				'link'  => $this->base,
				'title' => lang('home')
			),
			'module_members' 		=> array(
				'link'  => $this->base . AMP . 'method=members',
				'title' => lang('members')
			),
			'module_groups'			=> array(
				'link'  => $this->base . AMP . 'method=groups',
				'title' => lang('groups')
			),
			'module_preferences'	=> array(
				'link'  => $this->base . AMP . 'method=preferences',
				'title' => lang('preferences')
			),
			'module_demo_templates'		=> array(
				'link'	=> $this->base.'&method=code_pack',
				'title'	=> lang('demo_templates'),
			),
			'module_documentation'	=> array(
				'link'  => FRIENDS_DOCS_URL,
				'title' => lang('online_documentation'),
				'new_window' => TRUE
			),
		);

		//$this->cached_vars['module_menu_highlight'] = 'module_home';
		$this->cached_vars['lang_module_version'] 	= lang('friends_module_version');
		$this->cached_vars['module_version'] 		= FRIENDS_VERSION;
		$this->cached_vars['module_menu'] 			= $menu;

		//needed for header.html file views
		$this->cached_vars['js_magic_checkboxes']	= $this->js_magic_checkboxes();

		// --------------------------------------------
		//  Sites
		// --------------------------------------------

		$this->cached_vars['sites']	= array();

		foreach($this->data->get_sites() as $site_id => $site_label)
		{
			$this->cached_vars['sites'][$site_id] = $site_label;
		}
	}
	// END Friends_cp_base()

	//----------------------------------------------------------------------------------------
	// begin views
	//----------------------------------------------------------------------------------------


	// --------------------------------------------------------------------

	/**
	 * Module's Main Homepage
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function index($message='')
	{
		if ($message == '' && isset($_GET['msg']))
		{
			$message = lang($_GET['msg']);
		}

		return $this->home($message);
	}
	// END home()


	// --------------------------------------------------------------------

	/**
	 * Home
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function home( $message = '' )
	{
		// -------------------------------------
		//  Messages,  and Crumbs
		// -------------------------------------

		//message
		if ($message == '' AND ! in_array(ee()->input->get('msg'), array(FALSE, '')) )
		{
			$message = lang(ee()->input->get('msg'));
		}

		$this->cached_vars['message'] = $message;

		$this->add_crumb( lang('home') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_home';

		//	----------------------------------------
		//	Prep stats
		//	----------------------------------------

		$friends	= ee()->db->query(
			"SELECT DISTINCT 	member_id
			 FROM 				exp_friends
			 WHERE 				block 		= 'n'
			 AND 				friend_id 	!= 0
			 AND 				site_id 	= " . ee()->db->escape_str( ee()->config->item( 'site_id' ) )
		);

		$r_friends	= ee()->db->query(
			"SELECT 			COUNT(*) 	AS count
			 FROM 				exp_friends
			 WHERE 				reciprocal 	= 'y'
			 AND 				friend_id 	!= 0
			 AND 				site_id 	= " . ee()->db->escape_str( ee()->config->item( 'site_id' ) )
		);

		$b_friends	= ee()->db->query(
			"SELECT 			COUNT(*) 	AS count
			 FROM 				exp_friends
			 WHERE 				block 		= 'y'
			 AND 				friend_id 	!= 0
			 AND 				site_id 	= " . ee()->db->escape_str( ee()->config->item( 'site_id' ) )
		);

		$members	= ee()->db->query(
			"SELECT 			total_members
			 FROM 				exp_stats"
		);

		$p_friends	= round( ( $friends->num_rows() / $members->row('total_members') ) * 100, 2 );

		$top5		= ee()->db->query(
			"SELECT 			m.screen_name, COUNT(*) AS count
			 FROM 				exp_friends f
			 LEFT JOIN 			exp_members m
			 ON 				f.member_id = m.member_id
			 GROUP BY 			f.member_id
			 ORDER BY 			count DESC LIMIT 5"
		);

		$ranked		= array();

		if ( $top5->num_rows() > 0 )
		{
			foreach ( $top5->result_array() as $row )
			{
				$ranked[]	= $row['screen_name'];
			}
		}

		//	----------------------------------------
		//	Load vars
		//	----------------------------------------

		$this->cached_vars['version']					= constant(strtoupper($this->lower_name).'_VERSION');
		$this->cached_vars['total_friends']				= $friends->num_rows();
		$this->cached_vars['total_blocked_friends']		= $b_friends->row('count');
		$this->cached_vars['total_reciprocal_friends']	= $r_friends->row('count');
		$this->cached_vars['percent_participating']		= $p_friends.'%';
		$this->cached_vars['ranked']						= $ranked;

		$lang_items = array(
			'total_friends',
			'total_blocked_friends',
			'total_reciprocal_friends',
			'percent_participating',
			'top_5',
			'no_friends_saved',
			'no_entries_saved'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//  Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// --------------------------------------------
		//  Load page
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('home.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}

	//	End home


	// --------------------------------------------------------------------

	/**
	 * Delete Friend - Confirm
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function delete_friend_confirm($message = '')
	{
		// -------------------------------------
		//	Have any members been submitted?
		// -------------------------------------

		if ( empty( $_POST['toggle'] ) OR ! is_numeric(ee()->input->post('member_id')))
		{
			return $this->members();
		}

		// -------------------------------------
		//  Messages,  and Crumbs
		// -------------------------------------

		//message
		if ($message == '' AND ! in_array(ee()->input->get('msg'), array(FALSE, '')) )
		{
			$message = lang(ee()->input->get('msg'));
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  DATAAAAAAA
		// -------------------------------------

		$i								= 0;
		$this->cached_vars['item_id']	= ee()->input->post('member_id', TRUE);
		$this->cached_vars['items']		= array();

		foreach ( ee()->input->post('toggle') as $key => $val )
		{
			if ( is_numeric( $val ) )
			{
				$this->cached_vars['items'][]	= $val;
				$i++;
			}
		}

		if ( $i == 1 )
		{
			$replace[]	= $i;
			$replace[]	= 'friend';
		}
		else
		{
			$replace[]	= $i;
			$replace[]	= 'friends';
		}

		$this->cached_vars['question']	= str_replace(
			array( '%i%', '%count%' ),
			$replace,
			lang('friend_delete_question')
		);

		$this->cached_vars['method']	= 'delete_friend';

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('members'), $this->base . AMP . 'method=members' );
		$this->add_crumb( lang('delete_confirm') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_members';

		// --------------------------------------------
		//  Load page
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('delete_confirm.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}

	//	End delete friend confirm


	// --------------------------------------------------------------------

	/**
	 * Delete Friend
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function delete_friend ( $message = '' )
	{
		//
		if ( ! isset($_POST['delete']) OR ! is_array($_POST['delete']))
		{
			return $this->members();
		}

		// -------------------------------------
		//  Messages,  and Crumbs
		// -------------------------------------

		//message
		if ($message == '' AND ! in_array(ee()->input->get('msg'), array(FALSE, '')) )
		{
			$message = lang(ee()->input->get('msg'));
		}

		$this->cached_vars['message'] = $message;
		//

		$ids = array();

		foreach (ee()->input->post('delete') as $key => $val)
		{
			if ( is_numeric($val))
			{
				$ids[] = $val;
			}
		}

		$query	= ee()->db->query(
			"SELECT entry_id
			 FROM 	exp_friends
			 WHERE 	entry_id
			 IN 	('" . implode("','", ee()->db->escape_str($ids)) . "')"
		);

		//	----------------------------------------
		//	Delete
		//	----------------------------------------

		// Get some details before we delete

		$friendship_ids = array();

		$result = $query->result_array();

		foreach($result AS $row)
		{
			$friendship_ids[] = $row['entry_id'];
		}

		$member_ids = array();

		$qq = ee()->db->query("SELECT member_id, friend_id
						FROM exp_friends
						WHERE entry_id
						IN ('" . implode("','", ee()->db->escape_str($friendship_ids)) . "')");


		$first = $qq->row();

		$member_ids[] = $first->member_id;

		foreach ( $qq->result_array() AS $row )
		{
			$member_ids[] = $row['friend_id'];
		}


		$sql	= array();

		foreach ( $query->result_array() as $row )
		{
			$sql[]	= "DELETE FROM 	exp_friends
					   WHERE 		entry_id = '" . ee()->db->escape_str($row['entry_id']) . "'";
		}

		foreach ( $sql as $q )
		{
			ee()->db->query($q);
		}


		if ( count( $sql ) > 0 )
		{
			if ( ! class_exists('Friends'))
			{
				require $this->addon_path.'mod.friends.php';
			}

			$Friends = new Friends;

			$Friends->_update_stats_cp( $member_ids );

			$Friends->_reciprocal( $member_ids , 'delete' );
		}

		//friend or friends
		$message = str_replace(
			'%count%',
			$query->num_rows(),
			lang('friend' . (($query->num_rows() == 1) ? '' : 's') . '_deleted')
		);

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		/*$this->add_crumb(lang('members'), $this->base . AMP . 'method=members');
		$this->add_crumb( lang('delete_friend') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_members';*/

		return $this->members($message);
	}

	//	End delete friend


	// --------------------------------------------------------------------

	/**
	 * Delete Group
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function delete_group()
	{
		$sql	= array();

		if ( ! isset($_POST['delete']) OR ! is_array($_POST['delete']))
		{
			return $this->home();
		}

		$ids = array();

		foreach ($_POST['delete'] as $key => $val)
		{
			if ( is_numeric($val) )
			{
				$ids[] = $val;
			}
		}

		$query	= ee()->db->query(
			"SELECT group_id
			 FROM 	exp_friends_groups
			 WHERE 	group_id
			 IN 	('" . implode("','", ee()->db->escape_str($ids)) . "')"
		);

		//	----------------------------------------
		//	Delete groups
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			$sql[]	= "DELETE FROM 	exp_friends_groups
					   WHERE 		group_id = '" . ee()->db->escape_str($row['group_id']) . "'";

			$sql[]	= "DELETE FROM 	exp_friends_group_posts
					   WHERE 		group_id = '" . ee()->db->escape_str($row['group_id']) . "'";

			$sql[]	= "DELETE FROM 	exp_friends_group_entry_posts
					   WHERE 		group_id = '" . ee()->db->escape_str($row['group_id']) . "'";
		}

		foreach ( $sql as $q )
		{
			ee()->db->query($q);
		}

		$message = ($query->num_rows() == 1) ?
						str_replace(
							'%i%',
							$query->num_rows(),
							lang('group_deleted')
						) : str_replace(
							'%i%',
							$query->num_rows(),
							lang('groups_deleted')
						);

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $this->groups($message);
	}

	//	End delete group


	// --------------------------------------------------------------------

	/**
	 * Delete Group - Confirm
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function delete_group_confirm()
	{
		$this->cached_vars['item_id']	= '';

		// -------------------------------------
		//	Have any groups been submitted?
		// -------------------------------------

		if ( empty( $_POST['toggle'] ) )
		{
			return $this->groups();
		}

		$i		= 0;

		$this->cached_vars['items']	= array();

		foreach ( ee()->input->post('toggle') as $key => $val )
		{
			if ( is_numeric( $val ) )
			{
				$this->cached_vars['items'][]	= $val;

				$i++;
			}
		}

		if ( $i == 1 )
		{
			$replace[]	= $i;
			$replace[]	= 'group';
		}
		else
		{
			$replace[]	= $i;
			$replace[]	= 'groups';
		}

		$this->cached_vars['question']	= str_replace(
			array( '%i%', '%count%' ),
			$replace,
			lang('group_delete_question')
		);

		$this->cached_vars['method']	= 'delete_group';

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('groups'), $this->base . AMP . 'method=groups');
		$this->add_crumb( lang('group_delete_confirm') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('delete_confirm.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}

	//	End delete group confirm */

	// --------------------------------------------------------------------

	/**
	 * Delete Group Member
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function delete_group_member()
	{
		if ( ee()->input->get_post('item_id') === FALSE OR ee()->input->get_post('item_id') == '' )
		{
			return $this->groups();
		}

		if ( ! isset($_POST['delete']) OR ! is_array($_POST['delete']))
		{
			return $this->groups();
		}

		$ids = array();

		foreach ($_POST['delete'] as $key => $val)
		{
			if ( is_numeric($val) )
			{
				$ids[] = $val;
			}
		}

		$sql	= "SELECT 	fgp.member_id
				   FROM 	exp_friends_group_posts fgp
				   WHERE 	fgp.site_id = '".ee()->db->escape_str( ee()->config->item('site_id') )."'
				   AND 		fgp.group_id = '".ee()->db->escape_str( ee()->input->get_post('item_id') )."'
				   AND 		fgp.member_id
				   NOT IN 	( 	SELECT 	member_id
								FROM 	exp_friends_groups
								WHERE 	group_id = '" . ee()->db->escape_str( ee()->input->get_post('item_id') ) . "'
							)
				   AND 		fgp.member_id
				   IN 		('" . implode("','", ee()->db->escape_str($ids)) . "')";

		$query	= ee()->db->query( $sql );

		//	----------------------------------------
		//	Delete members
		//	----------------------------------------

		$sql	= array();

		foreach ( $query->result_array() as $row )
		{
			$sql[]	= "DELETE FROM 	exp_friends_group_posts
					   WHERE 		member_id = '" . ee()->db->escape_str($row['member_id']) . "'
					   AND 			group_id = '" . ee()->db->escape_str( ee()->input->get_post('item_id') ) . "'";
		}

		foreach ( $sql as $q )
		{
			ee()->db->query($q);
		}

		$message = ($query->num_rows() == 1) ?
					str_replace(
						'%i%',
						$query->num_rows(),
						lang('group_member_deleted')
					) : str_replace(
						'%i%',
						$query->num_rows(),
						lang('group_members_deleted')
					);

		//	----------------------------------------
		//	Recount members
		//	----------------------------------------

		ee()->load->library('friends_groups');
		ee()->friends_groups->_update_group_stats(
			ee()->input->get_post('item_id')
		);

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return $this->groups($message);
	}
	//	End delete group members


	// --------------------------------------------------------------------

	/**
	 * Delete Group Member - Confirm
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function delete_group_member_confirm()
	{
		// -------------------------------------
		//	Have any members been submitted?
		// -------------------------------------

		if ( empty( $_POST['toggle'] ) )
		{
			return $this->members();
		}

		$i		= 0;

		$this->cached_vars['item_id']	= ee()->input->get_post('group_id');
		$this->cached_vars['items']	= array();

		foreach ( ee()->input->post('toggle') as $key => $val )
		{
			if ( is_numeric( $val ) )
			{
				$this->cached_vars['items'][]	= $val;

				$i++;
			}
		}

		if ( $i == 1 )
		{
			$replace[]	= $i;
			$replace[]	= 'person';
		}
		else
		{
			$replace[]	= $i;
			$replace[]	= 'people';
		}

		$this->cached_vars['question']	= str_replace(
			array( '%i%', '%count%' ),
			$replace,
			lang('group_member_delete_question')
		);

		$this->cached_vars['method']	= 'delete_group_member';

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb(lang('groups'), $this->base . AMP . 'method=groups');
		$this->add_crumb( lang('group_member_delete_confirm') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('delete_confirm.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}

	//	End delete group member confirm */

	// --------------------------------------------------------------------

	/**
	 * Edit Group
	 *
	 * @access	public
	 * @return	string
	 */

	public function edit_group()
	{
	   $group_id			= ( ! in_array(ee()->input->get_post('group_id'), array(FALSE, '', 0, '0'), TRUE) AND
								is_numeric(ee()->input->get_post('group_id'))) ?
									ee()->input->get_post('group_id') : '';

		$group_name			= '';

		$group_title		= '';

		$group_description	= '';

		$group_private		= '';

		$update				= ( $group_id != '' );

		//	----------------------------------------
		//	Validate
		//	----------------------------------------

		if ( ! $group_name = ee()->input->get_post('group_name') )
		{
			return $this->show_error(lang('group_name_required'));
		}

		if ( ! $group_title = ee()->input->get_post('group_title') )
		{
			$group_title	= $group_name;
		}

		$group_description	= ee()->input->post('group_description');
		$group_private		= ( ee()->input->post('group_private') !== FALSE ) ?
								ee()->input->post('group_private') : 'n';

		//	----------------------------------------
		//	Check for duplicate
		//	----------------------------------------

		$sql	= "SELECT 	group_id, name
				   FROM 	exp_friends_groups
				   WHERE 	name = '" . ee()->db->escape_str($group_name) . "'";

		if ( $update )
		{
			$sql .= " AND group_id != '" . ee()->db->escape_str( $group_id ) . "'";
		}

		$sql	.= " LIMIT 1";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() > 0 )
		{
			return $this->show_error(
				str_replace(
					'%group_name%',
					$group_name,
					lang('group_exists')
				)
			);
		}

		//	----------------------------------------
		//	Update or Create
		//	----------------------------------------

		if ( $update )
		{
			ee()->db->query(
				ee()->db->update_string(
					'exp_friends_groups',
					array(
						'name' 			=> $group_name,
						'title' 		=> $group_title,
						'description' 	=> $group_description,
						'private' 		=> $group_private,
						'edit_date' 	=> ee()->localize->now
					),
					array(
						'group_id' 		=> $group_id
					)
				)
			);

			$message	= lang('group_updated');
		}
		else
		{
			ee()->db->query(
				ee()->db->insert_string(
					'exp_friends_groups',
					array(
						'name' 			=> $group_name,
						'title' 		=> $group_title,
						'description' 	=> $group_description,
						'private' 		=> $group_private,
						'member_id' 	=> ee()->session->userdata['member_id'],
						'entry_date' 	=> ee()->localize->now,
						'site_id'		=> $this->clean_site_id
					)
				)
			);

			$group_id	= ee()->db->insert_id();

			//	----------------------------------------
			//	Insert group post for owner
			//	----------------------------------------

			ee()->db->query(
				ee()->db->insert_string(
					'exp_friends_group_posts',
					array(
						'member_id' 	=> ee()->session->userdata('member_id'),
						'group_id' 		=> $group_id,
						'entry_date' 	=> ee()->localize->now,
						'accepted' 		=> 'y',
						'site_id'		=> $this->clean_site_id
					)
				)
			);

			$message	= str_replace( '%group_title%', $group_title, lang('group_added') );
		}

		//	----------------------------------------
		//	Recount members
		//	----------------------------------------


		ee()->load->library('friends_groups');

		ee()->friends_groups->_update_group_stats($group_id);

		return $this->groups($message);
	}

	//	End edit group

	// --------------------------------------------------------------------

	/**
	 * Edit group form
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function edit_group_form( $message = '' )
	{
		$edit_group_mode							= ( ee()->input->get_post('group_id') ) ?
														'edit_group': 'add_group';
		$this->cached_vars['group_id']				= '';
		$this->cached_vars['group_name']			= '';
		$this->cached_vars['group_title']			= '';
		$this->cached_vars['group_description']		= '';
		$this->cached_vars['group_private_no']		= '';
		$this->cached_vars['group_private_yes']		= '';
		$this->cached_vars['form_uri']				= $this->base . AMP . 'method=edit_group';

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		if ( ee()->input->get_post('group_id') !== FALSE )
		{
			$sql	= "SELECT 	*
					   FROM 	exp_friends_groups
					   WHERE 	group_id = '" . ee()->db->escape_str( ee()->input->get_post('group_id') ) . "'
					   LIMIT 	1";

			$query				= ee()->db->query($sql);

			$this->group_id							= $query->row('group_id');
			$this->cached_vars['group_id']			= $query->row('group_id');
			$this->cached_vars['group_name']		= $query->row('name');
			$this->cached_vars['group_title']		= $query->row('title');
			$this->cached_vars['group_description']	= $query->row('description');
			$this->cached_vars['group_private_no']	= ( $query->row('private') == 'n' ) ? 'checked="checked"': '';
			$this->cached_vars['group_private_yes']	= ( $query->row('private') == 'y' ) ? 'checked="checked"': '';
		}

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'edit_group',
			'add_group',
			'name',
			'title',
			'description',
			'private',
			'update'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		$this->cached_vars['lang_group_mode']	= lang($edit_group_mode) .
													(($edit_group_mode == 'edit_group') ?
													 ' : ' . $this->cached_vars['group_name'] : '');

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('groups'), $this->base . AMP . 'method=groups' );
		$this->add_crumb( lang( $edit_group_mode ) );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('edit_group.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End edit group form


	// --------------------------------------------------------------------

	/**
	 * Group invitees
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function group_invitees($message = '')
	{
		$paginate							= '';
		$row_count							= 0;
		$this->cached_vars['invitees']		= array();
		$this->cached_vars['group_id']		= ee()->input->get_post('group_id');
		$this->cached_vars['group_title']	= '';

		//	----------------------------------------
		//	Group title
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT title
			 FROM 	exp_friends_groups
			 WHERE 	group_id = '" . ee()->db->escape_str( ee()->input->get_post('group_id') ) . "'"
		);

		if ( $query->num_rows() > 0 )
		{
			$this->cached_vars['group_title']	= $query->row('title');
		}

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$sql	= "SELECT 		%q
				   FROM 		exp_friends_group_posts fgp
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fgp.member_id
				   WHERE 		fgp.group_id = '" . ee()->db->escape_str(ee()->input->get_post('group_id')) . "'
				   AND 			fgp.accepted = 'n'
				   ORDER BY 	m.screen_name ASC";

		$query	= ee()->db->query( str_replace( '%q', 'COUNT(*) AS count', $sql ) );

		//	----------------------------------------
		//	Paginate
		//	----------------------------------------

		if ( $query->row('count') > $this->row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row');

			$url			= $this->base . AMP . 'method=group_invitees' .
											AMP . 'group_id=' . $this->cached_vars['group_id'];

			//get pagination info
			$pagination_data 	= $this->universal_pagination(array(
				'sql'					=> $sql,
				'total_results'			=> $query->row('count'),
				'limit'					=> $this->row_limit,
				'current_page'			=> $row_count,
				'pagination_config'		=> array('base_url' => $url),
				'query_string_segment'	=> 'row'
			));


			$sql				= $pagination_data['sql'];
			$paginate 			= $pagination_data['pagination_links'];
		}

		$query	= ee()->db->query(
			str_replace(
				'%q',
				"fgp.entry_date,
				 m.member_id,
				 m.email,
				 m.screen_name,
				 m.total_friends,
				 m.total_reciprocal_friends",
				$sql
			)
		);

		foreach ( $query->result_array() as $row )
		{
			$row['date']						= $this->human_time( $row['entry_date'] );
			$row['member_uri']					= $this->base . AMP . 'method=member' . AMP . 'member_id=' . $row['member_id'];
			$this->cached_vars['invitees'][] 	= $row;
		}

		$this->cached_vars['row_count']			= $row_count;
		$this->cached_vars['paginate']			= $paginate;
		$this->cached_vars['form_uri']			= $this->base . AMP . 'method=delete_group_member_confirm';

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'groups',
			'no_groups',
			'name',
			'invitees_to_',
			'no_invitees',
			'total_reciprocal_friends',
			'total_friends',
			'delete',
			'submit',
			'view_friends_of',
			'member'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('groups'), $this->base . AMP . 'method=groups' );
		$this->add_crumb( lang('invitees_to_') . $this->cached_vars['group_title'] );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('group_invitees.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End group invitees


	// --------------------------------------------------------------------

	/**
	 * Group requests
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function group_requests($message = '')
	{
		$paginate		= '';
		$row_count		= 0;
		$this->cached_vars['requests']		= array();
		$this->cached_vars['group_id']		= ee()->input->get_post('group_id');
		$this->cached_vars['group_title']	= '';

		//	----------------------------------------
		//	Group title
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT title
			 FROM 	exp_friends_groups
			 WHERE 	group_id = '" . ee()->db->escape_str( ee()->input->get_post('group_id') ) . "'"
		);

		if ( $query->num_rows() > 0 )
		{
			$this->cached_vars['group_title']	= $query->row('title');
		}

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$sql	= "SELECT 		%q
				   FROM 		exp_friends_group_posts fgp
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fgp.member_id
				   WHERE 		fgp.group_id = '" . ee()->db->escape_str(ee()->input->get_post('group_id')) . "'
				   AND 			fgp.request_accepted = 'n'
				   ORDER BY 	m.screen_name
				   ASC";

		$query	= ee()->db->query( str_replace( '%q', 'COUNT(*) AS count', $sql ) );

		//	----------------------------------------
		//	Paginate
		//	----------------------------------------

		if ( $query->row('count') > $this->row_limit )
		{
			$row_count			= ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row');

			$url				= $this->base . AMP . 'method=group_members' .
												AMP . 'group_id=' . $this->cached_vars['group_id'];

			//get pagination info
			$pagination_data 	= $this->universal_pagination(array(
				'sql'					=> $sql,
				'total_results'			=> $query->row('count'),
				'limit'					=> $this->row_limit,
				'current_page'			=> $row_count,
				'pagination_config'				=> array('base_url' => $url),
				'query_string_segment'	=> 'row'
			));

			$sql				= $pagination_data['sql'];
			$paginate 			= $pagination_data['pagination_links'];
		}

		$query	= ee()->db->query(
			str_replace(
				'%q',
				'fgp.entry_date,
				 m.member_id,
				 m.email,
				 m.screen_name,
				 m.total_friends,
				 m.total_reciprocal_friends',
				$sql
			)
		);

		foreach ( $query->result_array() as $row )
		{
			$row['date']						= $this->human_time( $row['entry_date'] );
			$row['view_friends_uri']			= $this->base . AMP . 'method=member' .
																AMP . 'member_id=' . $row['member_id'];
			$this->cached_vars['requests'][]	= $row;
		}

		$this->cached_vars['row_count']	= $row_count;
		$this->cached_vars['paginate']	= $paginate;
		$this->cached_vars['form_uri']	= $this->base . AMP . 'method=delete_group_member_confirm';

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'membership_requests_to_',
			'no_requests',
			'total_friends',
			'total_reciprocal_friends',
			'delete',
			'view_friends_of',
			'members'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('groups'), $this->base . AMP . 'method=groups' );
		$this->add_crumb( lang('membership_requests_to_') . $this->cached_vars['group_title'] );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('group_requests.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}

	//	End group requests

	// --------------------------------------------------------------------

	/**
	 * Group members
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function group_members($message = '')
	{
		$paginate							= '';
		$row_count							= 0;
		$this->cached_vars['members']		= array();
		$this->cached_vars['group_id']		= ee()->input->get_post('group_id');
		$this->cached_vars['group_title']	= '';

		//	----------------------------------------
		//	Group title
		//	----------------------------------------

		$query	= ee()->db->query(
			"SELECT title
			 FROM 	exp_friends_groups
			 WHERE 	group_id = '" . ee()->db->escape_str( ee()->input->get_post('group_id') ) . "'"
		);

		if ( $query->num_rows() > 0 )
		{
			$this->cached_vars['group_title']	= $query->row('title');
		}

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$sql	= "SELECT 		%q
				   FROM 		exp_friends_group_posts fgp
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fgp.member_id
				   WHERE 		fgp.group_id = '" . ee()->db->escape_str(ee()->input->get_post('group_id')) . "'
				   AND 			fgp.accepted = 'y'
				   AND 			fgp.declined = 'n'
				   AND 			fgp.request_accepted = 'y'
				   AND 			fgp.request_declined = 'n'
				   ORDER BY 	m.screen_name
				   ASC";

		$query	= ee()->db->query( str_replace( '%q', 'COUNT(*) AS count', $sql ) );

		//	----------------------------------------
		//	Paginate
		//	----------------------------------------

		if ( $query->row('count') > $this->row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row');

			$url			= $this->base. AMP . 'method='.'group_members&group_id='.$this->cached_vars['group_id'];

			//get pagination info
			$pagination_data 	= $this->universal_pagination(array(
				'sql'					=> $sql,
				'total_results'			=> $query->row('count'),
				'limit'					=> $this->row_limit,
				'current_page'			=> $row_count,
				'pagination_config'				=> array('base_url' => $url),
				'query_string_segment'	=> 'row'
			));


			$sql				= $pagination_data['sql'];
			$paginate 			= $pagination_data['pagination_links'];
		}

		$query	= ee()->db->query(
			str_replace(
				'%q',
				'fgp.entry_date,
				 m.member_id,
				 m.email,
				 m.screen_name,
				 m.total_friends,
				 m.total_reciprocal_friends',
				$sql
			)
		);

		foreach ( $query->result_array() as $row )
		{
			$row['date']					= $this->human_time( $row['entry_date'] );
			$row['member_uri']				= $this->base . AMP . 'method=member' .
															AMP . 'member_id=' . $row['member_id'];
			$this->cached_vars['members'][]	= $row;
		}

		$this->cached_vars['row_count'] = $row_count;
		$this->cached_vars['paginate'] 	= $paginate;
		$this->cached_vars['form_uri'] 	= $this->base . AMP . 'method=delete_group_member_confirm';

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'members_of',
			'no_members',
			'total_friends',
			'total_reciprocal_friends',
			'delete',
			'view_friends_of',
			'member'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('groups'), $this->base . AMP . 'method=groups' );
		$this->add_crumb( lang('members_of_').$this->cached_vars['group_title'] );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('group_members.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End group members


	// --------------------------------------------------------------------

	/**
	 * Groups
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function groups( $message = '' )
	{
		$paginate						= '';
		$row_count						= 0;
		$this->cached_vars['groups']	= array();

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$sql	= "SELECT 		%q
				   FROM 		exp_friends_groups fg
				   LEFT JOIN 	exp_members m
				   ON 			m.member_id = fg.member_id
				   ORDER BY 	fg.title
				   ASC";

		$query	= ee()->db->query( str_replace( '%q', 'COUNT(*) AS count', $sql ) );

		//	----------------------------------------
		//	Paginate
		//	----------------------------------------

		if ( $query->row('count') > $this->row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row');

			$url			= $this->base. AMP . 'method='.'groups';

			//get pagination info
			$pagination_data 	= $this->universal_pagination(array(
				'sql'					=> $sql,
				'total_results'			=> $query->row('count'),
				'limit'					=> $this->row_limit,
				'current_page'			=> $row_count,
				'pagination_config'		=> array('base_url' => $url),
				'query_string_segment'	=> 'row'
			));


			$sql				= $pagination_data['sql'];
			$paginate 			= $pagination_data['pagination_links'];
		}

		$query	= ee()->db->query(
			str_replace(
				'%q',
				'fg.*,
				 fg.total_members,
				 fg.total_entries,
				 m.screen_name',
				$sql
			)
		);

		foreach ( $query->result_array() as $row )
		{
			$row['date']					= $this->human_time( $row['entry_date'] );
			$row['edit_group_uri']			= $this->base . AMP . 'method=edit_group_form' 	.
															AMP . 'group_id=' . $row['group_id'];
			$row['invitees_uri']            = $this->base . AMP . 'method=group_invitees' 	.
															AMP . 'group_id=' . $row['group_id'];
			$row['requests_uri']            = $this->base . AMP . 'method=group_requests' 	.
															AMP . 'group_id=' . $row['group_id'];
			$row['members_uri']             = $this->base . AMP . 'method=group_members' 	.
															AMP . 'group_id=' . $row['group_id'];

			$this->cached_vars['groups'][]	= $row;
		}

		$this->cached_vars['row_count'] = $row_count;
		$this->cached_vars['paginate'] 	= $paginate;
		$this->cached_vars['form_uri']	= $this->base . AMP . 'method=delete_group_confirm';

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'groups',
			'no_groups',
			'name',
			'owner',
			'view_invitees',
			'view_requests',
			'view_members',
			'total_members',
			'view_member_of_',
			'view_invitees_of_',
			'view_requests_of_',
			'view_members_of_',
			'delete',
			'submit'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Prep crumb button
		// -------------------------------------

		$this->_build_right_link('add_group', $this->base . AMP . 'method=edit_group_form');

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('groups') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_groups';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('groups.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End groups


	// --------------------------------------------------------------------

	/**
	 * Member
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function member( $message = '' )
	{
		$paginate						= '';
		$row_count						= 0;
		$member_id						= 0;
		$this->cached_vars['member']	= array( 'screen_name' => '' );
		$this->cached_vars['friends']	= array();

		//	----------------------------------------
		//	Member id?
		//	----------------------------------------

		if ( ( $member_id	= ee()->input->get_post('member_id') ) === FALSE )
		{
			return $this->index();
		}

		//	----------------------------------------
		//	Query for member data
		//	----------------------------------------

		$sql	= "SELECT 	member_id,
							email,
							screen_name,
							total_friends,
							total_reciprocal_friends,
							total_blocked_friends,
							friends_groups_public,
							friends_groups_private
				   FROM 	exp_members
				   WHERE 	member_id = " . ee()->db->escape_str( $member_id ) . "
				   LIMIT 	1";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() > 0 )
		{
			$this->cached_vars['member']	= $query->row_array();
		}

		//	----------------------------------------
		//	Query for friends
		//	----------------------------------------

		$sql	=  "SELECT 		%q
					FROM 		exp_friends f
					LEFT JOIN 	exp_members m
					ON 			f.friend_id = m.member_id
					WHERE 		f.member_id = " . ee()->db->escape_str( $member_id ) . "
					AND 		f.friend_id != 0
					ORDER BY 	m.screen_name
					ASC";

		$query	= ee()->db->query( str_replace( '%q', 'COUNT(*) AS count', $sql ) );

		//	----------------------------------------
		//	Paginate
		//	----------------------------------------

		if ( $query->row('count') > $this->row_limit )
		{
			$row_count			= ( ee()->input->get_post('row') === FALSE ) ? 0 : ee()->input->get_post('row');

			$url				= $this->base . AMP . 'method=member' .
												AMP . 'member_id=' . $member_id;

			//get pagination info
			$pagination_data 	= $this->universal_pagination(array(
				'sql'					=> $sql,
				'total_results'			=> $query->row('count'),
				'limit'					=> $this->row_limit,
				'current_page'			=> $row_count,
				'pagination_config'		=> array('base_url' => $url),
				'query_string_segment'	=> 'row'
			));

			$sql				= $pagination_data['sql'];
			$paginate 			= $pagination_data['pagination_links'];
		}

		$query	= ee()->db->query( str_replace( '%q', 'f.*, m.screen_name, m.total_friends', $sql ) );

		foreach ( $query->result_array() as $row )
		{
			$row['date']		= $this->human_time( $row['entry_date'] );
			$row['friend_uri']	= $this->base . AMP . 'method=member' .
												AMP . 'member_id=' . $row['friend_id'];
			$this->cached_vars['friends'][]	= $row;
		}

		$this->cached_vars['row_count'] = $row_count;
		$this->cached_vars['paginate'] 	= $paginate;
		$this->cached_vars['form_uri']	= $this->base . AMP . 'method=delete_friend_confirm';

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'member',
			'id',
			'name',
			'total_friends',
			'total_reciprocal_friends',
			'total_blocked_friends',
			'friends_groups_public',
			'friends_groups_private',
			'_added_no_friends_yet',
			'date',
			'reciprocal',
			'blocked',
			'delete',
			'view_friends_of_'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('members'), $this->base . AMP . 'method=members' );
		$this->add_crumb( lang('friends_of_').$this->cached_vars['member']['screen_name'] );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_members';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('members_friends.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End member


	// --------------------------------------------------------------------

	/**
	 * Members
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function members($message = '')
	{
		$paginate		= '';
		$row_count		= 0;

		//	----------------------------------------
		//	Query
		//	----------------------------------------

		$sql	= "SELECT 	%q
				   FROM 	exp_members
				   WHERE 	member_id
				   IN 		( 	SELECT 	member_id
								FROM 	exp_friends
								WHERE 	site_id = " . ee()->db->escape_str( ee()->config->item( 'site_id' ) ) . "
							)
				   ORDER BY screen_name
				   ASC";

		$query	= ee()->db->query( str_replace( '%q', 'COUNT(*) AS count', $sql ) );

		//	----------------------------------------
		//	Paginate
		//	----------------------------------------

		if ( $query->row('count') > $this->row_limit )
		{
			$row_count			= ( ee()->input->get_post('row') === FALSE ) ? 0 : ee()->input->get_post('row');

			$url				= $this->base . AMP . 'method=members';

			//get pagination info
			$pagination_data 	= $this->universal_pagination(array(
				'sql'					=> $sql,
				'total_results'			=> $query->row('count'),
				'limit'					=> $this->row_limit,
				'current_page'			=> $row_count,
				'pagination_config'		=> array('base_url' => $url),
				'query_string_segment'	=> 'row'
			));

			$sql				= $pagination_data['sql'];
			$paginate 			= $pagination_data['pagination_links'];
		}

		$query	= ee()->db->query(
			str_replace(
				'%q',
				'member_id,
				 email,
				 screen_name,
				 total_friends,
				 total_reciprocal_friends,
				 total_blocked_friends',
				$sql
			)
		);

		$this->cached_vars['row_count'] = $row_count;
		$this->cached_vars['paginate'] 	= $paginate;

		// -------------------------------------
		//	Prep members array
		// -------------------------------------

		$this->cached_vars['members']	= array();

		foreach ( $query->result_array() as $row )
		{
			$row['view_friends_url']		= $this->base . AMP . 'method=member' .
															AMP . 'member_id=' . $row['member_id'];

			$this->cached_vars['members'][]	= $row;
		}

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'member',
			'total_friends',
			'total_blocked_friends',
			'total_reciprocal_friends',
			'no_friends',
			'view_friends_of_'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('members') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_members';

		// --------------------------------------------
		//	Load content and wrapper
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('members.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End members

	// --------------------------------------------------------------------

	/**
	 * Messaging preferences
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */

	public function preferences( $message = '' )
	{
		// -------------------------------------
		//	Prep vars
		// -------------------------------------

		$prefs	= array(
			'max_message_chars'				=> 6000,
			'message_waiting_period'		=> 24,
			'message_throttling'			=> 30,
			'message_day_limit'				=> 1000,
			'max_recipients_per_message'	=> 20,
			'notify_parse_all'				=> 'y'
		);

		foreach ( $prefs as $key => $val )
		{
			$pref						= $this->data->get_preference_from_site_id(
												ee()->config->item( 'site_id' ), $key );
			$this->cached_vars[$key]	= ( ! in_array($pref, array(FALSE,  '') ) ) ? $pref : $val;
		}

		// -------------------------------------
		//	Are we updating / inserting?
		// -------------------------------------

		if ( ee()->input->post('max_message_chars') !== FALSE )
		{
			// -------------------------------------
			//	Prep vars
			// -------------------------------------

			foreach ( $prefs as $key => $val )
			{
				if ( ee()->input->get_post($key) !== FALSE )
				{
					//message data needs to be numeric
					if( stristr($key, 'message') AND is_numeric( ee()->input->get_post($key) ) !== TRUE) {continue;}
					$prefs[ $key ]				= ee()->db->escape_str( ee()->input->get_post($key) );
					$this->cached_vars[$key]	= ee()->input->get_post($key);
				}
			}

			//encode for DB  'ick! :(' - gf
			$prefs	= base64_encode( serialize( $prefs ) );

			// -------------------------------------
			//	Check DB for insert / update
			// -------------------------------------

			$query	= ee()->db->query(
				"SELECT COUNT(*) AS count
				 FROM 	exp_friends_preferences
				 WHERE 	site_id = " . ee()->db->escape_str( ee()->config->item( 'site_id' ) )
			);

			if ( $query->row('count') == 0 )
			{
				$sql	= ee()->db->insert_string(
					'exp_friends_preferences',
					array(
						'site_id' 		=> $this->clean_site_id,
						'preferences' 	=> $prefs
					)
				);
			}
			else
			{
				$sql	= ee()->db->update_string(
					'exp_friends_preferences',
					array( 'preferences' 	=> $prefs ),
					array( 'site_id' 		=> $this->clean_site_id )
				);
			}

			ee()->db->query( $sql );

			$message	= lang( 'messaging_preferences_updated' );
		}

		//this needs to be done after we change and post data
		$selected 									= ' checked="checked" ';
		$this->cached_vars['notify_parse_all_yes'] 	= ($this->cached_vars['notify_parse_all'] == 'y') ?
														$selected : '';
		$this->cached_vars['notify_parse_all_no'] 	= ($this->cached_vars['notify_parse_all'] != 'y') ?
														$selected : '';
		$this->cached_vars['form_uri']				= $this->base . AMP . 'method=preferences';

		// -------------------------------------
		//	lang files
		// -------------------------------------

		$lang_items = array(
			'general_preferences',
			'notify_parse_all',
			'yes',
			'no',
			'notify_parse_all_exp',
			'messaging_preferences',
			'messaging_preferences_explanation',
			'max_message_chars',
			'characters',
			'max_message_chars_exp',
			'message_waiting_period',
			'hours',
			'message_waiting_period_exp',
			'message_throttling',
			'seconds',
			'message_throttling_exp',
			'message_day_limit',
			'per_day',
			'message_day_limit_exp',
			'max_recipients_per_message',
			'per_message',
			'max_recipients_per_message_exp',
			'submit'
		);

		foreach($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = lang($item);
		}

		// -------------------------------------
		//	Prep message
		// -------------------------------------

		$this->_prep_message( $message );

		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------

		$this->add_crumb( lang('preferences') );
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight'] = 'module_preferences';

		// --------------------------------------------
		//	Load Page
		// --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('preferences.html', NULL, TRUE);
		return $this->ee_cp_view('index.html');
	}
	//	End messaging preferences


	// --------------------------------------------------------------------

	/**
	 * Code pack installer page
	 *
	 * @access public
	 * @param	string	$message	lang line for update message
	 * @return	string				html output
	 */

	public function code_pack($message = '')
	{
		//--------------------------------------------
		//	message
		//--------------------------------------------

		if ($message == '' AND ee()->input->get_post('msg') !== FALSE)
		{
			$message = lang(ee()->input->get_post('msg'));
		}

		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//	load vars from code pack lib
		// -------------------------------------

		$lib_name = str_replace('_', '', $this->lower_name) . 'codepack';
		$load_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->lower_name))) . 'CodePack';

		ee()->load->library($load_name, $lib_name);
		ee()->$lib_name->autoSetLang = true;

		$cpt = ee()->$lib_name->getTemplateDirectoryArray(
			$this->addon_path . 'code_pack/'
		);

		$screenshot = ee()->$lib_name->getCodePackImage(
			$this->sc->addon_theme_path . 'code_pack/',
			$this->sc->addon_theme_url . 'code_pack/'
		);

		$this->cached_vars['screenshot'] = $screenshot;

		$this->cached_vars['prefix'] = $this->lower_name . '_';

		$this->cached_vars['code_pack_templates'] = $cpt;

		$this->cached_vars['form_url'] = $this->base . '&method=code_pack_install';

		//--------------------------------------
		//  menus and page content
		//--------------------------------------

		$this->cached_vars['module_menu_highlight'] = 'module_demo_templates';

		$this->add_crumb(lang('demo_templates'));

		$this->cached_vars['current_page'] = $this->view('code_pack.html', NULL, TRUE);

		//---------------------------------------------
		//  Load Homepage
		//---------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	//END code_pack


	// --------------------------------------------------------------------

	/**
	 * Code Pack Install
	 *
	 * @access public
	 * @param	string	$message	lang line for update message
	 * @return	string				html output
	 */

	public function code_pack_install()
	{
		$prefix = trim((string) ee()->input->get_post('prefix'));

		if ($prefix === '')
		{
			ee()->functions->redirect($this->base . '&method=code_pack');
		}

		// -------------------------------------
		//	load lib
		// -------------------------------------

		$lib_name = str_replace('_', '', $this->lower_name) . 'codepack';
		$load_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->lower_name))) . 'CodePack';

		ee()->load->library($load_name, $lib_name);
		ee()->$lib_name->autoSetLang = true;

		// -------------------------------------
		//	¡Las Variables en vivo! ¡Que divertido!
		// -------------------------------------

		$variables = array();

		$variables['code_pack_name']	= $this->lower_name . '_code_pack';
		$variables['code_pack_path']	= $this->addon_path . 'code_pack/';
		$variables['prefix']			= $prefix;

		// -------------------------------------
		//	install
		// -------------------------------------

		$details = ee()->$lib_name->getCodePackDetails($this->addon_path . 'code_pack/');

		$this->cached_vars['code_pack_name'] = $details['code_pack_name'];
		$this->cached_vars['code_pack_label'] = $details['code_pack_label'];

		$return = ee()->$lib_name->installCodePack($variables);

		$this->cached_vars = array_merge($this->cached_vars, $return);

		//--------------------------------------
		//  menus and page content
		//--------------------------------------

		$this->cached_vars['module_menu_highlight'] = 'module_demo_templates';

		$this->add_crumb(lang('demo_templates'), $this->base . '&method=code_pack');
		$this->add_crumb(lang('install_demo_templates'));

		$this->cached_vars['current_page'] = $this->view('code_pack_install.html', NULL, TRUE);

		//---------------------------------------------
		//  Load Homepage
		//---------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	//END code_pack_install


	//----------------------------------------------------------------------------------------
	// end views
	//----------------------------------------------------------------------------------------


	// --------------------------------------------------------------------

	/**
	 * Prep message
	 *
	 * @access	private
	 * @param	message
	 * @return	boolean
	 */

	function _prep_message( $message = '' )
	{
		if ( $message == '' AND isset( $_GET['msg'] ) )
		{
			$message = lang( $_GET['msg'] );
		}

		$this->cached_vars['message']	= $message;

		return TRUE;
	}
	//	End prep message


	// --------------------------------------------------------------------

	/**
	 * Module Upgrading
	 *
	 * This function is not required by the 1.x branch of ExpressionEngine by default.  However,
	 * as the install and deinstall ones are, we are just going to keep the habit and include it
	 * anyhow.
	 *		- Originally, the $current variable was going to be passed via parameter, but as there might
	 *		  be a further use for such a variable throughout the module at a later date we made it
	 *		  a class variable.
	 *
	 *
	 * @access	public
	 * @return	bool
	 */

	public function friends_module_update()
	{
		if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
		{
			$this->add_crumb(lang('update_friends_module'));
			$this->build_crumbs();
			$this->cached_vars['form_url'] 			= $this->base . '&msg=update_successful';
			$this->cached_vars['current_page'] 		= $this->view('update_module.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}

		require_once 'upd.friends.php';

		$U = new Friends_upd();

		if ($U->update() !== TRUE)
		{
			return ee()->functions->redirect($this->base . AMP . 'msg=update_failure');
		}
		else
		{
			return ee()->functions->redirect($this->base . AMP . 'msg=update_successful');
		}
	}
	// END friends_module_update()


	//---------------------------------------------------------------------

	/**
	 * _build_right_link
	 * @access	public
	 * @param	(string)	lang string
	 * @param	(string)	html link for right link
	 * @return	(null)
	 */

	function _build_right_link($lang_line, $link)
	{
		$msgs 		= array();
		$links 		= array();
		$ee2_links 	= array();

		if (is_array($lang_line))
		{
			for ($i = 0, $l= count($lang_line); $i < $l; $i++)
			{
				$ee2_links[$lang_line[$i]] = $link[$i];
			}
		}
		else
		{
			$ee2_links[$lang_line] = $link;
		}

		ee()->cp->set_right_nav($ee2_links);
	}
	// END _build_right_link
}
// END CLASS Friends