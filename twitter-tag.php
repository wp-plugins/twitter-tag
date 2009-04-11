<?php
/*
Plugin Name: Twitter Tag
Plugin URI: http://www.paulmc.org/whatithink/wordpress/plugins/twitter-tag/
Description: Link to a users Twitter page when you include a Twitter @username in a post and tweet the user that they have been tagged.
Version: 1.0
Author: Paul McCarthy
Author URI: http://www.paulmc.org/whatithink
*/

/*  Copyright 2009  Paul McCarthy  (email : paul@paulmc.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//function to load options page
function pmcTwitterTagging_init() {
	add_action('admin_menu', 'pmcTTMenu');
}

//function to create options page
function pmcTTMenu() {
	add_options_page('Twitter Tagging', 'Twitter Tagging', 8, 'twittertagging', 'pmcTTOptions');
}

//function to write the options page
function pmcTTOptions() {
	
	//get the option for the form checkbox
	$pmcTTtweet = get_option('pmc_TT_tweet');
	
	if ($pmcTTtweet == 'on') {
		$pmcTTtweet = ' checked="checked" ';
	}
	
	echo '<div class="wrap">';
	echo '<h2>Twitter Tagging Options</h2>';
	echo '<h3>Your Twitter Login Details</h3>';
	echo '<form action="options.php" method="post">';
	wp_nonce_field('update-options');
	echo '<table class="form-table">';
	echo '<tr><td>';
	echo '<label for="pmc_TT_user">Your Twitter User Name:</label>';
	echo '</td><td>';
	echo '<input type="text" name="pmc_TT_user" id="pmc_TT_user" value="' . get_option('pmc_TT_user') . '" />';
	echo '</td></tr><tr><td>';
	echo '<label for="pmc_TT_pass">Your Twitter Password:</label>';
	echo '</td><td>';
	echo '<input type="password" name="pmc_TT_pass" id="pmc_TT_pass" value="' . get_option('pmc_TT_pass') . '"/>';
	echo '</td></tr><tr><td>';
	echo '<label for="pmc_TT_tweet">Automatically Send Tweet When Post is Published?</label>';
	echo '</td><td>';
	echo '<input type="checkbox" id="pmc_TT_tweet" name="pmc_TT_tweet"' . $pmcTTtweet . ' />';
	echo '</td></tr><tr><td>';
	echo '<input type="submit" value="Save Settings" class="button-primary" />';
	echo '</td></tr></table>';
	echo '<input type="hidden" name="action" value="update" />';
	echo '<input type="hidden" name="page_options" value="pmc_TT_user,pmc_TT_pass,pmc_TT_tweet" />';
	echo '</form>';
	echo '</div>';
	
} //close pmcTTOptions
	
//function to search post content for Twitter usernames of the form @username
function pmcLinkUserName($pmcText) {

	//regex to serch for @usernames - must start with a space followed by @ - only letters, numbers and underscore allowed, case insensitive
	preg_match_all('/\040@([0-9a-z_]+)/i', $pmcText, $pmcMatch);
	
	//preg_match_all returns a multidimensional array
	//first sub-array contains the @username, and second sub-array contains the plain username
	//get the length of the arrays
	$pmcLen = count($pmcMatch[0]);
	
	//loop through the array
	for ($i=0; $i<$pmcLen; $i++) {
		//build the link to the twitter user
		$pmcReplace = ' <a href="http://twitter.com/' . $pmcMatch[1][$i] . '" title="Twitter page for' . $pmcMatch[0][$i] . '">' . trim($pmcMatch[0][$i]) . '</a>';
		
		//replace the @username with the links
		$pmcSearch = $pmcMatch[0][$i];
		$pmcText = str_replace($pmcSearch, $pmcReplace, $pmcText);
	}
	
	//return the updated text
	return $pmcText;
	
} // close pmcTagUserName()

//function to create a tiny url
function pmcCreateTinyURL($pmcURL) {
	
	//build the url to fetch
	$pmcTinyURL = "http://tinyurl.com/api-create.php?url=" . $pmcURL;
	
	//use curl to get the url
	$pmcTinyCurl = curl_init();
	
	//set the curl option
	curl_setopt($pmcTinyCurl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($pmcTinyCurl, CURLOPT_URL, $pmcTinyURL);
	
	$pmcTinyResult = curl_exec($pmcTinyCurl);
	
	//close the curl session
	curl_close($pmcTinyCurl);
	
	//return the URL
	return $pmcTinyResult;
	
} //close pmcCreateTinyURL

//function to send Tweet with Twitter usernames and link to post 
//takes post id as parameter
function pmcSendTweet($pmcID) {
	//check if the user wants to send a tweet
	$pmcTweet = get_option('pmc_TT_tweet');
	
	if ($pmcTweet == "on") {
		//get the user options
		$pmcUser = get_option('pmc_TT_user');
		$pmcPass = get_option('pmc_TT_pass');
	
		//get the post content
		$pmcPost = get_post($pmcID);
		$pmcContent = $pmcPost->post_content;
	
		//find the twitter usernames
		preg_match_all('/\040@([0-9a-z_]+)/i', $pmcContent, $pmcUsers);
	
		//check that the is a twitter username in the post
		if ($pmcUsers[0][0] != "") {
			//get the permalink for the post
			$pmcPermalink = get_permalink($pmcID);
	
			//get a TinyURL for the post
			$pmcTinyURL = pmcCreateTinyURL($pmcPermalink);
	
			//start building the Tweet content
			$pmcTweetText = 'status="Tagged in New Post ' . $pmcTinyURL . ' - ';
	
			//get the length of the array
			$pmcLen = count($pmcUsers[0]);
	
			//loop through the users array to get the usernames
			for ($i=0; $i<$pmcLen; $i++) {
				$pmcTweetText .= $pmcUsers[0][$i];
			} //close for

			//Twitter API Url to post the tweet
			$pmcTweetURL = 'http://twitter.com/statuses/update.xml';
	
			//open a new curl session
			$pmcCurl = curl_init();
	
			//set the curl options
			curl_setopt($pmcCurl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($pmcCurl, CURLOPT_USERPWD, "$pmcUser:$pmcPass");
			curl_setopt($pmcCurl, CURLOPT_URL, $pmcTweetURL);
			curl_setopt($pmcCurl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($pmcCurl, CURLOPT_POST, 1);
			curl_setopt($pmcCurl, CURLOPT_POSTFIELDS, $pmcTweetText);
	
			//use curl to send the tweet
			$pmcTweetResult = curl_exec($pmcCurl);
	
			//close the curl session
			curl_close($pmcCurl);
		
			echo "pmcTweet: $pmcTweet";
		} //close inner if
	} //close outer if
	
} //close pmcSendTweet

//add action to load plugin
add_action('plugins_loaded', 'pmcTwitterTagging_init');
//add content filter
add_filter('the_content', 'pmcLinkUserName');
//add action to send Tweet when post is published
add_action('publish_post', 'pmcSendTweet');
?>