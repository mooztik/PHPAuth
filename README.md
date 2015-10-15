[![Build Status](https://travis-ci.org/PHPAuth/PHPAuth.png)](https://travis-ci.org/Mooztik/PHPAuth)
PHPAuth Plugin project
=======

What is it
---------------

PHPAuth is a secure user authentication class for PHP websites, using a powerful password hashing system and attack blocking to keep your website and users secure.
the plugin project let add some functionalities without the need for the coders to alter PHPAuth original code.

Features
---------------
* A logic architecture for plugin support
* an easy way to boost default features (like using another captcha or another group system)
* set to easily activate or replace existing plugins without the need to rewrite/alter PHPAuth class or default plugins
* activate a plugin class just need a setting in config table


Requirements
---------------
* PHP 5.4
* MySQL / MariaDB database
* SMTP server / sendmail
* PHP Mcrypt
* PHPAuth


Configuration
---------------

The database table `config` need to contain some informations about plugins

* `plugin_userdata`   : the name of the userdata plugin (to add more than just login informations)
* `table_userdata`    : the name of the table used to store userdata informations (not the login user table from PHPAuth)
* `plugin_captcha`  : name of the captcha plugin used 

in a short while new plugins will be added.
if theses entries are not set or empty, PHPAuth will ignore it and do it's work.

as an exemple, you can watch the `Userdata` plugin who adds some user informations. 
the captcha plugin is not set by default but an entrie exists in the `config` table


How to secure a page
---------------

Making a page accessible only to authenticated users is quick and easy, requiring only a few lines of code at the top of the page:

```php
<?php

include("languages/en_GB.php");
include("Config.php");
include("Auth.php");
include("Plugins.php");

$dbh = new PDO("mysql:host=localhost;dbname=phpauth", "username", "password");

$config = new PHPAuth\Config($dbh);
$auth   = new PHPAuth\Plugins($dbh, $config, $lang);

if (!$auth->isLogged()) {
    header('HTTP/1.0 403 Forbidden');
    echo "Forbidden";

    exit();
}

?>
```

Contributing
---------------

Anyone can contribute to improve or fix PHPAuth, to do so you can either report an issue (a bug, an idea...) or fork the repository, perform modifications to your fork then request a merge.

Credits
---------------

* [password_compat](https://github.com/ircmaxell/password_compat) - @ircmaxell
* [disposable](https://github.com/lavab/disposable) - @lavab
* [PHPMailer](https://github.com/PHPMailer/PHPMailer) - @PHPMailer
* [PHPAuth](https://github.com/PHPAuth/PHPAuth) - @PHPAuth
