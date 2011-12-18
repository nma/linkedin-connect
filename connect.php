<?php
/*
Plugin Name: LinkedIn Connect
Plugin URI: http://www.example.com
Description: Adds a one-click login/registeration integration with Linkedin to bbPress.
Author: swayt10
Version: 1.0
*/

// way to get string attr names to all profile infomation
$li_attr['id'] = 'id';
$li_attr['first-name'] = 'first-name';
$li_attr['last-name'] = 'last-name';
$li_attr['industry'] = 'industry';
$li_attr['picture-url'] = 'picture-url';
$li_attr['headline'] = 'headline';
$li_attr['public-profile-url'] = 'public-profile-url';

/**************************************************************
 * Main Plugin Portions Start Here
 **************************************************************/
if (!class_exists('LinkedIn')) {
	include_once('linkedin_3.2.0.class.php');
}
//let's try connecting to linkedinadd_action('init', 'process_post');
define('PORT_HTTP', '8080');
define('PORT_HTTP_SSL','432');

function try_li_connect() 
{
	global $_SESSION;
	 // start the session
	  if(!session_id()) {
	    bb_die('This script requires session support, which appears to be disabled according to session_start().');
	  }
	
	// check for cURL
     if(extension_loaded('curl')) {
        $curl_version = curl_version();
        $curl_version = $curl_version['version'];
     } else {
        bb_die('You must load the cURL extension to use this library.'); 
     }
	
	$API_CONFIG = array(
    'appKey'       => bb_get_option( 'li_app_id' ),
	  'appSecret'    => bb_get_option( 'li_secret' ),
	  'callbackUrl'  => NULL
  	);
	$API_CONFIG['callbackUrl'] = 'http://' 
								. $_SERVER['SERVER_NAME'] 
								. ((($_SERVER['SERVER_PORT'] != PORT_HTTP) || ($_SERVER['SERVER_PORT'] != PORT_HTTP_SSL)) ? ':' 
								. $_SERVER['SERVER_PORT'] : '') 
								. $_SERVER['PHP_SELF'] . '?' . LINKEDIN::_GET_TYPE . '=initiate&' . LINKEDIN::_GET_RESPONSE . '=1';
								
	$OBJ_linkedin = new LinkedIn($API_CONFIG);
	try {
		// perform linkedin rest authorization
	  $_GET[LINKEDIN::_GET_RESPONSE] = (isset($_GET[LINKEDIN::_GET_RESPONSE])) ? $_GET[LINKEDIN::_GET_RESPONSE] : '';
      if(!$_GET[LINKEDIN::_GET_RESPONSE]) {
        // LinkedIn hasn't sent us a response, the user is initiating the connection
        
        // send a request for a LinkedIn access token
        $response = $OBJ_linkedin->retrieveTokenRequest();
        if($response['success'] === TRUE) {
          // store the request token
          $_SESSION['oauth']['linkedin']['request'] = $response['linkedin'];
          
		  // redirect the user to the LinkedIn authentication/authorisation page to initiate validation.
          header('Location: ' . LINKEDIN::_URL_AUTH . $response['linkedin']['oauth_token']);
		  exit;
        } else {
          // bad token request
          bb_die("unable to connect to LinkedIn");
        }
      } else {
        // LinkedIn has sent a response, user has granted permission, 
        // take the temp access token, the user's secret and the verifier to request the user's real secret key
        $response = $OBJ_linkedin->retrieveTokenAccess($_SESSION['oauth']['linkedin']['request']['oauth_token'], 
        												$_SESSION['oauth']['linkedin']['request']['oauth_token_secret'], 
        												$_GET['oauth_verifier']);
        if($response['success'] === TRUE) {
          // the request went through without an error, gather user's 'access' tokens
          $_SESSION['oauth']['linkedin']['access'] = $response['linkedin'];
          
          // set the user as authorized for future quick reference
          $_SESSION['oauth']['linkedin']['authorized'] = TRUE;
            //bb_die($_SESSION['oauth']['linkedin']['request']['oauth_token']
            //.' '.$_SESSION['oauth']['linkedin']['request']['oauth_token_secret']
            //.' '.$_GET['oauth_verifier']);
        } else {
          // bad token access
         bb_die("authorization failed");
        }
      }
      } catch (LinkedInException $e) {
      	 error_log($e);
      }

	return;
}

function get_li_profile() {
	$API_CONFIG = array(
    'appKey'       => bb_get_option( 'li_app_id' ),
	  'appSecret'    => bb_get_option( 'li_secret' ),
	  'callbackUrl'  => NULL
  	);
	
	$me = null;
	try {
	$_SESSION['oauth']['linkedin']['authorized'] = (isset($_SESSION['oauth']['linkedin']['authorized'])) ? $_SESSION['oauth']['linkedin']['authorized'] : FALSE;
          if($_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
          	//bb_die("Authorized, accessing profile");
            $OBJ_linkedin = new LinkedIn($API_CONFIG);
			//bb_die($_SESSION['oauth']['linkedin']['access']);
            $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
          	$OBJ_linkedin->setResponseFormat(LINKEDIN::_RESPONSE_XML);
			
	  // if successful authorization proceed to retrieve user information
	  $response = $OBJ_linkedin->profile('~:(id,first-name,last-name,industry,picture-url,headline,public-profile-url)');
	  if($response['success'] === TRUE) {
		 $response['linkedin'] = new SimpleXMLElement($response['linkedin']);
		 $me = $response['linkedin'];
	  } else {
	  	bb_die("profiled request failed.");
	  }
	}
	} catch (LinkedInException $e) {
     	 error_log($e);
    }
	return $me;
}

/**
* Revoke Authorization.
*/
function bb_li_revoke() {
	$API_CONFIG = array(
    'appKey'       => bb_get_option( 'li_app_id' ),
	  'appSecret'    => bb_get_option( 'li_secret' ),
	  'callbackUrl'  => NULL
  	);
       
      // check the session
    try {
	$_SESSION['oauth']['linkedin']['authorized'] = (isset($_SESSION['oauth']['linkedin']['authorized'])) ? $_SESSION['oauth']['linkedin']['authorized'] : FALSE;	
      if($_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
  
		      $OBJ_linkedin = new LinkedIn($API_CONFIG);
		      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
			  
		      $response = $OBJ_linkedin->revoke();
			  //bb_die($response['info']['http_code']);
		      if(($response['success'] == TRUE) || ($response['info']['http_code'] == 200)) {
		        // revocation successful, logout and clear session
		        	$var='oauth';
					unset ($_SESSION[$var]);
					session_unregister ($var);
					//bb_die(bb_get_option('uri'));
		          	//bb_safe_redirect(bb_get_option('uri').'bb-login.php?logout=1');
					$redirect_url = $_REQUEST['li_bb_revoke'];
					if (strpos($redirect_url, bb_get_option('uri')) !== 0)
						$redirect_url = bb_get_option('uri');
					
					bb_safe_redirect($redirect_url);
					exit;
		      } else {
		        // revocation failed
		       	bb_die("revocation failed");
		      }
		  }
     } catch (LinkedInException $e) {
     	 error_log($e);
     }
}

function get_li_login_button($always_display = false)
{
	if ( !bb_is_user_logged_in() ) {
		if (strpos(bb_get_option('uri'),'bb-login.php') !== false) {
				$style=	'float: left;
				padding: 10px 0 0 0;';
		} else {
				$style=	'float: left;
				padding: 5px 30px 0 0;
				margin-left: 15px;';
		}
		
		return '<a href="#" onclick="javascript:li_login_action(); return false;" style="'.
		$style.'
		"><img src="http://www.complianceforum.com.au/wp-content/themes/complianceforum/images/linkedin.png" style=""></a>';
	}
}

function li_login_button($always_display = false)
{
	echo get_li_login_button($always_display);
}

function li_bb_get_avatar($avatar, $id_or_email, $size, $default, $alt)
{
	//$me->get_li_profile();
	
	//$li_id = li_get_linkedin_id_by_userid($id_or_email);
	//bb_die($id_or_email);
	$pic_src = bb_get_usermeta($id_or_email,'li_avatar');
	if (!$pic_src)
		return $avatar;

	$pictype = "";

	if ( false === $alt)
		$safe_alt = '';
	else
		$safe_alt = esc_attr( $alt );

	$class = 'photo avatar avatar-' . $size;
	$src = $pic_src;
	
	$avatar = '<img alt="' . $safe_alt . '" src="' . $src . '" class="' . $class . '" style="height:' . $size . 'px; width:' . $size . 'px;" />';	

	return $avatar;
}

function li_bb_current_user_can($retvalue, $capability, $arg)
{
	if ($capability != 'change_user_password' && $capability != 'edit_user')
		return $retvalue;

	if (bb_current_user_can('edit_users'))
		return $retvalue;

	$user_id = bb_get_current_user_info( 'id' );
	if (!$user_id)
		return $retvalue;

	$li_id = li_get_linkedin_id_by_userid($user_id);
	if (!$li_id)
		return $retvalue;

	switch ($capability) {
		case 'change_user_password':
			return false;

		case 'edit_user':
			return ( bb_get_option( 'li_allow_useredit' ) ) ? $retvalue : false;

		default:
			return $retvalue;
	}
}

function bb_li_connect() {
	global $wp_users_object,$li_attr;
	//li authorization
	if (!$_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
		try_li_connect();
	}
	
	$me = get_li_profile();
	
	if (!$me) {
		bb_die("Linkedin Connect failed");
		exit;
	}

	$li_id = trim($me->$li_attr['id']);
	//bb_die($li_id);
	if (!$li_id) {
		bb_die("LinkedIn Connect failed, no user id found.");
		exit;
	}

	// Check if the user has already connected before
	$user_id = li_get_userid_by_linkedin_id($li_id);
	
	if (!$user_id) {	
		// User did not exist yet, lets create the local account
		
		// First order of business is to find a unused usable account name
		for ($i = 1; ; $i++) {
			$user_login = strtolower(sanitize_user(li_get_user_displayname($me), true)); 
			$user_login = str_replace(' ', '_', $user_login);
			$user_login = str_replace('__', '_', $user_login);

			if (strlen($user_login) < 2)
				$user_login = "user";

			if (strlen($user_login) > (50 - strlen($i)))
				$user_login = substr($user_login, 0, (50 - strlen($i)));

			if ($i > 1)
				$user_login .= $i;

			// A very rare potential race condition exists here, if two users with the same name
			// happen to register at the same time. One of them would fail, and have to retry.
			if (bb_get_user($user_login, array ('by' => 'login')) === false)
				break;
		}

		$user_nicename = $user_login;
		$user_email = $user_login."@none.local";
		$user_url = trim($me->$li_attr['public-profile-url']);
		$user_url = $user_url ? bb_fix_link($user_url) : '';
		$user_status = 0;
		$user_pass = bb_generate_password();

		// User may have given permission to use his/her real email. Lets use it if so.
		/*if (isset($me['email']) && $me['email'] != '' && is_email($me['email'])) {
			$user_email = trim($me['email']);
			if (bb_get_user($user_email, array ('by' => 'email')) !== false) {
				// Uh oh. A user with this email already exists. This does not work out for us.
				bb_die("Error: an user account with the email address '$user_email' already exists.");
			}	
		}*/

		$user = $wp_users_object->new_user( compact( 'user_login', 'user_email', 'user_url', 'user_nicename', 'user_status', 'user_pass' ) );
		if ( !$user || is_wp_error($user) ) {
			bb_die("Creating new user failed");
			exit;
		}
		$user_id = $user['ID'];
		
		//bb_die($user_id);
		bb_update_usermeta($user_id, $bbdb->prefix . 'capabilities', array('member' => true) );
		bb_update_usermeta($user_id, 'linkedin_id', $li_id);
		bb_update_usermeta($user_id, 'prompt_email', '1'); // will prompt user for email until set false. 1=true 0=false
		bb_update_usermeta($user_id, 'li_avatar', trim($me->$li_attr['picture-url'])); // user avatar
		
		bb_update_user($user_id, $user_email, $user_url, li_get_user_displayname($me));
		bb_update_usermeta($user_id, 'first_name', trim($me->$li_attr['first-name']));
		bb_update_usermeta($user_id, 'last_name', trim($me->$li_attr['last-name']));
		bb_update_usermeta($user_id, 'occ', trim($me->$li_attr['headline']));
		bb_update_usermeta($user_id, 'interest', trim($me->$li_attr['industry']));
		
		do_action('bb_new_user', $user_id, $user_pass);
		do_action('register_user', $user_id);
		
	} else {
		bb_update_usermeta($user_id, 'prompt_email', '1');
		bb_update_usermeta($user_id, 'li_avatar', trim($me->$li_attr['picture-url']));
		if (! bb_get_option( 'li_allow_useredit' ) ) {
			// enforce first name, last name and display name if the users are not allowed to change them
			bb_update_user($user_id, bb_get_user_email($user_id), get_user_link($user_id), li_get_user_displayname($me));

				bb_update_usermeta($user_id, 'first_name', trim($me->$li_attr['first-name']));
				bb_update_usermeta($user_id, 'last_name', trim($me->$li_attr['last-name']));
				bb_update_usermeta($user_id, 'occ', trim($me->$li_attr['headline']));
				bb_update_usermeta($user_id, 'interest', trim($me->$li_attr['industry']));
				
		}
	}
        bb_set_auth_cookie( $user_id, true );
        do_action('bb_user_login', $user_id);

	$redirect_url = $_REQUEST['li_bb_connect'];
	if (strpos($redirect_url, bb_get_option('uri')) !== 0)
		$redirect_url = bb_get_option('uri');

	bb_safe_redirect($redirect_url);
	exit;
}

function li_get_user_displayname($me)
{
	global $li_attr;
	
	switch (bb_get_option('li_displayname_from')) {
	case 2:
		$name = $me->$li_attr['last-name'];
		break;
	case 1:
		$name = $me->$li_attr['first-name'];
		break;
	case 0:
	default:
		$name = $me->$li_attr['first-name'].' '.$me->$li_attr['last-name'];
		break;
	}

	$name = trim($name);
	if (!$name) {
		$name = trim($me->$li_attr['first-name']);
		if (!$name) 
			$name = "Unknown";
	}
		
	return $name;
}

function li_get_userid_by_linkedin_id($li_id)
{
	global $bbdb;
	$bb_userid = $bbdb->get_var("SELECT user_id FROM ".$bbdb->usermeta." WHERE meta_key = 'linkedin_id' AND meta_value = '".$li_id."'");
	return ($bb_userid > 0) ? $bb_userid : 0;
}

function li_get_linkedin_id_by_userid($u_id)
{
	$bb_li_id = bb_get_usermeta($u_id, 'linkedin_id');
	return ($bb_li_id> 0) ? $bb_li_id : 0;
}
function li_get_prompt_status_by_userid($u_id)
{
	$prompt_email = bb_get_usermeta($u_id, 'prompt_email');
	return ($prompt_email == '1') ? true : false;
}

function li_check_if_email_set($email) {
	$ar=split("@",$email);
	if ("none.local" !== $ar[1] ) {
		//bb_update_usermeta($user_id, 'prompt_email', '0');
		return true;
	}
	//bb_update_usermeta($user_id, 'prompt_email', '1');
	return false;
}

//add_action('bb_user_login', 'linkedin_email_prompt');
function li_head_script(){
	global $bb_current_user;
	$_linkedin_need_email_form=FALSE;
	$user =& $bb_current_user->data;
	if ( ($_SESSION['oauth']['linkedin']['authorized'] === TRUE) 
		&& ( bb_is_user_logged_in() )
		&& ( !li_check_if_email_set(bb_get_user_email($user->ID)))) {
			
		//if ( li_get_prompt_status_by_userid(bb_get_user_id($user->ID))) {
			
			// make sure not show on profile edit tab as we want users to edit email
			if ( strpos( $_SERVER['REQUEST_URI'] , '/forum/profile/'.(get_user_name($user->ID).'/edit'))  !== FALSE ) {
				$_linkedin_need_email_form = FALSE;
			} else {
				$_linkedin_need_email_form = TRUE;
			}
		//}
	}
	//$_linkedin_need_email_form = FALSE;
	if ($_linkedin_need_email_form) :
	?>
	<!-- begin LinkedIn Connect styles -->
	<style type="text/css">
		#backgroundPopup{
		display: none;
		position:fixed;
		_position:absolute; /* hack for internet explorer 6*/
		height:100%;
		width:100%;
		top:0;
		left:0;
		background:#000000;
		border:1px solid #cecece;
		opacity:0.7;
		z-index:1;
		}
		
		#linkedin_email_form{
		display: none;
		position:fixed;
		_position:absolute; /* hack for internet explorer 6*/
		width:408px;
		background: #00b0dc; /* Old browsers */
		background: -moz-linear-gradient(top, #00b0dc 0%, #03769e 100%); /* FF3.6+ */
		background: -webkit-gradient(linear, left top, left bottom,
		color-stop(0%,#00b0dc), color-stop(100%,#03769e)); /* Chrome,Safari4+ */
		background: -webkit-linear-gradient(top, #00b0dc 0%,#03769e 100%); /*
		Chrome10+,Safari5.1+ */ background: -o-linear-gradient(top, #00b0dc 0%,#03769e
		100%); /* Opera 11.10+ */ background: -ms-linear-gradient(top, #00b0dc
		0%,#03769e 100%); /* IE10+ */ background: linear-gradient(top, #00b0dc
		0%,#03769e 100%); /* W3C */ filter: progid:DXImageTransform.Microsoft.gradient(
		startColorstr='#00b0dc', endColorstr='#03769e',GradientType=0 ); /* IE6-9 */;
				border:2px solid #cecece;
		z-index:2;
		padding:12px;
		font-size:13px;
		top:200px;
		}
	</style>
	<!-- begin LinkedIn Connect styles -->
	<?php
	endif;
}
//BB_PLUGIN_URL
function li_foot_script()
{
	//ob_start();
	global $bb_current_user;
	$_linkedin_need_email_form=FALSE;
	$user =& $bb_current_user->data;
	if ( ($_SESSION['oauth']['linkedin']['authorized'] === TRUE) 
		&& ( bb_is_user_logged_in() )
		&& ( !li_check_if_email_set(bb_get_user_email($user->ID)))) {
			
		//if ( li_get_prompt_status_by_userid(bb_get_user_id($user->ID))) {
			//echo $_SERVER['REQUEST_URI'];
			//echo $_SERVER['PHP_SELF'];
			//echo '/forum/profile/'.(get_user_name($user->ID).'/edit');
			// make sure not show on profile edit tab as we want users to edit email
			if ( strpos( $_SERVER['REQUEST_URI'] , '/forum/profile/'.(get_user_name($user->ID).'/edit'))  !== FALSE ) {
				$_linkedin_need_email_form = FALSE;
			} else {
				$_linkedin_need_email_form = TRUE;
			}
		//}
	}

?>
	
	<div id="li-root"></div>
	<!-- begin LinkedIn Connect footer -->
	<?php if ($_linkedin_need_email_form) {
		
					/*<form method="post" action="<?php $_SERVER['PHP_SELF'] ?>">
			<label for="user_email">Email</label>
			<input name="user_email" id="user_email" type="text" value="">
			</br>
			<label for="user_email_validate">Enter Email Again</label>
			<input name="user_email_validate" id="user_email_validate" type="text" value="">
			
			<p class="submit left">
			  <input type="submit" name="Defer" value="Skip">
			</p>
			<p class="submit right">
			  <input type="submit" name="Submit" value="Update Email »">
			</p>
			</form>*/
		?>
		<div id="linkedin_email_form">
			<a id="linkedin_email_form_close" style="cursor: pointer">x</a> 
			<br/> 

			<p>LinkedIn won’t give us an email address.</p>
			<p>Please click <a id="linkedin_email_form_close_button" 
				href="<?php profile_tab_link( bb_get_user_id($user->ID), 'edit' ); ?>" style="color:white;">here</a> to update it in your profile.</p>
			<br/>
			<p>That allows you to receive answers to comments on your posts by email.</p>
		</div>
		<div id="backgroundPopup"></div>  
	<?php } //ob_flush(); ?>
	
	<script>
	<?php if ($_linkedin_need_email_form) : ?>
		//SETTING UP OUR POPUP
		//0 means disabled; 1 means enabled;
		var popupStatus = 0;

		function loadPopup(){
		if(popupStatus==0){
		$("#backgroundPopup").css({
		"opacity": "0.7"
		});
		$("#backgroundPopup").fadeIn("slow");
		$("#linkedin_email_form").fadeIn("slow");
		popupStatus = 1;
		}
		}
		function disablePopup(){
		if(popupStatus==1){
		$("#backgroundPopup").fadeOut("slow");
		$("#linkedin_email_form").fadeOut("slow");
		popupStatus = 0;
		}
		}
		//centering popup
		function centerPopup(){
		//request data for centering
		var windowWidth = document.documentElement.clientWidth;
		var windowHeight = document.documentElement.clientHeight;
		var popupHeight = $("#linkedin_email_form").height();
		var popupWidth = $("#linkedin_email_form").width();
		//centering
		$("#linkedin_email_form").css({
		"position": "absolute",
		"top": windowHeight/2-popupHeight/2,
		"left": windowWidth/2-popupWidth/2
		});
		//only need force for IE6
		$("#backgroundPopup").css({
		"height": windowHeight
		});
		}

		$(document).ready(function(){
			
			centerPopup();
			loadPopup();

			$("#linkedin_email_form_close").click(function(){
				disablePopup();
			});
			
			$("#linkedin_email_form_close_button").click(function(){
				disablePopup();
			});

			$("#backgroundPopup").click(function(){
				disablePopup();
			});
			
		});
		
		 
	<?php endif; ?>
		var addUrlParam = function(search, key, val){
		  var newParam = key + '=' + val,
		  params = '?' + newParam;
	
		  if (search) {
		    params = search.replace(new RegExp('[\?&]' + key + '[^&]*'), '$1' + newParam);
		    if (params === search) {
		      params += '&' + newParam;
		    }
		  }
		  return params;
		};
		
		function li_login_action(){
			document.location = document.location.pathname + addUrlParam(document.location.search, 'li_bb_connect', escape(document.location));
		}; 
		
		function li_revoke_action() {
			document.location = document.location.pathname + addUrlParam(document.location.search, 'li_bb_revoke', escape(document.location));
		};
	</script>
	
	<!-- end Linkedin Connect footer -->
<?php
}

function li_configuration_page()
{
?>
<h2><?php _e( 'Linkedin Connect Settings' ); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>
<form class="settings" method="post" action="<?php bb_uri( 'bb-admin/admin-base.php', array( 'plugin' => 'li_configuration_page'), BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN ); ?>">
	<p>A LinkedIn Application ID and Secret Key are needed. These can be obtained from <a href="https://www.linkedin.com/secure/developer">Linkedin developer pages</a>.</p>
	<p>Remember to check that OAuth 2.0 support is enabled. This setting is located under Advanced-tab of your LinkedIn application page.</p>
	<fieldset class="submit">
<?php
	bb_option_form_element( 'li_app_id', array(
		'title' => __( 'LinkedIn Application ID' ),
		'attributes' => array( 'maxlength' => 20),
		'after' => '[Alphanumeric] Example: aQw3728er2' 
	) );

	bb_option_form_element( 'li_secret', array(
		'title' => __( 'Linkedin Application Secret' ),
		'attributes' => array( 'maxlength' => 40 ),
		'after' => "[Alphanumeric] Example: abcdef123456abcdef123456abcdef123456"
	) );

        bb_option_form_element( 'li_displayname_from', array(
                'title' => __( 'Set as Display Name' ),
                'type' => 'select',
                'options' => array(
			0 => __( 'Full Name' ),
			1 => __( 'First Name' ),
			2 => __( 'Last Name' )
                ),
		'after' => "The users Display Name will be set to this value as provided by LinkedIn"
        ) );

        bb_option_form_element( 'li_allow_useredit', array(
                'title' => __( 'Allow User Edit' ),
                'type' => 'checkbox',
                'options' => array(
                        1 => __( 'Allow users to edit their own profile information, such as first name, last name and display name' )
                )
        ) );

        bb_option_form_element( 'li_request_email', array(
                'title' => __( 'Request Real Email' ),
                'type' => 'checkbox',
                'options' => array(
                        1 => __( 'Request users real email address from LinkedIn (user must accept this). A dummy email is set to new users if this is disabled.' )
                )
        ) );

        bb_option_form_element( 'li_hide_post_login', array(
                'title' => __( 'Hide login in post form' ), 
                'type' => 'checkbox', 
                'options' => array(
                        1 => __( 'Hide the "You must login to reply" in post-form for non-logged in users. This links to the traditional login page otherwise, which LinkedIn Connected users cannot use.' ) 
                )  
        ) );
		
		bb_option_form_element( 'li_get_jquery', array(
                'title' => __( 'Include jQuery (require version 1.6+)' ), 
                'type' => 'checkbox', 
                'options' => array(
                        1 => __( 'Includes latest jQuery API from Google APIs.' ) 
                )  
        ) );

?>		<?php bb_nonce_field( 'options-liconnect-update' ); ?>
		<input type="hidden" name="action" value="update-li-settings" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>
<?php
}
function li_configuration_page_add()
{
	bb_admin_add_submenu( __( 'Linkedin Connect' ), 'moderate', 'li_configuration_page', 'options-general.php' );
}
add_action( 'bb_admin_menu_generator', 'li_configuration_page_add' );

function li_configuration_page_process()
{
	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && $_POST['action'] == 'update-li-settings') {
		bb_check_admin_referer( 'options-liconnect-update' );

		$li_app_id = trim( $_POST['li_app_id'] );
		$li_secret = trim( $_POST['li_secret'] );

		bb_update_option('li_app_id', $li_app_id);
		bb_update_option('li_secret', $li_secret);

		if (!isset($_POST['li_displayname_from']) || $_POST['li_displayname_from'] < 0 || $_POST['li_displayname_from'] > 2) {
			$_POST['li_displayname_from'] = 0;
		}
		bb_update_option('li_displayname_from', intval($_POST['li_displayname_from']));

		if (!isset($_POST['li_get_jquery']) || true !== (bool) $_POST['li_get_jquery']) {
			bb_delete_option('li_get_jquery');
		} else {
			bb_update_option('li_get_jquery', 1);
		}

		if (!isset($_POST['li_allow_useredit']) || true !== (bool) $_POST['li_allow_useredit']) {
			bb_delete_option('li_allow_useredit');
		} else {
			bb_update_option('li_allow_useredit', 1);
		} 

		if (!isset($_POST['li_request_email']) || true !== (bool) $_POST['li_request_email']) {
			bb_delete_option('li_request_email');
		} else {
			bb_update_option('li_request_email', 1);
		}

		if (!isset($_POST['li_hide_post_login']) || true !== (bool) $_POST['li_hide_post_login']) {
			bb_delete_option('li_hide_post_login');
		} else {
			bb_update_option('li_hide_post_login', 1);
		}

		bb_admin_notice( __('Configuration saved.') );
	}
}

add_action('li_configuration_page_pre_head', 'li_configuration_page_process');

// Exit if configuration has not been done
if ( !bb_get_option( 'li_app_id' ) || !bb_get_option( 'li_secret' )) {
	return;
}

if ( bb_get_option( 'li_get_jquery') ) {
	add_action( 'bb_init', 'enqueueJQuery' );
}

add_filter('bb_get_avatar', 'li_bb_get_avatar', 10, 5);
add_filter('bb_current_user_can', 'li_bb_current_user_can', 10, 3);

add_action('bb_head','li_head_script');
add_action('bb_foot', 'li_foot_script');

if ( bb_get_option( 'li_hide_post_login' ) ) {
	add_action('pre_post_form', 'li_pre_hide_post_login');
	add_action('post_post_form', 'li_post_hide_post_login');
}

/*add_action('profile_edited', 'li_check_duplicate_email');

function li_check_duplicate_email() {
	// put check into profile-edit.php in main bbpress folder 
    added logic here 
 * elseif ( (bb_get_user($user_email, array ('by' => 'email')) !== false) && (bb_get_user_email($bb_current_id) !== $user_email) ) {
		$errors->add( 'user_email', __( 'Email address exists!' ), array( 'data' => $_POST['user_email'] ) );
	}
}*/

function enqueueJQuery()  {
    wp_deregister_script( 'jquery' );
    wp_register_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.0/jquery.min.js');
    wp_enqueue_script( 'jquery' );
} 

function li_add_extra_profile_field()
{
		global $li_attr;
		
		if (bb_is_user_logged_in() && $_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
		$me = get_li_profile();
		if (!$me) {
			bb_die("Linkedin Connect failed");
			exit;
		}

		$li_id = trim($me->$li_attr['id']);
		if (!$li_id) {
			bb_die("LinkedIn Connect failed, no user id found.");
			exit;
		}
		
		$bb_current_id = bb_get_current_user_info( 'id' );
		
		if (li_get_userid_by_linkedin_id($li_id) == $bb_current_id) {
			?>
			<div style="margin:10px;padding:10px;background-color:#E6FFFF;">
			<div>Please update your email address above so you can receive forum comments and answers.  </div>
			<div>You can revoke your LinkedIn authorisation by clicking <a href="#" onclick="javascript: li_revoke_action(); return false;">Revoke</a></div>
			<div>You can log in with LinkedIn to re-authorise this account.</div>

			</div>
		<?php
		}
	} 
}

add_action('extra_profile_info', 'li_add_extra_profile_field',8);	

function li_pre_hide_post_login() {
	if ( !bb_is_user_logged_in() ) {
		echo "<div class=\"hide_login\" style=\"display:none;\">\n";
	}
}
function li_post_hide_post_login() {
	if ( !bb_is_user_logged_in() ) {
		echo "</div>\n";
	}
}

function linkedin_session_unregister() {
	$var='oauth';
	unset ($_SESSION[$var]);
	session_unregister ($var);
	//bb_die('logout');
}

add_action('bb_user_logout','linkedin_session_unregister');

// hooks
if ( isset( $_REQUEST['li_bb_connect'] ) ) {
	add_action('bb_send_headers', 'try_li_connect');	
}

if ( isset ( $_REQUEST['oauth_token']) && (isset ($_REQUEST['oauth_verifier']))) {
	add_action('bb_send_headers', 'bb_li_connect');	
}

if ( isset( $_REQUEST['li_bb_revoke'] ) ) {
	add_action('bb_send_headers', 'bb_li_revoke');	
}
?>