<?php
/**
 * File : github-api/index.php
 * Description : Index file to restrict direct folder access
 */

// Include wordpress defaults.
include_once( "../../../wp-load.php" );

// Set user details.
global $current_user;

// Get logged in user's id.
$userId = $current_user->ID;

// Set custom field's name.
$customField = "github_username";

// Get value of username posted.
$value = $_POST['username'];

// Get Group Id
$gid = $_POST['gid'];

// Get existing github user name for logged in user.
$val = get_user_meta( $userId, $customField );

// Check whether username already exists or not.
$result = true;
update_user_meta( $userId, $customField, $value );
/*
if( (int)$gid > 0 ) {
	if( groups_is_user_member( $userId, $gid ) || groups_is_user_admin( $userId, $gid )) {
		// Create object of github api plugin
		$githubObj = new Github_Api( true );
		
		// Get group details
		$groupDetails = groups_get_group( array( "group_id" => $gid ));
		
		// Assign member to group
		$githubObj->assignMemberToTeam( $userId, $groupDetails, $value );
		
	}
}*/
// Return
echo ( $result == true ? "true" : "false" ); die;
?>