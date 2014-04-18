<?php

class CUN_Admin {

	/**
	 * this is our constructor.
	 * there are many like it, but this one is mine
	 */
	public function __construct() {

		add_action		(	'admin_enqueue_scripts',		array(	$this,	'scripts_styles'		),	10		);
		add_action		(	'admin_init', 					array(	$this,	'reg_settings'			) 			);
		add_action		(	'admin_menu',					array(	$this,	'admin_pages'			) 			);
		add_action		(	'admin_notices',				array(	$this,	'settings_saved'		),	10		);

		add_filter		(	'cun_caps',						array(	$this,	'menu_filter'			),	10,	2	);
		add_filter		(	'plugin_action_links',			array(	$this,	'quick_link'			),	10,	2	);

	}

	/**
	 * show settings link on plugins page
	 * @param  [type] $links [description]
	 * @param  [type] $file  [description]
	 * @return [type]        [description]
	 */
	public function quick_link( $links, $file ) {

		static $this_plugin;

		if ( ! $this_plugin ) {
			$this_plugin = CNUPDN_BASE;
		}

		// check to make sure we are on the correct plugin
		if ( $file != $this_plugin ) {
			return $links;
		}

		$settings_link  = '<a href="' . menu_page_url( 'content-notification-settings', 0 ).' ">'.__( 'Settings', 'content-update-notification' ).'</a>';

		array_unshift( $links, $settings_link );

		return $links;

	}

	/**
	 * [scripts_styles description]
	 * @param  [type] $hook [description]
	 * @return [type]       [description]
	 */
	public function scripts_styles( $hook ) {

		$screen	= get_current_screen();

		if ( is_object( $screen ) && $screen->base == 'tools_page_content-notification-settings' ):

			wp_enqueue_style( 'cun-admin', plugins_url( '/css/cun.admin.css', __FILE__), array(), CNUPDN_VER, 'all' );

		endif;

	}

	/**
	 * display message on saved settings
	 * @return [HTML] message above page
	 */
	public function settings_saved() {

		// first check to make sure we're on our settings
		if ( ! isset( $_REQUEST['page'] ) || isset( $_REQUEST['page'] ) && $_REQUEST['page'] !== 'content-notification-settings' ) {
			return;
		}

		// make sure we have our updated prompt
		if ( ! isset( $_REQUEST['settings-updated'] ) || isset( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] !== 'true' ) {
			return;
		}

		echo '<div id="message" class="updated">';
			echo '<p>'.__( 'Settings have been saved.', 'content-update-notification' ).'</p>';
		echo '</div>';

		return;
	}

	/**
	 * [reg_settings description]
	 * @return [type] [description]
	 */
	public function reg_settings() {

		register_setting( 'cun-settings', 'cun-settings' );

	}

	/**
	 * Declare filters
	 *
	 * @return Reaktiv_Ratings
	 */

	public function menu_filter( $capability, $menu ) {

  		// Anybody who can manage options has access to the settings page
  		// If another function has changed this capability already, we'll respect that by just passing the value we were given
		return $capability;
	}

	/**
	 * [admin_pages description]
	 * @return [type] [description]
	 */
	public function admin_pages() {

		$settings	= add_management_page( __( 'Content Notifications', 'content-update-notification' ), __( 'Content Notifications', 'content-update-notification' ), apply_filters( 'cun_caps', 'manage_options', 'cun-settings' ), 'content-notification-settings', array( $this, 'settings_page' ) );

    	// Adds help tab when settings page loads
//		add_action( 'load-'.$settings, array( __CLASS__, 'help_tab' ) );

	}

	/**
	 * [help_tab description]
	 * @return [type] [description]
	 */
	static function help_tab() {

		// fetch the current scren object
		$screen	= get_current_screen();

		// bail if we aren't on our settings page
		if ( ! is_object( $screen ) || is_object( $screen ) && $screen->base != 'tools_page_content-notification-settings' ) {
			return;
		}

		// Add the admin filter help tab
		$screen->add_help_tab( array(
			'id'		=> 'cun-help-admin-filters',
			'title'		=> __( 'Admin Filters', 'content-update-notification' ),
			'content'	=> '<p>' . CUN_Core::help_content( 'admin-filters' ) . '</p>',
		) );



	}

	/**
	 * [email_tag_display description]
	 * @return [type] [description]
	 */
	static function email_tag_display() {

		// fetch list of email tags
		$tags	= CUN_Core::email_tag_data();

		if ( ! $tags ) {
			return;
		}

		$list	= '<ul>';

		foreach ( $tags as $tag ) :
			if ( isset( $tag['label'] ) && isset( $tag['code'] ) ) :
				$list	.= '<li>';
				$list	.= '<span>' . $tag['label'] . '</span>';
				$list	.= '<code>' . $tag['code'] . '</code>';
				$list	.= '</li>';
			endif;
		endforeach;

		$list	.= '</ul>';

		// send it back
		return $list;

	}

	/**
	 * [settings_page description]
	 * @return [type] [description]
	 */
	public function settings_page() {
		// fetch data
		$data	= get_option('cun-settings');

		$list		= isset( $data['list'] )	&& ! empty ( $data['list'] )	? $data['list']		: '';
		$subject	= isset( $data['subject'] )	&& ! empty ( $data['subject'] )	? $data['subject']	: '';
		$content	= isset( $data['content'] )	&& ! empty ( $data['content'] )	? $data['content']	: '';

		?>

        <div class="wrap">
        	<div id="icon-tools" class="icon32"><br /></div>
        	<h2><?php _e( 'Content Update Notifications', 'content-update-notification' ); ?></h2>

				<div class="cun-intro">
					<p><?php _e( 'Use the following tags (with curly brackets) for specific pieces of data created during the notification process', 'content-update-notification' ); ?></p>
					<?php echo self::email_tag_display(); ?>
				</div>

	            <form method="post" action="options.php">
			    <?php settings_fields( 'cun-settings' ); ?>

				<h3 class="title"><?php _e( 'Notification List', 'content-update-notification' ); ?></h3>
				<table class="form-table cun-table">
				<tbody>

					<?php do_action( 'cun_before_email_notification_settings', $data ); ?>

					<tr>
						<th><label for="admin-email-list"><?php _e( 'Emails', 'content-update-notification' ); ?></label></th>
						<td>
						<input type="text" class="widefat" value="<?php echo esc_html( $list ); ?>" id="admin-email-list" name="cun-settings[list]">
						<p class="description"><?php _e( 'Enter email address(es) to notify on changes, separated by commas', 'content-update-notification' ); ?></p>
						</td>
					</tr>

					<?php do_action( 'cun_after_email_notification_settings', $data ); ?>

				</tbody>
				</table>

				<h3 class="title"><?php _e( 'Email Notification', 'content-update-notification' ); ?></h3>
				<table class="form-table cun-table">
				<tbody>

					<?php do_action( 'cun_before_email_content_settings', $data ); ?>

					<tr>
						<th><label for="admin-email-subject"><?php _e( 'Email Subject', 'content-update-notification' ); ?></label></th>
						<td>
						<input type="text" class="widefat" value="<?php echo esc_attr( $subject ); ?>" id="admin-email-subject" name="cun-settings[subject]">
						<p class="description"><?php _e( 'The email subject line', 'content-update-notification' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><label for="admin-email-content"><?php _e( 'Email Content', 'content-update-notification' ); ?></label></th>
						<td>
						<?php
						$args	= array(
							'textarea_name'	=> 'cun-settings[content]',
							'textarea_rows'	=> 6,
							'media_buttons'	=> false
						);
						$args	= apply_filters( 'cun_email_body_editor_args', $args );
						wp_editor( $content, 'adminemailcontent', $args );
						?>
						<p class="description"><?php _e( 'This is the email body that will be sent when content has been added or updated.', 'content-update-notification' ); ?></p>
						</td>
					</tr>

					<?php do_action( 'cun_after_email_content_settings', $data ); ?>

				</tbody>
				</table>

				<p><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
				</form>

		</div>

	<?php }

// end class
}

new CUN_Admin();