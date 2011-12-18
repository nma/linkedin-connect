=== LinkedIn Connect ===
Tags: simple, linked, connect
Contributors: swayt10
Tested up to: 1.0.2
Requires at least: 1.0

Adds a single-click seamless Linkedin Connect -based registration/login to your bbPress.

== Description ==

Adds a single-click seamless LinkedIn Connect -based registration/login to your bbPress. 
The user is not bothered with anything extra (except email), and is automatically logged in right after accepting the LinkedIn authorization popup.

DEMO SITE: http://www.nickmacreations.com/forum/

* edit the theme, and place `<?php li_login_button(); ?>` where you want the login button to appear

A LinkedIn Application ID and Secret is required, as the LinkedIn Connect requires these.

Other features:

* LinkedIn Avatars are displayed automatically (if avatars are enabled in your bbPress settings)
* No unnecessary registration email spam is sent to the user. Though the email prompt will appear continuously.
* Disables password edit for LinkedIn connected users; the random password assigned to the account is never used (user can edit in profile).
* Select how users Display Name is set from LinkedIn (Full Name, First Name or Last Name)
* LinkedIn does not provide email. A dummy email is set for the user and will issue a prompts until the user changes their email.
* (optional) Disables profile edit for LinkedIn connected users
* (optional) Hide "You must login" from post form, which leads to the traditional login form. This would confuse LinkedIn Connected users, as they cannot login traditionally.
* (optional) If conflicting calls to jQuery the plugin can disable its own call. 

== Installation ==
Step 1------------------------------------------------------------------------------------------
add line in profile-edit.php in root folder after 
// Find out if we have a valid email address
	if ( isset( $user_email ) && !$user_email = is_email( $user_email ) ) {
		$errors->add( 'user_email', __( 'Invalid email address' ), array( 'data' => $_POST['user_email'] ) );
	}

add at the end the following line(unquoted)

 "elseif ( (bb_get_user($user_email, array ('by' => 'email')) !== false) && (bb_get_user_email($bb_current_id) !== $user_email)) {
		if ($bb_current_id != '1')
		$errors->add( 'user_email', __( 'Email address exists!' ), array( 'data' => $_POST['user_email'] ) );
	} elseif ( strpos($user_email,'none.local') !== FALSE) {
		if ($bb_current_id != '1')
		$errors->add( 'user_email', __( 'Please update your email!' ), array( 'data' => $_POST['user_email'] ) );
	} "
Step 2------------------------------------------------------------------------------------------
In bb-settings.php function "bb_unregister_GLOBALS" add 'oauth' to the line like below
// Variables that shouldn't be unset
	$noUnset = array( 'GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES', 'bb_table_prefix', 'bb' , 'oauth');
	
= Prerequirements =

1. PHP 5 (tested on 5.2 and 5.3)
2. PHP CURL and JSON extensions (required by LinkedIn's PHP connector)

= Installation =

1. Unzip plugin zip to plugin folder. Make sure that the whole linkedin-connect folder is there.
2. Activate plugin in bb-admin
3. Configure plugin (bb-admin -> Settings -> LinkedIn Connect)
4. Edit your theme's header.php:
5. Edit your theme, and place the LinkedIn login button on a suitable place. Use the function li_login_button() to add it:
5.1. `<?php if ( function_exists ( 'li_login_button' ) ) { li_login_button(); } ?>`
6. DONE

== Other Notes ==

= Why are the avatars not showing up? =
Have you enabled avatars in 'bb-admin -> Settings -> Discussion' ?

= Why is the avatar of such poor quality? I want a big good quality image! =
The only square image LinkedIn provides is 50px in height and width. All the larges images sizes are not squared, and would thus likely display distorted.

= I get a strange error from LinkedIn in the login popup. What's up? =
1. Check that your Application ID and Secret are correct
2. In LinkedIn Application plugins directory, ensure 'oauth.php' and 'linkedin_x.x.x.class.php' are there.
3. In LinkedIn Application Settings, add your sites domain name (domain.com if your site is www.domain.com) to 'Site Domain' field (Web Site-tab)

= What if I have other plugins using the LinkedIn as well, will these conflict? =
If the plugins are using the similar linkedin variables, then yes, very likely.
(for developers) avoid metadata 'linkedin_id' ; 'prompt_email' ; 'li_avatar' ; and function names with li_ in them.

This plugin has an option to disable the initialization of jQuery. This should be the first thing to try if you have a conflict situation. 

== Changelog ==

= Version 1.0 (2011-12-01) =

* First release for www.ComplianceForum.com.au

