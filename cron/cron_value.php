#!/usr/bin/php
<?php
/********************************************
* Cron script for value fetcher by Beansman
* Made for the www.eve-id.net killboard.
* Previous mod version available at
* http://svn.nsbit.dk/itemfetch
*
* Read though the script and change variables
* as needed.
*
* Made from liqs feed cron script ;)
*
********************************************/

@set_time_limit(0);

if (!substr_compare(PHP_OS, 'win', 0, 3, true))
{
	@ini_set('include_path', ini_get('include_path').';.\\common\\includes');
}
else
{
	@ini_set('include_path', ini_get('include_path').':./common/includes');
}

// Has to be run from the KB main directory for nested includes to work
if(file_exists(getcwd().'/cron_value.php'))
{
	// current working directory minus last 5 letters of string ("/cron")
	$KB_HOME = preg_replace('/[\/\\\\]cron$/', '', getcwd());
}
elseif(file_exists(__FILE__))
{
	$KB_HOME = preg_replace('/[\/\\\\]cron[\/\\\\]cron_value\.php$/', '', __FILE__);
}
else die("Set \$KB_HOME to the killboard root in cron/cron_value.php.");

// If the above doesn't work - place your working directory path to killboard root below - comment out the above two lines and uncomment the two below

// Edit the path below with your webspace directory to the killboard root folder - also check your php folder is correct as defined by the first line of this file
//$KB_HOME = "/home/yoursite/public_html/kb";

chdir($KB_HOME);

require_once('kbconfig.php');
require_once('common/includes/globals.php');
require_once('common/includes/class.config.php');
require_once('common/includes/db.php');
require_once('common/includes/class.valuefetcher.php');
//require_once('class.cachehandler.php');

$url = config::get('fetchurl');
if ($url == null || $url == "")
	$url = "http://eve.no-ip.de/prices/30d/prices-all.xml";

$fetch = new valueFetcher($url);

// Fetch
$count = $fetch->fetch_values();
// Ship values (Default)
$fetch->updateShips();

// Echo result
echo $count." Items updated\n";
