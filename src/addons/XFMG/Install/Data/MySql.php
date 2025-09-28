<?php

namespace XFMG\Install\Data;

use XF\Db\Schema\Create;

class MySql
{
	public function getTables()
	{
		$tables = [];

		$tables['xf_mg_album'] = function(Create $table)
		{
			$table->addColumn('album_id', 'int')->autoIncrement();
			$table->addColumn('category_id', 'int')->unsigned()->setDefault(0);
			$table->addColumn('album_hash', 'varchar', 32)->nullable();
			$table->addColumn('title', 'text');
			$table->addColumn('description', 'text');
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('last_update_date', 'int')->setDefault(0);
			$table->addColumn('media_item_cache', 'mediumblob')->nullable();
			$table->addColumn('view_privacy', 'enum', ['public', 'members', 'private', 'shared', 'inherit'])->nullable()->setDefault('private');
			$table->addColumn('view_users', 'mediumblob')->nullable();
			$table->addColumn('add_privacy', 'enum', ['public', 'members', 'private', 'shared', 'inherit'])->nullable()->setDefault('private');
			$table->addColumn('add_users', 'mediumblob')->nullable();
			$table->addColumn('album_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('media_count', 'int')->setDefault(0);
			$table->addColumn('view_count', 'int')->setDefault(0);
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('rating_avg', 'float', '')->setDefault(0);
			$table->addColumn('rating_weighted', 'float', '')->setDefault(0);
			$table->addColumn('comment_count', 'int')->setDefault(0);
			$table->addColumn('last_comment_date', 'int')->setDefault(0);
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('default_order', 'enum')->values(['','custom'])->setDefault('');
			$table->addColumn('thumbnail_date', 'int')->unsigned()->setDefault(0);
			$table->addColumn('custom_thumbnail_date', 'int')->setDefault(0);
			$table->addColumn('last_comment_id', 'int')->setDefault(0);
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0);
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('');
			$table->addKey('create_date', 'album_create_date');
			$table->addKey(['user_id', 'create_date'], 'album_user_id_album_create_date');
			$table->addKey('last_comment_user_id');
			$table->addKey('last_comment_date');
		};

		$tables['xf_mg_album_comment_read'] = function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('album_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'album_id']);
			$table->addKey('album_id');
			$table->addKey('comment_read_date');
		};

		$tables['xf_mg_album_view'] = function(Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('album_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('album_id');
		};

		$tables['xf_mg_album_watch'] = function(Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('album_id', 'int');
			$table->addColumn('notify_on', 'enum')->values(['','media','comment','media_comment']);
			$table->addColumn('send_alert', 'tinyint');
			$table->addColumn('send_email', 'tinyint');
			$table->addPrimaryKey(['user_id', 'album_id']);
			$table->addKey(['album_id', 'notify_on']);
		};

		$tables['xf_mg_attachment_exif'] = function(Create $table)
		{
			$table->addColumn('attachment_id', 'int');
			$table->addColumn('attach_date', 'int');
			$table->addColumn('exif_data', 'mediumblob')->nullable();
			$table->addPrimaryKey('attachment_id');
		};

		$tables['xf_mg_category'] = function(Create $table)
		{
			$table->addColumn('category_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100);
			$table->addColumn('description', 'text');
			$table->addColumn('parent_category_id', 'int')->setDefault(0);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addColumn('lft', 'int')->setDefault(0);
			$table->addColumn('rgt', 'int')->setDefault(0);
			$table->addColumn('depth', 'smallint', 5)->setDefault(0);
			$table->addColumn('breadcrumb_data', 'blob');
			$table->addColumn('category_type', 'enum')->values(['media', 'album', 'container'])->setDefault('media');;
			$table->addColumn('allowed_types', 'blob');
			$table->addColumn('media_count', 'int')->setDefault(0);
			$table->addColumn('album_count', 'int')->setDefault(0);
			$table->addColumn('comment_count', 'int')->setDefault(0);
			$table->addColumn('field_cache', 'mediumblob');
			$table->addColumn('min_tags', 'smallint', 5)->setDefault(0);
			$table->addColumn('category_index_limit', 'int')->nullable();
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		};

		$tables['xf_mg_category_watch'] = function(Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('category_id', 'int');
			$table->addColumn('notify_on', 'enum')->values(['','media']);
			$table->addColumn('send_alert', 'tinyint');
			$table->addColumn('send_email', 'tinyint');
			$table->addColumn('include_children', 'tinyint');
			$table->addPrimaryKey(['user_id', 'category_id']);
			$table->addKey(['category_id', 'notify_on']);
		};

		$tables['xf_mg_comment'] = function(Create $table)
		{
			$table->addColumn('comment_id', 'int')->autoIncrement();
			$table->addColumn('content_id', 'int')->setDefault(0);
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('comment_date', 'int')->setDefault(0);
			$table->addColumn('comment_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('rating_id', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey('comment_date');
			$table->addKey(['content_type', 'content_id', 'comment_date']);
			$table->addKey('user_id');
			$table->addKey('rating_id');
		};

		$tables['xf_mg_media_field'] = function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('below_media');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('album_use', 'tinyint')->setDefault(1);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addColumn('display_add_media', 'tinyint')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		};

		$tables['xf_mg_category_field'] = function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('category_id', 'int', 10)->unsigned();
			$table->addPrimaryKey(['field_id', 'category_id']);
			$table->addKey('category_id');
		};

		$tables['xf_mg_media_field_value'] = function(Create $table)
		{
			$table->addColumn('media_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['media_id', 'field_id']);
			$table->addKey('field_id');
		};

		$tables['xf_mg_media_item'] = function(Create $table)
		{
			$table->addColumn('media_id', 'int')->autoIncrement();
			$table->addColumn('media_hash', 'varchar', 32)->nullable();
			$table->addColumn('title', 'text');
			$table->addColumn('description', 'text');
			$table->addColumn('media_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_comment_date', 'int')->setDefault(0);
			$table->addColumn('media_type', 'varbinary', 25);
			$table->addColumn('media_tag', 'text')->nullable();
			$table->addColumn('media_embed_url', 'text')->nullable();
			$table->addColumn('media_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('album_id', 'int')->setDefault(0);
			$table->addColumn('category_id', 'int')->setDefault(0);
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 50);
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('comment_count', 'int')->setDefault(0);
			$table->addColumn('view_count', 'int')->setDefault(0);
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('rating_avg', 'float', '')->setDefault(0);
			$table->addColumn('rating_weighted', 'float', '')->setDefault(0);
			$table->addColumn('watermarked', 'tinyint')->setDefault(0)->unsigned();
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('exif_data', 'mediumblob');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('position', 'int')->setDefault(0);
			$table->addColumn('imported', 'int')->setDefault(0);
			$table->addColumn('thumbnail_date', 'int')->unsigned()->setDefault(0);
			$table->addColumn('custom_thumbnail_date', 'int')->setDefault(0);
			$table->addColumn('poster_date', 'int')->setDefault(0);
			$table->addColumn('last_comment_id', 'int')->setDefault(0);
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0);
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('');
			$table->addColumn('tags', 'mediumblob');
			$table->addUniqueKey('media_hash');
			$table->addKey('position');
			$table->addKey('media_date');
			$table->addKey('last_comment_user_id');
			$table->addKey('last_comment_date');
			$table->addKey(['user_id', 'media_date']);
			$table->addKey(['album_id', 'media_date']);
			$table->addKey(['category_id', 'media_date']);
		};

		$tables['xf_mg_media_temp'] = function(Create $table)
		{
			$table->addColumn('temp_media_id', 'int')->autoIncrement();
			$table->addColumn('media_hash', 'varchar', 32);
			$table->addColumn('temp_media_date', 'int');
			$table->addColumn('media_type', 'varbinary', 25);
			$table->addColumn('user_id', 'int');
			$table->addColumn('title', 'text');
			$table->addColumn('description', 'text');
			$table->addColumn('thumbnail_date', 'int');
			$table->addColumn('poster_date', 'int')->setDefault(0);
			$table->addColumn('attachment_id', 'int')->nullable();
			$table->addColumn('requires_transcoding', 'tinyint')->setDefault(0);
			$table->addColumn('exif_data', 'mediumblob')->nullable();
			$table->addUniqueKey('media_hash');
			$table->addUniqueKey('attachment_id');
		};

		$tables['xf_mg_media_user_view'] = function(Create $table)
		{
			$table->addColumn('media_view_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('media_id', 'int');
			$table->addColumn('media_view_date', 'int');
			$table->addUniqueKey(['user_id', 'media_id']);
			$table->addKey('media_id');
			$table->addKey('media_view_date');
		};

		$tables['xf_mg_media_comment_read'] = function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('media_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'media_id']);
			$table->addKey('media_id');
			$table->addKey('comment_read_date');
		};

		$tables['xf_mg_media_note'] = function(Create $table)
		{
			$table->addColumn('note_id', 'int')->autoIncrement();
			$table->addColumn('note_type', 'varbinary', 25)->setDefault('user_tag');
			$table->addColumn('media_id', 'int')->setDefault(0);
			$table->addColumn('note_data', 'blob')->nullable();
			$table->addColumn('note_text', 'text')->nullable();
			$table->addColumn('note_date', 'int')->setDefault(0);
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('tag_state', 'enum')->values(['approved','pending','rejected'])->setDefault('approved');
			$table->addColumn('tag_state_date', 'int')->setDefault(0);
			$table->addColumn('tagged_user_id', 'int')->setDefault(0);
			$table->addColumn('tagged_username', 'varchar', 50)->setDefault('');
			$table->addKey('media_id');
		};

		$tables['xf_mg_media_view'] = function(Create $table)
		{
			$table->engine('MEMORY');

			$table->addColumn('media_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('media_id');
		};

		$tables['xf_mg_media_watch'] = function(Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('media_id', 'int');
			$table->addColumn('notify_on', 'enum')->values(['','comment'])->setDefault('');
			$table->addColumn('send_alert', 'tinyint');
			$table->addColumn('send_email', 'tinyint');
			$table->addPrimaryKey(['user_id', 'media_id']);
			$table->addKey(['media_id', 'notify_on']);
		};

		$tables['xf_mg_rating'] = function(Create $table)
		{
			$table->addColumn('rating_id', 'int')->autoIncrement();
			$table->addColumn('content_id', 'int');
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('rating', 'tinyint');
			$table->addColumn('rating_date', 'int');
			$table->addUniqueKey(['content_type', 'content_id', 'user_id'], 'content_type_id_user_id');
		};

		$tables['xf_mg_shared_map_add'] = function(Create $table)
		{
			$table->addColumn('album_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addPrimaryKey(['album_id', 'user_id']);
		};

		$tables['xf_mg_shared_map_view'] = function(Create $table)
		{
			$table->addColumn('album_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addPrimaryKey(['album_id', 'user_id']);
		};

		$tables['xf_mg_transcode_queue'] = function(Create $table)
		{
			$table->addColumn('transcode_queue_id', 'int')->autoIncrement();
			$table->addColumn('queue_data', 'mediumblob');
			$table->addColumn('queue_state', 'enum')->values(['pending','processing'])->nullable()->setDefault('pending');
			$table->addColumn('queue_date', 'int');
			$table->addKey('queue_date');
		};

		return $tables;
	}

	public function getData()
	{
		$data = [];

		$data['xf_mg_category'] = "
			REPLACE INTO xf_mg_category
				(category_id, title, description, allowed_types, parent_category_id, depth, lft, rgt, display_order, breadcrumb_data, media_count, field_cache)
			VALUES
				(1, 
				'Example category', 
				'This is an example media gallery category. You can manage the media gallery categories via the <a href=\"admin.php?media-gallery/categories/\" target=\"_blank\">Admin control panel</a>. From there, you can setup more categories or change the media gallery options.',
				'[\"image\",\"video\",\"audio\",\"embed\"]',
				'0', '0', '1', '2', '100', '[]', '0', '')
		";

		return $data;
	}
}