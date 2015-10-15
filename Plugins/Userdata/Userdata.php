<?php
namespace PHPAuth\Plugins;

/**
 * Userdata plugin for PHPAuth
 */
 
class Userdata
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
	 * Returns user data without sensible data
	 * temporary class in wait of userdata plugin
	 * @param int $userid
	 * @return array()
	 */	 
	public function getData($uid) 
	{
	 	$query = $this->dbh->prepare("SELECT * FROM {$this->config->table_userdata} WHERE uid = ?");
		$query->execute(array($uid));

		if ($query->rowCount() == 0) {
			return false;
		}
		$data = $query->fetch(\PDO::FETCH_ASSOC);

		if (!$data) {
			return false;
		}		
		return $data;
	}
	
	/**
	 * drive request to the right method
	 */
	public function setData($uid, $params = Array())
	{
		if(!$uid || empty($params) || !array($params)) {return false;}
		
		// check if user exists in userdata table. if not create empty entry for this user
		if($this->getData($uid) === false)
		{
			$query = $this->dbh->prepare("INSERT INTO {$this->config->table_userdata} VALUES(uid=?)");
			$query->execute(array($uid));
		}		
		
		$return = $this->update($uid, $params);	
		
		return $return;		
	}
	
	
	/**
	 * update userdata
	 * @param int $uid
	 * @param array $params
	 */
	private function update($uid, $params)
	{
		if (is_array($params)&& count($params) > 0) {
			$customParamsQueryArray = Array();
	
			foreach($params as $paramKey => $paramValue) {
				$customParamsQueryArray[] = array('value' => $paramKey . ' = ?');
			}
	
			$setParams = implode(', ', array_map(function ($entry) {
				return $entry['value'];
			}, $customParamsQueryArray));
		} 
		else 
		{
			 return true; // no parameters, not really an error! do nothing and don't throw an error
		}

		$query = $this->dbh->prepare("UPDATE {$this->config->table_userdata} SET {$setParams} WHERE uid = ?");

		$bindParams = array_values(array_merge($params, array($uid)));

		if(!$query->execute($bindParams)) 
		{			
			return false; 
		}
		return true;
	}
	
	
	/**
	 * delete userdata line
	 * @param int $uid
	 * @return bool
	 */
	public function deleteData($uid)
	{
		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_userdata} WHERE uid = ?");
		$query->execute(array($uid));
		return true;
	}
}

?>