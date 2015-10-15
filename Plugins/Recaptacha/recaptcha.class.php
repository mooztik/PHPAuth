<?php
namespace PHPAuth\Plugins;

/*
 * captcha class from google
 * don't forget to set your secret code
 * 
 */
 
class Recaptcha
{
	private $secret;	
	 
	public function __construct(\PDO $dbh, $config, $lang)
	{
		$this->secret = $config->plugin_captcha_secret;
	}
	 
	/**
	 * basic function for captcha check
	 * 
	 */
	public function checkRecaptcha($captcha)
	{
		try {
	
	        $url = 'https://www.google.com/recaptcha/api/siteverify';
	        $data = ['secret'   => $this->secret,
	            'response' => $captcha,
	            'remoteip' => $_SERVER['REMOTE_ADDR']];
	
	        $options = [
	            'http' => [
	                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
	                'method'  => 'POST',
	                'content' => http_build_query($data)
	            ]
	        ];
	
	        $context  = stream_context_create($options);
	        $result = file_get_contents($url, false, $context);
	        return json_decode($result)->success;
	    }
	    catch (\Exception $e) {
	        return false;
	    }
	}
}
?>