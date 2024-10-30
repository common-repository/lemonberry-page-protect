<?php
/**
 * @package lb-page-protect
 * @version 1.2
 */
/*
Plugin Name: Lemonberry Page Protect
Plugin URI: http://simon-hall.info/2014/04/lemonberry-page-protect/
Description: Protect Wordpress pages and posts with a simple group membership plugin.  
Author: Simon Hall
Version: 1.2
Author URI: http://simon-hall.info/
*/

$memberdb=$wpdb->prefix."lb_page_protect"; 

// Create DB and settings when plugin is activated
register_activation_hook(__FILE__,'lbm_protect_plugin_init');

// Register the admin menu
add_action('admin_menu','page_protect_menu');  
function page_protect_menu() {
   add_options_page('Lemonberry Page Protect Administration', 'Page Protect', 'manage_options','page-protect-options', 'page_protect_admin');   
}

add_filter('the_content','lb_page_protect_shortcode',20);

// Process the [lbprotect <groupname>] shortcode...
function lb_page_protect_shortcode($content){
   global $wpdb,$memberdb,$post;
   $shortcode="lbprotect";
   $pattern="\[".$shortcode."(.*?)\]";
   // shortcode hit - have we found our shortcode?
   $schit=preg_match("/".$pattern."/",$content,$matches);
   // get the list of groups available
   $grouplist=get_option('lbpp_groups');  
   // get the current group
   $cgroup=trim($matches[1]);
   // remove our shortcode from the content
   $content=preg_replace('/\['.$shortcode.'.*\]/s',"",$content);
   // get username if logged in, false if not
   $user=is_logged_in($cgroup);

   if(!in_array($cgroup,$grouplist) && $schit>0) {
      return "<p>Shortcode error - invalid group</p>";
   }

   if(isset($_GET['changepass'])) $changepass=true; // show change password form?

   // Process change password
   if(isset($_POST['oldpass']) && isset($_POST['newpass1']) && isset($_POST['newpass2']) && check_admin_referer("userchangepass")) {
      if(user_validate($user,$_POST['oldpass'],$cgroup)) {
         if($_POST['newpass1']!=$_POST['newpass2']) {
            echo "<p style='color:red'>New passwords don't match!</p>";
            $changepass=true;
         }else{
            if(strlen($_POST['newpass1'])<8){
               echo "<p style='color:red'>New password is too short - please use at least 8 characters.  Password NOT changed.</p>";
               $changepass=true;
            }else {
               if (empty( $wp_hasher)) {
                  require_once ABSPATH . 'wp-includes/class-phpass.php';
               }
               $pwdHasher=new PasswordHash(8,FALSE);
               $hash=$pwdHasher->HashPassword($_POST['newpass1']);
               $wpdb->query($wpdb->prepare("UPDATE ".$memberdb." SET member_pass=%s WHERE member_name=%s AND member_group=%s",$hash,$user,$cgroup));               
               echo "<p style='color:red'>Password successfully changed.</p>";
            }
         }
      }else{
         echo "<p style='color:red'>Your current password is not correct!</p>";
         $changepass=true;
      }
   }
   
   // Process logout
   if(isset($_GET['logout'])) {
      // logout by setting cookie to time in the past
      setcookie('lbuser','',1,wp_path());
      setcookie('lbhash','',1,wp_path());
      $req=str_ireplace("?logout=true","",$_SERVER['REQUEST_URI']);
      header("Location: ".$req);
      die();
   }

   // Process login form
   if(isset($_POST['user']) && isset($_POST['pass']) && check_admin_referer("userlogin")) {
      if(user_validate($_POST['user'],$_POST['pass'],$cgroup)){
         $hash=create_hash($_POST['pass']);
         if(!setcookie('lbuser',$_POST['user'],0,wp_path())) {
            echo "<h1>Lemonberry Page Protect is not compatible with your current theme</h1>";
            echo "<p>Our apologies - due to the method this plugin uses to set cookies (used for the authentication mechanism), it can't currently work with your current theme.  We hope to fix this very soon, but in the meantime our apologies for this slackness.</p>";
         };
         setcookie('lbhash',crypt($_POST['user'].$secret),0,wp_path());
         header("Location: ".$_SERVER['REQUEST_URI']);
         die();
      } else {
         echo "<p style='color:red'>Login failed.  Please try again.</p>";
      }
   }

   // Return the content
   if ($schit>0) {
      if($user){
         if(isset($changepass)){
            return change_pass_form();
         }else{
            if(is_singular()){
               return userbar($content,$user);
            }else{
               return $content;
            }
         }
      }else{
         add_filter("comments_open", "__return_false");
         if(is_singular()) {
            return user_login_form();
         }else{
            return "<p>Restricted viewing.</p>";
         }
      }
   }else{
      // no shortcode in this content - return unmodified
      return $content;
   }    
}

// Show the change password form in place of the content
function change_pass_form(){
   $req=str_ireplace("?changepass=true","",$_SERVER['REQUEST_URI']);
   $content="";
   $content.="<form method='post' action='".$req."'>";
   $content.="<h3>Change Password</h3>";
   $content.="<legend for='oldpass'>Current Password</legend><input type='password' name='oldpass'/>";
   $content.="<legend for='newpass1'>New Password (twice for confirmation)</legend><input type='password' name='newpass1'><br><input type='password' name='newpass2'><br>";
   $content.="<input type='submit' value='Change Password'/>";
   $content.=wp_nonce_field("userchangepass");
   $content.="<p>&nbsp;</p>";
   $content.="</form>";
   $content.=userbar();
   return $content;
}

// add the userbar (with logout/change pass link) at bottom of content
function userbar($content,$user) {
   $user=is_logged_in();
   if($user){
      $content.="<hr/><p>Logged in as <strong>".$user."</strong> | <a href='?logout=true'>Logout</a> | <a href='?changepass=true'>Change Password</a></p>";
   }
   return $content;
}

// Return login form in place of content.
function user_login_form(){
   $content="<div>";
   $content.="<h3>Please login to continue</h3>";
   $content.="<form method='post'>";
   $content.="<legend for='user'>Username (email address)</legend><input type='email' name='user'><br>";
   $content.="<legend for='pass'>Password</legend><input type='password' name='pass'><br>";
   $content.="<input type='submit' value='Login'>";
   $content.=wp_nonce_field("userlogin");
   $content.="</form>";
   $content.="<br/><p>Please <a href='/contact/'>contact us</a> if you have problems logging in.</p>";
   $content.="</div>";
   return $content;
}
function block_post($content){
   return "<p>Protected content.</p>";
}

// Return a hash for the given password
function create_hash($user){
   global $wp_hasher;
   $secret=LOGGED_IN_SALT;
   if (empty($wp_hasher)) {
      require_once ABSPATH.'wp-includes/class-phpass.php';
   }
   $pwdHasher=new PasswordHash(8,FALSE);
   $hash=$pwdHasher->HashPassword($user.$secret);
   return $hash;
}

// Check the password vs hash value
function check_hash($plain,$hash){
   global $wp_hasher;
   $secret=LOGGED_IN_SALT;
   if (empty($wp_hasher)) {
      require_once ABSPATH.'wp-includes/class-phpass.php';
   }
   $pwdHasher=new PasswordHash(8,FALSE);
   if($pwdHasher->CheckPassword($plain,$hash)) {
      return true;
   }else{
      return false;
   }
}

// Check cookies and return username if authenticated
function is_logged_in($group) {
   if(isset($_COOKIE['lbuser']) && isset($_COOKIE['lbhash'])) {
      if(check_hash($_COOKIE['lbuser'].$secret,$_COOKIE['lbhash']) && check_group_member($_COOKIE['lbuser'],$group)) {
         return $_COOKIE['lbuser'];
      }
   }
   // if this point is reached, no cookies are set therefore not logged in
   return false;
}

function check_group_member($user,$cgroup) {
   global $wpdb,$memberdb;
   $usergroup=$wpdb->get_var("SELECT member_group FROM ".$memberdb." WHERE member_name='".$user."' AND member_group='".$cgroup."'");
   //var_dump($usergroup);
   if($usergroup==$cgroup) {
      return TRUE;
   }else{
      return FALSE;
   }
}

// Validate credentials from databse
function user_validate($user,$pass,$group){
   global $wpdb,$memberdb;
   $user=sanitize($user);
   $hash=$wpdb->get_var("SELECT member_pass FROM ".$memberdb." WHERE member_name='".$user."' AND member_group='".$group."'");
   if(check_hash($pass,$hash)) {
      $wpdb->update($memberdb,array('member_lastlogin'=>date("Y-m-d H:i:s",time()+7200),'member_ip'=>$_SERVER['REMOTE_ADDR'],'member_fails'=>0),array('member_name'=>$user,'member_group'=>$group));
      return true;
   }else{
      $fails=$wpdb->get_var("SELECT member_fails FROM ".$memberdb." WHERE member_name='".$user."' AND member_group='".$group."'");
      $wpdb->update($memberdb,array('member_fails'=>($fails+1),'member_ip'=>$_SERVER['REMOTE_ADDR']),array('member_name'=>$user,'member_group'=>$group));
      // username/password combo does not match.
      sleep(5);
      return false;
   }
}

// Only return sensible chars
function sanitize($string){
   $string=preg_replace('/[^(\x20-\x7F)]*/','', $string);
   return $string;
}




//
// ADMIN MENU
// 
function page_protect_admin() {
   global $wpdb,$wp_hasher,$memberdb;
   echo "<div class='wrap'>\n";
   echo "<div style='float:right'>by <a href='http://www.lemonberry.com' target='_blank'>Lemonberry.com</a></div>";
   echo "<h2>Lemonberry Page Protect : Administration</h2>\n";
   $grouplist=get_option('lbpp_groups');
   @sort($grouplist);
   
   // Save add group
   if(isset($_POST['newgroup'])) {
      if(trim($_POST['newgroup']!="") && check_admin_referer("addgroup")) {
         $grouplist[]=trim(ereg_replace("[^A-Za-z0-9]", "",$_POST['newgroup']));
         update_option('lbpp_groups',$grouplist);
         echo "<div class='updated'><p>Added group ".$_POST['newgroup'].".</p></div>";
         $grouplist=get_option('lbpp_groups');
         sort($grouplist);
         $cgroup=$_POST['newgroup'];
         $thistab=array_search($_POST['newgroup'],$grouplist);
      }
   }

   // Delete group
   if(isset($_GET['delgroup'])) {
      $grouplist=array_diff($grouplist,array($_GET['delgroup']));
      sort($grouplist);
      update_option('lbpp_groups',$grouplist);
      $wpdb->delete($memberdb,array('member_group'=>$_GET['delgroup']));
      echo "<div class='updated'><p>Deleted group ".$_GET['delgroup'].".</p></div>";
   }
   
   // Add group form
   if(isset($_GET['addgroup']) || !$grouplist) {
      if(!$grouplist){
         echo "<p>Add a group to get started...</p>";
      }
      echo "<form method='post' action='?page=page-protect-options'>";
      echo "<input type='text' name='newgroup'>";
      echo "<input type='submit' value='Add Group'>";
      wp_nonce_field("addgroup");
      echo "</form>";
   }
   


   // Delete user
   if(isset($_GET['del'])) {
      $wpdb->delete($memberdb,array('member_id'=>$_GET['del']));
      echo "<div class='error'><p>Deleted user.</p></div>";
   }
   
   // Work out what the current tab and group is
   if(!isset($cgroup)) {
      if(isset($_GET['tab'])) {
         $thistab=$_GET['tab'];
         $cgroup=$grouplist[$_GET['tab']];
      }else{
         $cgroup=$grouplist[0];
         $thistab=0;
      }
   }

   // Prepare the tabs
   $tabshtml="";
   if($grouplist) {
      foreach($grouplist as $tab=>$name){
        $class=($tab==$thistab)?'nav-tab-active':'';
        $tabshtml.="<a class='nav-tab $class' href='?page=page-protect-options&tab=$tab'>$name</a>";
      }
   }

   // Add user
   if(isset($_POST['newuser'])) {
      if(check_admin_referer("newuser")){;
         if(!is_email($_POST['newuser'])) {
            echo "<div class='error'><p>Error - That doesn't look like a valid email address.  Try again.</p></div>";
            $retryuser=$_POST['newuser'];
         }else{
            $checkuser=$wpdb->get_var("SELECT member_name FROM ".$memberdb." WHERE member_name='".$_POST['newuser']."' AND member_group='".$cgroup."'");
            if($checkuser) {
               echo "<div class='error'><p>User already exists!</p></div>";
            }else{
               $pass=wp_generate_password(12);
               if (empty($wp_hasher)) {
                  require_once ABSPATH.'wp-includes/class-phpass.php';
               }
               $pwdHasher=new PasswordHash(8,FALSE);
               $hash=$pwdHasher->HashPassword($pass);
               $wpdb->insert($memberdb,array('member_name'=>trim($_POST['newuser']),'member_pass'=>$hash,'member_group'=>$cgroup));
               echo "<div class='updated'><p>Added user: <span style='font-family:courier new, courier,monospace'>".$_POST['newuser']."</span>&nbsp;&nbsp;Password: <span style='font-family:courier new, courier,monospace'>".$pass."</span></p></div>";
            }
         }
      }
   }
   
   // Save reset password
   if(isset($_POST['newpass'])) {
      if(check_admin_referer("resetpass".$_POST['member_id'])){;
         if(strlen($_POST['newpass'])<8){
            echo "<div class='error'><p>New password is too short - please use at least 8 characters.  Password NOT changed.</p></div>";
         }else {
            if ( empty( $wp_hasher ) ) {
               require_once ABSPATH . 'wp-includes/class-phpass.php';
            }
            $pwdHasher=new PasswordHash(8,FALSE);
            $hash=$pwdHasher->HashPassword($_POST['newpass']);

            $wpdb->query($wpdb->prepare("UPDATE ".$memberdb." SET member_pass=%s WHERE member_id=%d AND member_group=%s",$hash,$_POST['member_id'],$cgroup));
            echo "<div class='updated'><p>Password changed to <span style='font-family:courier new, courier,monospace'>".$_POST['newpass']."</span></p></div>";
         }
      }
   }
   
   
   // Reset password
   if(isset($_GET['resetpass'])) {
      $user=$wpdb->get_var("SELECT member_name FROM ".$memberdb." WHERE member_id=".$_GET['resetpass']);
      echo "<form method='post' action='?page=page-protect-options&tab=".$thistab."'>";
      echo "Enter a new password for: ".$user."<br>";
      echo "<input type='text' name='newpass'>";
      echo "<input type='hidden' name='member_id' value='".$_GET['resetpass']."'>";
      echo "<input type='submit' class='standard-button' value='Change Password'>";
      wp_nonce_field("resetpass".$_GET['resetpass']);
      echo "</form>";
      echo "<hr/><br/>";
    }  

   if($grouplist){ // only if we have at least 1 group
      // Ouput tabs
      echo "<h2 class='nav-tab-wrapper'>";
      echo "<div class='dashicons dashicons-groups'> </div> ";
      echo $tabshtml;
      echo "</h2>";

      // Members list
      $members=$wpdb->get_results("SELECT * FROM ".$memberdb." WHERE member_group='".$cgroup."' ORDER BY member_name",ARRAY_A);
      echo "<table class='widefat' style='width:800px'>";
      echo "<tr><th>Username</th><th>Last login</th><th>Last IP</th><th>Failed attempts</th></tr>";
      foreach($members as $member) {
         echo "<tr><td>".$member['member_name']."</td><td>".($member['member_lastlogin']!="0000-00-00 00:00:00"?$member['member_lastlogin']:"")."</td><td>".($member['member_ip']!=""?$member['member_ip']:"")."</td><td>".($member['member_fails']>0?$member['member_fails']:"")."</td><td><a href='?page=page-protect-options&tab=".$thistab."&resetpass=".$member['member_id']."'>Password</a> | <a onClick='return confirm(\"Are you sure? - This cannot be undone!\");' href='?page=page-protect-options&tab=".$thistab."&del=".$member['member_id']."'>Delete</a></td></tr>";
      }
      if(!$members) {
         echo "<tr><td colspan=5 align='center'><br/><em><strong>This group is empty</strong></em><br/><br/></td></tr>";
      }
      echo "</table>";

      // Form for new user
      echo "<form method='post' action='?page=page-protect-options&tab=".$thistab."'>";
      echo "<input type='email' name='newuser' value='".(isset($retryuser)?$retryuser:"")."'>";
      echo "<input type='submit' class='standard-button' value='Add User'>";
      wp_nonce_field("newuser");
      echo "</form>";   
    
      
      echo "<p><a href='?page=page-protect-options&addgroup=true&tab=".$thistab."'>Add new group</a> | <a onClick='return confirm(\"Delete group ".$cgroup." and all the users in it - Are you sure? - This cannot be undone!\");' href='?page=page-protect-options&delgroup=".$cgroup."'>Delete this group</a></p>";
      echo "<hr/>";
      echo "<p>To restict a page or post to this group only, use the following shortcode:</p>";
      echo "<code>[lbprotect ".$cgroup."]</code>";
   }
   echo "</div>"; // wrap
}

function wp_path(){
   $urlinfo=parse_url(get_bloginfo('url'));
   return ($urlinfo['path']==""?"/":$urlinfo['path']);
}


// When plugin is activated, create table and register plugin settings
function lbm_protect_plugin_init(){
   global $wpdb;
   register_setting('lbpp_settings','lbpp_groups');
   $sql="CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."lb_page_protect` (
               `member_id` int(11) NOT NULL AUTO_INCREMENT,
               `member_name` varchar(128) NOT NULL,
               `member_pass` varchar(255) NOT NULL,
               `member_lastlogin` datetime NOT NULL,
               `member_ip` varchar(32) NOT NULL,
               `member_fails` int(11) NOT NULL,
               `member_group` varchar(32) NOT NULL,
            PRIMARY KEY (`member_id`),
            KEY `member_group` (`member_group`)
         ) DEFAULT CHARSET=utf8 COMMENT='Lemonberry Protect table';";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta($sql);   
}



