<? 
/*

Debuggr version 1.2.1-beta by Tor de Vries (tor.devries@wsu.edu)

Copy this PHP code into the root directory of your server-side coding project so others can study your code.
Then, add the parameter "?file=" and the name of a file to view its source code. For example: 
https://yourdomain.com/project/debuggr.php?file=yourfile.php

For more information, including an explanation of the options below: 
https://github.com/tordevries/debuggr

-----

Copyright (C) 2020 Tor de Vries (tor.devries@wsu.edu)

This program is free software: you can redistribute it and/or modify it under the terms of the 
GNU General Public License as published by the Free Software Foundation, either version 3 of 
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  
If not, see <http://www.gnu.org/licenses/>.

*/

// CONFIGURATION -- edit these variables as needed

$userName = "Host"; // put in your own name
$userEmail = "your@email.com"; // put in your own email address

$pagePassword = "477demo"; // set a password
$passwordRequired = true; // if true, requires a password and temporary session authorization to view the file; you really should leave this as true
$forceSSL = true; // if true, redirects HTTP requests to HTTPS

$accessCurrentDirectoryOnly = false; // if true, restricts access to only files in this same directory as this file, no subdirectories allowed
$accessParentDirectories = false; // if true, allows users to enter pathnames to parent directories, using '../', though never in the Files menu
$preventAccessToThisFile = true; // if true, prevents users from reading this PHP file with itself
$allowRemoteFileReading = false; // if true, Debuggr can attempt to read remote URL source codes; if false, will return nothing on attempts

$showFilesMenu = false; // if true, will add links to the FIles menu with files in the current directory
// note: if $accessCurrentDirectoryOnly is false, the Files menu will include local folders and their files/subdirectories

$highlightCode = true; // true to load Highlight.js for coloring text
$startInDarkMode = true; // true to start in dark mode by default; false to start in lite mode
$startWithLinesOn = true; // true to start with the line numbers visible
$showDebuggrLink = true; // true to include a link to Debuggr on Github in the options menu

// advanced remote file reading options related to $allowRemoteFileReading and the PHP cURL libraries
$allowCURLtoBypassHTTPS = true; // if true, cURL will bypass HTTPS security checks; if false, you must set a security certificate path, below, for cURL to work
$certificatePathForCURL = '/etc/ssl/certs'; // provide the absolute path to your server's security certificates; only applied if $allowCURLtoBypassHTTPS is false


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
	global $accessCurrentDirectoryOnly, $showFilesMenu;
	$result = "<ul>";
	if (!is_null($arr)) {
		foreach($arr as $key => $value) {
			if ( is_numeric($key) ) {
				$result .=	"<li><a onclick='loadFile(\"" . $path . $value . "\")'>" . $value . "</a></li>\n";
			} else if (!$accessCurrentDirectoryOnly) {
				$result .=	"<li class='hasSub'><a>" . $key . "</a>";
				$result .= buildFileMenu($value, ($path . $key . DIRECTORY_SEPARATOR), 1 );
				$result .= 	"</li>\n";
			}
		}
	}
	if ($depth == 0) $result .= "<li class='" . ($showFilesMenu ? "menuLine" : "") . "'><a onclick='openFile();'>Open File...</a></li>";
	$result .= "</ul>";
	return $result;
}


// create an unordered list for the CSS-based file menu
function fileMenu($dir = '.') {
	global $showFilesMenu;
	if ($showFilesMenu) $list = findAllFiles($dir);
	$listHTML = "<ul id='fileNav'><li>&#128196;" . buildFileMenu($list) . "</li></ul>";
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


function fetchLocalFile($localFilepath) {
	global $noFile, $fmenu;
	
	// check if file does not exist or is blocked
	if ((!file_exists($localFilepath)) || ($preventAccessToThisFile && ($localFilepath == basename(__FILE__)))) {

		// clear all the session variables
		unset( $_SESSION["filename"] );
		unset( $_SESSION["filetime"] );
		unset( $_SESSION["filemenu"] );
		
		$returnOutput = $noFile;

	} else { // the file DOES exist, so...

		// check if it's an image; if so, output an img tag, otherwise read in the file contents
		$isImage = getimagesize($localFilepath);
		if ($isImage != false) $returnOutput = "<img src='" . $localFilepath . "'>";
		else $returnOutput = file_get_contents($localFilepath);

		if (!$returnOutput) $returnOutput = $noFile; // file is empty, output error
		else if ($isImage == false) $returnOutput = htmlspecialchars($returnOutput); // convert to special characters for transmission

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
	global $noFile, $fmenu, $allowCURLtoBypassHTTPS, $certificatePathForCURL;

	// set session variables used for AJAX checks
	$_SESSION["filename"] = $remoteURL;
	$_SESSION["filetime"] = 0;
	$_SESSION["filemenu"] = $fmenu;
	
	// prepare for common image suffixes; this may or may not be trustworthy
	$imageSuffixes = ["png", "jpg", "jpeg", "gif", "svg", "webp", "jfif", "avif", "apng", "pjpeg", "pjp", "ico", "cur", "tif", "tiff", "bmp"];
	$remotePath = parse_url($remoteURL, PHP_URL_PATH); // get the path component of the URL
	$remoteSuffix = pathinfo($remotePath, PATHINFO_EXTENSION); // get the file extension on the path
	
	// if the extension on the file path of the URL ends in an image format, output an img tag
	if (in_array($remoteSuffix, $imageSuffixes)) {
		$returnOutput = "<img src='" . $remoteURL . "'>";
		
	} else { // not an image, so try to read the remote source
		
		if (function_exists('curl_init')) { // if cURL is available in PHP, use it
			
			// initialized cURL
			$remoteCURL = curl_init($remoteURL);
			
			// set cURL options
			curl_setopt($remoteCURL, CURLOPT_VERBOSE, false);
			curl_setopt($remoteCURL, CURLOPT_TIMEOUT_MS, 5000);
			curl_setopt($remoteCURL, CURLOPT_HEADER, false);
			curl_setopt($remoteCURL, CURLOPT_RETURNTRANSFER, true);
			
			// adapted from https://stackoverflow.com/questions/4184869/how-to-disguise-your-php-script-as-a-browser
			$curlHeader = ['User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-us,en;q=0.5',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Keep-Alive: 115',
				'Connection: keep-alive'];
			curl_setopt($remoteCURL, CURLOPT_HTTPHEADER, $curlHeader);
			
			if ($allowCURLtoBypassHTTPS) curl_setopt($remoteCURL, CURLOPT_SSL_VERIFYPEER, false);
			else curl_setopt($remoteCURL, CURLOPT_CAPATH, $certificatePathForCURL);
			$remoteCURLhttp = curl_getinfo($remoteCURL, CURLINFO_HTTP_CODE);
			error_log("cURL HTTP code: " . $remoteCURLhttp);
			error_log("cURL error: " . curl_strerror(curl_errno($remoteCURL)));
			
			// execute cURL call and convert to shareable code with htmlspecialchars()
			$returnOutput = htmlspecialchars( curl_exec($remoteCURL) );
			
		} else {
			error_log("Debuggr remote URL error: cURL is not enabled.");
			$returnOutput = $noFile;
			
		}
	}
			
	return $returnOutput;
}


// ********************************************************************************
// PHP PROCEDURES
// ********************************************************************************


// for security, kill the output if password is required but not set
if ($passwordRequired && ($pagePassword == "")) die("ERROR: No password set.");


// for security, redirect to HTTPS if it's not HTTPS
if ($forceSSL && (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on")) {
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"], true, 301);
    die();
}

// initalize sessions
session_start();

// set a boolean to use to confirm continued authorization
$isStillAuthorized = (!$passwordRequired || ($_SESSION["authorized"] == $pagePassword));
$fmenu = fileMenu();

// if a logout command been passed, clear the session and send back to login form
if ($_POST["logout"]) {
	session_unset();
	session_destroy();
	header("Location: " . $_SERVER["REQUEST_URI"], true, 301);
	die();
}


// for a quick AJAX pulse check on the file's timestamp using previously-stored session variables
// return 1 for update; 0 for no longer authorized; 2 to update menu; 3 to update file and menu;
// for remote files, always returns an update 
if ($_REQUEST["method"] == "pulse") {
	if (!$isStillAuthorized) die("0");
	$updateFile = (!isFileRemote($_SESSION["filename"]) && (filemtime($_SESSION["filename"]) > $_SESSION["filetime"]));
	$updateMenu = ($_SESSION["filemenu"] != $fmenu );
	if (isFileRemote($_SESSION["filename"])) {
		if (!$updateMenu) die("1"); // force an update on remote files by returning "1"
		if ($updateMenu) die("3");
	} else if (file_exists($_SESSION["filename"])) {
		if ($updateFile && !$updateMenu) die("1"); // indicate an update by returning "1"
		if ($updateFile && $updateMenu) die("3");
	}
	if ($updateMenu) die("2");
	die(); // if no filename, or no update to the file, die with nothing
}

// return only the file menu HTML
if ($_REQUEST["method"] == "menu") {
	if (!$isStillAuthorized) die();
	die($fmenu); // die outputting menu HTML
}

// for security, if the session is not authorized, check password and/or show login form if necessary
if (!$isStillAuthorized) {
	
	if ($_REQUEST["method"] == "ajax") die(); // if they're not calling from an authorized session, ajax returns nothing
	
	if ($_POST["pwd"] == $pagePassword) {
		$_SESSION["authorized"] = $pagePassword; // if a valid password has been passed, authorize the session
		
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
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<style>

		* {
			font-family: 'Source Code Pro', monospace;
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		#pageBox {
			display: flex;
			height: 100vh;
			width: 100vw;
			align-items: center;
			justify-content: center;
		}

		input, button {
			padding: 4px;
		}
		
	</style>
</head>
<body>
	<div id="pageBox">
		<div>
			<form method="POST">
				<input type="password" id="pwd" name="pwd" placeholder="password" value="">
				<button type="submit">LOG IN</button>
			</form>
		</div>
	</div>
</body>
</html>
<?
		die(); // end processing
	}
}

// ********************************************************************************
// PHP PROCEDURES, CONTINUED
// ********************************************************************************


// it's not a pulse check, or a menu check, and the user didn't need to authorize, so let's proceed with output

$noFile = "Nothing found."; // default message to output if the file does not exist or is empty
$fpassed = $_REQUEST["file"];

if ($accessCurrentDirectoryOnly) $fpassed = basename($fpassed); // if $accessCurrentDirectoryOnly is true, only allow files in current directory
if (!$accessParentDirectories) $fpassed = ltrim( str_replace("..", "", $fpassed), '/'); // if the passed file starts with a slash, remove it, and don't allow ".." directory traversal

$foutput = fetchFile($fpassed);

// if the method is ajax, just return the content without the rest of the HTML, CSS, JS
if ($_REQUEST["method"] == "ajax") die($foutput); 

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
	<title>Debuggr: <?= $fpassed; ?> by <?= $userName; ?></title>
	<script>
		
		// set some variables (including some passed from PHP
		baseFile = "<?= $fpassed; ?>";
		lineNumbersOn = <?= json_encode($startWithLinesOn); ?>;
		darkModeOn = <?= json_encode($startInDarkMode); ?>;
		reloadTimer = false;
		
		// shortcut for updating the status text
		function statusMessage(msg) {
			document.getElementById("statusMsg").innerHTML = msg;
		}
		
		// toggle the numbers using CSS and changing the button
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
			if (reloadTimer) statusMessage("&bull;");
			else statusMessage("Checking...");
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
					}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?method=pulse";
			ajax.open("GET", urlToLoad, true);
			ajax.send();
		}
		
		function loadMenu() {
			statusMessage("Loading menu...");
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200)) {
						document.getElementById("fileNav").outerHTML = this.responseText;
						setMenuClicks();
						statusMessage("");
					} else if ((this.readyState == 4) && (this.status != 200)) {
						console.log("AJAX menu error: " + this.responseText);
					}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?method=menu";
			ajax.open("POST", urlToLoad, true);
			ajax.send();
		}
		
		// use AJAX to reload the file or to load files from the Files menu (if enabled)
		function loadFile(fileToLoad = baseFile, historyUpdate = true) {
			closeMenus();
			codeLinesPre = document.querySelector("#codeLines pre");
			statusMessage("Loading file...");
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
						prepLineNumbers(this.responseText.split("\n").length);
						document.title = "Debuggr: " + fileToLoad;
						document.querySelector("#filenameRef span").innerHTML = fileToLoad;
						if (historyUpdate) {
							historyURL = "<?= $_SERVER['PHP_SELF']; ?>?file=" + fileToLoad;
							window.history.pushState( {}, "", historyURL);
						}
						statusMessage("");
					} else if ((this.readyState == 4) && (this.status != 200)) {
						codeLinesPre.innerHTML = "<?= $noFile; ?>";
						console.log("AJAX error: " + this.responseText);
					}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?file=" + fileToLoad + "&method=ajax";
			ajax.open("POST", urlToLoad, true);
			ajax.send();
		}
		
		// output line numbers in #codeNums pre; pad numbers with 0s to appropriate width
		function prepLineNumbers(numLines) {
			codeNumsPre = document.querySelector("#codeNums pre");
			codeNumsPre.innerHTML = "";
			outputLines = "";
			padTo = numLines.toString().length + 1;
			for (x=1; x<=numLines; x++) outputLines += (x + ":").padStart(padTo, "0") + "\n";
			codeNumsPre.innerHTML = outputLines;
			document.querySelector("style").innerHTML += "body.linesOn #codeNums { width: " + (padTo-1) + "rem; } body.linesOn #codeLines { width: calc(100vw - " + (padTo - 0.5) + "rem); left: " + (padTo - 0.5) + "rem; }";
		}
		
		function openFile() {
			closeMenus();
			toOpen = window.prompt("Enter a filename and path:", baseFile);
			if ((toOpen != "") && (toOpen !== null)) {
				loadFile(toOpen);
			}
		}

		function closeMenus() {
			allLI = document.querySelectorAll("#fileNav li");
			for (x=0; x<allLI.length; x++) allLI[x].classList.remove("showSub");
			document.querySelector("#optionsNav li").classList.remove("showSub");
		}
		
		function styleCode() { // apply Highlights.js
			<? if ($highlightCode) { ?>hljs.highlightBlock( document.querySelector('#codeLines pre') );<? } ?>
		}
		
		function logout() { document.getElementById("logoutForm").submit(); }
		
		// when the window loads, prep line numbers, and connect the scrollTops of #codeLines to #codeNums
		window.onload = function() {
			
			// output line numbers
			prepLineNumbers(document.querySelector("#codeLines pre").innerHTML.split("\n").length);

			// output column numbers
			for (x=1; x<30; x++) document.getElementById("codeCols").innerHTML += "<span>" + (x * 10) + "</span>";

			// reposition #codeNums and #codeCols as the user scrolls
			document.getElementById("codeLines").onscroll = function() { 
				document.getElementById("codeNums").scrollTop = document.getElementById("codeLines").scrollTop; 
				document.getElementById("codeCols").style.top = document.getElementById("codeLines").scrollTop + "px"; 
			}
			document.querySelector("#codeLines pre").onscroll = function() {
				document.getElementById("codeCols").style.left = (0 - document.querySelector("#codeLines pre").scrollLeft) + "px"; 
			}			
			
			// set clicks for the options menu
			document.querySelector('#optionsNav li').onclick = function() { 
				closeMenus();
				document.querySelector('#optionsNav li').classList.toggle('showSub');
			}
			
			// set clicks for the file menu
			setMenuClicks();
			
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
		}		
		
	</script>
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<style>
		
		* {
			font-family: 'Source Code Pro', monospace;
			tab-size: 3;
			margin: 0;
			padding: 0;
			box-sizing: border-box;
			transition: background-color 0.25s, color 0.25s;
			overflow: auto;
		}
		
		body {
			background-color: #eee;
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
		}

		#nav span {
			margin-right: 10px;
			float: left;
			max-width: 40vw;
		}

		#nav span a {
			color: #ddd;
			text-decoration: none;
		}
		
		#filenameRef {
			display: inline-block;
		}
		
		#nav span#statusMsg {
			margin-left: 10px;
			color: #aaa;
			float: right;
			max-width: 30vw;
		}
		
		a.uicon  {
			font-weight: bold;
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
			color: #04717a;
			transition: left 0.3s, width 0.3s;
			z-index: 1;
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
			background-image: linear-gradient(to right, #ddd 1px, transparent 1px);
			background-attachment: local;
			z-index: 2;
		}
		
		body.darkMode #codeLines pre {
			background-image: linear-gradient(to right, #333 1px, transparent 1px);
		}
		
		#codeNums pre, #codeLines pre {
			padding-top: 0.5em;
			padding-bottom: 2rem;
		}
		
		#codeCols {
			position: absolute;
			top: 0;
			left: 0;
			width: 500%;
			height: 1.5em;
			overflow: auto;
			z-index: 1;
		}
		
		#codeCols span {
			width: 10ch;
			display: inline-block;
			white-space: nowrap;
			text-align: right;
			color: #bbb;
			padding-right: 4px;
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
			font-size: 100%;
			line-height: 150%;
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
			padding: 0.2rem 0.2rem 0.2rem 0.5rem;
			margin-right: 0.5rem;
			text-decoration: none;
			color: #fff;
		}
		
		#optionsNav li ul li a {
			color: #000;
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
			text-align: right;
			font-size: 18px;
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
			list-style-type: none;
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
		
		@media only print {
			#nav, #codeCols {
				display: none;
			}
			#codeNums, #codeLines {
				position: absolute;
				overflow: visible;
			}
		}
		
	</style>
<? if ($highlightCode) { ?>	
	<script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/10.4.0/highlight.min.js"></script>
	<style>
		/*
		Google Code style (c) Aahan Krish <geekpanth3r@gmail.com>
		*/

		.hljs-comment,
		.hljs-javadoc {
			color: #800;
		}

		.hljs-keyword,
		.method,
		.hljs-list .hljs-keyword,
		.nginx .hljs-title,
		.hljs-tag .hljs-title,
		.setting .hljs-value,
		.hljs-winutils,
		.tex .hljs-command,
		.http .hljs-title,
		.hljs-request,
		.hljs-status {
			color: #008;
		}

		.hljs-envvar,
		.tex .hljs-special {
			color: #660;
		}

		.hljs-string,
		.hljs-tag .hljs-value,
		.hljs-cdata,
		.hljs-filter .hljs-argument,
		.hljs-attr_selector,
		.apache .hljs-cbracket,
		.hljs-date,
		.hljs-regexp,
		.coffeescript .hljs-attribute {
			color: #080;
		}

		.hljs-sub .hljs-identifier,
		.hljs-pi,
		.hljs-tag,
		.hljs-tag .hljs-keyword,
		.hljs-decorator,
		.ini .hljs-title,
		.hljs-shebang,
		.hljs-prompt,
		.hljs-hexcolor,
		.hljs-rule .hljs-value,
		.hljs-literal,
		.hljs-symbol,
		.ruby .hljs-symbol .hljs-string,
		.hljs-number,
		.css .hljs-function,
		.clojure .hljs-attribute {
			color: #066;
		}

		.hljs-class .hljs-title,
		.smalltalk .hljs-class,
		.hljs-javadoctag,
		.hljs-yardoctag,
		.hljs-phpdoc,
		.hljs-dartdoc,
		.hljs-type,
		.hljs-typename,
		.hljs-tag .hljs-attribute,
		.hljs-doctype,
		.hljs-class .hljs-id,
		.hljs-built_in,
		.setting,
		.hljs-params,
		.hljs-variable,
		.hljs-name {
			color: #606;
		}

		.css .hljs-tag,
		.hljs-rule .hljs-property,
		.hljs-pseudo,
		.hljs-subst {
			color: #000;
		}

		.css .hljs-class,
		.css .hljs-id {
			color: #9b703f;
		}

		.hljs-value .hljs-important {
			color: #ff7700;
			font-weight: bold;
		}

		.hljs-rule .hljs-keyword {
			color: #c5af75;
		}

		.hljs-annotation,
		.apache .hljs-sqbracket,
		.nginx .hljs-built_in {
			color: #9b859d;
		}

		.hljs-preprocessor,
		.hljs-preprocessor *,
		.hljs-pragma {
			color: #444;
		}

		.tex .hljs-formula {
			background-color: #eee;
			font-style: italic;
		}

		.diff .hljs-header,
		.hljs-chunk {
			color: #808080;
			font-weight: bold;
		}

		.diff .hljs-change {
			background-color: #bccff9;
		}

		.hljs-addition {
			background-color: #baeeba;
		}

		.hljs-deletion {
			background-color: #ffc8bd;
		}

		.hljs-comment .hljs-yardoctag {
			font-weight: bold;
		}
	</style>
	<style>
		/* highlight.js dark mode theme adaptation */
		/* Dracula Theme v1.2.5
		 *
		 * https://github.com/dracula/highlightjs
		 *
		 * Copyright 2016-present, All rights reserved
		 *
		 * Code licensed under the MIT license
		 *
		 * @author Denis Ciccale <dciccale@gmail.com>
		 * @author Zeno Rocha <hi@zenorocha.com>
		 */

		body.darkMode .hljs-built_in,
		body.darkMode .hljs-selector-tag,
		body.darkMode .hljs-section,
		body.darkMode .hljs-link {
			color: #8be9fd;
		}

		body.darkMode .hljs-keyword {
			color: #d52feb;
		}

		body.darkMode .hljs,
		body.darkMode .hljs-subst {
			color: #f8f8f2;
		}

		body.darkMode .hljs-title {
			color: #50fa7b;
		}

		body.darkMode .hljs-string,
		body.darkMode .hljs-meta,
		body.darkMode .hljs-name,
		body.darkMode .hljs-type,
		body.darkMode .hljs-attr,
		body.darkMode .hljs-symbol,
		body.darkMode .hljs-bullet,
		body.darkMode .hljs-addition,
		body.darkMode .hljs-variable,
		body.darkMode .hljs-template-tag,
		body.darkMode .hljs-template-variable {
			color: #f1fa8c;
		}

		body.darkMode .hljs-comment,
		body.darkMode .hljs-quote,
		body.darkMode .hljs-deletion {
			color: #6272a4;
		}

		body.darkMode .hljs-keyword,
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
			color: #bd93f9;
		}

		body.darkMode .hljs-emphasis {
			font-style: italic;
		}

	</style>
	<style>
		/* highlight.js noStyle adaptation */

		body.noStyle .hljs,
		body.noStyle .hljs-built_in,
		body.noStyle .hljs-selector-tag,
		body.noStyle .hljs-section,
		body.noStyle .hljs-link,
		body.noStyle .hljs-keyword,
		body.noStyle .hljs,
		body.noStyle .hljs-subst,
		body.noStyle .hljs-title,
		body.noStyle .hljs-string,
		body.noStyle .hljs-meta,
		body.noStyle .hljs-name,
		body.noStyle .hljs-type,
		body.noStyle .hljs-attr,
		body.noStyle .hljs-symbol,
		body.noStyle .hljs-bullet,
		body.noStyle .hljs-addition,
		body.noStyle .hljs-variable,
		body.noStyle .hljs-template-tag,
		body.noStyle .hljs-template-variable,
		body.noStyle .hljs-comment,
		body.noStyle .hljs-quote,
		body.noStyle .hljs-deletion,
		body.noStyle .hljs-keyword,
		body.noStyle .hljs-selector-tag,
		body.noStyle .hljs-literal,
		body.noStyle .hljs-title,
		body.noStyle .hljs-section,
		body.noStyle .hljs-doctag,
		body.noStyle .hljs-type,
		body.noStyle .hljs-name,
		body.noStyle .hljs-strong,
		body.noStyle .hljs-literal,
		body.noStyle .hljs-number,
		body.noStyle .hljs-emphasis {
			font-style: normal;
			font-weight: normal;
			color: #000;
		}

		body.darkMode.noStyle .hljs,
		body.darkMode.noStyle .hljs-built_in,
		body.darkMode.noStyle .hljs-selector-tag,
		body.darkMode.noStyle .hljs-section,
		body.darkMode.noStyle .hljs-link,
		body.darkMode.noStyle .hljs-keyword,
		body.darkMode.noStyle .hljs,
		body.darkMode.noStyle .hljs-subst,
		body.darkMode.noStyle .hljs-title,
		body.darkMode.noStyle .hljs-string,
		body.darkMode.noStyle .hljs-meta,
		body.darkMode.noStyle .hljs-name,
		body.darkMode.noStyle .hljs-type,
		body.darkMode.noStyle .hljs-attr,
		body.darkMode.noStyle .hljs-symbol,
		body.darkMode.noStyle .hljs-bullet,
		body.darkMode.noStyle .hljs-addition,
		body.darkMode.noStyle .hljs-variable,
		body.darkMode.noStyle .hljs-template-tag,
		body.darkMode.noStyle .hljs-template-variable,
		body.darkMode.noStyle .hljs-comment,
		body.darkMode.noStyle .hljs-quote,
		body.darkMode.noStyle .hljs-deletion,
		body.darkMode.noStyle .hljs-keyword,
		body.darkMode.noStyle .hljs-selector-tag,
		body.darkMode.noStyle .hljs-literal,
		body.darkMode.noStyle .hljs-title,
		body.darkMode.noStyle .hljs-section,
		body.darkMode.noStyle .hljs-doctag,
		body.darkMode.noStyle .hljs-type,
		body.darkMode.noStyle .hljs-name,
		body.darkMode.noStyle .hljs-strong,
		body.darkMode.noStyle .hljs-literal,
		body.darkMode.noStyle .hljs-number,
		body.darkMode.noStyle .hljs-emphasis {
			font-style: normal;
			font-weight: normal;
			color: #fff;
		}

	</style>
<? } ?>
</head>
<body class="<? if ($startWithLinesOn) { ?>linesOn<? } ?> <? if ($startInDarkMode) { ?>darkMode<? } ?>">
	<div id="nav">
		<?= $fmenu; ?>
		<div id="filenameRef">
			<span><?= $fpassed; ?></span> 
			<a class="uicon" title="Reload file" href="javascript:checkPulse(true);">&#8635;</a>
			<span id="statusMsg"></span>
		</div>
		<ul id="optionsNav">
			<li><a id="menuIcon">&#9776;</a>
				<ul>
					<li><a href="javascript:window.open(baseFile);"><span>&nbsp;</span> Open File in New Tab</a></li>
					<li><a href="javascript:selectCode()"><span>&nbsp;</span> Select All Code</a>
					<li id="optDarkMode" class="menuLine"><a href="javascript:toggleVisualMode()"><span><?= ($startInDarkMode ? "&check;" : "&nbsp;") ?></span> Dark Mode</a></li>
					<li id="optLineNumbers"><a href="javascript:toggleNums();"><span><?= ($startWithLinesOn ? "&check;" : "&nbsp;") ?></span> Line Numbers</a></li>
					<li id="optReload"><a href="javascript:toggleReloadTimer();"><span>&nbsp;</span> Auto-load updates (5s)</a></li>
					<li class="menuLine"><a href="mailto:<?= $userEmail; ?>"><span>&nbsp;</span> Email <?= $userName; ?></a></li>
					<? if ($passwordRequired) { ?><li><a href="javascript:logout()"><span>&nbsp;</span> Log Out</a></li><? } ?>
					<? if ($showDebuggrLink) { ?><li class="menuLine"><a href="https://github.com/tordevries/debuggr" target="_blank"><span>&nbsp;</span> Debuggr Info</a></li><? } ?>
				</ul>
			</li>
		</ul>
	</div>
	<div id="codeNums"><pre></pre></div>
	<div id="codeLines">
		<div id="codeCols"></div>
		<pre><?= $foutput; ?></pre>
	</div>
	<? if ($passwordRequired) { ?><form method="POST" id="logoutForm"><input type="hidden" value="1" name="logout" id="logout"></form><? } ?>
</body>
</html>