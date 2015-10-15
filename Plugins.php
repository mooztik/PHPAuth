<?php
namespace PHPAuth;
/*
* Auth plugin class 
* Works with PHP 5.4 and above.
* replace some phpAuth functions for initialisation
 * phpAuth plugin class is not part of official phpAuth developpement(not yet ?)
*/

/*
 * this extension let PHPAuth to be extented with more features without altering original code
 * this class is the internal engine for loading plugins (bootstrap)
 * it does only that, and provide a unique plateform for any extension
 * it loads listed plugins from plugins folder, 
 * each plugin need to be placed in it's own folder in  'plugins/__PLUGIN_NAME__/'. class file need to be named  '__PLUGIN_NAME__.class.php'
 * each plugin need to be provided with an 'info.txt' who contains informations about this plugin (probably JSON in a near future)
 * for devs -> use the userdata plugin to understand how it work 
 */


class Plugins extends Auth
{
 		
	private $dbh;
    public $config;	
	public $lang;
	public $about;
	public $plugin;
	
   /**
    * replace phpauth constructor
    * link to parent class
    * load default plugins
    */   
	public function __construct(\PDO $dbh, $config, $lang)
    {
    	parent::__construct($dbh, $config, $lang);
		$this->config = $config;
        $this->dbh = $dbh;
		$this->lang = $lang;		
    }
	
	
	
	/**
	 * load plugin when needed based on plugins listed in config table
	 * $pluginName = name of the plugin = name of the plugin folder = name of the class file
	 * ex : plugin = Recaptcha , folder = Plugins/Recaptcha/Recaptcha.php
	 * 
	 * how phpauth access theses methods ?
	 * 	-in  'about.txt',  found inside plugin folder is stored basic access methods
	 * 		get= ,set= ,delete=,check=  where corresponding methods are defined
	 *  for this exemple, check= checkRecaptcha  <- checkRecaptcha is the name of the method who return a simple boolean check
	 * see userdata exemple
	 * 
	 * @param string $pluginName : name of the plugin
	 */
	private function pluginLoad($pluginName)
	{
		$nameSpace = 'PHPAuth\Plugins\\'. $pluginName;
		if(class_exists($nameSpace))
		{
			return true;
		}
			
		$directory = __DIR__.'/plugins';
		
		if(is_dir($directory.'/'.$pluginName)) 
		{
			if(file_exists($directory.'/'.$pluginName.'/'.$pluginName.'.php')) {
				include_once($directory.'/'.$pluginName.'/'.$pluginName.'.php');
				
				$this->$pluginName = new $nameSpace($this->dbh, $this->config, $this->lang);			
				$this->plugin[$pluginName] = $this->$pluginName->about = $this->pluginAbout($pluginName, $directory);
				return true;
			}
		}
	} 
	
	/**
	 * load read and store plugin specific config
	 * @param string $dir : plugin folder
	 * @param string $directory : plugins main directory
	 */
	private function pluginAbout($dir, $directory)
	{
		//get info from each plugin
		$file = $directory . '/'.$dir.'/about.txt';
		$handle = fopen($file, "r");
		$raw = fread($handle, filesize($file));
		fclose($handle);
		
		$lines = explode("\n", $raw);
		foreach($lines as $line)
		{
			$tmp = explode('=', $line);
			$data[$tmp[0]] = $tmp[1];				
		}
		return $data;
	}
	
	

	
	
	
	
	
	/**
	* temporary overwrite PHPAuth methods for testing purpose (to not alter phpauth original code)
	*
	* Get public user data for a given UID and returns an array, password is not returned
	* @param int $uid
	* @return array $data
	*/

	public function getUser($uid)
	{
		$query = $this->dbh->prepare("SELECT * FROM {$this->config->table_users} WHERE id = ?");
		$query->execute(array($uid));

		if ($query->rowCount() == 0) {
			return false;
		}

		$data = $query->fetch(\PDO::FETCH_ASSOC);

		if (!$data) {
			return false;
		}

		$data['uid'] = $uid;
		unset($data['password']);
		
		$func = $this->config->plugin_userdata;
        if(!empty($func) ) {        	
        	if($this->pluginLoad($func) === TRUE)
			{
				$do = trim($this->plugin[$func]['get']);				
				$return = $this->$func->$do($uid);			
				if($return != FALSE && is_array($return)) {
					$data = array_merge($data, $return);
				}
			}	
        }
		
		return $data;
	}
	
	
	public function changeUserdata($uid, $params)
	{
		// check if an extended userdata plugin exists
		$func = $this->config->plugin_userdata;
        if(!empty($func) ) 
        {        	
        	if($this->pluginLoad($func) === TRUE)
			{
				$do = trim($this->plugin[$func]['set']);				
				$return = $this->$func->$do($uid, $params);			
				if($return != FALSE && is_array($return)) {
					$data = array_merge($data, $return);
				}
			}	
        }
		else 
		{
			return true;  // no plugin used, return true
		}
	}
	
		/**
	* Allows a user to delete their account
	* @param int $uid
	* @param string $password
    * @param string $captcha = NULL
	* @return array $return
	*/

	public function deleteUser($uid, $password, $captcha = NULL)
	{
		$return['error'] = true;

        $block_status = $this->isBlocked();
        if($block_status == "verify")
        {
            if($this->checkCaptcha($captcha) == false)
            {
                $return['message'] = $this->lang["user_verify_failed"];
                return $return;
            }
        }
        if ($block_status == "block") {
            $return['message'] = $this->lang["user_blocked"];
            return $return;
        }

		$validatePassword = $this->validatePassword($password);

		if($validatePassword['error'] == 1) {
			$this->addAttempt();

			$return['message'] = $validatePassword['message'];
			return $return;
		}

		$user = $this->getBaseUser($uid);

		if(!password_verify($password, $user['password'])) {
			$this->addAttempt();

			$return['message'] = $this->lang["password_incorrect"];
			return $return;
		}

		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_users} WHERE id = ?");

		if(!$query->execute(array($uid))) {
			$return['message'] = $this->lang["system_error"] . " #05";
			return $return;
		}

		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_sessions} WHERE uid = ?");

		if(!$query->execute(array($uid))) {
			$return['message'] = $this->lang["system_error"] . " #06";
			return $return;
		}

		$query = $this->dbh->prepare("DELETE FROM {$this->config->table_requests} WHERE uid = ?");

		if(!$query->execute(array($uid))) {
			$return['message'] = $this->lang["system_error"] . " #07";
			return $return;
		}

		$func = $this->config->plugin_userdata;
        if(!empty($func) ) 
        {        	
        	if($this->pluginLoad($func) === TRUE)
			{
				$do = trim($this->plugin[$func]['delete']);				
				$return = $this->$func->$do($uid);			
				if($return == FALSE ) {
					$return['message'] = $this->lang["system_error"] . " #08";
				}
			}	
        }

		$return['error'] = false;
		$return['message'] = $this->lang["account_deleted"];

		return $return;
	}
	 
 }
