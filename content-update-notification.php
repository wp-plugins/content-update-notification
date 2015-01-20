<?php
/*
Plugin Name: Content Update Notification
Plugin URI: http://reaktivstudios.com/custom-plugins
Description: Alert users and other people when content has been created or changed.
Author: Andrew Norcross
Version: 1.0.1
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
	define( 'CNUPDN_VER', '1.0.1' );
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
		add_action( 'plugins_loaded',           array( $this, 'textdomain'          )           );
		add_action( 'plugins_loaded',           array( $this, 'load_files'          )           );
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return
	 */
	public static function getInstance() {

		if ( ! self::$instance ) {
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
	public static function email_tag_data() {

		// build our array of tags
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

		// filter available tags
		$tags   = apply_filters( 'cun_email_tag_list', $tags );

		// return the tags (or false if empty)
		return ! empty( $tags ) ? $tags : false;
	}


	/**
	 * filter the available post types the
	 * plugin will function on
	 *
	 * @return [type] [description]
	 */
	public static function content_types() {
		return apply_filters( 'cun_content_types', array( 'post', 'page' ) );
	}

	/**
	 * filter the post statuses the notifications
	 * will be triggered on
	 *
	 * @return [type] [description]
	 */
	public static function content_statuses() {
		return apply_filters( 'cun_content_statuses', array( 'publish', 'pending', 'future', 'private' ) );
	}

	/**
	 * get the content of the email from the settings and
	 * run it through the various filters
	 *
	 * @param  [type] $data    [description]
	 * @param  [type] $content [description]
	 * @return [type]          [description]
	 */
	public static function convert_email_tags( $post_id, $data, $text ) {

		// fetch the email tag data
		$tags   = self::email_tag_data();

		// bail with no tags, return just the text
		if ( ! $tags ) {
			return $text;
		}

		// get some data for swapping
		$site   = get_bloginfo( 'name' );
		$name   = get_the_title( $post_id );
		$time   = get_post_modified_time( apply_filters( 'cun_date_format', 'm/d/Y @ g:i a' ), false, $post_id, false );
		$link   = get_permalink( $post_id );
		$user   = isset( $data['user_id'] ) ? get_the_author_meta( 'display_name', $data['user_id'] ) : '';

		// set up the arrays for the find / replace
		$hold   = array( '{content-site}', '{content-name}', '{content-time}', '{content-view-link}', '{content-edit-user}' );
		$full   = array( $site, $name, $time, $link, $user );

		// do the find / replace
		$text   = str_replace( $hold, $full, $text );

		// filter and return the text for other possible search replace
		return apply_filters( 'cua_convert_email_text', $text );
	}

	/**
	 * get the email subject line
	 *
	 * @param  [type] $settings [description]
	 * @return [type]           [description]
	 */
	public static function get_email_subject( $post_id, $data, $settings ) {

		// set a default
		$default    = __( 'Content has recently been changed', 'content-update-notification' );

		// check for a user generated value
		if ( ! empty( $settings['subject'] ) ) {
			$subject    = self::convert_email_tags( $post_id, $data, $settings['subject'] );
		} else {
			$subject    = self::convert_email_tags( $post_id, $data, $default );
		}

		// run the filter and return
		return apply_filters( 'cua_email_subject', $subject );
	}

	/**
	 * get the email content
	 *
	 * @param  [type] $settings [description]
	 * @return [type]           [description]
	 */
	public static function get_email_content( $post_id, $data, $settings ) {

		// build the default
		$default	= 'The item {content-name} was updated at {content-time} by {content-edit-user}'."\n";
		$default	.= 'You can view the content here: {content-view-link}';

		// check for a user generated value
		if ( ! empty( $settings['content'] ) ) {
			$content    = self::convert_email_tags( $post_id, $data, $settings['content'] );
		} else {
			$content    = self::convert_email_tags( $post_id, $data, $default );
		}

		// run the filter and return
		return apply_filters( 'cua_email_content', $content );
	}

	/**
	 * get the "from" name in the email
	 *
	 * @return [type] [description]
	 */
	public static function get_email_from_name() {

		// fetch the site name run it through the filter
		$name   = apply_filters( 'cun_email_from_name', get_bloginfo( 'name' ) );

		// run the check and return the default if no one sets it
		if ( ! $name || empty( $name ) ) {
			return get_bloginfo( 'name' );
		}

		// return it escaped
		return esc_html( $name );
	}

	/**
	 * get the "from" address
	 *
	 * @return [type] [description]
	 */
	public static function get_email_from_address() {

		// fetch the site admin email run it through the filter
		$addr   = apply_filters( 'cun_email_from_address', get_option( 'admin_email' ) );

		// return the user set one or admin email as fallback
		return ! $addr || empty( $addr ) || ! is_email( $addr ) ? get_option( 'admin_email' ) : sanitize_email( $addr );
	}

	/**
	 * get the items for the email based on post ID
	 * @param  [type] $post_id [description]
	 * @param  [type] $data    [description]
	 * @return [type]          [description]
	 */
	public static function get_email_items( $post_id = 0, $data ) {

		// fetch our settings and bail if we don't have any
		$settings   = get_option( 'cun-settings' );

		// bail with no settings
		if ( ! $settings ) {
			return false;
		}

		// get the email pieces
		$subject    = self::get_email_subject( $post_id, $data, $settings );
		$content    = self::get_email_content( $post_id, $data, $settings );

		if ( ! $subject || ! $content ) {
			return false;
		}

		// fetch some basic info
		$from_name  = self::get_email_from_name();
		$from_addr  = self::get_email_from_address();

		// return the array
		return array(
			'from-name'	=> $from_name,
			'from-addr'	=> $from_addr,
			'subject'	=> $subject,
			'content'	=> $content
		);
	}

	/**
	 * get the list items for the email
	 *
	 * @return [type] [description]
	 */
	public static function get_email_list() {

		// fetch the settings
		$settings	= get_option( 'cun-settings' );

		// bail if settings list is empty
		if ( empty( $settings['list'] ) ) {
			return false;
		}

		// bust out our list into an array
		$list   = explode(',', $settings['list'] );

		// trim each item and return
		return array_map( 'trim', $list );
	}

	/**
	 * format the email content
	 *
	 * @param  [type] $content [description]
	 * @return [type]          [description]
	 */
	public static function format_email_content( $content ) {

		// set an empty
		$message    = '';

		$message   .= '<html>'."\n";
		$message   .= '<body>'."\n";
		$message   .= apply_filters( 'cun_formatted_email_before', '' );
		$message   .= wpautop( $content );
		$message   .= apply_filters( 'cun_formatted_email_after', '' );
		$message   .= '</body>'."\n";
		$message   .= '</html>'."\n";

		// send it back
		return trim( $message );
	}

	/**
	 * setup the help tab content
	 * @param  [type] $tab [description]
	 * @return [type]      [description]
	 */
	public static function help_content( $tab = false ) {

		// admin filters
		$help['admin-filters']	= __( '<code>cun_before_email_notification_settings</code>', 'content-notification-settings' );

		// return our requested help tab or false
		return ! empty( $help[$tab] ) ? $help[$tab] : false;
	}

/// end class
}

// Instantiate our class
$CUN_Core = CUN_Core::getInstance();