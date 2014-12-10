<?php

/**
 * Friends - Language
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2014, Solspace, Inc.
 * @link		http://solspace.com/docs/friends
 * @license		http://www.solspace.com/license_agreement
 * @version		1.6.4
 * @filesource	friends/language/english/lang.friends.php
 */

$L = $lang = array(

//----------------------------------------
// Required for MODULES page
//----------------------------------------

"friends_module_name" =>
"Friends",

"friends_label" =>
"Friends",

"friends_module_description" =>
"Social networking for ExpressionEngine",

//----------------------------------------
//  Main Menu
//----------------------------------------

'homepage' =>
"Homepage",

'online_documentation' =>
"Online Documentation",

'friends_module_version' =>
"Friends",

//----------------------------------------
//  Buttons
//----------------------------------------

'save' =>
"Save",

//----------------------------------------
//  Errors
//----------------------------------------

'invalid_request' =>
"Invalid Request",

'friends_module_disabled' =>
"The Friends module is currently disabled.  Please insure it is installed and up to date by going
to the module's control panel in the ExpressionEngine Control Panel",

'disable_module_to_disable_extension' =>
"To disable this extension, you must disable its corresponding <a href='%url%'>module</a>.",

'enable_module_to_enable_extension' =>
"To enable this extension, you must install its corresponding <a href='%url%'>module</a>.",

'cp_jquery_requred' =>
"The 'jQuery for the Control Panel' extension must be <a href='%extensions_url%'>enabled</a> to use this module.",

//----------------------------------------
//  Update routine
//----------------------------------------

'update_friends_module' =>
"Update Friends Module",

'friends_update_message' =>
"You have recently uploaded a new version of Friends, please click here to run the update script.",

"update_successful" =>
"The module was successfully updated.",

"update_failure" =>
"There was an error while trying to update your module to the latest version.",

//----------------------------------------
// Required for MODULES page
//----------------------------------------

"friends_module_name"				=>
"Friends",

"friends_module_description"		=>
"Social networking for ExpressionEngine",

'friends_module_disabled' =>
"The Friends module is currently disabled.  Please insure it is installed and up to date by going
to the module's control panel in the ExpressionEngine Control Panel",

'disable_module_to_disable_extension' =>
"To disable this extension, you must disable its corresponding <a href='%url%'>module</a>.",

'enable_module_to_enable_extension' =>
"To enable this extension, you must install its corresponding <a href='%url%'>module</a>.",

//----------------------------------------
//	CP Home
//----------------------------------------

"friends"							=>
"Friends",

"home"								=>
"Homepage",

"statistics"						=>
"Statistics",

"module_version"					=>
"Module Version",

"total_friends"						=>
"Total Friends",

"total_reciprocal_friends"			=>
"Total reciprocal friends",

"total_blocked_friends"	=>
"Total blocked friends",

"percent_participating"				=>
"Percent of members participating",

"top_5"								=>
"Top 5",

"no_friends_saved"					=>
"No friends saved",

"friends_module_disabled"			=>
"The Friends module has been disabled.",

//----------------------------------------
//	Language for Update
//----------------------------------------

"update_friends"					=>
"Update the Friends module",

"friends_update_message"			=>
"It looks like you have installed new Friends Module software. We recommend that you run the upgrade script",

//----------------------------------------
//	Nav
//----------------------------------------

"member"							=>
"Member",

"entries"							=>
"Entries",

"preferences"						=>
"Preferences",

//----------------------------------------
//	Language for documentation
//----------------------------------------

"documentation"						=>
"Online Documentation",

"no_documentation"					=>
"Documentation for this module can be found at <a href='http://www.solspace.com/docs/addon/c/Friends'>solspace.com/docs/addon/c/Friends</a>.",

//----------------------------------------
//	Language for members
//----------------------------------------

"members"							=>
"Members",

"total_friends"						=>
"Total friends",

"friends_groups_public"				=>
"Total public groups",

"friends_groups_private"			=>
"Total private groups",

"view_friends_of_"					=>
"View friends of ",

"_added_no_friends_yet"				=>
" hasn't added any friends yet.",

"continue"							=>
"Continue",

"reciprocal"						=>
"Reciprocal",

"blocked"	=>
"Blocked",

"reciprocal_friends_only"	=>
"Only reciprocal friends may %hug% each other.",

"hug_subject"	=>
"%screen_name% has sent you a %hug%.",

"hug_successfully_sent"	=>
"Your %hug% was successfully sent.",

"hug_limit_exceeded"	=>
"You have reached your %hug% limit.",

//----------------------------------------
//	Language for invitees
//----------------------------------------

"invitees"							=>
"Invitees",

"total_invitees"					=>
"Total invitees",

"view_invitees"						=>
"View invitees",

"view_invitees_of_"					=>
"View invitees of ",

//----------------------------------------
//	Language for requests
//----------------------------------------

"requests"							=>
"Requests",

"total_requests"					=>
"Total requests",

"view_requests"						=>
"View requests",

"view_requests_of_"					=>
"View requests of ",

//----------------------------------------
//	Language for member
//----------------------------------------

"id"								=>
"ID",

"friends_of_"						=>
"Friends of ",

"screen_name"						=>
"Screen Name",

"email"								=>
"Email",

"date"								=>
"Date",

"no_friends"						=>
"No members have added friends yet.",

//----------------------------------------
//	Language for preferences
//----------------------------------------

"preferences"	=>
"Preferences",

'notify_parse_all' =>
'Parse all member custom fields for notification templates?',

'notify_parse_all_exp' =>
'Settings this option to \'no\' could increase DB performance on sites where there are a LOT of notifications being sent and received.',

"general_preferences"	=>
"General Preferences",

"messaging_preferences"	=>
"Messaging Preferences",

"messaging_preferences_explanation"	=>
"These preferences control the various limitations on member messaging through the Friends module.",

"max_message_chars"	=>
"Maximum message characters",

"max_message_chars_exp"	=>
"This is the maximum number of characters that a message may contain. Any messages with a character count exceeding this number will trigger an error message and will not be sent.",

"characters"	=>
"Characters",

"message_waiting_period"	=>
"Message waiting period",

"message_waiting_period_exp"	=>
"This is the minimum number of hours that must pass before a new site member may send a message.",

"hours"	=>
"Hours",

"message_throttling"	=>
"Message throttling",

"message_throttling_exp"	=>
"This is the minimum number of seconds that must pass before a member may send another message.",

"seconds"	=>
"Seconds",

"message_day_limit"	=>
"Messages per day limit",

"message_day_limit_exp"	=>
"This is the maximum number of messages that a given member may send per day.",

"per_day"	=>
"Per day",

"max_recipients_per_message"	=>
"Maximum recipients per message",

"max_recipients_per_message_exp"	=>
"This is the maximum number of recipients to whom a given message may be sent.",

"per_message"	=>
"Per message",

"messaging_preferences_updated"	=>
"Your messaging preferences have been updated.",

"message_waiting_period_fail"	=>
"You have not been a member of this site long enough to send messages.",

"message_throttling_fail"	=>
"You must wait %seconds% seconds between sending messages.",

"max_message_chars_fail"	=>
"Your message is too long to send.",

"message_day_limit_fail"	=>
"You have sent the maximum allowed messages today.",

"max_recipients_per_message_fail"	=>
"You have exceeded the maximum number of recipients for a given message.",

//----------------------------------------
//	Language for groups
//----------------------------------------

"groups"							=>
"Groups",

"group"								=>
"Group",

"name"								=>
"Name",

"title"								=>
"Title",

"owner"								=>
"Owner",

"description"						=>
"Description",

"private"							=>
"Private",

"view_members_of_"					=>
"View members of ",

"total_members"						=>
"Total Members",

"total_entries"						=>
"Total Entries",

"no_groups"							=>
"There are currently no groups in the system.",

"no_members"						=>
"No members belong to this group.",

"view_members"						=>
"View Members",

"group_members"						=>
"Group Members",

"screen_name_deleted"				=>
'deleted',

'username_deleted'					=>
'deleted',

//----------------------------------------
//	Language for add / edit groups
//----------------------------------------

"add_group"							=>
"Add Group",

"edit_group"						=>
"Edit Group",

"group_name_required"				=>
"You must provide a name for your group",

"group_title_required"				=>
"You must provide a title for your group",

"group_exists"						=>
"A group by the name %group_name% already exists.",

"group_updated"						=>
"Your group was successfully updated.",

"group_added"						=>
"Your group '%group_title%' was successfully added.",

"group_edited"						=>
"Your group '%group_title%' was successfully edited.",

"group_title_deleted"						=>
"Your group '%group_title%' was successfully deleted.",

"groups_title_deleted"						=>
"Your groups '%group_title%' were successfully deleted.",

"group_not_found"					=>
"The specified group was not found.",

"group_not_belongs_to_member"		=>
"The group indicated does not belong to the specified member.",

//----------------------------------------
//	Language for add to group
//----------------------------------------

"group_id_required"					=>
"A group id is required.",

"friends_required"					=>
"A list of friends is required.",

"not_group_member"					=>
"You must be a member of the indicated group in order to make additions.",

"not_group_owner"					=>
"You must be the owner of a group in order to invite new people to join.",

"no_valid_members"					=>
"None of the members in your list could be added to the specified group.",

"friends_added"						=>
"%count% friends were successfully added.",

"friend_removed"					=>
"1 friend was successfully removed.",

"friends_removed"					=>
"%n% friends were successfully removed.",

"membership_request_email_subject"	=>
"Membership request: %friends_group_title%",

"membership_accept_group_invitation_email_subject"	=>
"Invitation accepted to group: %friends_group_title%.",

"membership_request_decline_group_is_private"	=>
"The group: '%friends_group_title%' is a private group. You must be invited to join.",

"accepted_group_invitation"			=>
"You have accepted an invitation to the group: %friends_group_title%.",

"declined_group_invitation"			=>
"You have declined an invitation to the group: %friends_group_title%.",

"already_accepted_group_invitation"	=>
"You have already accepted an invitation to the group: %friends_group_title%.",

"already_requested_group_invitation"	=>
"You have already requested to join the group %friends_group_title%.",

"accepted_group_invitation_owner"	=>
"%screen_name% has accepted an invitation to your group: %friends_group_title%.",

"declined_group_invitation_owner"	=>
"%screen_name% has declined an invitation to your group: %friends_group_title%.",

"group_add_fail"	=>
"We were unable to detect what action you wanted to perform.",

"request_group_membership"	=>
"You have requested to join the group %friends_group_title%.",

"subject_approve_email"	=>
"Membership request accepted: %friends_group_title%.",

//----------------------------------------
//	Language for block friends
//----------------------------------------

"friends_blocked"					=>
"You have blocked %count% friend invitations.",

"friend_blocked"					=>
"You have blocked 1 friend invitation.",

"friendships_ended"					=>
"You have blocked %count% existing friends. Your former friends will receive no notification of this change.",

"friendship_ended"					=>
"You have blocked an existing friend. Your former friend will receive no notification of this change.",

//----------------------------------------
//	Language for delete friends
//----------------------------------------

"delete_confirm"					=>
"Delete Confirmation",

"friend_delete_confirm"				=>
"Delete Confirmation",

"friend_delete_question"			=>
"Are you sure you want to delete %i% %count%?",

//----------------------------------------
//	Language for delete groups
//----------------------------------------

"group_delete_confirm"				=>
"Delete Confirmation",

"group_delete_question"				=>
"Are you sure you want to delete %i% %count%?",

"action_can_not_be_undone"			=>
"This action cannot be undone.",

"group_deleted"						=>
"%i% group successfully deleted.",

"groups_deleted"					=>
"%i% groups successfully deleted.",

//----------------------------------------
//	Language for add entry to group
//----------------------------------------

"entries_required"					=>
"An entry id is required in order to add an entry to a friends group.",

"no_valid_entries"					=>
"Either no valid entries were found to add to the specified friends group or the entries have already been added.",

"entry_added"						=>
"The entry was successfully added to the friends group.",

"entries_added"						=>
"%n% entries were successfully added to the friends group.",

"entry_removed"						=>
"The entry was successfully removed from the friends group.",

"entries_removed"					=>
"%n% entries were successfully removed from the friends group.",

"entries_not_yours"					=>
"%n% of the submitted entries do not belong to you and cannot be removed by you from the group.",

"no_owned_entries"					=>
"None of the entries submitted could be removed from the group due to your level of permission.",

"no_valid_entries_to_remove"		=>
"Either no valid entries were found to remove from the specified friends group or the entries have already been removed.",


"missing_required_field"			=>
"Missing required form field: %field%.",

//----------------------------------------
//	Language for delete group member
//----------------------------------------

"group_member_delete_confirm"		=>
"Delete Confirmation",

"group_member_delete_question"		=>
"Are you sure you want to delete %i% %count%?",

"group_member_deleted"				=>
"%i% person successfully deleted.",

"group_members_deleted"				=>
"%i% people successfully deleted.",

"subject_remove_email"	=>
"You have been removed from the group %friends_group_title%.",

"subject_leave_email"	=>
"%friends_screen_name% has your group %friends_group_title%.",

"self_removed"	=>
"You have removed yourself from the group %friends_group_title%.",

"cant_delete_self"	=>
"You can't delete yourself from your own group silly.",

//----------------------------------------
//	Language for delete group requests
//----------------------------------------

"no_requests"	=>
"There are no outstanding requests for membership to this group.",

"membership_requests_to_"	=>
"Membership requests to ",

"group_request_delete_confirm"	=>
"Delete Confirmation",

"group_request_delete_question"	=>
"Are you sure you want to delete %i% %requests%?",

"group_membership_request_deleted"	=>
"%i% group membership request successfully deleted.",

"group_membership_request_deleted"	=>
"%i% group membership request successfully deleted.",

//----------------------------------------
//	Language for delete group invitee
//----------------------------------------

"no_invitees"						=>
"There are no outstanding invites for this group.",

"invitees_to_"						=>
"Invitees to ",

"members_of_"						=>
"Members of ",

"members_of"						=>
"Members of ",

"group_invitee_delete_confirm"		=>
"Delete Confirmation",

"group_invitee_delete_question"		=>
"Are you sure you want to delete %i% %invitees%?",

"group_invitee_deleted"				=>
"%i% group invitee successfully deleted.",

"group_invitees_deleted"			=>
"%i% group invitees successfully deleted.",

//----------------------------------------
//	Language for add
//----------------------------------------

"no_member_id"						=>
"A member id is needed in order to invite someone to join your friends list.",

"no_member_id_to_block"	=>
"A member id is needed in order to block someone from joining your friends list.",

"invalid_member_id"					=>
"A valid member id was not provided.",

"not_logged_in"						=>
"You must be logged in to perform this action.",

"your_own_friend"					=>
"We all should be our own best friend, but it would be silly to put yourself on your friends list.",

"member_not_found"					=>
"The specified member was not found in the database.",

"friend_not_exists"					=>
"The specified member does not belong to your friends list.",

"not_friends"					=>
"You are not friends with the specified member.",

"friends_not_exist"					=>
"The specified members do not belong to your friends list.",

"members_not_found"					=>
"None of the specified members were found in the database.",

"remaining_members_not_found"		=>
"None of the remaining specified members were found in the database.",

"duplicate_friend"					=>
"The specified member has already been added to your friends list.",

"duplicate_friends"					=>
"%count% of the specified friends have already been added.",

"member_opted_out"					=>
"The specified member has opted out of this website's friends functionality.",

"members_opted_out"					=>
"%count% of the specified members have opted out of this website's friends functionality.",

"no_members_left"					=>
"All of the specified members are either already your friends or have opted out of this website's friends functionality.",

"friend_added"						=>
"1 friend was successfully invited.",

"friend_deleted"					=>
"1 friend was successfully deleted.",

"friends_deleted"					=>
"%count% friends were successfully deleted.",

"template_loop"						=>
"It appears that a template loop has occurred. This happens when the message formatting template you specify with the 'template' parameter contains tags from this module.",

//----------------------------------------
//	Language for form
//----------------------------------------

"members_var_pair_required"			=>
"In order to correctly format the friends form, you must wrap your member row formatting inside the 'members' tag pair. Please see the documentation.",

"success"							=>
"Success",

"not_authorized"					=>
"You are not authorized to perform this action.",

//----------------------------------------
//	Language for message form
//----------------------------------------

"message_subject"					=>
"%screen_name% has sent you a message.",

"message_required"					=>
"You must provide a message in order to submit this form.",

"message_too_long"					=>
"Your message is too long. It must contain less than %max_chars%.",

"recipients_required"				=>
"Please choose some recipients for your message.",

"no_valid_recipients"				=>
"There were no valid recipients from your selected list.",

"messages_sent"						=>
"%count% messages were successfully sent.",

"message_sent"						=>
"1 message was successfully sent.",

//----------------------------------------
//	Language for message move
//----------------------------------------

"no_folder_id"							=>
"No folder was provided into which messages could be moved.",

"folder_not_exists"						=>
"The specified folder does not exist.",

"no_message_ids_for_move"				=>
"No valid messages were provided to move.",

"no_message_ids_from_db_for_move"		=>
"No valid messages were found to move.",

"no_message_ids_remain_for_move"		=>
"No valid messages were available for moving.",

"your_message_already_moved"			=>
"The message you provided has already been moved to the %folder% folder.",

"message_already_moved"					=>
"1 of the messages you provided has already been moved to the %folder% folder.",

"messages_already_moved"				=>
"%count% of the messages you provided have already been moved to the %folder% folder.",

"message_moved"						=>
"1 message was successfully moved to the %folder% folder.",

"messages_moved"						=>
"%count% messages were successfully moved to the %folder% folder.",

//----------------------------------------
//	Language for message delete
//----------------------------------------

"no_message_ids_for_deletion"			=>
"No valid messages were provided for deletion.",

"no_message_ids_from_db_for_deletion"	=>
"No valid messages were found for deletion.",

"no_message_ids_remain_for_deletion"	=>
"No valid messages were available for deletion.",

"your_message_already_deleted"			=>
"The message you provided has already been deleted.",

"message_already_deleted"				=>
"1 of the messages you provided has already been deleted.",

"messages_already_deleted"				=>
"%count% of the messages you provided have already been deleted.",

"message_deleted"						=>
"1 message was successfully deleted.",

"messages_deleted"						=>
"%count% messages were successfully deleted.",

//----------------------------------------
//	Language for message folders
//----------------------------------------

"no_folders_provided"					=>
"No message folders were provided.",

"folder_limit_exceeded"					=>
"You are allowed only 7 custom folders. Your submission exceeded that limit.",

"folders_not_allowed"					=>
"The following folders were not allowed to be changed or added: %folders%.",

"folders_duplicated"					=>
"The following folders were duplicates: %folders%.",

"folders_created"						=>
"The following folders were created: %folders%.",

"folders_renamed"						=>
"The following folders were renamed: %folders%.",

"folders_deleted"						=>
"The following folders were deleted: %folders%.",

"was_renamed_to"	=>
" was renamed to ",

" and "	=>
" and ",

"no_folders_renamed"					=>
"No folders were renamed.",

"folders_contain_messages"				=>
"The following folders contain messages and could not be deleted: %folders%.",

//----------------------------------------
//	Language for notify
//----------------------------------------

"no_email"							=>
"No notification email address was provided.",

"no_friend_template"				=>
"No template was provided to format the notification email to be sent to the new friend.",

"no_friend_template_found"			=>
"The specified template was not found for formatting the notification email to be sent to the new friend.",

"no_notification_template"			=>
"No template was provided to format the notification email to be sent.",

"no_notification_template_found"	=>
"The specified template was not found for formatting the notification email to be sent.",

//----------------------------------------
//	Language for invite
//----------------------------------------

"no_emails"							=>
"No email addresses were submitted.",

"no_valid_emails"					=>
"No valid email addresses were submitted.",

"friend_invited"					=>
"1 friend was successfully invited.",

"friends_invited"					=>
"%count% friends were successfully invited.",

"friend_confirmed"					=>
"1 friend was successfully confirmed.",

"friends_confirmed"					=>
"%count% friends were successfully confirmed.",

"friend_blocked"					=>
"1 friend was successfully blocked.",

"friends_blocked"					=>
"%count% friends were successfully blocked.",

"subject_invite_email"	=>
"You have been invited to join the group: %friends_group_title%.",

//----------------------------------------
//	Language for save entry
//----------------------------------------

"no_url"							=>
"No URL was found containing information to save an entry to a friends group.",

"no_login"							=>
"You must be logged in to save an entry to a friends group.",

"no_id"								=>
"No entry id was found containing information to save an entry to a friends group.",

"success_delete"					=>
"The entry was successfully removed from the friends group.",

"no_duplicate_entries"				=>
"That entry has already been saved to the indicated friends group.",

"success_save"						=>
"The entry was successfully saved to the friends group.",

//----------------------------------------
//	Language for notifications
//----------------------------------------

"friends_entry_notification"		=>
"New entries from your favorite website",

"friends_comment_notification"		=>
"New comments from your favorite website",

//----------------------------------------
//	Language for preferences
//----------------------------------------

"friends_group_entries_notify"		=>
"Notify me when entries are added to groups",

"friends_group_comments_notify"		=>
"Notify me when comments are added to groups",

"friends_group_joins_notify"		=>
"Notify me when someone joins a group of which I am a member",

"friends_group_favorites_notify"	=>
"Notify me when someone favorites an entry associated with my group",

"friends_group_ratings_notify"		=>
"Notify me when someone rates an entry associated with my group",

"no_prefs_to_update"				=>
"No preferences were chosen to update.",

"group_prefs_updated"				=>
"Your preferences have been updated.",

//----------------------------------------
//	Language for status
//----------------------------------------

"no_status"	=>
"No status update was submitted.",

"no_status_id"	=>
"A status id is required in order to delete a status.",

"not_your_status"	=>
"You are not the owner of this status posting. Only the owner may delete the status.",

"status_deleted"	=>
"Your status has been deleted.",

"status_too_long"	=>
"The status update you submitted was too long. Only %count% characters are allowed.",

"status_update_submitted"	=>
"Your status update was successfully submitted.",

//----------------------------------------
//	Language for group comments
//----------------------------------------

"no_comment_id"	=>
"A comment id is required in order to delete a status.",

"not_your_comment"	=>
"You are not the owner of this comment. Only the owner may delete the status.",

"comment_deleted"	=>
"Your comment has been deleted.",

"duplicate_comment"	=>
"You are attempting to submit a duplicate comment.",

"comment_deleted"	=>
"Your comment has been deleted.",

//----------------------------------------
//	Language for pagination
//----------------------------------------

"page"	=>
"Page",

"of"	=>
"of",

// -------------------------------------
//	demo install (code pack)
// -------------------------------------

'demo_description' =>
'These demonstration templates will help you understand better how the Solspace Friends Addon works.',

'template_group_prefix' =>
'Template Group Prefix',

'template_group_prefix_desc' =>
'Each Template group and global variable installed will be prefixed with this variable in order to prevent colission.',

'groups_and_templates' =>
"Groups and Templates to be installed",

'groups_and_templates_desc' =>
"These template groups and their accompanying templates will be installed into your ExpressionEngine installation.",

'screenshot' =>
'Screenshot',

'install_demo_templates' =>
'Install Demo Templates',

'prefix_error' =>
'Prefixes, which are used for template groups, may only contain alpha-numeric characters, underscores, and dashes.',

'demo_templates' =>
'Demo Templates',

//errors
'ee_not_running'				=>
'ExpressionEngine 2.x does not appear to be running.',

'invalid_code_pack_path'		=>
'Invalid Code Pack Path',

'invalid_code_pack_path_exp'	=>
'No valid codepack found at \'%path%\'.',

'missing_code_pack'				=>
'Code Pack missing',

'missing_code_pack_exp'			=>
'You have chosen no code pack to install.',

'missing_prefix'				=>
'Prefix needed',

'missing_prefix_exp'			=>
'Please provide a prefix for the sample templates and data that will be created.',

'invalid_prefix'				=>
'Invalid prefix',

'invalid_prefix_exp'			=>
'The prefix you provided was not valid.',

'missing_theme_html'			=>
'Missing folder',

'missing_theme_html_exp'		=>
'There should be a folder called \'html\' inside your site\'s \'/themes/solspace_themes/code_pack/%code_pack_name%\' folder. Make sure that it is in place and that it contains additional folders that represent the template groups that will be created by this code pack.',

'missing_codepack_legacy'		=>
'Missing the CodePackLegacy library needed to install this legacy codepack.',

//@deprecated
'missing_code_pack_theme'		=>
'Code Pack Theme missing',

'missing_code_pack_theme_exp'	=>
'There should be at least one theme folder inside the folder \'%code_pack_name%\' located inside \'/themes/code_pack/\'. A theme is required to proceed.',

//conflicts
'conflicting_group_names'		=>
'Conflicting template group names',

'conflicting_group_names_exp'	=>
'The following template group names already exist. Please choose a different prefix in order to avoid conflicts. %conflicting_groups%',

'conflicting_global_var_names'	=>
'Conflicting global variable names.',

'conflicting_global_var_names_exp' =>
'There were conflicts between global variables on your site and global variables in this code pack. Consider changing your prefix to resolve the following conflicts. %conflicting_global_vars%',

//success messages
'global_vars_added'				=>
'Global variables added',

'global_vars_added_exp'			=>
'The following global template variables were successfully added. %global_vars%',

'templates_added'				=>
'Templates were added',

'templates_added_exp'			=>
'%template_count% templates were successfully added to your site as part of this code pack.',

"home_page"						=>"Home Page",
"home_page_exp"					=> "View the home page for this code pack here: %link%",

// END
''=>''
);