<? 
/*

Debuggr version 1.7-beta by Tor de Vries (tor.devries@wsu.edu)

Copy this PHP code into the root directory of your server-side coding project so others can study your code.
You must configure the $userName, $userEmail, and $pagePassword variables, at the very least.

Then, use the lower left menu's "Open" command, or add the parameter "?file=" and the name of a file, to view
its source code. For example:
https://yourdomain.com/project/debuggr.php?file=yourfile.php

For more information: 
https://github.com/tordevries/debuggr

-----

Copyright (C) 2020-2022 Tor de Vries (tor.devries@wsu.edu)

This program is free software: you can redistribute it and/or modify it under the terms of the 
GNU General Public License as published by the Free Software Foundation, either version 3 of 
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  
If not, see <http://www.gnu.org/licenses/>.

*/


// ********************************************************************************
// CONFIGURATION -- edit these variables as needed
// ********************************************************************************

// REQUIRED: your name
$userName = "Host"; // default = "Host"

// REQUIRED: your email address
$userEmail = "your@email.com"; // default = "your@email.com"

// REQUIRED: set a password
$pagePassword = "default"; // default = "default"

// if true, requires a password and temporary session authorization to view the file; you really should leave this as true
$passwordRequired = true; 

// if true, redirects HTTP requests to HTTPS
$forceSSL = true;

// if true, restricts access to only files in this same directory as this file, no subdirectories allowed
$accessCurrentDirectoryOnly = false; 

// if true, allows users to enter pathnames to parent directories, using '../', though never in the Files menu
$accessParentDirectories = false; 

// if true, prevents users from reading this PHP file with itself
$preventAccessToThisFile = true;

// if true, Debuggr can attempt to read remote URL source codes; if false, will return nothing on attempts
$allowRemoteFileReading = true; 

// if true, will add links to the Files menu with files in the current directory
// note: if $accessCurrentDirectoryOnly is false, the Files menu will also include local folders and their files/subdirectories
$showFilesMenu = false; 

// true to load Highlight.js for coloring text
$highlightCode = true; 

// true to start in dark mode by default; false to start in lite mode
$startInDarkMode = true; 

// true to start with the line numbers visible
$startWithLinesOn = true;

// set to  true to start with the column markers and numbers visible
$startWithColsOn = true;

// set to true to enable timing logs every time Debuggr fetches a file or URL 
$logTimings = false;

// customize the log path and filename
$logTimingsFilename = "debuggr-timing.txt"; 

// customize the log timestamp format; defaults to month/day/year plus 24-hour hour:minute:second
$logTimingsTimestamp = "m/d/Y H:i:s";


// advanced remote file reading options related to $allowRemoteFileReading and the PHP cURL libraries

// if true, cURL will bypass HTTPS security checks; if false, you must set a security certificate path, below, for cURL to work
$allowCURLtoBypassHTTPS = true; 

// provide the absolute path to your server's security certificates; only applied if $allowCURLtoBypassHTTPS is false
$certificatePathForCURL = '/etc/ssl/certs'; 


// ********************************************************************************
// ********************************************************************************
//
//
// WARNING: CODE BELOW
//
// Ignore everything below this line unless you really know what you're doing.
//
//
// ********************************************************************************
// ********************************************************************************




// ********************************************************************************
// PHP FUNCTIONS
// ********************************************************************************

// version
$debuggrVersion = "1.7-beta";

// start timer
if ($logTimings) {
	$timerStart = hrtime(true);
	$timerTimestamp = date($logTimingsTimestamp, time());
}

// a recursive function that returns a multidimensional array of files/folders in local directory; 
// adapted from user-submitted code on https://www.php.net/manual/en/function.scandir.php
function findAllFiles($dir = '.') { 
	global $preventAccessToThisFile; // check global configuration on access to the current file
	$result = array(); // initialize empty array
	$cdir = scandir($dir); // scan submitted directory
	foreach ($cdir as $key => $value) { // look line by line at directory scan
		if ( !in_array( $value, array(".", "..") ) ) {
			 if ( is_dir($dir . DIRECTORY_SEPARATOR . $value) ) $result[$value] = findAllFiles( $dir . DIRECTORY_SEPARATOR . $value );
			 else if ( !$preventAccessToThisFile || ($preventAccessToThisFile && ($value != basename(__FILE__))) ) $result[] = $value;
		}
	}
	return $result;
}


// a recursive function to build a hierarchical <ul> menu of files from the array produced in findAllFiles();
function buildFileMenu($arr = null, $path = "", $depth = 0) {
	global $accessCurrentDirectoryOnly, $showFilesMenu, $allowRemoteFileReading;
	$result = "<ul>";
	if (!is_null($arr)) {
		foreach($arr as $key => $value) {
			if ( is_numeric($key) ) {
				$result .=	"<li><a onclick='loadFile(\"" . $path . $value . "\")'>" . $value . "</a></li>\n";
			} else if (!$accessCurrentDirectoryOnly) {
				$result .=	"<li class='hasSub'><a>" . $key . "</a>";
				$result .= buildFileMenu($value, ($path . $key . DIRECTORY_SEPARATOR), ($depth+1) );
				$result .= 	"</li>\n";
			}
		}
	}
	if ($depth == 0) $result .= "<li><a class='" . ($showFilesMenu ? "menuLine" : "") . "' href='javascript:checkPulse(true);'>Reload File</a></li>" . 
			"<li><a onclick='tidyCode()'>Reload and Tidy (beta)</a></li>" .
			"<li><a onclick='window.open(baseFile);'>Execute File</a></li>" .
			"<li><a onclick='downloadFile()'>Download File</a></li>" .
			"<li><a onclick='selectCode()'>Select All Text</a></li>" .
			"<li><a onclick='lineJumper()'>Go to Line...</a>" .
			"<li class='menuLine'><a onclick='openFile();'>Open File" . ($allowRemoteFileReading ? "/URL" : "") . "...</a></li>";
	$result .= "</ul>";
	return $result;
}


// create an unordered list for the CSS-based file menu
function fileMenu($dir = '.') {
	global $showFilesMenu;
	if ($showFilesMenu) $list = findAllFiles($dir);
	else $list = null;
	$listHTML = "<ul id='fileNav'><li><span id='fileIcon'>&#9776;</span>&nbsp;" . buildFileMenu($list) . "</li></ul>"; // not file icon &#128196;
	return $listHTML;
}


// check if a filepath is local or remote, then fetch accordingly
function fetchFile($filepath) {
	global $allowRemoteFileReading;
	if (isFileRemote($filepath) && $allowRemoteFileReading) $returnData = fetchRemoteFile($filepath);
	else $returnData = fetchLocalFile($filepath);
	return $returnData;
}


// simple boolean check if passed value is a valid sanitized URL or not
function isFileRemote($url) {
	$url = filter_var($url, FILTER_SANITIZE_URL);
	return filter_var($url, FILTER_VALIDATE_URL);
}


// use the PHP Tidy function to try to clean up code formatting
function tidyCode($input) {
	$tidy = new tidy;
	$tidyConfig = array('indent' => true,
											'indent-spaces' => 5,
											'wrap' => 0,
											'wrap-attributes' => false,
											'vertical-space' => true,
											'preserve-entities' => true,
											'merge-emphasis' => false,
											'merge-divs' => false,
											'merge-spans' => false,
											'join-styles' => false,
											'fix-bad-comments' => false,
											'drop-empty-paras' => false,
											'coerce-endtags' => false,
											'drop-empty-elements' => false,
											'enclose-block-text' => false,
											'output-xhtml' => false);
	$tidy->parseString($input, $tidyConfig, "UTF8");
	$tidy->cleanRepair();
	return $tidy;
}

// check if a local file is valid and return appropriate info
function fetchLocalFile($localFilepath) {
	global $noFile, $fmenu, $preventAccessToThisFile, $imageSuffixes, $audioSuffixes, $videoSuffixes, $logTimings, $logTimingsFilename, $timerStart, $timerTimestamp, $tidyMode;
	
	// check if file does not exist or is blocked
	if ((!file_exists($localFilepath)) || ($preventAccessToThisFile && ($localFilepath == basename(__FILE__)))) {

		// clear all the session variables
		unset( $_SESSION["filename"] );
		unset( $_SESSION["filetime"] );
		unset( $_SESSION["filemenu"] );
		
		// set output to noFile
		$returnOutput = $noFile;

	} else { // the file DOES exist, so...

		// get the path component of the URL, then the file extension on the path
		$localSuffix = pathinfo($localFilepath, PATHINFO_EXTENSION); 
		
		// check if it's an image, audio file, or video file, otherwise read contents
		$isFile = false;
		$isImage = @getimagesize($localFilepath);
		if ($isImage != false) $returnOutput = "<img src='" . $localFilepath . "'>";
		else if (in_array($localSuffix, $audioSuffixes)) $returnOutput = "<audio src='" . $localFilepath . "' controls></audio>";
		else if (in_array($localSuffix, $videoSuffixes)) $returnOutput = "<video src='" . $localFilepath . "' controls></video>";
		else {
			$isFile = true;
			$returnOutput = file_get_contents($localFilepath);
			if ($tidyMode) $returnOutput = tidyCode($returnOutput);
			$returnOutput = htmlspecialchars($returnOutput);
		}

		if (!$returnOutput) $returnOutput = $noFile; // file is empty, output error

		// set session variables used for AJAX checks
		$_SESSION["filename"] = $localFilepath;
		$_SESSION["filetime"] = filemtime($localFilepath);
		$_SESSION["filemenu"] = $fmenu;
	}
	
	if ($logTimings) {
		$timerElapsed = round( ((hrtime(true) - $timerStart)/1e+9), 5);
		if ($isFile) {
			$localDataSize = round( (filesize($localFilepath) / 1024), 2);
			$toLog = "[" . $timerTimestamp . "] " . $timerElapsed . "s to read and return local " . $localDataSize . "kb: " . $localFilepath . "\n";
		} else {
			$toLog = "[" . $timerTimestamp . "] " . $timerElapsed . "s to process local media: " . $localFilepath . "\n";
		}
		error_log($toLog, 3, $logTimingsFilename);
	}
	
	return $returnOutput;
}

// function to read remote URLs via cURL; note that this is bypasses HTTPS confirmation checks and
// is thus inherently insecure; it may be subject to MITM (man in the middle) attacks.
function fetchRemoteFile($remoteURL) {
	global $noFile, $fmenu, $allowCURLtoBypassHTTPS, $certificatePathForCURL, $imageSuffixes, $audioSuffixes, $videoSuffixes, $logTimings, $logTimingsFilename, $timerStart, $timerTimestamp, $tidyMode;

	// set session variables used for AJAX checks
	$_SESSION["filename"] = $remoteURL;
	$_SESSION["filetime"] = 0;
	$_SESSION["filemenu"] = $fmenu;
		
	// get the path component of the URL, then the file extension on the path
	$remotePath = parse_url($remoteURL, PHP_URL_PATH);
	$remoteSuffix = pathinfo($remotePath, PATHINFO_EXTENSION);
	
	// create empty string
	$toLog = "";
	
	// if the extension on the file path of the URL ends in an image format, output an img tag
	if (in_array($remoteSuffix, $imageSuffixes)) $returnOutput = "<img src='" . $remoteURL . "'>";
	else if (in_array($remoteSuffix, $audioSuffixes)) $returnOutput = "<audio src='" . $remoteURL . "' controls></audio>";
	else if (in_array($remoteSuffix, $videoSuffixes)) $returnOutput = "<video src='" . $remoteURL . "' controls></video>";
	else { // not an image, video, or audio file, so try to scrape the remote source
		
		if (function_exists('curl_init')) { // if cURL is available in PHP, use it
			
			// initialized cURL
			$remoteCURL = curl_init();
			
			// set cURL options
			curl_setopt($remoteCURL, CURLOPT_URL, $remoteURL);
			curl_setopt($remoteCURL, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
			curl_setopt($remoteCURL, CURLOPT_ENCODING, '');
			curl_setopt($remoteCURL, CURLOPT_TCP_FASTOPEN, true);
			curl_setopt($remoteCURL, CURLOPT_VERBOSE, false);
			curl_setopt($remoteCURL, CURLOPT_POST, false);
			curl_setopt($remoteCURL, CURLOPT_TIMEOUT, 60);
			curl_setopt($remoteCURL, CURLOPT_CONNECTTIMEOUT, 0);
			curl_setopt($remoteCURL, CURLOPT_HEADER, false);
			curl_setopt($remoteCURL, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($remoteCURL, CURLOPT_RETURNTRANSFER, true);
			
			// set the cURL user agent to match the current browser's user agent
			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			$curlHeader = [$userAgent,
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-us,en;q=0.5',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Keep-Alive: 115',
				'Connection: keep-alive'];
			curl_setopt($remoteCURL, CURLOPT_HTTPHEADER, $curlHeader);
			
			if ($allowCURLtoBypassHTTPS) curl_setopt($remoteCURL, CURLOPT_SSL_VERIFYPEER, false);
			else curl_setopt($remoteCURL, CURLOPT_CAPATH, $certificatePathForCURL);
			$remoteCURLhttp = curl_getinfo($remoteCURL, CURLINFO_HTTP_CODE);
			// error_log("cURL HTTP code: " . $remoteCURLhttp);
			// error_log("cURL error: " . curl_strerror(curl_errno($remoteCURL)));
			
			// execute cURL call and convert to shareable code with htmlspecialchars()
			$returnCURLoutput = curl_exec($remoteCURL);
			if ($tidyMode) $returnCURLoutput = tidyCode($returnCURLoutput);
			$returnOutput = htmlspecialchars($returnCURLoutput);
			
			
			if ($logTimings) {
				$info = curl_getinfo($remoteCURL);
				$curlTime = round( $info['total_time'], 5);
				$timerElapsed = round( ((hrtime(true) - $timerStart)/1e+9), 5);
				$curlTimePercentage = round( (($curlTime / $timerElapsed) * 100), 2);
				$curlDataSize = round( ($info['size_download'] / 1024), 2); 
				$curlDataSpeed = round( ($info['speed_download'] / 1024), 2);
 				$toLog = "[" . $timerTimestamp . "] " . $timerElapsed . "s to fetch and return remote " . $curlDataSize . "kb (cURL: " . $curlTime . "s, " . $curlTimePercentage . "%): " . $remoteURL . "\n";
				error_log($toLog, 3, $logTimingsFilename);
			}
			
		} else {
			// error_log("Debuggr remote URL error: cURL is not enabled.");
			$returnOutput = $noFile;
			
		}
	}
	
	if ( ($logTimings) && ($toLog == "") ) {
		$timerElapsed = round( ((hrtime(true) - $timerStart)/1e+9), 5);
		$toLog = "[" . $timerTimestamp . "] " . $timerElapsed . "s to process remote media: " . $remoteURL . "\n";
		error_log($toLog, 3, $logTimingsFilename);
	}
			
	return $returnOutput;
}


// function to output a simple PNG favicon
function outputFavicon() {
	$image = imagecreatetruecolor(32, 32); 	// create a blank image
	$bkgd = imagecolorallocate($image, 34, 34, 34);	// set the background color
	$textcolor = imagecolorallocate($image, 255, 255, 255); // set the text color
	imagestring($image, 4, 5, 7,  "</>", $textcolor); // add text to the image
	header("Content-Type: image/png"); // output correct header for PNG graphics
	imagepng($image); // output the image as png
}


// ********************************************************************************
// PHP PROCEDURES
// ********************************************************************************

// set $reqMode if one is passed
if (isset($_REQUEST["mode"])) $reqMode = $_REQUEST["mode"];
else $reqMode = "";

// set $tidyMode if one is passed
if (isset($_REQUEST["maketidy"])) {
	$tidyMode = ($_REQUEST["maketidy"] == "true");
	
} else $tidyMode = false;

// output a favicon just to avoid the 404 error in the browser console
if ($reqMode == "favicon") {
	outputFavicon();
	die();
}

// for security, kill the output if the basic defaults have not been changed
if (($userName == "Host") || ($userEmail == "your@email.com") || (($pagePassword == "default") && $passwordRequired) ) die("ERROR: name, email, and password must be configured.");

// for security, kill the output if password is required but blank
if ($passwordRequired && ($pagePassword == "")) die("ERROR: no password set.");

// for security, redirect to HTTPS if it's not HTTPS
if ($forceSSL && (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on")) {
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"], true, 301);
    die();
}

// initalize sessions
session_start();


// if a logout command been passed, clear the session and send back to login form
if (isset($_POST["logout"])) {
	session_unset();
	session_destroy();
	header("Location: " . $_SERVER["REQUEST_URI"], true, 301);
	die();
}


// set a boolean to use to confirm continued authorization
// note: if you change the password, the next reload will force existing sessions to log out
$isStillAuthorized = (!$passwordRequired || (isset($_SESSION["authorized"]) && ($_SESSION["authorized"] == $pagePassword)));

// for security, if the session is not authorized, check password and/or show login form if necessary
if (!$isStillAuthorized) {
	
	if ($reqMode == "pulse") die("0");  // if they're not calling from an authorized session, pulse returns a zero to indicate logout
	
	if ( ($reqMode == "ajax") || ($reqMode == "menu") ) die(); // if they're not calling from an authorized session, ajax and menu return nothing
	
	if (isset($_POST["pwd"]) && ($_POST["pwd"] == $pagePassword)) {
		
		// if a valid password has been passed, authorize the session
		$_SESSION["authorized"] = $pagePassword; 
		
		// refresh back to itself to eliminate the POST resubmit issue
		header("Location: " . $_SERVER["REQUEST_URI"], true, 301);
		die();
		
	} else {
		// needs new authorization, so show the login page


// ********************************************************************************
// HTML PAGE #1: LOG IN
// ********************************************************************************

		?><!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Debuggr: Log In</title>
	<link rel="icon" type="image/png" href="<?= $_SERVER['PHP_SELF']; ?>?mode=favicon" />
	<link href="https://cdn.jsdelivr.net/gh/tordevries/debuggr@main/debuggr-main.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<script>window.onload = function() { document.getElementById("pwd").focus(); };</script>
</head>
<body class="loginPage">
	<div id="pageBox">
		<div>
			<form method="POST">
				<input type="password" id="pwd" name="pwd" placeholder="password" value="" tabindex="0">
				<button type="submit">LOG IN</button>
			</form>
		</div>
	</div>
	<p><a href="https://github.com/tordevries/debuggr">What is Debuggr?</a></p>
</body>
</html>
<?
		die(); // end processing
	}
}

// ********************************************************************************
// PHP PROCEDURES, CONTINUED
// ********************************************************************************

// generate HTML for the Files menu
$fmenu = fileMenu();

// prepare for common image, audio, and video file suffixes; this may or may not be trustworthy
$imageSuffixes = ["png", "jpg", "jpeg", "gif", "svg", "webp", "jfif", "avif", "apng", "pjpeg", "pjp", "ico", "cur", "tif", "tiff", "bmp"];
$audioSuffixes = ["mp3", "wav", "aac"]; // can't tell ogg audio from video by file suffix, so all ogg files will be treated as video
$videoSuffixes = ["mp4", "webm", "ogg"];

// for a quick AJAX pulse check on the file's timestamp using previously-stored session variables
// return 1 for update; 0 for no longer authorized; 2 to update menu; 3 to update file and menu;
// for remote files, always returns an update 
if ($reqMode == "pulse") {
	$updateFile = (isset($_SESSION["filename"]) && isset($_SESSION["filetime"]) && !isFileRemote($_SESSION["filename"]) && (filemtime($_SESSION["filename"]) > $_SESSION["filetime"]));
	$updateMenu = (isset($_SESSION["filemenu"]) && ($_SESSION["filemenu"] != $fmenu ));
	if (isset($_SESSION["filename"]) && isFileRemote($_SESSION["filename"])) {
		if (!$updateMenu) die("1"); // force an update on remote files by returning "1"
		if ($updateMenu) die("3");
	} else if (isset($_SESSION["filename"]) && file_exists($_SESSION["filename"])) {
		if ($updateFile && !$updateMenu) die("1"); // indicate an update by returning "1"
		if ($updateFile && $updateMenu) die("3");
	}
	if ($updateMenu) die("2");
	die(); // if no filename, or no update to the file, die with nothing
}


// for an AJAX reload of only the file menu HTML
if ($reqMode == "menu") {
	$_SESSION["filemenu"] = $fmenu;
	die($fmenu); // die outputting menu HTML
}


// it's not a pulse check, or a menu check, and the user didn't need to authorize, so let's proceed with output

$noFile = "Nothing found."; // default message to output if the file does not exist or is empty

// was a file passed via file= or f= parameters in the URL? otherwise set it to the query string
$fpassed = rawurldecode($_SERVER['QUERY_STRING']);
if ($reqMode == "ajax") $fpassed = str_replace('mode=ajax&', '', $fpassed);
if ($tidyMode) $fpassed = str_replace('maketidy=true&', '', $fpassed);
if (isset($_REQUEST["file"])) $fpassed = str_replace('file=', '', $fpassed);
else if (isset($_REQUEST["f"])) $fpassed = str_replace('f=', '', $fpassed);

if ($accessCurrentDirectoryOnly) $fpassed = basename($fpassed); // if $accessCurrentDirectoryOnly is true, only allow files in current directory
if (!$accessParentDirectories) $fpassed = ltrim( str_replace("..", "", $fpassed), '/'); // if the passed file starts with a slash, remove it, and don't allow ".." directory traversal

if ($fpassed != "") $foutput = fetchFile($fpassed);
else $foutput = "No source indicated.\n\nInclude a source in the URL with ?file=filename, or use the \"Open\" command in the menu below.";

// if the mode is ajax, just return the content without the rest of the HTML, CSS, JS
if ($reqMode == "ajax") die($foutput); 

// if mode is download, force the browser to download a copy of the currently-accessed code
if ($reqMode == "download") {
	$foutput = htmlspecialchars_decode($foutput);
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $_SESSION["filename"] . '"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . strlen($foutput));
	die($foutput);
}

// if we got this far, output the whole page

// ********************************************************************************
// HTML PAGE #2: LOAD COMPLETE HTML AND JAVASCRIPT INTERFACE
// ********************************************************************************

?><!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="Cache-Control" content="no-store" />
	<link rel="icon" type="image/png" href="<?= $_SERVER['PHP_SELF']; ?>?mode=favicon" />
	<title>Debuggr: <?= $fpassed; ?> by <?= $userName; ?></title>
	<? if ($highlightCode) { ?><script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/10.7.2/highlight.min.js" integrity="sha512-s+tOYYcC3Jybgr9mVsdAxsRYlGNq4mlAurOrfNuGMQ/SCofNPu92tjE7YRZCsdEtWL1yGkqk15fU/ark206YTg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><? } ?>
	<script>
		
		// set initial variables from PHP for use with the externalized JavaScript
		urlToSelf = "<?= $_SERVER['PHP_SELF']; ?>";
		baseFile = "<?= $fpassed; ?>";
		basenameFile = "<?= basename(__FILE__); ?>";
		lineNumbersOn = <?= json_encode($startWithLinesOn); ?>;
		colNumbersOn = <?= json_encode($startWithColsOn); ?>;
		darkModeOn = <?= json_encode($startInDarkMode); ?>;
		highlightCodeOn = <?= json_encode($highlightCode); ?>;
		allowRemoteFileOn = <?= json_encode($allowRemoteFileReading); ?>;
		noFileMessage = "<?= $noFile; ?>";
		reloadTimer = false;
		
	</script>
	<script src="https://cdn.jsdelivr.net/gh/tordevries/debuggr@main/debuggr.js"></script>
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/gh/tordevries/debuggr@main/debuggr-main.css" rel="stylesheet">
	<? if ($highlightCode) { ?><link href="https://cdn.jsdelivr.net/gh/tordevries/debuggr@main/debuggr-highlight.css" rel="stylesheet"><? } ?>
	<style></style>
</head>
<body class="isLoading <? if ($startWithLinesOn) { ?>linesOn<? } ?> <? if ($startInDarkMode) { ?>darkMode<? } ?>">
	<div id="nav">
		<?= $fmenu; ?>
		<div id="filenameRef">
			<span onclick="openFile();"><?= $fpassed; ?></span> 
			<a class="uicon" title="Reload file" onclick="checkPulse(true);">&#8635;</a>
			<span id="statusMsg"></span>
		</div>
		<ul id="optionsNav">
			<li><a id="menuIcon">&#9881;</a><ul>
					<li id="optDarkMode"><a onclick="toggleVisualMode()"><span><?= ($startInDarkMode ? "&check;" : "&nbsp;") ?></span> Dark Mode</a></li>
					<li id="optLineNumbers"><a onclick="toggleNums();"><span><?= ($startWithLinesOn ? "&check;" : "&nbsp;") ?></span> Line Numbers</a></li>
					<li id="optColumns"><a onclick="toggleCols();"><span><?= ($startWithColsOn ? "&check;" : "&nbsp;") ?></span> Column Markers</a></li>
					<li id="optReload"><a onclick="toggleReloadTimer();"><span>&nbsp;</span> Auto-load updates (5s)</a></li>
					<li class="menuLine"><a href="mailto:<?= $userEmail; ?>"><span>&nbsp;</span> Email <?= $userName; ?></a></li>
					<? if ($passwordRequired) { ?><li><a onclick="logout()"><span>&nbsp;</span> Log Out</a></li><? } ?>
					<li class="menuLine"><a href="https://github.com/tordevries/debuggr" target="_blank"><span>&nbsp;</span> Debuggr v<?= $debuggrVersion; ?></a></li>
				</ul></li>
		</ul>
	</div>
	<div id="codeNums"><pre></pre></div>
	<div id="codeLines" class="<?= ($startWithColsOn ? "" : "colsOff") ?>">
		<div id="codeCols"></div>
		<pre><?= $foutput; ?></pre>
	</div>
	<div id="loadingOverlay">
		<div id="outerLoading">
			<div id="innerContent">Loading...</div>
			<div id="innerLoading">
				<div id="innerDot"></div>
				<div id="innerCover"></div>
			</div>
		</div>
	</div>
	<? if ($passwordRequired) { ?><form method="POST" id="logoutForm"><input type="hidden" value="1" name="logout" id="logout"></form><? } ?>
</body>
</html>