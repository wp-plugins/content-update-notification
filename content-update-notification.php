<?php
/*
Plugin Name: Content Update Notification
Plugin URI: http://southernweb.com/
Description: Alert users and other people when content has been created or changed.
Author: Andrew Norcross and Southern Web Group
Version: 1.0.0
Requires at least: 3.7
Author URI: http://southernweb.com/
*/
/*  Copyright 2014 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License (GPL v2) only.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if( ! defined( 'CNUPDN_BASE ' ) ) {
	define( 'CNUPDN_BASE', plugin_basename(__FILE__) );
}

if( ! defined( 'CNUPDN_VER' ) ) {
	define( 'CNUPDN_VER', '1.0.0' );
}


class CUN_Core
{

	/**
	 * Static property to hold our singleton instance
	 * @var $instance
	 */
	static $instance = false;

	/**
	 * this is our constructor.
	 * there are many like it, but this one is mine
	 */
	private function __construct() {
		add_action		(	'plugins_loaded',			array(  $this,  'textdomain'			)			);
		add_action		(	'plugins_loaded',			array(	$this,	'load_files'			)			);

	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return
	 */

	public static function getInstance() {

		if ( !self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * [textdomain description]
	 * @return [type] [description]
	 */
	public function textdomain() {

		load_plugin_textdomain( 'content-update-notification', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * [load_files description]
	 * @return [type] [description]
	 */
	public function load_files() {

		require_once( 'lib/admin.php'	);
		require_once( 'lib/content.php'	);

	}

	/**
	 * build and display the available tags to use within the email content
	 * the "item" portion is tied to where the data lives, either a function, the
	 * database, or part of the $_POST data
	 *
	 * @return [type] [description]
	 */
	static function email_tag_data() {

		$tags	= array(
			array(
				'code'	=> '{content-site}',
				'label'	=> __( 'Name of site the content resides on', 'content-update-notification' )
			),
			array(
				'code'	=> '{content-name}',
				'label'	=> __( 'Name of content edited', 'content-update-notification' )
			),
			array(
				'code'	=> '{content-time}',
				'label'	=> __( 'Time of edit', 'content-update-notification' )
			),
			array(
				'code'	=> '{content-view-link}',
				'label'	=> __( 'Link to content', 'content-update-notification' )
			),
			array(
				'code'	=> '{content-edit-user}',
				'label'	=> __( 'Username who processed update', 'content-update-notification' )
			),
		);

		$tags	= apply_filters( 'cun_email_tag_list', $tags );

		return $tags;

	}


	/**
	 * [content_types description]
	 * @return [type] [description]
	 */
	static function content_types() {

		$types	= apply_filters( 'cun_content_types', array( 'post', 'page' ) );

		return $types;

	}

	/**
	 * [content_statuses description]
	 * @return [type] [description]
	 */
	static function content_statuses() {

		$statuses	= apply_filters( 'cun_content_statuses', array( 'publish', 'pending', 'future', 'private' ) );

		return $statuses;

	}

	/**
	 * get the content of the email from the settings and run it through the various filters
	 * @param  [type] $data    [description]
	 * @param  [type] $content [description]
	 * @return [type]          [description]
	 */
	static function convert_email_tags( $post_id, $data, $text ) {

		$tags	= self::email_tag_data();

		if ( ! $tags ) {
			return $text;
		}

		// get some data for swapping
		$site	= get_bloginfo( 'name' );
		$name	= get_the_title( $post_id );
		$time	= get_post_modified_time( apply_filters( 'cun_date_format', 'm/d/Y @ g:i a' ), false, $post_id, false );
		$link	= get_permalink( $post_id );
		$user	= isset( $data['user_id'] ) ? get_the_author_meta( 'display_name', $data['user_id'] ) : '';

		$hold	= array( '{content-site}', '{content-name}', '{content-time}', '{content-view-link}', '{content-edit-user}' );
		$full	= array( $site, $name, $time, $link, $user );

		$text	= str_replace( $hold, $full, $text );

		// filter the text for other possible search replace
		$text	= apply_filters( 'cua_convert_email_text', $text );

		// send back the filtered text
		return $text;

	}

	/**
	 * [get_email_subject description]
	 * @param  [type] $settings [description]
	 * @return [type]           [description]
	 */
	static function get_email_subject( $post_id, $data, $settings ) {

		$default	= __( 'Content has recently been changed', 'content-update-notification' );

		if ( isset( $settings['subject'] ) && ! empty( $settings['subject'] ) ) {
			$subject	= self::convert_email_tags( $post_id, $data, $settings['subject'] );
		} else {
			$subject	= self::convert_email_tags( $post_id, $data, $default );
		}

		// run the filter
		$subject	= apply_filters( 'cua_email_subject', $subject );

		// send it back
		return $subject;

	}

	/**
	 * [get_email_content description]
	 * @param  [type] $settings [description]
	 * @return [type]           [description]
	 */
	static function get_email_content( $post_id, $data, $settings ) {

		$default	= 'The item {content-name} was updated at {content-time} by {content-edit-user}'."\n";
		$default	.= 'You can view the content here: {content-view-link}';

		if ( isset( $settings['content'] ) && ! empty( $settings['content'] ) ) {
			$content	= self::convert_email_tags( $post_id, $data, $settings['content'] );
		} else {
			$content	= self::convert_email_tags( $post_id, $data, $default );
		}

		// run the filter
		$content	= apply_filters( 'cua_email_content', $content );

		// send it back
		return $content;

	}

	/**
	 * [get_email_from_name description]
	 * @return [type] [description]
	 */
	static function get_email_from_name() {

		// fetch the site name run it through the filter
		$name	= apply_filters( 'cun_email_from_name', get_bloginfo( 'name' ) );

		// run the check and return the default if no one sets it
		if ( ! $name || empty( $name ) ) {
			return get_bloginfo( 'name' );
		}

		// return it escaped
		return esc_html( $name );

	}

	/**
	 * [get_email_from_address description]
	 * @return [type] [description]
	 */
	static function get_email_from_address() {

		// fetch the site admin email run it through the filter
		$address	= apply_filters( 'cun_email_from_address', get_option( 'admin_email' ) );

		if ( ! $address || empty( $address ) || ! is_email( $address ) ) {
			return get_option( 'admin_email' );
		}

		// return it escaped
		return $address;

	}

	/**
	 * [get_email_items description]
	 * @param  [type] $post_id [description]
	 * @param  [type] $data    [description]
	 * @return [type]          [description]
	 */
	static function get_email_items( $post_id, $data ) {

		// fetch our settings and bail if we don't have any
		$settings	= get_option( 'cun-settings' );

		if ( ! $settings ) {
			return false;
		}

		// get the email pieces
		$subject	= self::get_email_subject( $post_id, $data, $settings );
		$content	= self::get_email_content( $post_id, $data, $settings );

		if ( ! $subject || ! $content ) {
			return false;
		}

		// fetch some basic info
		$from_name	= self::get_email_from_name();
		$from_addr	= self::get_email_from_address();

		return array(
			'from-name'	=> $from_name,
			'from-addr'	=> $from_addr,
			'subject'	=> $subject,
			'content'	=> $content
		);

	}

	/**
	 * [get_email_list description]
	 * @return [type] [description]
	 */
	static function get_email_list() {

		$settings	= get_option( 'cun-settings' );

		if ( ! $settings['list'] )
			return false;

		// bust out our list into an array
		$list	= explode(',', $settings['list'] );

		// trim each item
		$list	= array_map( 'trim', $list );

		// send it back
		return $list;

	}

	/**
	 * [format_email_content description]
	 * @param  [type] $content [description]
	 * @return [type]          [description]
	 */
	static function format_email_content( $content ) {

		$message	= '';

		$message	.= '<html>'."\n";
		$message	.= '<body>'."\n";
		$message	.= apply_filters( 'cun_formatted_email_before', '' );
		$message	.= wpautop( $content );
		$message	.= apply_filters( 'cun_formatted_email_after', '' );
		$message	.= '</body>'."\n";
		$message	.= '</html>'."\n";

		// send it back
		return trim( $message );

	}

	/**
	 * [help_content description]
	 * @param  [type] $tab [description]
	 * @return [type]      [description]
	 */
	static function help_content( $tab = false ) {

		$help['admin-filters']	= __( '<code>cun_before_email_notification_settings</code>', 'content-notification-settings' );


		// bail if we don't have our requested tab
		if ( ! isset( $help[$tab] ) || isset( $help[$tab] ) && empty( $help[$tab] ) ) {
			return;
		}

		// return our requested help tab
		return $help[$tab];

	}

/// end class
}

// Instantiate our class
$CUN_Core = CUN_Core::getInstance();