<?php
/*
Plugin Name: Simple Membership WP Multi-Members Forms
Plugin URI:
Description: Allow multi membership to be added after payment to a custom page using a shortcode
Author: info@grafxflow.co.uk
Author URI: https://grafxflow.co.uk
Version: 1.0
*/

$form_errors = array();

/* Custom Member Functions */
function create_multiple_members( $members ) {

	foreach($members['user_email'] as $key => $member) {

		if ( null == username_exists( $member ) ) {

			// Generate the password and create the user
			$password = wp_generate_password( 12, false );

			$user_id = wp_create_user( $member, $password, $member );

			// Set the nickname
			wp_update_user(
				array(
					'ID'       =>    $user_id,
					'nickname' =>    $member
				)
			);

			// Set the role
			$user = new WP_User( $user_id );
			$user->set_role( 'subscriber' );

			// Add members
			add_member($user);

			$email_subject = 'Thank you for joining...';
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$headers[] = 'From: {} <i>';

			// Email the user
			ob_start();
			include('template/email-header.php');
			printf(__('<p>Thank you for joining %2$s!</p>', 'cell-email'), '', get_bloginfo('name'));
			printf(__('<p> Your password is <strong style="color:#6a4d81;">'.$password.'</strong> <br> Please keep it secret and keep it safe! </p>', 'cell-email'), $password);
			printf(__('<p>We hope you enjoy your stay at %s. If you have any problems, questions, opinions, praise, comments, suggestions, please feel free to contact us at any time.</p>', 'cell-email'), get_bloginfo('name'));
			include('template/email-footer.php');
			$message = ob_get_contents();
			ob_end_clean();
			wp_mail($member, $email_subject, $message, $headers);

		}
	}

	// Email admin with email address that have been added
	$email_subject = 'There are some new members added to...';
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$headers[] = 'From: [] <>';

	ob_start();
	include('template/email-header.php');
	printf(__('<p>The following emails have been added.</p>', 'cell-email'), '', get_bloginfo('name'));

	foreach($members['user_email'] as $key => $member) {
		printf(__('<p> Email <strong style="color:#6a4d81;">'.$member.'</strong><br></p>', 'cell-email'), $password);
	}

	include('template/email-footer.php');
	$message = ob_get_contents();
	ob_end_clean();
	wp_mail($member, $email_subject, $message, $headers);

	// Redirect to thank you page
	$redirect_url = '/membership-join/thank-you-for-your-registration/';
	echo "<script>location.href = '$redirect_url';</script>";
	exit;

	// Conlifct with hp_header so don't use
	// wp_redirect($url);
	// exit;
}

add_action( 'members_order_success', 'create_multiple_members' );

/* Add shortcode for use on pages for Members registration */
function wp_create_members_form_shortcode($members) {

	$form = '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';

	extract(shortcode_atts(array(
		'members' => null,
	), $members));

	$i = 1;
	while($i <= $members) {
		// $form .= "This member is number ".$i." <br />";
		$form .='<label for="member_email_'.$i.'">Enter Members '.$i.' Email *</label>';

		if(isset($_POST['user_email'][$i - 1])){
			$userEmail = $_POST['user_email'][$i - 1];
		} else {
			$userEmail = null;
		}
		$form .= '<input type="text" name="user_email[]" value="' . $userEmail . '" id="user_email" class="input"  placeholder="Enter Members '.$i.' Email" />';
		$i++;
	}

	if($members >= 1){
		$form .= '<input type="submit" name="member-form-submitted" value="Submit">';
	}

	$form .= '</form>';

	process_form($members);

	// Output needs to be return
	return $form;
}

function wpmembers_shortcodes_init()
{
	// register shortcode
	/* EXAMPLE USAGE:
	IMPORTANT Use "" not ''!

	[members_form_shortcode members="4"][/members_form_shortcode]

	*/
	add_shortcode('members_form_shortcode', 'wp_create_members_form_shortcode');
}

// initiate shortcode
add_action('init', 'wpmembers_shortcodes_init');

function process_form($membersNumber) {

	global $form_errors;

	if ( isset($_POST['member-form-submitted']) ) {

		// call validate_form() to validate the form values
		validate_form($membersNumber, $_POST);

		// display form error if it exist
        if (!empty($form_errors)) {
            foreach ($form_errors as $error) {
                echo '<p>';
                echo '<strong style="color:red;">ERROR</strong>: ';
              	echo $error . '<br/>';
                echo '</p>';
            }
        } else {
			// Add the members to wordpress users
			create_multiple_members($_POST);
        }
	}
}

function validate_form($membersNumber, $members) {

	global $form_errors;

	foreach ($members['user_email'] as $key => $member) {

		// If any field is left empty,
		if ( empty($member) ) {
	        array_push($form_errors, 'No field should be left empty' );
			break;
	    }

		// Check if the email is valid
	    if ( !is_email($member) ) {
	        array_push($form_errors, $member . ' email is not valid' );
	    }
	}

}

function add_member($member) {
	global $wpdb;

	$user_info = get_userdata($member->ID);
	$user_cap = is_array($user_info->wp_capabilities) ? array_keys($user_info->wp_capabilities) : array();
	$fields = array();
	$fields['user_name'] = $user_info->user_login;
	$fields['first_name'] = $user_info->user_firstname;
	$fields['last_name'] = $user_info->user_lastname;
	$fields['password'] = $user_info->user_pass;
	$fields['member_since'] = date('Y-m-d H:i:s');
	// $fields['membership_level'] = $member->membership_level;
	// You need to create a defalt membership level on the backend then update this id number as the default for each memeber.
	$fields['membership_level'] = 3;
	$fields['account_state'] = 'active';
	$fields['email'] = $user_info->user_email;
	$fields['address_street'] = '';
	$fields['address_city'] = '';
	$fields['address_state'] = '';
	$fields['address_zipcode'] = '';
	$fields['country'] = '';
	$fields['gender'] = 'not specified';
	$fields['referrer'] = '';
	$fields['last_accessed_from_ip'] = SwpmUtils::get_user_ip_address();
	$fields['subscription_starts'] = date('Y-m-d');
	$fields['extra_info'] = '';

	if (!empty($member->preserve_wp_role)) {
		$fields['flags'] = 1;
	} else {
		$fields['flags'] = 0;
		if (($member->account_state === 'active') && !in_array('administrator', $user_cap)){
			if(method_exists("SwpmMemberUtils", "update_wp_user_role_with_level_id")){
				SwpmMemberUtils::update_wp_user_role_with_level_id($member->ID, $row->membership_level);
			}
		}
	}
	$user_exists = BUtils::swpm_username_exists($fields['user_name']);
	if ($user_exists) {
		return $wpdb->update($wpdb->prefix . "swpm_members_tbl",  $fields, array('member_id'=>$user_exists));
	} else {
		//Insert a new user in SWPM
		$wpdb->insert($wpdb->prefix . "swpm_members_tbl",  $fields);

		//Trigger action hook
		$args = array('first_name' => $fields['first_name'], 'last_name' => $fields['last_name'], 'email' => $fields['email'], 'membership_level' => $fields['membership_level']);
		do_action('swmp_wpimport_user_imported', $args);
		return true;
   }
}
