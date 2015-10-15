<?php
namespace PHPAuth\Plugins;

/**
 * Group plugin for PHPAuth
 */
 
class Group
{
	private $dbh;
	public $config;
	public $lang;
	public $about;
	
	
	public function __construct(\PDO $dbh, $config, $lang)
	{
		$this->dbh = $dbh;
		$this->config = $config;
		$this->lang = $lang;
	}
	
	
	
	
	/**
	 * check if user is in this group
	 * @param int $gid : group id
	 * @param int $uid : user id
	 * @return boolean
	 */	 
	 
	public function checkAuthGroup($gid,$uid)
	{
		//prepare query
		$query = $this->dbh->prepare("SELECT uid FROM {$this->config->table_usergroups} WHERE gid = ? AND uid = ?");
        $query->execute(array($gid, $uid));

        if ($query->rowCount() == 0) {
            return false;
        } else {
        	return true;
		}
	}
	
	/**
	 * get datas from one group
	 * @param int $gid : group id
	 * @param bool $full : get users list if true
	 * @param var $groupName : get group data by name (usefull for name search)
	 * @return array
	 */	 
	 
	public function getGroup($gid, $full = false, $groupName = false)
	{
		$data = array();		
		
		if($gid !== 0) 
		{
			$query = $this->dbh->prepare("SELECT * FROM {$this->config->table_groups} WHERE gid = ?");
	        $query->execute(array($gid));
		} elseif ($groupName !== false) {
			$query = $this->dbh->prepare("SELECT * FROM {$this->config->table_groups} WHERE group_name LIKE '%?%'");
	        $query->execute(array($groupName));
		} else {
			return false;
		}
        if ($query->rowCount() == 0) {
            return false;
        } else {
            $data = $query->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return false;
            } else {
                if($full === TRUE)
				{
					$data['users'] =  $this->groupUsersGet($gid);
				}
						
				return $data;
            }
        }
	}
	
	/**
	 * create or modify group
	 * @param int $gid  : NULL => create , if set => modify
	 * @param var $groupName
	 * @param var $groupDescription
	 * @return bool
	 */	 
	 
	public function setGroup($gid, $groupName, $groupDescription)
	{
		// no group id, then create a new group
		if(is_null($gid)) {
			return $this->groupCreate($groupName, $groupDescription);
		}
		else { // modify group information
			return $this->groupModify($gid, $groupName, $groupDescription);
		}
			
	}
	
	/***
	 * add, remove or change admin level from one user from group(s)
	 * if $gid is an array, user will be removed from multiple groups
	 */
	 
	public function SetUserInGoups($gid, $uid, $set)
	{
		if(is_array($uid)) {			
			return false;
		}	
			
		if($set === 'add') {
			return $this->groupUserAdd($gid, $uid);
		} elseif ($set === 'delete') {
			return $this->groupUserDelete($gid, $uid);
		} else {
			return false;
		}
	}
	
	/**
	 * Get user list from group(s)
	 */
	 
	public function getUserByGroup($gid)
	{
		$data = array();
		
		$query = $this->dbh->prepare("SELECT ug.uid, ug.level, u.username FROM {$this->config->table_usergroups} as ug LEFT JOIN {$this->config->table_users} as u ON u.id = ug.uid WHERE ug.gid=?");
        $query->execute(array($gid));
		
		if ($query->rowCount() == 0) {
            return false;
        } else {
            $data = $query->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return false;
            } else {
            	return $data;
			}
		}
	}
	
	/**
	 * get groups from one user
	 */
	 
	public function getGroupByUser($uid)
	{
		$data = array();
		
		$query = $this->dbh->prepare("SELECT g.*, ug.level FROM {$this->config->table_groups} AS g INNER JOIN {$this->config->table_usergroups} AS ug ON ug.gid = g.gid WHERE ug.uid=?");
        $query->execute(array($uid));
		
		if ($query->rowCount() == 0) {
            return false;
        } else {
            $data = $query->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return false;
            } else {
            	return $data;
			}
		}
	}
	
	
	
	// Go for private internal functions
	
	/**
	 * create a new group
	 * @param string $groupName
	 * @param string $groupDescription
	 * @return array
	 */
	 
	private function groupCreate($groupName, $groupDescription)
	{
		$return['error'] = true;
		
		// first check if this group name does not already exists 		
		if($this->getGroup(false, false, $groupName) !== FALSE) {
			$return['message'] = _("group_allready_exists");
			return $return;
		}
		
		// security check on group name
		$return = $this->validateGroupName($groupName);
		if($return['error'] == true) {			
			return $return;
		}
		
		// security check on group description
		$groupDescription = trim(htmlspecialchars(str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $groupDescription), ENT_COMPAT, 'UTF-8'));
		if(empty($groupDescription)) {
			$return['message'] = _("group_desc_empty");
			return $return;
		}
		
		 $query = $this->dbh->prepare("INSERT INTO {$this->config->table_groups} (group_name, group_desc) VALUES (?, ?)");
         $return = $query->execute(array(               
                $groupName,
                $groupDescription));

         $return['error'] = false;
		 $return['message'] = _("group_creation_successfull");
	}
	
	/**
	 * modify group into DB
	 * @param int $gid
	 * @param string $groupName
	 * @param string $groupDescription
	 * @return array
	 */ 
	 
	private function groupModify($gid, $groupName, $groupDescription)
	{
		$return['error'] = true;
			
		// if group id is not integrer, it s an error
		if(!is_int($gid)){
			$return['message'] = _("group_id_invalid");
			return $return;
		}
		
		// security check on group name
		$return = validateGroupName($groupName);
		if($return['error'] == true) {
			return $return;
		}
		
		// security check on group description
		$groupDescription = trim(htmlspecialchars(str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", ''), $groupDescription), ENT_COMPAT, 'UTF-8'));
		if(empty($groupDescription)) {
			$return['message'] = _("group_desc_invalid");
			return $return;
		}
		
		 $query = $this->dbh->prepare("UPDATE {$this->config->table_groups} SET group_name = ?, group_desc = ? WHERE gid = ?");
         $return = $query->execute(array(               
                $groupName,
                $groupDescription,
                $gid));

        $return['error'] = false;
        $return['message'] = _("group_modify_successfull");
		return $return;
	}
	
	/**
	 * delete group from DB , 'locked' groups can't be deleted
	 * @param int $gid
	 * @return bool
	 */ 
	 
	private function groupDelete($gid)
	{
		// if group id is not integrer, it s an error
		if(!is_int($gid)){
			return false;
		}
		// first delete users from this group
		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_usergroups} WHERE gid = ?");
		$return = $query->execute(array($gid));
		//then delete the group
		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_groups} WHERE gid = ? AND locked != 1");
		$return = $query->execute(array($gid));
		
	}


	/**
	 * add one user to one group
	 * @todo add multi user handling
	 * @param int $gid
	 * @param int $uid
	 * @param bool $admin / set user as group admin/owner
	 * @return bool
	 */
	 
	private function groupUserAdd($gid, $uid, $admin = false)
	{
		// if group or user id is not integrer, it s an error
		if(!is_int($gid) || !is_int($uid)){
			return false;
		}
		
		//check if user is not already in group
		$data = array();
		
		$query = $this->dbh->prepare("SELECT * FROM {$this->config->table_usergroups} WHERE gid = ? AND uid=?");
        $query->execute(array($gid, $uid));
		
		if ($query->rowCount() != 0) {
            return false;
        }
		
		 $query = $this->dbh->prepare("INSERT INTO {$this->config->table_usergroups} (gid, uid, admin) VALUES (?, ?, ?)");
         $return = $query->execute(array(               
                $gid,
                $uid,
				$admin));

         return true;	
		
	}
	
	/**
	 *  remove one user from one group
	 * @todo add multi user handling
	 * @todo add group owner control
	 * @param int $gid
	 * @param int $uid
	 * @param bool $admin
	 * @return bool
	 */
	 
	private function groupUserDelete($gid, $uid, $admin = false)
	{
		// if group or user id is not integrer, it s an error
		if(!is_int($gid) || !is_int($uid)){
			return false;
		}
		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_usergroups} WHERE gid = ? AND uid = ?");
		$return = $query->execute(array($gid, $uid));
		
		return true;
	}
	
	
	/**
	 * change admin/owner level
	 * @todo add group owner control
	 * @param int $gid
	 * @param int $uid
	 * @param bool $admin
	 * @return bool
	 */
	 
	private function groupUserAdmin($gid, $uid, $admin = false)
	{
		// if group or user id is not integrer, it s an error
		if(!is_int($gid) || !is_int($uid)){
			return false;
		}
		
		$query = $this->dbh->prepare("UPDATE {$this->config->table_usergroups} SET admin = ? WHERE gid = ? AND uid = ?");
		$return = $query->execute(array($admin, $gid, $uid));
		
		return true;		
	}
		
	
	/**
    * Verify name validity
    * @param string $groupName
    * @return bool
    */
    
    private function validateGroupName($groupName) 
    {
        $return['error'] = true;
		
		// make sure we work in UTF8 format		
		if(mb_detect_encoding($groupName) != 'UTF-8') {
			utf8_encode($groupName);
		}
		
		// set the matching regular expression
		//		
		//$match = '/^([-a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ\s])+$/i'; 
		//$match = '/^(\w\d\s+)$/'; 
		$match = '/^[\p{L}]+$/ui'; // support languages with special caracters
		$groupName = trim((string)$groupName);
		
		
		echo $groupName;
		echo '<br>';
		echo preg_match($match,$groupName); die();
		
        if (mb_strlen($groupName) < 3) {
            $return['message'] = _("group_name_short");
            
        } elseif (mb_strlen($groupName) > 30) {
            $return['message'] =  _("group_name_long");
            
        } elseif (!preg_match($match,$groupName)) {
            $return['message'] =  _("group_name_invalid");
            
        } else {
            $return['error'] = false;
			$return['message'] = "";
        }
		return $return;
    } 
	
	/**
	 * text sanitization
	 * convert text to UTF8 and check/clean content
	 * @param string $text
	 * @param array controler(type, clean)  type=> words(all letters/numbers/spaces), letters(az-AZ), numbers(0-9), 
	 * @param array length(min, max) default(3,30)
	 * @return array $text(error, text)
	 */
	 
	public function validateText($text, $controler, $length)
	{
		$return['error'] = true;
		
		// make sure we work in UTF8 format		
		if(mb_detect_encoding($text) != 'UTF-8') {
			utf8_encode($text);
		}
				
		// set the matching regular expression
		if($controler[0] === 'words') {
			$match = '/^[\p{L}_\-\s\d]+$/ui'; // letters, numbers, space, underscore , minus (i18n support)
		}
		elseif($controler[0] === 'letters') {
			$match = '/^[a-z]+$/i';  // only letters
		}
		elseif($controler[0] === 'numbers') {
			$match = '/^[0-9]+$/';  // only integrers
		}
		else {
			$match = '/^(.)+$/ui';  // match any caracter / DO NOTHING, make sure to clean content
		}
		
		$min = (int)$length[0];
		$max = (int)$length[1];
				
		$min = (!empty($min) ? $min : 3 );
		$max = (!empty($max) ? $max : 30 );
		
		// some cleanup
		$text = trim((string)$text);
		if($controler[1] === 'clean' && (empty($controler[0]) || $controler[0] === 'all')) {
			//  html chars
			$text = htmlspecialchars($text);
		}
		
		
		echo $text;
		echo '<br>';
		echo preg_match($match,$text); die();
		
        if (mb_strlen($text) < $min) {
            $return['message'] = _("group_name_short");
            
        } elseif (mb_strlen($text) > $max) {
            $return['message'] =  _("group_name_long");
            
        } elseif (!preg_match($match,$text)) {
            $return['message'] =  _("group_name_invalid");
            
        } else {
            $return['error'] = false;
			$return['message'] = "";
        }
		return $return;
	}
}

?>