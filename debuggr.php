<? 
/*

Debuggr version 0.98 by Tor de Vries

For more info, see https://github.com/tordevries/debuggr

Copy this PHP code into a "debuggr.php" file in the root directory of your PHP coding so others can study your code.
Then, just add "?file=" and the name of a file to view its source code. 

Example URL: https://dtc477.net/unit3/debuggr.php?file=debuggr.php

*/

// CONFIGURATION -- edit these variables as needed

$userName = "Host"; // put in your own name
$userEmail = "your@email.com"; // put in your own email address

$pagePassword = "477demo"; // set a password
$passwordRequired = true; // if true, requires a password and temporary session authorization to view the file; you really should leave this as true
$forceSSL = true; // if true, redirects HTTP requests to HTTPS

$accessCurrentDirectoryOnly = false; // if true, restricts access to only files in this same directory as this file, no subdirectories allowed
$accessParentDirectories = false; // if true, allows users to enter pathnames to parent directories, using '../'
$preventAccessToThisFile = true; // if true, prevents users from reading this PHP file with itself

$showFilesMenu = true; // if true, will show a "Files" menu that links to files in the current directory
// note: if $accessCurrentDirectoryOnly is false, the "Files" menu will include local folders and their files/subdirectories

$startInDarkMode = true; // true to start in dark mode by default; false to start in lite mode
$startWithLinesOn = true; // true to start with the line numbers visible


// ********************************************************************************
//
// WARNING: CODE BELOW
// Ignore everything below this line unless you really know what you're doing.
//
// ********************************************************************************


// a recursive function that returns a multidimensional array of files/folders in local directory; 
// adapted from user-submitted code on https://www.php.net/manual/en/function.scandir.php
function findAllFiles($dir = '.') { 
	global $preventAccessToThisFile;
	$result = array();
	$cdir = scandir($dir);
	foreach ($cdir as $key => $value) {
		if ( !in_array( $value, array(".", "..") ) ) {
			 if ( is_dir($dir . DIRECTORY_SEPARATOR . $value) ) $result[$value] = findAllFiles( $dir . DIRECTORY_SEPARATOR . $value );
			 else if ( !$preventAccessToThisFile || ($preventAccessToThisFile && ($value != basename(__FILE__))) ) $result[] = $value;
		}
	}
	return $result;
}

// a recursive function to build a hierarchical <ul> menu of files from the array produced in findAllFiles();
function buildFileMenu($arr, $path = "") {
	global $accessCurrentDirectoryOnly;
	$result = "<ul>";
	foreach($arr as $key => $value) {
		if ( is_numeric($key) ) {
			$result .=	"<li><a onclick='loadFile(\"" . $path . $value . "\")'>" . $value . "</a></li>\n";
		} else if (!$accessCurrentDirectoryOnly) {
			$result .=	"<li class='hasSub'><a>" . $key . "</a>";
			$result .= buildFileMenu($value, ($path . $key . DIRECTORY_SEPARATOR) );
			$result .= 	"</li>\n";
		}
	}
	$result .= "<li class='menuLine'><a onclick='openFile();'>Open File...</a></ul>\n";
	return $result;
}

// create an unordered list CSS-based file menu
function fileMenu($dir = '.') {
	$list = findAllFiles($dir);
	$listHTML = "<ul id='fileNav'><li>&#128196;" . buildFileMenu($list) . "</li></ul>";
	return $listHTML;
}



// for security, redirect to HTTPS if it's not HTTPS
if ($forceSSL && (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on")) {
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"], true, 301);
    die();
}

// for security, kill the output if password is required but not set
if ($passwordRequired && ($pagePassword == "")) {
	die("ERROR: No password set.");
}

// we're using sessions, so let's go
session_start();

// has a logout command been passed?
if ($_POST["logout"]) {
	session_unset();
	session_destroy();
	header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"], true, 301);
	die();
}

// for security, if the session is not authorized, check password and/or show login form if necessary
if ($passwordRequired && (!$_SESSION["authorized"] || ($_SESSION["authorized"] != $pagePassword))) {
	
	if ($_REQUEST["method"] == "ajax") {
		die(); // if they're not calling from an authorized session, ajax returns nothing
	}
	
	if ($_POST["pwd"] == $pagePassword) {
		$_SESSION["authorized"] = $pagePassword; // if a valid password has been passed, authorize the session
		
	} else {
		
		// needs new authorization, so show the login page
		
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
			height: 100vh;c
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

// didn't need a login, so let's proceed

$noFile = "Nothing found."; // error to output if the file does not exist or is empty
$fpassed = $_REQUEST["file"];

if ($accessCurrentDirectoryOnly) $fpassed = basename($fpassed);
if (!$accessParentDirectories) $fpassed = ltrim( str_replace("..", "", $fpassed), '/'); // if the passed file starts with a slash, remove it, and don't allow ".." directory traversal

if (!file_exists($fpassed)) {
	$foutput = $noFile;
	
} else {

	// check if it's an image
	$isImage = getimagesize($fpassed);
	if ($isImage != false) $foutput = "<img src='" . $fpassed . "'>";
	else $foutput = file_get_contents( $fpassed );

	if (!$foutput || ($preventAccessToThisFile && ($fpassed == basename(__FILE__)))) {
		$foutput = $noFile; // no file there, or it's completely empty
	} else if ($isImage == false) { 
		$foutput = trim( htmlspecialchars( $foutput ) );
	}
}

if ($_REQUEST["method"] == "ajax") {
	die($foutput); // if the method is ajax, just return the content without the rest of the HTML, CSS, JS
}

// if we got this far, output the whole page
	
?><!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="Cache-Control" content="no-store" />
	<title>Debuggr: <?= $fpassed; ?> by <?= $userName; ?></title>
	<script>
		
		// pass in some PHP variables
		baseFile = "<?= $fpassed; ?>";
		darkModeOn = <?= $startInDarkMode; ?>;
		
		// toggle the numbers using CSS and changing the button
		function toggleNums() {
			document.body.classList.toggle('linesOn');
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
		
		// toggle visual dark/lite mode
		function toggleVisualMode() {
			if (darkModeOn) document.body.classList.remove("darkMode");
			else document.body.classList.add("darkMode");
			darkModeOn = !darkModeOn;
		}
	
		// use AJAX to reload the file or to load files from the Files menu (if enabled)
		function loadFile(fileToLoad = baseFile) {
			closeFileMenu();
			codeLinesPre = document.querySelector("#codeLines pre");
			codeLinesPre.innerHTML = "Loading...";
			document.querySelector("#codeNums pre").innerHTML = "";
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200)) {
						codeLinesPre.innerHTML = this.responseText;
						prepLineNumbers(this.responseText.split("\n").length);
						baseFile = fileToLoad;
						document.title = "Debuggr: " + fileToLoad;
						document.querySelector("#filename span").innerHTML = fileToLoad;
						historyURL = "<?= $_SERVER['PHP_SELF']; ?>?file=" + fileToLoad;
						window.history.pushState( {}, "", historyURL);
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
			if (numLines > 1) {
				outputLines = "";
				padTo = numLines.toString().length + 1;
				for (x=1; x<=numLines; x++) {
					line = x + ":";
					outputLines += line.padStart(padTo, "0") + "\n";
				}
				codeNumsPre.innerHTML = outputLines;
				document.querySelector("style").innerHTML += "body.linesOn #codeNums { width: " + (padTo-1) + "rem; } body.linesOn #codeLines { width: calc(100vw - " + (padTo - 0.5) + "rem); left: " + (padTo - 0.5) + "rem; }";

			} else {
				document.querySelector("style").innerHTML += "body.linesOn #codeNums { width: 0rem; } body.linesOn #codeLines { width: calc(100vw - 0.25rem); left: 0.25rem; }";
			}
		}
		
		function openFile() {
			closeFileMenu();
			toOpen = window.prompt("Enter a filename and path:","");
			console.log("toOpen: " + toOpen);
			if ((toOpen != "") && (toOpen !== null)) {
				loadFile(toOpen);
			}
		}
		
		function closeFileMenu() {
			allLI = document.querySelectorAll("#fileNav li");
			for (x=0; x<allLI.length; x++) {
				allLI[x].classList.remove("showSub");
			}
			document.querySelector("#optionsNav li").classList.remove("showSub");
		}
		
		<? if ($passwordRequired) { ?>
		function logout() { document.getElementById("logoutForm").submit(); }
		<? } ?>
		
		// when the window loads, prep line numbers, and connect the scrollTops of #codeLines to #codeNums
		window.onload = function() {
			prepLineNumbers(document.querySelector("#codeLines pre").innerHTML.split("\n").length);
			
			document.getElementById("codeLines").onscroll = function() { document.getElementById("codeNums").scrollTop = document.getElementById("codeLines").scrollTop; }
			
			document.querySelector('#optionsNav li').onclick = function() { 
				closeFileMenu(); 
				document.querySelector('#optionsNav li').classList.toggle('showSub');
			}
			
<? if ($showFilesMenu) { ?>
			// set file menu to open on click, not just hover
			allLI = document.querySelectorAll("#fileNav li");
			for (x=0; x<allLI.length; x++) {
				allLI[x].onclick = function(event) {
					document.querySelector('#optionsNav li').classList.remove('showSub');
					this.classList.toggle("showSub");
					event.stopPropagation();
				}
			}
			
			document.getElementById("codeLines").onclick = function() { closeFileMenu(); }
			document.getElementById("codeNums").onclick = function() { closeFileMenu(); }
<? } // end PHP if $showFilesMenu ?>	
			
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
		}

		#nav span a {
			color: #ddd;
			text-decoration: none;
		}
		
		a.uicon  {
			font-weight: bold;

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
			z-index: 1;
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
			line-height: 140%;
		}
		
		input, button {
			padding: 4px;
			text-transform: uppercase;
		}

		@media only print {
			#nav {
				display: none;
			}
			#codeNums, #codeLines {
				position: absolute;
				overflow: visible;
			}
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
		
		a#menuIcon {
			text-align: right;
			font-size: 18px;
		}
		
		.menuLine {
			padding-top: 4px;
			margin-top: 4px;
			border-top: 1px solid #ccc;
		}

<? if ($showFilesMenu) { ?>
		
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
		
<? } // end CSS file menu check ?>
		
	</style>
</head>
<body class="<? if ($startWithLinesOn) { ?>linesOn<? } ?> <? if ($startInDarkMode) { ?>darkMode<? } ?>">
	<div id="nav">
		<? if ($showFilesMenu) echo fileMenu(); ?>
		<? if ($fpassed != "") { ?><span id="filename"><span><?= $fpassed; ?></span> <a class="uicon" title="Reload file" href="javascript:loadFile();">&#8635;</a> <a class="uicon"  title="Open file in new tab" href="javascript:window.open(baseFile);">&#10162;</a></span><? } ?>
		<ul id="optionsNav">
			<li><a id="menuIcon">&#9776;</a>
				<ul>
					<? if ($foutput != $noFile) { ?><li><a href="javascript:selectCode()">Select All Code</a></li>
					<li class="menuLine"><a href="javascript:toggleNums();">Toggle Line Numbers</a></li><? } ?>
					<li><a href="javascript:toggleVisualMode()">Toggle Dark Mode</a></li>
					<li class="menuLine"><a href="mailto:<?= $userEmail; ?>">Email <?= $userName; ?></a></li>
					<? if ($passwordRequired) { ?><li><a href="javascript:logout()">Log Out</a></li><? } ?>
				</ul>
			</li>
		</ul>
	</div>
	<div id="codeNums"><pre></pre></div>
	<div id="codeLines"><pre><?= $foutput; ?></pre></div>
	<? if ($passwordRequired) { ?><form method="POST" id="logoutForm"><input type="hidden" value="1" name="logout" id="logout"></form><? } ?>
</body>
</html>