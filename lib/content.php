<?php

class CUN_Content {

	/**
	 * this is our constructor.
	 * there are many like it, but this one is mine
	 */
	public function __construct() {

		add_action		(	'save_post',					array(	$this,	'status_change_notify'	),	1		);
	}

	/**
	 * [status_change_notify description]
	 * @param  [type] $post_id [description]
	 * @return [type]          [description]
	 */
	public function status_change_notify( $post_id ) {

		// run various checks to make sure we aren't doing anything weird
		if ( defined( 'DOING_CRON' ) && DOING_CRON  || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// load our two arrays of items
		$types		= CUN_Core::content_types();
		$statuses	= CUN_Core::content_statuses();

		// bail if we never set them or someone cleared them
		if ( ! $types || ! $statuses ) {
			return $post_id;
		}

		// fetch item type and compare it
		if ( ! in_array( get_post_type( $post_id ), $types ) ) {
			return $post_id;
		}

		// fetch item status and compare it
		if ( ! in_array( get_post_status( $post_id ), $statuses ) ) {
			return $post_id;
		}

		// run the filter check to include the post ID if we wanna bail it on a single one
		if ( false === apply_filters( 'cun_single_item_email', $post_id ) ) {
			return $post_id;
		}

		// fetch the user ID of the change
		$user_id	= isset( $_POST['user_ID'] ) && ! empty( $_POST['user_ID'] ) ? $_POST['user_ID'] : '';

		// set our $_POST as a data variable
		$data	= array(
			'user_id'	=> $user_id
		);

		// run the data filter to catch other stuff
		$data	= apply_filters( 'cun_notification_data', $_POST, $data, $post_id );

		// send our email
		self::build_notification_email( $post_id, $data );

		return $post_id;

	}

	/**
	 * [process_notification_email description]
	 * @param  [type] $data [description]
	 * @return [type]          [description]
	 */
	static function build_notification_email( $post_id, $data ) {

		// make sure our post ID is actually numeric
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		// now make sure it exists in the DB
		$post_data	= get_post( $post_id );

		if ( ! $post_data ) {
			return;
		}

		// fetch the data related to the email
		$items	= CUN_Core::get_email_items( $post_id, $data );

		// bail if there's nothing to send
		if ( ! $items ) {
			return;
		}

		// fetch the email address list
		$list	= CUN_Core::get_email_list();

		// run the filter to look for item-specific settings
		$list	= apply_filters( 'cun_email_list_members', $list, $post_id );

		if ( ! $list || empty( $list ) ) {
			return;
		}

		// loop through each email address and send the email
		foreach ( $list as $email ) {
			self::send_notification_email( $email, $items );
		}

		// just be done
		return;

	}

	/**
	 * [set_html_content_type description]
	 */
	static function set_html_content_type() {

		return 'text/html';

	}

	/**
	 * [send_notification_email description]
	 * @param  [type] $email [description]
	 * @param  [type] $items [description]
	 * @return [type]        [description]
	 */
	static function send_notification_email( $email, $items ) {

		// bail if we have no data or email
		if ( ! $email || ! $items ) {
			return;
		}

		// switch to HTML format
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );

		// set my headers
		$headers	= 'From: '.$items['from-name'].' <'.$items['from-addr'].'>' . "\r\n" ;
		$headers	= apply_filters( 'cun_email_headers', $headers );

		// run the email body through the formatter
		$content	= CUN_Core::format_email_content( $items['content'] );

		// send the actual email
		$process	= wp_mail( $email, $items['subject'], $content, $headers );

		 // reset content-type
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );

		// set a quick message to return back
		$result		= ! $process ? __( 'Email failed', 'content-update-notification' ) : __( 'Email sent', 'content-update-notification' );

		// then done
		return $result;

	}

// end class
}

new CUN_Content();