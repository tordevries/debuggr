<? 
/*

Debuggr version 1.5.7.4-beta by Tor de Vries (tor.devries@wsu.edu)

Copy this PHP code into the root directory of your server-side coding project so others can study your code.
You must configure the $userName, $userEmail, and $pagePassword variables, at the very least.

Then, use the lower left menu's "Open" command, or add the parameter "?file=" and the name of a file, to view
its source code. For example:
https://yourdomain.com/project/debuggr.php?file=yourfile.php

For more information: 
https://github.com/tordevries/debuggr

-----

Copyright (C) 2020-2021 Tor de Vries (tor.devries@wsu.edu)

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
$userName = "Host";

// REQUIRED: your email address
$userEmail = "your@email.com";

// REQUIRED: set a password
$pagePassword = "default";

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

// true to start with the column markers and numbers visible
$startWithColsOn = true;

// true to include a link to Debuggr on Github in the options menu
$showDebuggrLink = true; 

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
	$listHTML = "<ul id='fileNav'><li>&#9776;&nbsp;" . buildFileMenu($list) . "</li></ul>"; // not file icon &#128196;
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


// check if a local file is valid and return appropriate info
function fetchLocalFile($localFilepath) {
	global $noFile, $fmenu, $preventAccessToThisFile, $imageSuffixes, $audioSuffixes, $videoSuffixes;
	
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
		$isImage = @getimagesize($localFilepath);
		if ($isImage != false) $returnOutput = "<img src='" . $localFilepath . "'>";
		else if (in_array($localSuffix, $audioSuffixes)) $returnOutput = "<audio src='" . $localFilepath . "' controls></audio>";
		else if (in_array($localSuffix, $videoSuffixes)) $returnOutput = "<video src='" . $localFilepath . "' controls></video>";
		else $returnOutput = htmlspecialchars(file_get_contents($localFilepath));

		if (!$returnOutput) $returnOutput = $noFile; // file is empty, output error

		// set session variables used for AJAX checks
		$_SESSION["filename"] = $localFilepath;
		$_SESSION["filetime"] = filemtime($localFilepath);
		$_SESSION["filemenu"] = $fmenu;
	}
	
	return $returnOutput;
}

// function to read remote URLs via cURL; note that this is bypasses HTTPS confirmation checks and
// is thus inherently insecure; it may be subject to MITM (man in the middle) attacks.
function fetchRemoteFile($remoteURL) {
	global $noFile, $fmenu, $allowCURLtoBypassHTTPS, $certificatePathForCURL, $imageSuffixes, $audioSuffixes, $videoSuffixes;

	// set session variables used for AJAX checks
	$_SESSION["filename"] = $remoteURL;
	$_SESSION["filetime"] = 0;
	$_SESSION["filemenu"] = $fmenu;
		
	// get the path component of the URL, then the file extension on the path
	$remotePath = parse_url($remoteURL, PHP_URL_PATH);
	$remoteSuffix = pathinfo($remotePath, PATHINFO_EXTENSION); 
	
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
			$returnOutput = htmlspecialchars( curl_exec($remoteCURL) );
			
		} else {
			// error_log("Debuggr remote URL error: cURL is not enabled.");
			$returnOutput = $noFile;
			
		}
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
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<? if ($highlightCode) { ?><script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/10.4.0/highlight.min.js" defer></script><? } ?>
	<script>window.onload = function() { document.getElementById("pwd").focus(); };</script>
	<style>

		* {
			font-family: 'Source Code Pro', monospace;
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		#pageBox {
			height: 88vh;
			width: 100vw;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		input, button { padding: 4px; }
		
		p { text-align: center; }
		
	</style>
</head>
<body>
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

// version
$debuggrVersion = "1.5.7.4-beta";

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
if (isset($_REQUEST["file"])) $fpassed = str_replace('file=', '', $fpassed);
else if (isset($_REQUEST["f"])) $fpassed = str_replace('f=', '', $fpassed);

if ($accessCurrentDirectoryOnly) $fpassed = basename($fpassed); // if $accessCurrentDirectoryOnly is true, only allow files in current directory
if (!$accessParentDirectories) $fpassed = ltrim( str_replace("..", "", $fpassed), '/'); // if the passed file starts with a slash, remove it, and don't allow ".." directory traversal

if ($fpassed != "") $foutput = fetchFile($fpassed);
else $foutput = "No source indicated.\n\nInclude a source in the URL with ?file=filename, or use the \"Open\" command in the menu below.";

// if the mode is ajax, just return the content without the rest of the HTML, CSS, JS
if ($reqMode == "ajax") die($foutput); 

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
// HTML PAGE #2: LOAD COMPLETE INTERFACE
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
		
		// set some variables (including some passed from PHP
		baseFile = "<?= $fpassed; ?>";
		lineNumbersOn = <?= json_encode($startWithLinesOn); ?>;
		colNumbersOn = <?= json_encode($startWithColsOn); ?>;
		darkModeOn = <?= json_encode($startInDarkMode); ?>;
		reloadTimer = false;
		
		// shortcut for updating the status text
		function statusMessage(msg) {
			document.getElementById("statusMsg").innerHTML = msg;
		}
		
		// toggle the row numbers using CSS
		function toggleNums() {
			closeMenus();
			if (lineNumbersOn) {
				document.body.classList.remove('linesOn');
				document.querySelector("#optLineNumbers span").innerHTML = "&nbsp;";
			} else {
				document.body.classList.add('linesOn');
				document.querySelector("#optLineNumbers span").innerHTML = "&check;";
			}
			lineNumbersOn = !lineNumbersOn;
		}

		// toggle the column numbers using CSS
		function toggleCols() {
			closeMenus();
			if (colNumbersOn) {
				document.getElementById("codeLines").classList.add("colsOff");
				document.querySelector("#optColumns span").innerHTML = "&nbsp;";
			} else {
				document.getElementById("codeLines").classList.remove("colsOff");
				document.querySelector("#optColumns span").innerHTML = "&check;";
			}
			colNumbersOn = !colNumbersOn;
		}
		
		// toggle visual dark/lite mode
		function toggleVisualMode() {
			closeMenus();
			if (darkModeOn) {
				document.body.classList.remove("darkMode");
				document.querySelector("#optDarkMode span").innerHTML = "&nbsp;";
			} else {
				document.body.classList.add("darkMode");
				document.querySelector("#optDarkMode span").innerHTML = "&check;";
			}
			darkModeOn = !darkModeOn;
		}
		
		// toggle the auto-load pulse check every 5 seconds
		function toggleReloadTimer() {
			closeMenus();
			if (!reloadTimer) {
				reloadPulse = setInterval(checkPulse, 5000);
				document.querySelector("#optReload span").innerHTML = "&check;";
				checkPulse();
			} else {
				clearInterval(reloadPulse);
				document.querySelector("#optReload span").innerHTML = "&nbsp;";
			}
			reloadTimer = !reloadTimer;
		}

		// select the code text 
		function selectCode() {
			var r = document.createRange();
			var w = document.querySelector("#codeLines pre");  
			r.selectNodeContents(w);  
			var sel = window.getSelection(); 
			sel.removeAllRanges(); 
			sel.addRange(r);
		}
		
		// update the #fileNav UL-based menu to open and close with clicks
		function setMenuClicks() {
			allLI = document.querySelectorAll("#fileNav li");
			for (x=0; x<allLI.length; x++) {
				allLI[x].onclick = function(event) {
					document.querySelector('#optionsNav li').classList.remove('showSub');
					this.classList.toggle("showSub");
					event.stopPropagation();
				}
			}
		}
		
		// use AJAX to check pulse on the file; has it been updated since last load?
		function checkPulse(toCloseMenus) {
			if (toCloseMenus) closeMenus();
			statusMessage("&bull;");
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200)) {
						switch (this.responseText) {
							case "0": logout(); break;
							case "1": loadFile(); break;
							case "2": loadMenu(); break;
							case "3": loadFile(); loadMenu(); break;
							default: statusMessage("");								
						}
					} else if ((this.readyState == 4) && (this.status != 200)) {
						console.log("AJAX pulse error: " + this.responseText);
						statusMessage("!");
					}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?mode=pulse";
			ajax.open("GET", urlToLoad, true);
			ajax.send();
		}
		
		function loadMenu() {
			statusMessage("&#8857;");
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200)) {
						document.getElementById("fileNav").outerHTML = this.responseText;
						setMenuClicks();
						statusMessage("");
					} else if ((this.readyState == 4) && (this.status != 200)) {
						console.log("AJAX menu error: " + this.responseText);
						statusMessage("!");
					}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?mode=menu";
			ajax.open("POST", urlToLoad, true);
			ajax.send();
		}
		
		// use AJAX to reload the file or to load files from the Files menu (if enabled)
		function loadFile(fileToLoad = baseFile, historyUpdate = true) {
			document.body.classList.add("isLoading");
			closeMenus();
			codeLinesPre = document.querySelector("#codeLines pre");
			statusMessage("&#8857;");
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
				if ((this.readyState == 4) && (this.status == 200)) {
					if (fileToLoad != baseFile) {
						document.getElementById("codeNums").scrollTop = 0;
						document.getElementById("codeLines").scrollTop = 0;
					}
					baseFile = fileToLoad;
					codeLinesPre.innerHTML = this.responseText;
					styleCode();
					prepCodeNumbers();
					document.title = "Debuggr: " + fileToLoad;
					document.querySelector("#filenameRef span").innerHTML = fileToLoad;
					if (historyUpdate) {
						historyURL = "<?= $_SERVER['PHP_SELF']; ?>?file=" + fileToLoad;
						window.history.pushState( {}, "", historyURL);
					}
					statusMessage("");
					document.body.classList.remove("isLoading");
				} else if ((this.readyState == 4) && (this.status != 200)) {
					codeLinesPre.innerHTML = "<?= $noFile; ?>";
					console.log("Error: " + this.responseText);
					document.body.classList.remove("isLoading");
					statusMessage("!");
				}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?mode=ajax&file=" + encodeURIComponent(fileToLoad);
			ajax.open("POST", urlToLoad, true);
			ajax.send();
		}

		// output line and column numbers in #codeNums pre, padding numbers with 0s to appropriate width; calculate and add column numbers
		function prepCodeNumbers() {
			document.getElementById("codeCols").innerHTML = ""; // clear column numbers
			codeNumsPre = document.querySelector("#codeNums pre");
			codeNumsPre.innerHTML = ""; // clear row numbers
			numLines = document.querySelector("#codeLines pre").innerHTML.split("\n"); // extract lines into an array
			maxWidth = 1; // set column character width low
			padTo = numLines.length.toString().length + 1; // number of zeros to add to front of line numbers
			
			// update the CSS with new widths
			document.querySelector("style").innerHTML += "body.linesOn #codeNums { width: " + (padTo-1) + "rem; } body.linesOn #codeLines { width: calc(100vw - " + (padTo - 0.5) + "rem); left: " + (padTo - 0.5) + "rem; }";
			
			// output line numbers and check line widths for column output
			for (x=1; x<=numLines.length; x++) {
				codeNumsPre.innerHTML += (x + ":").padStart(padTo, "0") + "\n";
				if (x<numLines.length) if (numLines[x].length > maxWidth) maxWidth = numLines[x].length;
			}
			
			// output columns based on maxWidth analysis
			for (x=1; x<((maxWidth/10)+1); x++) document.getElementById("codeCols").innerHTML += "<span>" + (x * 10) + "</span>";
			return true;
		}
		
		// download a file
		function downloadFile() {
			urlToDownload = "<?= $_SERVER['PHP_SELF']; ?>?mode=download&file=" + baseFile;
			window.location.href = urlToDownload;
		}
		
		// go to a line
		function lineJumper() {
			closeMenus();
			toJump = window.prompt("Go to line number:", "");
			if (!isNaN(toJump)) {
				jumpLine = (toJump-1) * 20.7; // based on CSS of font size and line height
				document.getElementById("codeNums").scrollTop = jumpLine;
				document.getElementById("codeLines").scrollTop = jumpLine;
			}
		}
		
		// open a file or URL
		function openFile() {
			closeMenus();
			toOpen = window.prompt("Enter a filename<?= ($allowRemoteFileReading ? " or complete URL" : ""); ?>:", baseFile);
			if ((toOpen != "") && (toOpen !== null)) {
				document.body.classList.add("isLoading");
				loadFile(toOpen);
			}
		}

		// close all the menus
		function closeMenus() {
			allLI = document.querySelectorAll("#fileNav li");
			for (x=0; x<allLI.length; x++) allLI[x].classList.remove("showSub");
			document.querySelector("#optionsNav li").classList.remove("showSub");
		}
		
		// apply Highlights.js and Anchorme.js, if configured in PHP
		function styleCode() {
			<? if ($highlightCode) { ?>
			toStyle = document.querySelector('#codeLines pre');
			toStyle.className = ""; // erase pre-existing highlight.js classes applied here
			hljs.highlightElement(toStyle); // run highlight.js on the code block
			
			// prep hyperlinking
			pageTags = document.querySelectorAll(".hljs-string");
			const noProtocol = new RegExp('^.*.(htm|html|js|css|jpg|png|gif|php)$');
			const someProtocol = new RegExp('^(http|https).*$');
			for (const tag of pageTags) {
				sTag = tag.innerHTML.substr(1, (tag.innerHTML.length - 2) );
				if (someProtocol.test(sTag)) {
					tag.innerHTML = tag.innerHTML.substr(0,1) + '<a href="<?= $_SERVER['PHP_SELF']; ?>?file=' + sTag + '">' + sTag + '</a>' + tag.innerHTML.substr(-1);
				} else if (noProtocol.test(sTag)) {
					sPassed = "<?= $fpassed; ?>";
					sTagPre = "";
					if ( sTag.startsWith("//") ) {
						sTagPreTest = "<?= $fpassed ?>";
						if ( sTagPreTest.startsWith("http://") ) sTagPre = "http:";
						else sTagPre = "https:";
					} else if ( someProtocol.test(sPassed) ) {
						basePassed = "<?= basename($fpassed); ?>"; 
						if ( sPassed.endsWith("/") ) {
							sTagPre = sPassed;
						} else if (basePassed != "") {
							sTagPre = sPassed.substr(0, (sPassed.length - basePassed.length) );
						}
					}
					tag.innerHTML = tag.innerHTML.substr(0,1) + '<a href="<?= $_SERVER['PHP_SELF']; ?>?file=' + sTagPre + sTag + '">' + sTag + '</a>' + tag.innerHTML.substr(-1);
				}
			}
			<? } ?>
		}
		
		// submit the hidden logout form
		function logout() { document.getElementById("logoutForm").submit(); }
		
		// when the window loads, prep line numbers, and connect the scrollTops of #codeLines to #codeNums
		window.onload = function() {
			
			// output line and column numbers
			prepCodeNumbers();

			// reposition #codeNums as the user scrolls
			document.getElementById("codeLines").onscroll = function() { 
				document.getElementById("codeNums").scrollTop = document.getElementById("codeLines").scrollTop; 
				document.getElementById("codeCols").style.top = document.getElementById("codeLines").scrollTop + "px"; 
			}
			
			// reposition #codeCols as the user scrolls
			document.querySelector("#codeLines pre").onscroll = function() {
				document.getElementById("codeCols").style.left = (0 - document.querySelector("#codeLines pre").scrollLeft) + "px"; 
			}			
			
			// set clicks for the file menu
			setMenuClicks();
			
			// set clicks for the options menu
			document.querySelector('#optionsNav li').onclick = function() { 
				closeMenus();
				document.querySelector('#optionsNav li').classList.toggle('showSub');
			}
			
			// close menus when someone clicks on the main code
			document.getElementById("codeLines").onclick = function() { closeMenus(); }
			document.getElementById("codeNums").onclick = function() { closeMenus(); }

			// apply highlight.js
			styleCode();
			
			// since the URL is changed dynamically, we need to dynamically respond to back buttons
			window.onpopstate = function(event) {
				historyParam = document.location.href.split("<?= basename(__FILE__); ?>?file=").pop(); // get the file value passed to debuggr
				loadFile(historyParam, false);
			};
			
			// all done loading? remove the isLoading CSS class
			document.body.classList.remove("isLoading");
		}		
		
	</script>
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<style>
		
		* {
			font-family: 'Source Code Pro', monospace;
			font-size: 14px;
			tab-size: 3;
			margin: 0;
			padding: 0;
			box-sizing: border-box;
			overflow: auto;
		}
		
		body {
			background-color: #fff;
			color: #000;
		}
		
		body.darkMode {
			background-color: #222;
			color: #fff;
		}
		
		#nav {
			position: fixed;
			background-color: #555;
			color: #ddd;
			bottom: 0;
			left: 0;
			width: 100vw;
			height: 44px;
			padding: 10px;
			z-index: 100;
			overflow: visible;
			user-select: none;
		}
		
		#nav span {
			margin-right: 10px;
			float: left;
			max-width: 60vw;
		}

		#nav span a {
			color: #ddd;
			text-decoration: none;
		}
		
		#filenameRef {
			display: inline-block;
			cursor: pointer;
		}
		
		#nav span#statusMsg {
			margin-left: 10px;
			color: #aaa;
			float: right;
			max-width: 2ch;
			color: #c8ff00;
		}
		
		a.uicon  {
			font-weight: normal;
			color: #ddd;
			text-decoration: none;
		}
		
		#codeNums {
			position: fixed;
			top: 0;
			left: -4rem;
			width: 3.75rem;
			height: calc(100vh - 44px);
			font-weight: 300;
			overflow: hidden;
			text-align: right;
			color: #007680;
			transition: left 0.3s, width 0.3s;
			z-index: 1;
			user-select: none;
		}
		
		body.linesOn #codeNums {
			left: 0rem;
		}
		
		body.darkMode #codeNums {
			color: #a9e3e8;
		}
		
		#codeLines {
			position: absolute;
			top: 0;
			left: 0.25rem;
			height: calc(100vh - 44px);
			width: calc(100vw - 0.25rem);
			overflow: scroll;
			transition: left 0.5s, width 0.5s;
			z-index: 2;
		}
		
		#codeLines pre {
			overflow: display;
			background-size: 10ch 10ch;
			background-image: linear-gradient(to right, #eee 1px, transparent 1px);
			background-attachment: local;
			padding-right: 10ch;
			z-index: 2;
		}
		
		body.darkMode #codeLines pre {
			background-image: linear-gradient(to right, #333 1px, transparent 1px);
		}
		
		#codeNums pre, 
		#codeLines pre {
			padding-top: 0.5em;
			padding-bottom: 80px; /* above the nav bar */
		}
		
		body.isLoading #codeNums,
		body.isLoading #codeLines {
			filter: blur(1px);
		}
		
		#codeCols {
			position: absolute;
			top: -4px;
			left: 0;
			width: auto;
			height: 1.5em;
			overflow: auto;
			z-index: 1;
			user-select: none;
		}
		
		#codeCols span {
			width: 10ch;
			display: inline-block;
			white-space: nowrap;
			text-align: right;
			color: #ccc;
			padding-right: 4px;
		}
		
		#codeLines.colsOff #codeCols {
			display: none;
		}
		
		#codeLines.colsOff pre {
			background: none;
		}
		
		body.darkMode #codeCols span {
			color: #555;
		}
		
		body.linesOn #codeLines {
			left: 4.25rem;
			width: calc(100vw - 4.25rem);
		}

		#codeLines img {
			position: absolute;
			top: 0;
			left: 0;
		}
		
		#codeNums, #codeLines {
			line-height: 21px;
		}
		
		input, button {
			padding: 4px;
			text-transform: uppercase;
		}
		
		#optionsNav {
			float: right;
			overflow: visible;
			text-align: right;
			margin-top: -5px;
		}
		
		#optionsNav li a {
			display: block;
			text-decoration: none;
			color: #fff;
		}
		
		#optionsNav li ul li a {
			color: #000;
			padding: 0.2rem 0.7rem 0.2rem 0.5rem;
			text-align: left;
		}
		
		#optionsNav li ul li a:hover {
			background-color: #bbb;
		}
		
		#optionsNav ul {
			position: absolute;
			display: none;
			list-style-type: none;
			margin: 0;
			padding: 0;
			background-color: #fff;
			border: 1px solid #ccc;
			z-index: 100;
			overflow: visible;
		}
		
		#optionsNav li.showSub ul {
			display: block;
			bottom: 44px;
			right: 0;
		}
		
		#optionsNav li span {
			color: green;
			font-weight: bold;
			font-size: 90%;
			margin-top: -2px;
		}
		
		a#menuIcon {
			font-family: Arial, sans-serif;
			text-align: right;
			font-size: 14px;
			padding: 7px 0 0 0;
		}
		
		.menuLine {
			padding-top: 4px;
			margin-top: 4px;
			border-top: 1px solid #ccc;
		}
		
		#fileNav {
			float: left;
			z-index: 100;
			padding-right: 1rem;
			overflow: visible;
			list-style-type: none;
		}

		#fileNav a {
			display: block;
			padding: 0.2rem 2rem 0.2rem 0.5rem;
			text-decoration: none;
			color: #000000;
		}

		#fileNav a:hover {
			background-color: #bbb;
		}

		#fileNav ul {
			position: absolute;
			margin: 0;
			padding: 0;
			left: 0;
			background-color: #fff;
			border: 1px solid #ccc;
			z-index: 100;
			overflow: visible;
		}
		
		#fileNav ul ul {
			border-left: 1px solid #000;
		}

		#fileNav li {
			float: left;
			width: 100%;
			white-space: nowrap;
			overflow: visible;
			cursor: pointer;
		}
		
		#fileNav li.hasSub::before {
			content: ">";
			width: 2rem;
			text-align: right;
			padding: 0.2rem;
			float: right;
			color: #777;
		}
		
		#fileNav li.hasSub li::before {
			content: "";
		}

		#fileNav li.hasSub li.hasSub::before {
			content: ">";
		}
		
		#fileNav li li {
			clear: left;
			width: 100%;
		}

		#fileNav ul,
		#fileNav li.showSub ul ul {
			display: none;
			position: absolute;
		}

		#fileNav li.showSub ul {
			display: block;
			position: absolute;
			bottom: 44px;
		}

		#fileNav li.showSub li.showSub {
			position: relative;			
		}
		
		#fileNav li.showSub li.showSub ul {
			display: block;
			left: 100%;
			bottom: 0;
		}
		
		#fileNav li.showSub li.showSub ul ul {
			display: none;
		}

		#fileNav li.showSub li.showSub li.showSub ul {
			display: block;
			left: 100%;
			bottom: 0;
		}
		
		/* for loading screen */
		
		#loadingOverlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 100vw;
			height: calc(100vh - 44px);
			background-color: rgba(128, 128, 128, 0.5);
			display: none;
			align-items: center;
			justify-content: center;
			z-index: 10000;
		}
		
		body.isLoading #loadingOverlay {
			display: flex;
		}
		
		#outerLoading {
			position: relative;
			width: 150px;
			height: 150px;
			background-color: #444;
			border-radius: 50%;
			text-align: center;
			color: #fff;
			overflow: hidden;
		}

		#innerLoading {
			position: absolute;
			width: 100%;
			height: 100%;
			left: 0;
			top: 0;
			border-radius: 50%;
			z-index: 3;
		}
		
		body.isLoading #innerLoading {
			animation: circleRotate 3s infinite linear;
		}

		@keyframes circleRotate {
			from { transform: rotate(0deg); }
			to { transform: rotate(359deg); }
		}

		#innerCover {
			position: absolute;
			top: 0;
			left: 50%;
			width: 50%;
			height: 100%;
			background-image: linear-gradient(#444, #fff);
			z-index: 5;
		}

		#innerDot {
			position: absolute;
			background-color: #fff;
			width: 4%;
			height: 4%;
			bottom: 0;
			left: 48%;
			border-radius: 50%;
			z-index: 6;
		}

		#innerContent {
			position: absolute;
			background-color: #444;
			width: 92%;
			height: 92%;
			top: 4%;
			left: 4%;
			border-radius: 50%;
			z-index: 5;
			display: flex;
			flex-direction: column;
			justify-content: center;
		}

		@media only print {
			#nav, #codeCols {
				display: none;
			}
			#codeNums, #codeLines {
				position: absolute;
				overflow: visible;
			}
		}
		
<? if ($highlightCode) { ?>			
		/* highlight.js for lite mode */
		
		.hljs a {
			text-decoration: none;
			border-bottom: 1px dotted #888;
		}

		.hljs a:hover {
			border-bottom: 1px solid #888;
		}
		
		.hljs,
		.hljs-subst {
			color: #525242;
		}

		.hljs-built_in,
		.hljs-selector-tag,
		.hljs-selector-id,
		.hljs-selector-class,
		.hljs-section,
		.hljs-link {
			color: #4291a1;
		}

		.hljs-keyword {
			color: #d52feb;
		}

		.hljs-title,
		.hljs-params {
			color: #9c5e24;
		}

		.hljs a, 
		.hljs-string {
			color: #49910d;	
		}
		
		.hljs-keyword,
		.hljs-tag,
		.hljs-meta,
		.hljs-name,
		.hljs-type,
		.hljs-symbol,
		.hljs-bullet,
		.hljs-addition,
		.hljs-variable,
		.hljs-template-tag,
		.hljs-template-variable {
			color: #47749e;
		}
		
		.hljs-attr,
		.hljs-attribute {
			color: #878758;
		}

		a .hljs-comment,
		.hljs-comment {
			color: #9e5555;	
		}
		
		.hljs-quote,
		.hljs-deletion {
			color: #75715e;
		}

		.hljs-selector-tag,
		.hljs-literal,
		.hljs-title,
		.hljs-section,
		.hljs-doctag,
		.hljs-type,
		.hljs-name,
		.hljs-strong {
			font-weight: bold;
		}

		.hljs-literal,
		.hljs-number {
			color: #c251c0;
		}

		.hljs-emphasis {
			font-style: italic;
		}


		/* highlight.js for dark mode */

		body.darkMode .hljs,
		body.darkMode .hljs-subst {
			color: #f8f8f2;
		}
		
		body.darkMode .hljs-built_in,
		body.darkMode .hljs-selector-tag,
		body.darkMode .hljs-selector-id,
		body.darkMode .hljs-selector-class,
		body.darkMode .hljs-section,
		body.darkMode .hljs-link {
			color: #66d8ef;
		}

		body.darkMode .hljs-keyword {
			color: #d52feb;
		}

		body.darkMode .hljs-title,
		body.darkMode .hljs-params {
			color: #ffd2a7;
		}

		body.darkMode .hljs a, 
		body.darkMode .hljs-string {
			color: #a8ff60;	
		}
		
		body.darkMode .hljs-keyword,
		body.darkMode .hljs-tag,
		body.darkMode .hljs-meta,
		body.darkMode .hljs-name,
		body.darkMode .hljs-type,
		body.darkMode .hljs-symbol,
		body.darkMode .hljs-bullet,
		body.darkMode .hljs-addition,
		body.darkMode .hljs-variable,
		body.darkMode .hljs-template-tag,
		body.darkMode .hljs-template-variable {
			color: #96cbfe;
		}
		
		body.darkMode .hljs-attr,
		body.darkMode .hljs-attribute {
			color: #f7f7cd;
		}

		body.darkMode a .javascript .hljs-comment, 
		body.darkMode .hljs-comment {
			color: #c28686;	
		}
		
		body.darkMode .hljs-quote,
		body.darkMode .hljs-deletion {
			color: #75715e;
		}

		body.darkMode .hljs-selector-tag,
		body.darkMode .hljs-literal,
		body.darkMode .hljs-title,
		body.darkMode .hljs-section,
		body.darkMode .hljs-doctag,
		body.darkMode .hljs-type,
		body.darkMode .hljs-name,
		body.darkMode .hljs-strong {
			font-weight: bold;
		}

		body.darkMode .hljs-literal,
		body.darkMode .hljs-number {
			color: #ff73fd;
		}

		body.darkMode .hljs-emphasis {
			font-style: italic;
		}

	<? } ?></style>
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
			<li><a id="menuIcon">&#9650;</a>
				<ul>
					<li id="optDarkMode"><a onclick="toggleVisualMode()"><span><?= ($startInDarkMode ? "&check;" : "&nbsp;") ?></span> Dark Mode</a></li>
					<li id="optLineNumbers"><a onclick="toggleNums();"><span><?= ($startWithLinesOn ? "&check;" : "&nbsp;") ?></span> Line Numbers</a></li>
					<li id="optColumns"><a onclick="toggleCols();"><span><?= ($startWithColsOn ? "&check;" : "&nbsp;") ?></span> Column Markers</a></li>
					<li id="optReload"><a onclick="toggleReloadTimer();"><span>&nbsp;</span> Auto-load updates (5s)</a></li>
					<li class="menuLine"><a href="mailto:<?= $userEmail; ?>"><span>&nbsp;</span> Email <?= $userName; ?></a></li>
					<? if ($passwordRequired) { ?><li><a onclick="logout()"><span>&nbsp;</span> Log Out</a></li><? } ?>
					<? if ($showDebuggrLink) { ?><li class="menuLine"><a href="https://github.com/tordevries/debuggr" target="_blank"><span>&nbsp;</span> Debuggr v<?= $debuggrVersion; ?></a></li><? } ?>
				</ul>
			</li>
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