<?php
//Instagram Credentials
define('IG_USERNAME', 'USERNAME');
define('IG_PASS', 'PASSWORD');

/*
 * Settings below this line are optional!
 */

//General Settings
define("UPDATE_AUTO", false); //Change false to true if you want the script to automatically update itself without having to run the update.php script

//OBS Settings
define('OBS_BITRATE', '4000');

define('OBS_CUSTOM_PATH', 'INSERT_PATH'); //**OPTIONAL** Specify a custom path for the script to search for an obs executable
define('OBS_EXEC_NAME', 'obs64.exe'); //Recommend you don't touch this unless you modify the custom path & know what you're doing

define('OBS_X', '720'); //You shouldn't touch this
define('OBS_Y', '1280'); //You shouldn't touch this


//Config Metadata
define('configVersionCode', '5'); //You shouldn't touch this