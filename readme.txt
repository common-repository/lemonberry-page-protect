=== Plugin Name ===
Contributors: SimonHall
Donate link: http://simon-hall.info/
Tags: protect,password,restrict,club,membership,simple
Requires at least: 3.0.1
Tested up to: 3.9
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lemonberry Page Protect is a simple way to restict page/post content to a list of (non-Wordpress) users.

== Description ==

Lemonberry Page protect is a free, open source plugin for WordPress to provide a simple method of restricting access to a group of (non-Wordpress) users.  It's intended use is for club member sites etc where you want to keep some information/forms restricted.

Multiple groups can be defined and re-used of multiple pages.  The user controls allow change of password.

The administration page allows passwords to be changed, users to be added and groups to be defined.

The excerpt of a post is not blocked.

Note: This plugin will not currently work with all themes, and you'll get a message after login to show this.  We're working on a solution to make it work with all themes which should be available soon.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the `/lb-page-protect/` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Set up users and groups using the WP-Admin->Settings->Page Protect menu
1. Use the shortcode [lbprotect groupname] on any page or post you want to restrict.  Change groupname to a group you've defined in the admin menu.  Note:  make sure the shortcode is before the <!--more--> tag (if you have one) in your content. If you don't, the content before the <!--more--> tag will be visible in when a post category is listed.

== Frequently Asked Questions ==
= No questions... yet =

== Screenshots ==

1. Not available... yet

== Changelog ==

= 1.0 =
* Initial release

= 1.1 =
* Switched from custom password generation function to Wordpress builtin wp_generate_password();

= 1.2 =
* Bug fix (group membership).
* Warning for incompatibility with some themes.

== Upgrade Notice ==

= 1.0 =
* Initial release

= 1.1 = 
* No special requirements

= 1.2 = 
* No special requirements
