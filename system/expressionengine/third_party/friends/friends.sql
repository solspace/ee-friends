CREATE TABLE IF NOT EXISTS `exp_friends` (
	`entry_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`friend_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`referrer_id`		int(10) unsigned		NOT NULL DEFAULT '0',
	`group_id`			varchar(132)			NOT NULL DEFAULT '',
	`site_id`			smallint(3) unsigned	NOT NULL DEFAULT '1',
	`first`				varchar(132)			NOT NULL DEFAULT '',
	`last`				varchar(132)			NOT NULL DEFAULT '',
	`email`				varchar(255)			NOT NULL DEFAULT '',
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`edit_date`			int(10) unsigned		NOT NULL DEFAULT '0',
	`private`			char(1)					NOT NULL DEFAULT 'n',
	`reciprocal`		char(1)					NOT NULL DEFAULT 'n',
	`block`				char(1)					NOT NULL DEFAULT 'n',
	PRIMARY KEY			(entry_id),
	KEY					`member_id` (member_id),
	KEY					`friend_id` (friend_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_referrals` (
	`referral_id`		int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`referrer_id`		int(10) unsigned		NOT NULL DEFAULT '0',
	`site_id`			smallint(3) unsigned	NOT NULL DEFAULT '1',
	PRIMARY KEY			(referral_id),
	KEY					`member_id` (member_id),
	KEY					`referrer_id` (referrer_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_groups` (
	`group_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`site_id`			smallint(3) unsigned	NOT NULL DEFAULT '1',
	`name`				varchar(132)			NOT NULL DEFAULT '',
	`title`				varchar(132)			NOT NULL DEFAULT '',
	`description`		text,
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`edit_date`			int(10) unsigned		NOT NULL DEFAULT '0',
	`private`			char(1)					NOT NULL DEFAULT 'n',
	`total_members`		int(10) unsigned		NOT NULL DEFAULT '0',
	`total_entries`		int(10) unsigned		NOT NULL DEFAULT '0',
	PRIMARY KEY			(group_id),
	KEY `member_id`		(member_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_group_posts` (
	`post_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`group_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`site_id`			smallint(3) unsigned	NOT NULL DEFAULT '1',
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`invite_or_request` varchar(7)				NOT NULL DEFAULT '',
	`accepted`			char(1)					NOT NULL DEFAULT 'n',
	`declined`			char(1)					NOT NULL DEFAULT 'n',
	`request_accepted`	char(1)					NOT NULL DEFAULT 'y',
	`request_declined`	char(1)					NOT NULL DEFAULT 'n',
	`notify_entries`	char(1)					NOT NULL DEFAULT 'y',
	`notify_comments`	char(1)					NOT NULL DEFAULT 'y',
	`notify_joins`		char(1)					NOT NULL DEFAULT 'y',
	`notify_favorites`	char(1)					NOT NULL DEFAULT 'y',
	`notify_ratings`	char(1)					NOT NULL DEFAULT 'y',
	PRIMARY KEY			(post_id),
	KEY					`group_id` (group_id),
	KEY					`member_id` (member_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_group_entry_posts` (
	`group_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`entry_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`site_id`			smallint(3) unsigned	NOT NULL DEFAULT '1',
	`private`			char(1)					NOT NULL DEFAULT 'n',
	KEY					`group_id` (group_id),
	KEY					`entry_id` (entry_id),
	KEY					`member_id` (member_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_notification_log` (
	`log_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`total_sent`		int(6) unsigned			NOT NULL DEFAULT '0',
	`from_name`			varchar(70)				NOT NULL DEFAULT '',
	`from_email`		varchar(70)				NOT NULL DEFAULT '',
	`recipient`			text,
	`cc`				text,
	`bcc`				text,
	`recipient_array`	mediumtext,
	`subject`			varchar(120)			NOT NULL DEFAULT '',
	`message`			mediumtext,
	`plaintext_alt`		mediumtext,
	`mailtype`			varchar(6)				NOT NULL DEFAULT '',
	`text_fmt`			varchar(40)				NOT NULL DEFAULT '',
	`wordwrap`			char(1)					NOT NULL DEFAULT 'y',
	`priority`			char(1)					NOT NULL DEFAULT '3',
	PRIMARY KEY			(log_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_status` (
	`status_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`group_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`site_id`			smallint(3) unsigned	NOT NULL DEFAULT '1',
	`status`			varchar(255)			NOT NULL DEFAULT '',
	`status_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`private`			char(1)					NOT NULL DEFAULT 'n',
	PRIMARY KEY			(status_id),
	KEY					`member_id` (member_id),
	KEY					`site_id` (site_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_hugs` (
	`hug_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`friend_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`site_id`			int(10) unsigned		NOT NULL DEFAULT '1',
	`hug_label`			varchar(100)			NOT NULL DEFAULT '',
	`email_subject`		varchar(255)			NOT NULL DEFAULT '',
	`email_address`		varchar(255)			NOT NULL DEFAULT '',
	`date`				int(10) unsigned		NOT NULL DEFAULT '0',
	PRIMARY KEY			(hug_id),
	KEY					`member_id` (member_id),
	KEY					`friend_id` (friend_id),
	KEY					`site_id` (site_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_preferences` (
	`pref_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`site_id`			int(10) unsigned		NOT NULL DEFAULT '1',
	`preferences`		text,
	PRIMARY KEY			(pref_id),
	KEY					`site_id` (site_id)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_automations` (
	`automation_id`		int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`site_id`			int(10) unsigned		NOT NULL DEFAULT '1',
	`member_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`action`			varchar(255)			NOT NULL DEFAULT '',
	PRIMARY KEY			(automation_id),
	KEY					`site_id` (site_id),
	KEY					`action` (action)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;


CREATE TABLE IF NOT EXISTS `exp_friends_group_comments` (
	`comment_id`		int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`group_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`author_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`comment`			text,
	`site_id`			int(10) unsigned		NOT NULL DEFAULT '1',
	PRIMARY KEY			(`comment_id`),
	KEY					(`group_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_profile_comments` (
	`comment_id`		int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`author_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`friend_id`			int(10) unsigned		NOT NULL DEFAULT '0',
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`comment`			text,
	`site_id`			int(10) unsigned		NOT NULL DEFAULT '1',
	PRIMARY KEY			(`comment_id`),
	KEY					(`friend_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_friends_params` (
	`params_id`			int(10) unsigned		NOT NULL AUTO_INCREMENT,
	`hash`				varchar(25)				NOT NULL DEFAULT '',
	`entry_date`		int(10) unsigned		NOT NULL DEFAULT '0',
	`data`				text,
	PRIMARY KEY			(`params_id`),
	KEY					`hash` (`hash`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;