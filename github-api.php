<?php
/*
 * 	Plugin Name: Github Api
 * 	Plugin URI: http://aress.com
 * 	Description: Perform github operations
 * 	Author: Aress Software
 * 	Version: 1.0
 * 	Author URI: http://aress.com
 * 	Text Domain: N/A
 */

if (!class_exists('Github_Api')) {
/**
 * Github_Api class contains all methods.
 */
	class Github_Api extends WP_Widget {
	
		 /**
		 * Set class variables
		 */
		 /**
		  * Client id of the application created on github
			*/
			var $clientId = "***********";
			
		 /**
		  * Client secret of the application created on github
			*/
			var $clientSecret = "*************************************";
			
		 /**
		  * Set default github username to avoid malfunctioning
			*/
			var $userName	= "***********";
			
		 /**
		  * Set default password for the above github username to avoid malfunctioning
			*/
			var $password	= "***********";
			
		 /**
		  * Set default organization on github to avoid malfunctioning
			*/
			var $organization = "********";
			
		 /**
		  * Class variable which will contain client object of Github API
			*/
			var $client;
			
		 /**
		  * Class variable which will define collaborators to the repository on github
			*/
			var $collab;
			
		 /**
		  * Name of the table which will contain data of user and team mapping
			*/
			var $userTeamsTbl = "github_api_user_teams";
			
		 /**
		  * Name of the table which will map groups and associated team ids
			*/
			var $groupTeamTbl = "github_api_group_teams";
			
		 /**
		  * Class variable which will store repositories on github
			*/
			var $repositories = array();
			
		 /**
		  * Class variable which will hold list of teams
			*/
			var $teams = array();
	
		 /**
		 * Constructor
		 *
		 * @uses add_action() , add_shortcode(), add_filter(), register_activation_hook()
		 */
		 function __construct( $authenticate = false ) {
		 	// Add ajax action
			add_action( 'wp_ajax_save_github_username', array( $this, 'save_github_username_ajax' ) );
			
			// Create client object
			$github_username 			= 'github_username';
			$github_password 			= "github_password";
			$github_client_id 		= "github_client_id";
			$github_client_secret = "github_client_secret";
			$github_organization 	= "github_organization";
	
			// Read in existing option value from database
			$userName 		= get_option( $github_username );
			$password 		= get_option( $github_password );
			$clientId 		= get_option( $github_client_id );
			$clientSecret = get_option( $github_client_secret );
			$organization = get_option( $github_organization );
			
			if( !empty( $userName ) && !empty( $password ) && !empty( $clientId ) && !empty( $clientSecret ) && !empty( $organization )) {
				// Assign the values from database
				$this->userName 		= $userName;
				$this->password 		= $password;
				$this->clientId 		= $clientId;
				$this->clientSecret = $clientSecret;
				$this->organization = $organization;
			} else {
				// Update options table with default values
				update_option( $github_username, $this->userName );
				update_option( $github_password, $this->password );
				update_option( $github_client_id, $this->clientId );
				update_option( $github_client_secret, $this->clientSecret );
				update_option( $github_organization, $this->organization );
			}
			if( $authenticate ) {
				//Includes files
				require_once 'vendor/autoload.php';
				
				$this->client = new \Github\Client();
				$this->collab = $this->client->api('repo')->collaborators();
				
				$this->authenticate();
				$this->createAuthorization();
				
				$this->repositories = $this->listRepositories( "", $isOrganization = true );
				$this->teams				= $this->listTeams();
			}
		}
		
	
		 /**
		 * Manage user authentication.
		 *
		 * @return array loginArr.
		 */
		function authenticate() {
			// Create authentication.
			$loginArr = $this->client->authenticate( $this->userName, $this->password );
			
			// Return authentication array.
			return $loginArr;
		}
		
		
		/**
		 * Lists user's repositories on github.
		 *
		 * @param string userName - github username.
		 * @param string organization - name of the organization on github.
		 * @return array loginArr.
		 */
		function listRepositories( $userName = '', $isOrganization = false ) {
			$repositories = array();
			if( $isOrganization ) {
				// Get repositories of organization
				$tempRepoArray = array();
				$per_page = 100;
				$page = 1;
				
				do {
					$tempRepoArray = array();
					$tempRepoArray = $this->client->api('organization')->repositories( $this->organization, 'all', $page, $per_page );
					$noOfRecords = sizeof( $tempRepoArray );
					if( $noOfRecords > 0 ) {
						for( $i = 0; $i < $noOfRecords; $i++ ) {
							$repositories[] = $tempRepoArray[$i];
						}
					}
					$page++;
				} while( $noOfRecords == $per_page );
				
				$this->repositories = $repositories;
			} else {
				// Set username if not passed as parameter
				$userName = ( empty( $userName ) ? $this->userName : $userName );
				
				// List repositories.
				$repositories = $this->client->api('user')->repositories( $userName);
			} 
			// Return repositories array.
			return $repositories;
		}
		
		/**
		 * Create autorization to perform github operations.
		 *
		 * @param string clientId - client id of application created on github.
		 * @param string clientSecret - client secret of application created on github.
		 * @param string note
		 * @return array loginArr.
		 */
		function createAuthorization( $clientId = '', $clientSecret = '', $note = '' ) {
			// Create Authorization
			$authorizations = array();
			
			// Set authorization fields
			$fields = array(
												'client_id' => urlencode( empty( $clientId ) ? $this->clientId : $clientId ),
												'client_secret' => urlencode( empty( $clientSecret ) ? $this->clientSecret : $clientSecret ),
												'note'=> urlencode( empty( $note ) ? "Test" : $note )
											);
			
			// Create authorizations using API
			$authorizations = $this->client->api('authorizations')->create( $fields );
			
			// Return authorizations array.
			return $authorizations;
	
		}
		
		/**
		 * Delete repository from github
		 *
		 * @param string organization - organization name / github username.
		 * @param string repository - repository name to be deleted.
		 * @return none.
		 */
		function deleteRepository( $organization = '', $repository = '' ) {
			// Check for username / organization name.
			$organization = ( empty( $organization ) ? $this->organization : $organization );
			
			// Delete repository using api
			$deleteRepository = $this->client->api('repo')->remove( $organization, $repository );
			
		}
		
		/**
		 * Create repository on github
		 *
		 * @param string name - name of the repository.
		 * @param string description - description for the repository.
		 * @param string homepage - URL of home page.
		 * @param boolean public - flag to identify if the new repository will be public or private.
		 * @param string organization - description for the repository.
		 * @param boolean hasIssues - flag to identify if repository can have issues reporting.
		 * @param boolean hasWiki - 
		 * @param boolean hasDownloads - flag to identify if repository can have downloads.
		 * @param int teamId - id of the team which should be assigned to repository.
		 * @param boolean autoInit - flag to identify if repository should be created with default readme file.
		 * @return none.
		 */
		function createRepository(
			$name,
			$description = '',
			$homepage = '',
			$public = true,
			$organization = null,
			$hasIssues = false,
			$hasWiki = false,
			$hasDownloads = false,
			$teamId = null,
			$autoInit = false ){
			
			// Uncomment the line below if repository should be created for an organization
			//$organization = (( $organization != '' || $organization != null ) ? $organization : $this->organization );
			
			// Create repository using api
			$create = $this->client->api('repo')->create( $name, $description, $homepage, $public, $this->organization, $hasIssues, $hasWiki, $hasDownloads, $teamId, $autoInit );
			
			// Return create repository array.
			return $create;
		}
		
		
		/**
		 * Add a collaborator to repository using api.
		 *
		 * @param string username - github username of a repository owner.
		 * @param string repository - repository name to which collaborator is being added.
		 * @param string collaborator - github username of a user who is being added as a collaborator.
		 * @return none.
		 */
		function addCollaborator( $username = '', $repository = '', $collaborator = '') {
			// Check for username
			$username = ( empty( $userName ) ? $this->userName : $username );
			
			// Add collaborator to repository using api.
			$this->collab->add( $username, $repository, $collaborator );
			
			// Return
			return true;
		}
		
		
		/**
		 * Remove a collaborator to repository using api.
		 *
		 * @param string username - github username of a repository owner.
		 * @param string repository - repository name to which collaborator is being added.
		 * @param string collaborator - github username of a user who is being added as a collaborator.
		 * @return none.
		 */
		function removeCollaborator( $username = '', $repository = '', $collaborator = '') {
			// Check for username
			$username = ( empty( $userName ) ? $this->userName : $username );
			
			// Remove collaborator using API.s
			$this->collab->remove( $username, $repository, $collaborator );
			return true;
		}
		
		
		/**
		 * List all teams of an organization.
		 *
		 * @param string organization - name of the organization of which teams to be listed.
		 * @return teams array.
		 */
		function listTeams( $organization = '' ) {
			// Get list of all teams using api
			$teams = array();
			
			$tempRepoArray = array();
			$per_page = 100;
			$page = 1;
			
			do {
				$tempRepoArray = array();
				$tempTeams = $this->client->api('teams')->all( $this->organization, $page, $per_page );
				$noOfRecords = sizeof( $tempTeams );
				if( $noOfRecords > 0 ) {
					for( $i = 0; $i < $noOfRecords; $i++ ) {
						$teams[] = $tempTeams[$i];
					}
				}
				$page++;
			} while( $noOfRecords == $per_page );
			
			$this->teams = $teams;
			
			// Return teams array			
			return $teams;
		}
		
		
		/**
		 * Get team details.
		 *
		 * @param string team - name of the team of which details has to be returned.
		 * @return teamDetails array.
		 */
		function getTeamDetails( $organization = '', $team = 0 ) {
			$returnArray = array();
			$returnArray['status'] = false;
			$returnArray['message'] = "Team not found";
			$returnArray['teamDetails'] = array();
			
			try {
				// Get team details using api
				$returnArray['teamDetails'] = $this->client->api('teams')->show( $team );
				$returnArray['status'] 			= true;
				$returnArray['message']			= "Team details available";
			} catch ( \Github\Exception\RuntimeException $e ) {
				$returnArray['status'] 	= false;
				$returnArray['message']	= $e->getMessage();
			}
			
			// Return teams array			
			return $returnArray;
		}
		
		
		/**
		 * Delete team.
		 *
		 * @param string organization - name of the organization of which teams to be listed.
		 * @return none.
		 */
		function deleteTeam( $teamId = 0 ) {
			// Remove team using api.
			$deleteTeam = $this->client->api('teams')->remove( $teamId );
			
		}
		
		
		/**
		 * Create team.
		 *
		 * @param string organization - name of the organization in which team needs to be created.
		 * @param string name - name of the team.
		 * @param string permission - permission that has to be set to team for accessing repositories.
		 * @return array createTeam.
		 */
		function createTeam( $name = '', $permission = 'admin' ) {
			
			// Create team
			$createTeam = $this->client->api('teams')->create( $this->organization, array( "name" => stripslashes( $name ), "permission" => $permission ));
			
			// Return array of newly created team
			return $createTeam;
		}
		
		
		/**
		 * Assign team member.
		 *
		 * @param int teamId - id of the team to which user needs to be added.
		 * @param string githubUserName - github user name of user which needs to be added in the team.
		 * @return none.
		 */
		function assignTeamMember( $teamId = 0, $githubUserName = '' ) {
			// Check team membership
			$checkMember = $this->client->api('teams')->getTeamMembership( $teamId, $githubUserName );
			
			if( $checkMember['state'] == "active" ) {
				// Assign member to team using api
				$assignMember = $this->client->api('teams')->addMember( $teamId, $githubUserName );
			} else {
				// Add membership
				$assignMember = $this->client->api('teams')->addTeamMembership( $teamId, $githubUserName );
			}
		}
		
		
		/**
		 * Remove a member from team.
		 *
		 * @param int teamId - id of the team to which user needs to be added.
		 * @param string githubUserName - github user name of user which needs to be added in the team.
		 * @return none.
		 */
		function removeTeamMember( $teamId = 0, $githubUserName = '' ) {
			// Remove member from a team using api
			$removeMember = $this->client->api('teams')->removeMember( $teamId, $githubUserName );
	
		}
		
		
		/**
		 * Checks if user is a member of a team or not
		 *
		 * @param int teamId - id of the team to which user needs to be added.
		 * @param string githubUserName - github user name of user which needs to be added in the team.
		 * @return none.
		 */
		function checkTeamMember( $teamId = 0, $githubUserName = '' ) {
			// Check if user is a member of a team using api
			return $this->client->api('teams')->check( $teamId, $githubUserName );
	
		}
		
		
		/**
		 * Assign team to repository.
		 *
		 * @param int teamId - id of the team to which user needs to be added.
		 * @param string repository - repository name to which team is being added.
		 * @return none.
		 */
		function assignTeamToRepo( $teamId, $repository = '' ) {
			
			// Assign team to repository
			$addRepo = $this->client->api('teams')->addRepository( $teamId, $this->organization, $repository);
	
		}
		
		
		/**
		 * Remove team from repository.
		 *
		 * @param int teamId - id of the team to which user needs to be added.
		 * @param string repository - repository name to which team is being added.
		 * @return none.
		 */
		function revokeTeamFromRepository( $teamId, $repository = '' ) {
			
			// Assign team to repository
			$addRepo = $this->client->api('teams')->removeRepository( $teamId, $this->organization, $repository);
	
		}
		
		
		/**
		 * List issues.
		 *
		 * @param string organization - name of the organization / github username.
		 * @param string repository - repository name of which issues needs to be listed.
		 * @return array issues.
		 */
		function listIssues( $organization = '', $repository = 'MyTestRepository' ) {
			if( empty( $organization ) ) return array();
			// Check for organization name
			$organization = (( empty( $organization ) ? $this->organization : $organization ));
			
			// List all issues to the repository
			$issues = $this->client->api('issues')->all( $organization, $repository );
			
			// Return array of issues
			return $issues;
	
		}
		
		
		/**
		 * List Activities.
		 *
		 * @param string organization - name of the organization / github username.
		 * @param string repository - repository name of which activities needs to be listed.
		 * @return array issues.
		 */
		function listActivities( $organization = '', $repository = '', $type = "events" ) {
			if( empty( $organization ) ) return array();
			// Check for organization name
			$organization = ( empty( $organization ) ? $this->organization : $organization );
			
			// List all activities to repository
			$activities = $this->client->api('repo')->repo( $organization, $repository, $type );
			
			// Return array of activities
			return $activities;
	
		}
		
		
		/**
		 * This function lists the ids of the repositories which are already assigned on github
		 *
		 * @param none
		 * @return array repositories.
		 */
		function getAssignedRepositories() {
			// Get global database object
			global $wpdb;
			
			// Initalize array
			$results = array();
			
			// Define table name
			$groupTeamsTbl 	= $wpdb->prefix . $this->groupTeamTbl;
			
			// Get records from databse using wp database object
			$results = $wpdb->get_results( 'SELECT repositoryId FROM ' . $groupTeamsTbl . ' WHERE 1', OBJECT );
			
			// Get sorted array
			$sizeofResult = sizeof( $results );
			$finalArray = array();
			if( is_array( $results ) && $sizeofResult > 0 ) {
				for( $i = 0; $i < $sizeofResult; $i++ )
					$finalArray[] = $results[$i]->repositoryId;
			}
			
			// return
			return $finalArray;
		}
		
		
		/**
		 * This function returns an array of mapping
		 *
		 * @param int groupId - id of the group
		 * @return array mapping array.
		 */
		function getMappingFromGroupId( $groupId = 0 ) {
			// Get global wp database object
			global $wpdb;
			
			// Check for valid group id
			if( (int)$groupId < 1 ) return array();
			
			// Initialize array
			$results = array();
			
			// Define table name of group-team-repository mapping
			$groupTeamsTbl 	= $wpdb->prefix . $this->groupTeamTbl;
			
			// Get records using wp database object
			$results = $wpdb->get_results( 'SELECT * FROM ' . $groupTeamsTbl . ' WHERE groupId = ' . $groupId, OBJECT );
			
			// Get size of resultant array
			$sizeofResult = sizeof( $results );
			
			// Check whether mapping for given group id exists or not
			if( is_array( $results ) && $sizeofResult > 0 ) {
				// Mapping exists.
				// Return record
				return $results[0];
			}
			
			// Return
			return array();
		}
		
		
		/**
		 * This function lists the ids of the repositories which are already assigned on github
		 *
		 * @param none
		 * @return array repositories.
		 */
		function getRepositoryDetailsFromGroupId( $groupId = 0 ) {
			// Get global wp database object
			global $wpdb;
			$repositoryDetails = array();
			$mappingArray = array();
			
			if( (int)$groupId < 1 ) return array();
			
			// Get mapping of repository and group from wp db
			$mappingArray = $this->getMappingFromGroupId( $groupId );
			
			// If mapping does not exists, return empty array
			if( sizeof( $mappingArray ) == 0 ) return array();
			
			// Get repository details from repository id got from mapping
			$repositoryDetails = $this->getRepositoryDetailsFromId( $mappingArray->repositoryId );
			
			// Return repository details
			return $repositoryDetails;
		}
		
		
		/**
		 * This function returns array containing repository details.
		 *
		 * @param int repositoryId - id of repository on the github
		 * @return array repositoryDetails
		 */
		function getRepositoryDetailsFromId( $repositoryId = 0 ) {
			// Initialize details array
			$repositoryDetails = array();
			
			// Check whether repository id is set or not
			if( (int)$repositoryId < 1 ) return array();
			
			// Get size of total repositories on github
			$sizeofRepositories = sizeof( $this->repositories );
			
			// If size of repositories array is 0, return blank array.
			if( (int) $sizeofRepositories == 0 ) return array();
			
			// Check for the repository id in the repositories array
			for( $i = 0; $i < $sizeofRepositories; $i++ ) {
				// check if id of current repository is equal to required
				if( $repositoryId == (int)$this->repositories[$i]['id'] ) {
					// Id found.
					// Assign details and break the loop
					$repositoryDetails = $this->repositories[$i];
					break;
				}
			}
			
			// Return repository details
			return $repositoryDetails;
		}
		
		
		/**
		 * This function lists filtered/unassigned repositories from github
		 *
		 * @param none
		 * @return array filteredArray.
		 */
		function getUnassignedRepositories() {
			$repositories = array();
			
			// Get all repositories from github
			//$repositories = $this->listRepositories( "", $isOrganization = true );
			$repositories = $this->repositories;
			$sizeOfRepositories = sizeof( $repositories );
			
			// Get assigned repositories
			$assignedRepositories = $this->getAssignedRepositories();
			$sizeOfAssignedRepositories = sizeof( $assignedRepositories );
			
			// Filter repositories which are not assigned
			$filteredArray = array();
			if( $sizeOfAssignedRepositories > 0 ) {
				// There are some repositories which are assigned to groups
				for( $i = 0; $i < $sizeOfRepositories; $i++ ) {
					// Check if this repository id exists in assigned list
					if( !in_array( $repositories[$i]['id'], $assignedRepositories )) {
						// This repository is un-assigned.
						// List it into filtered array
						$filteredArray[] = $repositories[$i];
					}
				}
			} else {
				// There are no assigned repositories
				// Assign actual repositories array to filtered array
				$filteredArray = $repositories;
			}
			
			return $filteredArray;
		}
		
		
		/**
		 * This function checks if team exists on github or not.
		 *
		 * @param string teamName - Name of the team
		 * @return array returnArray - array containing status flag and team details if exists.
		 */
		function checkIfTeamExists( $teamName = '' ) {
			// Initialze return array
			$returnArray = array();
			$returnArray['status'] 			= "false";
			$returnArray['teamDetails'] = array();
			
			// Check wheter team name is empty or not
			// If it is blank, return.
			if( empty( $teamName )) return $returnArray;
			
			// Get size of teams array
			$sizeofTeamsArray = sizeof( $this->teams );
			
			// Check whether team name exists in teams array
			for( $i = 0; $i < $sizeofTeamsArray; $i++ ) {
				if( strtolower( $this->teams[$i]['name'] ) == strtolower( $teamName ) ) {
					// Team name exists
					// Set status as true, set team details and break the loop
					$returnArray['status'] 			= "true";
					$returnArray['teamDetails'] = $this->teams[$i];
					break;
				}
			}
			
			// Return
			return $returnArray;
		}
		
		
		/**
		 * This function checks if repository exists on github or not.
		 *
		 * @param string teamName - Name of the repository
		 * @return array returnArray - array containing status flag and repository details if exists.
		 */
		function checkIfRepositoryExists( $repository = '', $groupId = 0 ) {
			global $wpdb;
			$returnArray = array();
			$returnArray['status'] 						= "false";
			$returnArray['repositoryDetails'] = array();
			
			if( empty( $repository ) && (int)$groupId == 0 ) return $returnArray;
			
			$sizeofRepositoriesArray = sizeof( $this->repositories );
			
			if( $groupId > 0 ) {
				// Get associated repository details from wp db
				$result = $wpdb->get_results( "select repositoryId from " . $wpdb->prefix . $this->groupTeamTbl . " where groupId = " . $groupId );
				if( !empty( $result )) {
					for( $i = 0; $i < $sizeofRepositoriesArray; $i++ ) {
						if( strtolower( $this->repositories[$i]['id'] ) == strtolower( $result[0]->repositoryId ) ) {
							$returnArray['status'] = "true";
							$returnArray['repositoryDetails'] = $this->repositories[$i];
							break;
						}
					}
				}
			} else if( !empty( $repository )) {
				for( $i = 0; $i < $sizeofRepositoriesArray; $i++ ) {
					if( strtolower( $this->repositories[$i]['name'] ) == strtolower( $repository ) ) {
						$returnArray['status'] = "true";
						$returnArray['repositoryDetails'] = $this->repositories[$i];
						break;
					}
				}
			}
			
			return $returnArray;
		}
		
		
		/**
		 * This function lists saves mapping of repository and group or creates a new repository on github as per the option selected
		 *
		 * @param array postedArray - array of posted values
		 * @param int groupDetails - group details of newly created group
		 * @return boolean flag
		 */
		function saveGroupRepository( $postedArray = array(), $groupDetails = array() ) {
			// Initialize variables.
			global $wpdb;
			$returnArray 				= array();
			$repositoryDetails 	= array();
			$repositoryId 			= 0;
			$flag = false;
			
			// Get size of arrays in parameters
			$sizeofPostedArray 	= sizeof( $postedArray );
			$sizeofGroupDetails = sizeof( $groupDetails );
			
			$createAssignNewFlag = false;
			$repoExistFlag = false;
			$updateThroughAdmin = false;
			if( isset( $postedArray['createAssignNewFlag'] ) ) $updateThroughAdmin = true;
			if( isset( $postedArray['createAssignNewFlag'] ) && $postedArray['createAssignNewFlag'] == "1" ) $createAssignNewFlag = true;
			if( isset( $postedArray['repoExistFlag'] ) && $postedArray['repoExistFlag'] == "1" ) $repoExistFlag = true;
			
			// If sizes of the arrays in parameters is zero, return
			if( $sizeofPostedArray == 0 || $sizeofGroupDetails == 0 ) return false;
			
			// Get team details
			$teamDetailsArr = $this->checkIfTeamExists( stripslashes( $groupDetails->slug ));
			
			// Check status
			if( $teamDetailsArr['status'] == "false" ) {
				// Team does not exists
				// Create new team
				$teamDetails = $this->createTeam( $name = stripslashes( $groupDetails->slug ));
				
				// Set teams array again.
				$this->teams = $this->listTeams();
				
			} else {
				// Team exists. Assign details
				$teamDetails = $teamDetailsArr['teamDetails'];
			}
			
			// Check if new repository has to be created
			if( $postedArray['repo_option'] == "createNew" ) {
				// Check if repository already exists on github
				$repositoryDetailsArr = $this->checkIfRepositoryExists( $groupDetails->slug );
				
				if( $repositoryDetailsArr['status'] == "false" ) {
					
					// Repository does not exist on github.
					// Create new repository.
					$repositoryDetails = $this->createRepository( $groupDetails->slug, stripslashes( $groupDetails->description ), '', true, $this->organization, true, true, true, 0, true );
					// Set repositories array again.
					$this->repositories = $this->listRepositories( $isOrganization = true );
					
					// Assign repository id
					$repositoryId = $repositoryDetails['id'];
				} else {
					
					if( $repoExistFlag && $createAssignNewFlag ) {
						// Existing repository id
						$repositoryId = $repositoryDetailsArr['repositoryDetails']['id'];
						
						// Revoke access of already assigned team.
						$this->revokeTeamFromRepository( $teamDetails['id'],$repositoryDetailsArr['repositoryDetails']['name'] );
						
						// Create new group is called from the administrator end
						$newRepoName = stripslashes( $groupDetails->slug );
						
						// Create new repository.
						$repositoryDetails = $this->createRepository( $newRepoName, stripslashes( $groupDetails->description ), '', true, $this->organization, true, true, true, 0, true );
						
						// Set repositories array again.
						$this->repositories = $this->listRepositories( $isOrganization = true );
						
						// Assign repository id
						$repositoryId = $repositoryDetails['id'];
					} else {
						// Repository already exists
						// Assign repository details
						$repositoryId = $repositoryDetailsArr['repositoryDetails']['id'];
						$repositoryDetails = $repositoryDetailsArr['repositoryDetails'];
					}
				}
			} else {
				
				// Repository needs to be mapped
				$repositoryId = $postedArray['repo-id'];
				$sizeofRepostories = sizeof( $this->repositories );
				
				// Check for the repository details
				for( $i = 0; $i < $sizeofRepostories; $i++ ) {
					
					if( (int)$this->repositories[$i]['id'] == (int)$repositoryId ) {
						// Assign repository details and break the loop
						$repositoryDetails = $this->repositories[$i];
						break;
					}
				}
			}
			
			// Assign team to repository
			$this->assignTeamToRepo( $teamDetails['id'], $repositoryDetails['name'] );
			
			if( !$updateThroughAdmin ) {
			
				// Get current logged in user from wordpress
				global $current_user;
				
				// Define table name of user-team mapping
				$userTeamsTbl 	= $wpdb->prefix . $this->userTeamsTbl;
				
				// Check if github user name exists or not
				$val = get_user_meta( $current_user->ID, "github_username" );
				
				if( sizeof( $val ) > 0 ) {
					if( $val[0] != '' && strlen( $val[0] ) > 0 ) {
						// Assign user to team on github
						$this->assignTeamMember( (int)$teamDetails['id'], $val[0] );
					
						// Build query to save/update mapping of group and repository
						$query  = " INSERT INTO " . $userTeamsTbl . " ( userId, teamId ) VALUES ( '" . (int)$current_user->ID . "', '" . (int)$teamDetails['id'] . "' ) ";
						$query .= " ON DUPLICATE KEY UPDATE teamId = '" . (int)$teamDetails['id'] . "', userId = '" . (int)$current_user->ID . "'; ";
						
						// Execute query
						$flag = $wpdb->query( $query );
					}
				}
			}
			
			// Define table name of mappings
			$groupTeamsTbl 	= $wpdb->prefix . $this->groupTeamTbl;
			
			// Build query to save/update mapping of group and repository
			$query  = " INSERT INTO " . $groupTeamsTbl . " ( groupId, teamId, repositoryId ) VALUES ( '" . (int)$groupDetails->id . "', '" . (int)$teamDetails['id'] . "', '" . (int)$repositoryId . "' ) ";
			$query .= " ON DUPLICATE KEY UPDATE teamId = '" . (int)$teamDetails['id'] . "', repositoryId = '" . (int)$repositoryId . "'; ";
			
			$flag = $wpdb->query( $query );
			
			// Return
			return ( $flag == 0 || $flag == false ? false : true );
		}
		
		
		/**
		 * This function lists activities of the team
		 *
		 * @param array userId - id of the logged in user
		 * @param int groupDetails - group details of newly created group
		 * @param boolean assignToGroup - If set true, user should be assigned to team if not already assigned
		 * @return boolean flag
		 */
		function getTeamActivities( $userId = 0, $groupDetails = array(), $assignToGroup = false, $githubUsername = '', $type = "commits" ) {
			// Check for user id
			if( (int)$userId < 1 ) return array();
			
			// Get team details
			$teamDetailsArr = $this->checkIfTeamExists( stripslashes( $groupDetails->slug ));
			
			if( $teamDetailsArr['status'] == "true" ) {
				// Assign team details
				$teamDetails = $teamDetailsArr['teamDetails'];
				
				// Get user teams
				$flag = $this->checkIfUserIsMemberOfTeam( $userId, $teamDetails['id'], $assignToGroup, $githubUsername );
				
				// Check if repository on github exists for this group
				$checkRepoFlag = $this->checkIfRepositoryExists( ucwords( $groupDetails->slug ), $groupDetails->id );
				
				if( $flag && $checkRepoFlag['status'] == "true" ) { 

					$activities = $this->listActivities( $this->organization, $checkRepoFlag['repositoryDetails']['name'], $type);
  				
					return $activities;
				} else {
					return array();
				}
			}
			
			return array();
		}
		
		
		/**
		 * This function returns the flag which tells if user is a member of a team or not
		 *
		 * @param array userId - id of the logged in user
		 * @param int teamId - id of the team
		 * @param boolean assignToGroup - If set true, user should be assigned to team if not already assigned
		 * @return boolean flag
		 */
		function checkIfUserIsMemberOfTeam( $userId, $teamId, $assignToGroup, $githubUsername = '' ) {
			// Get global wp database object
			global $wpdb;
			
			// Check for valid group id
			if( (int)$userId < 1 ) return false;
			
			// Initialize array
			$results = array();
			
			// Define table name of group-team-repository mapping
			$userTeamsTbl 	= $wpdb->prefix . $this->userTeamsTbl;
			
			// Get records using wp database object
			$results = $wpdb->get_results( 'SELECT * FROM ' . $userTeamsTbl . ' WHERE userId = ' . $userId . ' and teamId = ' . $teamId, OBJECT );
			
			// Get size of resultant array
			$sizeofResult = sizeof( $results );
			
			// Check whether mapping for given group id exists or not
			if( is_array( $results ) && $sizeofResult > 0 ) {
				// Mapping exists.
				// Return record
				return true;
			}
			
			if( $assignToGroup && strlen( $githubUsername ) > 0 ) {
				// Assign user to team on github
				$this->assignTeamMember( $teamId, $githubUsername );
			
				// Build query to save/update mapping of group and repository
				$query  = " INSERT INTO " . $userTeamsTbl . " ( userId, teamId ) VALUES ( '" . (int)$userId . "', '" . (int)$teamId . "' ) ";
				$query .= " ON DUPLICATE KEY UPDATE teamId = '" . (int)$teamId . "', userId = '" . (int)$userId . "'; ";
				
				$flag = $wpdb->query( $query );
				
				// Return
				return ( $flag == 0 || $flag == false ? false : true );
			}
			
			// Return 
			return false;
		}
		
		/**
		 * This function returns the flag which tells if user is a member of a team or not
		 *
		 * @param array userId - id of the logged in user
		 * @param int groupDetails - details of the group to which userneeds to be bound
		 * @param boolean githubUsername - github username of logged in user
		 * @return boolean flag
		 */
		function assignMemberToTeam( $userId, $groupDetails, $githubUsername = '' ) {
			// Get global wp database object
			global $wpdb;
			
			// Check for valid group id
			if( (int)$userId < 1 ) return false;
			
			// Initialize array
			$results = array();
			
			// Check if team exists on github for this group
			$teamDetailsArr = $this->checkIfTeamExists( $groupDetails->slug );
						
			if( $teamDetailsArr['status'] == "true" ) {
				// Set team id
				$teamId = $teamDetailsArr['teamDetails']['id'];
			
				// Define table name of group-team-repository mapping
				$userTeamsTbl 	= $wpdb->prefix . $this->userTeamsTbl;
				
				// Get records using wp database object
				$results = $wpdb->get_results( 'SELECT * FROM ' . $userTeamsTbl . ' WHERE userId = ' . $userId . ' and teamId = ' . $teamId, OBJECT );
								
				// Get size of resultant array
				$sizeofResult = sizeof( $results );
				
				// Check whether mapping for given group id exists or not
				if( is_array( $results ) && $sizeofResult > 0 ) {
					// Mapping exists.
					// Return record
					$this->assignTeamMember( $teamId, $githubUsername );
					return true;
				}
				
				if( strlen( $githubUsername ) > 0 ) {
					// Assign user to team on github
					$this->assignTeamMember( $teamId, $githubUsername );
				
					// Build query to save/update mapping of group and repository
					$query  = " INSERT INTO " . $userTeamsTbl . " ( userId, teamId ) VALUES ( '" . (int)$userId . "', '" . (int)$teamId . "' ) ";
					$query .= " ON DUPLICATE KEY UPDATE teamId = '" . (int)$teamId . "', userId = '" . (int)$userId . "'; ";
					
					$flag = $wpdb->query( $query );
					
					// Return
					return ( $flag == 0 || $flag == false ? false : true );
				}
			}
			
			// Return 
			return false;
		}
		
		
		/**
		 * This function returns the flag which tells if user is a member of a team or not
		 *
		 * @param array userId - id of the logged in user
		 * @param int groupDetails - details of the group to which userneeds to be bound
		 * @param boolean githubUsername - github username of logged in user
		 * @return boolean flag
		 */
		function removeMemberFromTeam( $userId, $groupDetails, $githubUsername = '' ) {
			// Get global wp database object
			global $wpdb;
			
			// Check for valid group id
			if( (int)$userId < 1 ) return false;
			
			// Initialize array
			$results = array();
			
			// Check if team exists on github for this group
			$teamDetailsArr = $this->checkIfTeamExists( stripslashes( $groupDetails->slug ));
			
			if( $teamDetailsArr['status'] == "true" ) {
			
				// Set team id
				$teamId = $teamDetailsArr['teamDetails']['id'];
				
				// Define table name of group-team-repository mapping
				$userTeamsTbl 	= $wpdb->prefix . $this->userTeamsTbl;
				
				// Get records using wp database object
				$results = $wpdb->get_results( 'SELECT * FROM ' . $userTeamsTbl . ' WHERE userId = ' . $userId . ' and teamId = ' . $teamId, OBJECT );
				
				// Get size of resultant array
				$sizeofResult = sizeof( $results );
				
				// Check whether mapping for given group id exists or not
				if( is_array( $results ) && $sizeofResult > 0 ) {
					// Mapping exists.
					// Return record
					// Remove member of team
					$this->removeTeamMember( $teamId, $githubUsername );
					
					// Remove team-user mapping
					$query = 'DELETE FROM ' . $userTeamsTbl . ' WHERE userId = ' . $userId . ' and teamId = ';
					$flag = $wpdb->query( $query );
					
					return true;
				}
				
				if( strlen( $githubUsername ) > 0 ) {
					// Assign user to team on github
					$this->assignTeamMember( $teamId, $githubUsername );
				
					// Build query to save/update mapping of group and repository
					$query  = " INSERT INTO " . $userTeamsTbl . " ( userId, teamId ) VALUES ( '" . (int)$userId . "', '" . (int)$teamId . "' ) ";
					$query .= " ON DUPLICATE KEY UPDATE teamId = '" . (int)$teamId . "', userId = '" . (int)$userId . "'; ";
					
					$flag = $wpdb->query( $query );
					
					// Return
					return ( $flag == 0 || $flag == false ? false : true );
				}
			}
			
			// Return 
			return false;
		}
		
		
		/**
		 * This function returns the string containing listing of commit activities
		 *
		 * @param array activities - array containing commit activities
		 * @return string str
		 */
		function buildCommitActivitiesHtml( $activities = array(),$groupDetails = array() ) {
			if( sizeof( $activities ) == 0 ) return '';
			$sizeOfactivities = sizeof( $activities );
			
			$str = '';
			
			if( $sizeOfactivities > 0 ) {
				$str .= '<ul class="activity-list item-list">';
				for( $i = 0; $i < $sizeOfactivities; $i++ ) {
				 
				   $users = get_users( array( 'meta_key' => 'github_username', 'meta_value' => $activities[$i]['committer']['login']) );
				   $user_link = bp_core_get_user_domain( $users[0]->ID );
				   $profile_image = bp_core_fetch_avatar ( array( 'item_id' => $users[0]->ID, 'type' => 'full' ));
				   $action = '<a href="'.$user_link.'"> '.$activities[$i]['committer']['login'].'</a> committed files at <a href="'.$site_url.'/groups/'.$groupDetails->slug.'/" >'.stripslashes( $groupDetails->name ).'</a> - <a href="'.$activities[$i]['html_url'].'">'.$activities[$i]['commit']['message'].'</a>';
				   $actionDate = date( 'Y-m-d H:i:s', strtotime( $activities[$i]['commit']['committer']['date'] ) );
				   $time = $this->build_time_elapsed_string($actionDate, true);

				   $str .= '<li style="float:none;"><div class="activity-avatar rounded">'.$profile_image.'</div>';
				   $str .='<div class="activity-content"><div class="activity-header"><p>'.$action.'<span class="time-since"> '.$time.'</span></p></div></div>';
				   $str .= "</li>";
				  
				}
				$str .= "</ul>";
			} else {
				$str .= "Activities not found";
			}
			
			return $str;
		}
		
		
		/**
		 * This function returns the string containing listing of all activities
		 *
		 * @param array activities - array containing commit activities
		 * @return string str
		 */
		function buildActivitiesHtml( $activities = array() ) {
			if( sizeof( $activities ) == 0 ) return '';
			
			$sizeOfactivities = sizeof( $activities );
			
			$str = '';
			
			if( $sizeOfactivities > 0 ) {
				$str .= "<ul>";
				for( $i = 0; $i < $sizeOfactivities; $i++ ) {
					$str .= '<li class="membersList">
										<div class="members_profile"><img src="' . $activities[$i]['actor']['avatar_url'] . '" /></div>
										';
						$str .= '<a href="' . $activities[$i]['repo']['url'] . '" target="_blank">' . $activities[$i]['repo']['name'] . '</a>';
						$str .= '<br/>Type: ' . $activities[$i]['type'];
						$str .= '<br/>Date time: ' . $activities[$i]['created_at'];
					$str .= "</li>";
				}
				$str .= "</ul>";
			} else {
				$str .= "Activities not found";
			}
			
			return $str;
		}
		
		/**
		 * This function returns repository details from github
		 *
		 * @param array repository - name of the repository
		 * @return array repositoryDetails
		 */
		function getRepositoryDetails( $repository = "", $groupId = 0 ) {
			global $wpdb;
						
			// Check for repository name
			if( empty( $repository )) return array();
			
			// Initialize details array
			$repositoryDetails = array();
			
			// Get size of total repositories on github
			$sizeofRepositories = sizeof( $this->repositories );
			
			// If size of repositories array is 0, return blank array.
			if( (int) $sizeofRepositories == 0 ) return array();
			
			if( $groupId > 0 ) {
				// Get associated repository details from wp db
				$result = $wpdb->get_results( "select repositoryId from " . $wpdb->prefix . $this->groupTeamTbl . " where groupId = " . $groupId );
				if( !empty( $result )) {
					for( $i = 0; $i < $sizeofRepositories; $i++ ) {
						if( strtolower( $this->repositories[$i]['id'] ) == strtolower( $result[0]->repositoryId ) ) {
							$repositoryDetails = $this->repositories[$i];
							break;
						}
					}
				}
			} else {
				// Check for the repository name in the repositories array
				for( $i = 0; $i < $sizeofRepositories; $i++ ) {
					// check if repository name of current repository is equal to required
					if( $repository == strtolower( $this->repositories[$i]['name'] )) {
						// Id found.
						// Assign details and break the loop
						$repositoryDetails = $this->repositories[$i];
						break;
					}
				}
			}
			
			// Return repository details
			return $repositoryDetails;
		}
		
		/**
		 * List branches.
		 *
		 * @param string organization - name of the organization / github username.
		 * @param string repository - repository name of which branches needs to be listed.
		 * @return array issues.
		 */
		function listBranches( $organization = '', $repository = '', $branch = null ) {
			if( empty( $repository ) ) return array();
			// Check for organization name
			$organization = ( empty( $organization ) ? $this->organization : $organization );
			
			// List all branches to repository
			$branches = $this->client->api('repo')->branches( $organization, $repository, $branch );
			// Return array of branches
			return $branches;
	
		}
		
		
		/**
		 * Manage the releases of a repository (Currently Undocumented)
		 * @link http://developer.github.com/v3/repos/ 
		 *
		 * @return Releases
		 */
		function listReleases( $organization = '', $repository = '' ) {
			if( empty( $repository ) ) return array();
			$organization = ( empty( $organization ) ? $this->organization : $organization );
			// List all releases to repository
			$releaseObj = $this->client->api('repo')->releases();
			
			$releases = $releaseObj->all( $organization, $repository );
			// Return array of releases
			return $releases;
	
		}
		
		
		/**
		 * Get the contributors of a repository
		 * @link http://developer.github.com/v3/repos/
		 *
		 * @param string  $username           the user who owns the repository
		 * @param string  $repository         the name of the repository
		 * @param boolean $includingAnonymous by default, the list only shows GitHub users.
		 *                                    You can include non-users too by setting this to true
		 * @return array list of the repo contributors
		 */
		function listContributors( $organization = '', $repository = '' ) {
			if( empty( $repository ) ) return array();
			$organization = ( empty( $organization ) ? $this->organization : $organization );
			// Return array of contributors
			return $contributorsArray = $this->client->api('repo')->contributors( $organization, $repository );
		}
		
		
		/**
		 * Get the files of a repository
		 * @link http://developer.github.com/v3/repos/ 
		 *
		 * @return contents
		 */
		function listRepositoryFiles( $organization = '',$repository = '' ) {
			if( empty( $repository ) ) return array();
		    
			// Check for organization name
			$organization = ( empty( $organization ) ? $this->organization : $organization );
		    
			// List all content files to repository
			$contentsObj = $this->client->api('repo')->contents();
			$contents = $contentsObj->show( $organization, $repository );
			
			//Return array of files
			return $contents;
	
		}
		
    /**
		 * Function to build time string
		 *
		 * @param string datetime
		 * @param string full
		 * @return contents
		 */   
	  function build_time_elapsed_string($datetime, $full = false) {
         $now = new DateTime;
         $ago = new DateTime($datetime);
         $diff = $now->diff($ago);

         if (isset($diff)) {
            $string = array(
           'y' => 'year',
           'm' => 'month',
           'd' => 'day',
           'h' => 'hour',
           'i' => 'minute'
        );

        foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
         } else {
            unset($string[$k]);
         }
       }
       if (!$full)
        $string = array_slice($string, 0, 1);
       return $string ? implode(', ', $string) . ' ago' : 'just now';
       }
      else {
           return 0;
         }
      }
			
			function save_github_username_ajax(){
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
				
				// Check whether username already exists or not.
				$result = true;
				update_user_meta( $userId, $customField, $value );
				echo "true"; die;
			}
	}
}

// Create tables
register_activation_hook( __FILE__, 'createTables' );
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

/**
 * Creates tables in wordpress database when plugin is activated
 *
 * @param none
 * @return none
 */
function createTables() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . "github_api_user_teams";
	$sql = "DROP TABLE IF EXISTS `" . $table_name . "`;";
	$wpdb->query($sql);
	
	$sql = "
					CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
						`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key to identify unique row in the table',
						`userId` int(11) NOT NULL COMMENT 'Wordpress user id',
						`teamId` int(11) NOT NULL COMMENT 'Id of a team on github',
						PRIMARY KEY (`id`),
						KEY `userId` (`userId`,`teamId`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
	
	dbDelta( $sql );
	
	$table_name1 = $wpdb->prefix . "github_api_group_teams";
	
	$sql = "DROP TABLE IF EXISTS `" . $table_name1 . "`;";
	$wpdb->query($sql);
	
	$sql = "
					CREATE TABLE IF NOT EXISTS `" . $table_name1 . "` (
						`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key to identify unique row in the table',
						`groupId` int(11) NOT NULL COMMENT 'Id of a group in wordpress',
						`teamId` int(11) NOT NULL COMMENT 'Id of a team on github',
						`repositoryId` int(11) NOT NULL COMMENT 'Id of the repository on github',
						PRIMARY KEY (`id`),
						UNIQUE KEY `groupId_2` (`groupId`),
						KEY `groupId` (`groupId`),
						KEY `team` (`teamId`),
						KEY `repositoryId` (`repositoryId`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
	
	dbDelta( $sql );
}

// Create menu in admin settings
add_action( 'admin_menu', 'github_api_menu' );

/**
 * Create sub menu in settings menu of wordpress admin
 *
 * @param none
 * @return none
 */
function github_api_menu() {
	add_options_page( 'Github API Options', 'Github API', 'manage_options', 'github-api', 'github_api_options' );
}

/**
 * Create options to be set
 *
 * @param none
 * @return none
 */
function github_api_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// variables for the field and option names 
    
	$hidden_field_name = 'mt_submit_hidden';
	
	$github_username 			= 'github_username';
	$github_password 			= "github_password";
	$github_client_id 		= "github_client_id";
	$github_client_secret = "github_client_secret";
	$github_organization 	= "github_organization";

	// Read in existing option value from database
	$github_username_val 			= get_option( $github_username );
	$github_password_val 			= get_option( $github_password );
	$github_client_id_val 		= get_option( $github_client_id );
	$github_client_secret_val = get_option( $github_client_secret );
	$github_organization_val 	= get_option( $github_organization );

	// See if the user has posted us some information
	// If they did, this hidden field will be set to 'Y'
	if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
		// Read their posted value
		$github_username_val = $_POST[ $github_username ];
		update_option( $github_username, $github_username_val );
		
		 // Read their posted value
		$github_password_val = $_POST[ $github_password ];
		update_option( $github_password, $github_password_val );
		
		 // Read their posted value
		$github_client_id_val = $_POST[ $github_client_id ];
		update_option( $github_client_id, $github_client_id_val );
		
		 // Read their posted value
		$github_client_secret_val = $_POST[ $github_client_secret ];
		update_option( $github_client_secret, $github_client_secret_val );
		
		 // Read their posted value
		$github_organization_val = $_POST[ $github_organization ];
		update_option( $github_organization, $github_organization_val );

		// Put an settings updated message on the screen

?>
<div class="updated"><p><strong><?php _e('settings saved.', 'menu-test' ); ?></strong></p></div>
<?php
   }

    // settings form
    ?>
		<div class="wrap">
			<h2><?php __( 'Github API settings', 'menu-test' ); ?></h2>
			<style>
			.api_settings label{
				width:200px;
				display:block;
				float:left;
			}
			</style>
			<form name="form1" method="post" action="" class="api_settings">
				<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
				
				<p><label><?php _e("Github Username:", 'menu-test' ); ?> </label>
					<input type="text" name="<?php echo $github_username; ?>" value="<?php echo $github_username_val; ?>" size="20">
				</p>
				
				<p><label><?php _e("Github Password:", 'menu-test' ); ?> </label>
					<input type="text" name="<?php echo $github_password; ?>" value="<?php echo $github_password_val; ?>" size="20">
				</p>
				
				<p><label><?php _e("Client Id:", 'menu-test' ); ?> </label>
					<input type="text" name="<?php echo $github_client_id; ?>" value="<?php echo $github_client_id_val; ?>" size="20">
				</p>
				
				<p><label><?php _e("Client Secrete:", 'menu-test' ); ?> </label>
					<input type="text" name="<?php echo $github_client_secret; ?>" value="<?php echo $github_client_secret_val; ?>" size="20">
				</p>
				
				<p><label><?php _e("Organization:", 'menu-test' ); ?> </label>
					<input type="text" name="<?php echo $github_organization; ?>" value="<?php echo $github_organization_val; ?>" size="20">
				</p>
				
				<hr />
				
				<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
				</p>
			
			</form>
		</div>
<?php
 
}

/**
 * List github issues
 *
 * @param array atts - attributes defined while adding shortcode
 * @return string str containing html
 */
function github_issues( $atts ) {
	// Original Attributes, for filters
	$original_atts = $atts;

	// Pull in shortcode attributes and set defaults
	$atts = shortcode_atts(array( 'username' => '', 'repository' => '', 'groupname' => ''), $atts);
	// create api object
	$obj = new Github_Api( true );
	
	$username = $atts['username'];
	$repository = $atts['repository'];
	$groupname = $atts['groupname'];
	
	// Get all issues
	$issues = $obj->listIssues( ( $username != '' ? $username : $obj->organization ), ucwords( $repository ));
	
	$sizeOfissues = sizeof( $issues );
	$str = '';
				
	// Create a list using html tags
	if( $sizeOfissues > 0 ) {
		$str .= '<ul class="respository-activity-list" id="activity-stream">';
		for( $i = 0; $i < $sizeOfissues; $i++ ) {
		
	     $users = get_users( array( 'meta_key' => 'github_username', 'meta_value' => $issues[$i]['user']['login']) );
		 $user_link = bp_core_get_user_domain( $users[0]->ID );
		 $profile_image = bp_core_fetch_avatar ( array( 'item_id' => $users[0]->ID, 'type' => 'full' ));
			
	     $action = '<a href="'. $user_link .'" >'.$issues[$i]['user']['login'].'</a> reported an issue at <a href="'.$site_url.'/groups/'.$repository.'/" >'.$groupname.'</a> - <a href="'.$issues[$i]['html_url'].'" >'.$issues[$i]['title'].'</a>';
		 $actionDate = date( 'Y-m-d H:i:s', strtotime( $issues[$i]['created_at'] ) );
		 $time =$obj->build_time_elapsed_string($actionDate, true);

		 $str .= '<li style="float:none;">
		 			<div class="col-lg-11 col-sm-11 col-md-11 no-padding">
		 				<img src="' . site_url() . '/wp-content/themes/kleo-child/images/issue-ico.png" class="issue-img"/>
						<div class="test-issue">'. $issues[$i]['title'] .'<span>Opened by <strong>' . $issues[$i]['user']['login'] . '</strong> ' . $time . '</span></div>
					</div>
					<div class="col-lg-1 col-sm-1 col-md-1 no-padding">
						<div class="count">#'. $issues[$i]['number'] .'</div>
					</div>';
		 //$str .='<div class="activity-content"><div class="activity-header"><p>'.$action.'<span class="time-since">'.$time.'</span></p></div></div>';
		 $str .= "<div class='clear-both'></div></li>";
		
		}
		$str .= "</ul>";
	} else {
		$str .= "Issues not found";
	}
	
	return $str;
}

// Create short code for github issues list
add_shortcode('github_issues', 'github_issues');


/**
 * List activities on github through shortcode
 *
 * @param array atts - attributes defined while adding shortcode
 * @return string str containing html
 */
function github_activities( $atts ) {
	// Original Attributes, for filters
	$original_atts = $atts;

	// Pull in shortcode attributes and set defaults
	$atts = shortcode_atts(array(
													'username' => '',
													'repository' => 'MyTestRepository'
												), $atts);
	
	$obj = new Github_Api( true );
	
	$activities = $obj->listActivities( $atts['username'], $atts['repository'] );
	$sizeOfactivities = sizeof( $activities );
	
	$str = '';
	
	if( $sizeOfactivities > 0 ) {
		$str .= "<ul>";
		for( $i = 0; $i < $sizeOfactivities; $i++ ) {
			$str .= '<li class="membersList">
								<div class="members_profile"><img src="' . $activities[$i]['actor']['avatar_url'] . '" /></div>
								';
				$str .= '<a href="' . $activities[$i]['repo']['url'] . '" target="_blank">' . $activities[$i]['repo']['name'] . '</a>';
				$str .= '<br/>Type: ' . $activities[$i]['type'];
				$str .= '<br/>Date time: ' . $activities[$i]['created_at'];
			$str .= "</li>";
		}
		$str .= "</ul>";
	} else {
		$str .= "Activities not found";
	}
	
	return $str;
}

// Create short code for github activities
add_shortcode('github_activities', 'github_activities');

/**
 * List repositories through shortcode.
 *
 * @param array atts - attributes defined while adding shortcode
 * @return string str containing html
 */
function github_repositories( $atts ) {
	// Original Attributes, for filters
	$original_atts = $atts;

	// Pull in shortcode attributes and set defaults
	$atts = shortcode_atts(array( 'username' => '' ), $atts);
	
	$obj = new Github_Api( true );
	
	$repos = $obj->listRepositories( $atts['username'] );
	$sizeOfRepos = sizeof( $repos );
	$str = '';
	
	if( $sizeOfRepos > 0 ) {
		$str .= '<ul class="repoUL">';
		for( $i = 0; $i < $sizeOfRepos; $i++ ) {
			$str .= "<li>";
				$str .= '<a href="' . $repos[$i]['html_url'] . '" target="_blank">' . $repos[$i]['name'] . '</a>';
			$str .= "</li>";
		}
		$str .= "</ul>";
	} else {
		$str .= "Repositries not found";
	}
	
	return $str;
}

// Create short code for displaying list of github repositories
add_shortcode('github_repositories', 'github_repositories');

/**
 * Function to add css and javascript when plugin is loaded
 *
 * @param none
 * @return none
 */
function github_api_scripts() {
	wp_enqueue_style( 'github-api', plugins_url( 'css/github-api.css' , __FILE__ ) );
	wp_enqueue_script( 'github-api', plugins_url( 'js/github-api.js' , __FILE__ ) );
}

// Add action to call plugins function which adds css and javascript files
add_action( 'wp_enqueue_scripts', 'github_api_scripts' );

function github_api_remove_database() {
	global $wpdb;
	$table_name = $wpdb->prefix . "github_api_group_teams";
	$sql = "DROP TABLE IF EXISTS " . $table_name . ";";
	$wpdb->query($sql);
	
	$table_name = $wpdb->prefix . "github_api_user_teams";
	$sql = "DROP TABLE IF EXISTS " . $table_name . ";";
	$wpdb->query($sql);
	
	delete_option( "github_username" );
	delete_option( "github_password" );
	delete_option( "github_client_id" );
	delete_option( "github_client_secret" );
	delete_option( "github_organization" );
	
	$table_name = "wp_usermeta";
	$sql = "delete from wp_usermeta where meta_key = 'github_username';";
	$wpdb->query($sql);
}

register_deactivation_hook( __FILE__, 'github_api_remove_database' );
?>