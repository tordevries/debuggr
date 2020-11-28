<? 
/*

Debuggr version 0.97 by Tor de Vries -- verbose version

A self-contained file of PHP, HTML, CSS, and JavaScript to enable reading of code files remotely.
If you set a password below -- and you really, really should -- don't forget to tell your instructor!!!

Copy this PHP code into a "debuggr.php" file in the root directory of your PHP coding, so your instructor can study your code.
Then, just add "?file=" and the name of a file to view its source code. 

Example URL: https://dtc477.net/unit3/debuggr.php?file=debuggr.php

*/

// CONFIGURATION -- edit these variables as needed

$userName = "Your Name"; // put in your own name
$userEmail = "your@email.com"; // put in your own email address

$pagePassword = "477demo"; // set a password
$passwordRequired = true; // if true, requires a password and temporary session authorization to view the file; you really should leave this as true
$forceSSL = true; // if true, redirects HTTP requests to HTTPS

$accessCurrentDirectoryOnly = true; // if true, restricts access to only files in this same directory as this file, no subdirectories allowed
$accessParentDirectories = false; // if true, allows users to enter pathnames to parent directories, using '../'
$preventAccessToThisFile = true; // if true, prevents users from reading this PHP file with itself

$showFilesMenu = false; // if true, will show a "Files" menu that links to files in the current directory
// note: if $accessCurrentDirectoryOnly is false, the "Files" menu will include local folders and their files/subdirectories

$startInDarkMode = true; // true to start in dark mode by default; false to start in lite mode


// ********************************************************************************
//
// WARNING: CODE BELOW
// Ignore everything below this line unless you really know what you're doing.
//
// ********************************************************************************


// a recursive function that returns a multidimensional array of files/folders in local directory; 
// adapted from user-submitted code on https://www.php.net/manual/en/function.scandir.php
function findAllFiles($dir = '.') { 
	$result = array();
	$cdir = scandir($dir);
	foreach ($cdir as $key => $value) {
		if ( !in_array( $value, array(".", "..") ) ) {
			 if ( is_dir($dir . DIRECTORY_SEPARATOR . $value) ) $result[$value] = findAllFiles( $dir . DIRECTORY_SEPARATOR . $value );
			 else $result[] = $value;
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
	$result .= "</ul>\n";
	return $result;
}

// create a file menu
function fileMenu($dir = '.') {
	$list = findAllFiles($dir);
	$listHTML = "<ul id='filenav'><li>Files &#9650;" . buildFileMenu($list) . "</li></ul>";
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

// for security, if the session is not authorized, show login form if necessary, or check submitted password
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

// didn't need a login, so let's proceed

$noFile = "Nothing found."; // error to output if the file does not exist or is empty
$fpassed = $_REQUEST["file"];

if ($accessCurrentDirectoryOnly) $fpassed = basename($fpassed);
if (!$accessParentDirectories) $fpassed = ltrim( str_replace("..", "", $fpassed), '/'); // if the passed file starts with a slash, remove it, and don't allow ".." directory traversal

if ($fpassed == "") {
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
	<meta http-equiv="Cache-Control" content="no-store" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Debuggr: <?= $fpassed; ?> by <?= $userName; ?></title>
	<script>
		
		// pass in some PHP variables
		darkModeOn = <?= $startInDarkMode; ?>;
		baseFile = "<?= $fpassed; ?>";
		
		// toggle the numbers using CSS and changing the button
		function toggleNums() {
			document.body.classList.toggle('linesOn');
			if (document.getElementById('btnToggle').innerHTML == "Show Line #s") document.getElementById('btnToggle').innerHTML = "Hide Line #s";
			else document.getElementById('btnToggle').innerHTML = "Show Line #s";
		}
		
		// select the code
		function selectCode() {
			var r = document.createRange();
			var w = document.getElementById("codeLines");  
			r.selectNodeContents(w);  
			var sel = window.getSelection(); 
			sel.removeAllRanges(); 
			sel.addRange(r);
		}
		
		// toggle visual dark/lite mode
		function toggleVisualMode() {
			if (darkModeOn) {
				document.body.classList.remove("darkMode");
				document.querySelector("#visualMode button").innerHTML = "Dark Mode";
			} else {
				document.body.classList.add("darkMode");
				document.querySelector("#visualMode button").innerHTML = "Lite Mode";
			}
			darkMode = !darkMode;
		}
	
		// use AJAX to load or reload files
		function loadFile(fileToLoad = baseFile) {
			codeLinesPre = document.querySelector("#codeLines pre");
			codeLinesPre.innerHTML = "Loading...";
			document.querySelector("#codeNums pre").innerHTML = "";
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200)) {
						codeLinesPre.innerHTML = this.responseText;
						baseFile = fileToLoad;
						historyURL = "<?= $_SERVER['PHP_SELF']; ?>?file=" + fileToLoad;
						window.history.pushState( {}, "", historyURL);
						document.querySelector("#filename span").innerHTML = fileToLoad;
						prepLineNumbers(this.responseText.split("\n").length);
					} else if ((this.readyState == 4) && (this.status != 200)) {
						codeLinesPre.innerHTML = "<?= $noFile; ?>";
						console.log("AJAX error: " + this.responseText);
					}
			}
			urlToLoad = "<?= $_SERVER['PHP_SELF']; ?>?file=" + fileToLoad + "&method=ajax";
			ajax.open("POST", urlToLoad, true);
			ajax.send();
		}
		
		// output line numbers in #codeNums pre
		function prepLineNumbers(numLines = 1) {
			codeNumsPre = document.querySelector("#codeNums pre");
			codeNumsPre.innerHTML = "";
			outputLines = "";
			padTo = numLines.toString().length + 1;
			for (x=1; x<=numLines; x++) {
				line = x + ":";
				outputLines += line.padStart(padTo, "0") + "\n";
			}
			codeNumsPre.innerHTML = outputLines;
		}
		
		// when the window loads, prep line numbers, and connect the scrollTops of #codeLines to #codeNums
		window.onload = function() {
			prepLineNumbers(document.querySelector("#codeLines pre").innerHTML.split("\n").length);
			document.getElementById("codeLines").onscroll = function() {
				document.getElementById("codeNums").scrollTop = document.getElementById("codeLines").scrollTop;
			}
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
			font-size: 18px;
			height: 44px;
			padding: 8px;
			z-index: 100;
			overflow: visible;
		}

		#nav span {
			margin-right: 50px;
		}

		#nav span a {
			color: #ddd;
		}

		#nav span#user,
		#nav span#reload,
		#nav span#logout,
		#nav span#visualMode {
			float: right;
			margin: 0 0 0 20px;
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
			transition: left 0.5s;
			z-index: 1;
		}
		
		body.linesOn #codeNums {
			left: 0.25rem;
		}
		
		body.darkMode #codeNums {
			color: #a9e3e8;
		}
		
		#codeLines {
			position: absolute;
			top: 0;
			left: 0.25rem;
			height: calc(100vh - 44px);
			width: 100vw;
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
				position: relative;
				bottom: auto;
				top: 0;
				width: 100%;
				padding: 0;
				color: #000;
				border-bottom: 1px solid #000;
			}
			#nav a {
				display: none;
			}
			#code {
				position: relative;
				display: inline;
			}
		}

<? if ($showFilesMenu) { ?>
		
		#filenav {
			overflow: auto;
			float: left;
			z-index: 100;
			padding-right: 1rem;
			margin-right: 1rem;
			border-right: 1px solid #fff;
		}

		#filenav a {
			display: block;
			padding: 0.2rem 2rem 0.2rem 0.5rem;
			font-size: 0.8rem;
			text-decoration: none;
			color: #000000;
		}

		#filenav a:hover {
			background-color: #bbbbbb;
		}

		#filenav ul {
			list-style-type: none;
			margin: 0;
			padding: 0;
			background-color: #fff;
			border: 1px solid #ccc;
			z-index: 100;
		}
		
		#filenav ul ul {
			border-left: 1px solid #000;
		}

		#filenav li {
			float: left;
			width: 100%;
			white-space: nowrap;
		}
		
		#filenav li.hasSub::before {
			content: ">";
			width: 2rem;
			text-align: right;
			font-size: 0.8rem;
			padding: 0.2rem;
			float: right;
			color: #777;
		}
		
		#filenav li.hasSub li::before {
			content: "";
		}

		#filenav li.hasSub li.hasSub::before {
			content: ">";
		}
		
		#filenav li li {
			clear: left;
			width: 100%;
		}

		#filenav ul,
		#filenav li:hover ul ul {
			display: none;
			position: absolute;
		}

		#filenav li:hover ul {
			display: block;
			position: absolute;
			bottom: 30px;
		}

		#filenav li:hover li:hover {
			position: relative;			
		}
		
		#filenav li:hover li:hover ul {
			display: block;
			left: 100%;
			bottom: 0;
		}
		
		#filenav li:hover li:hover ul ul {
			display: none;
		}

		#filenav li:hover li:hover li:hover ul {
			display: block;
			left: 100%;
			bottom: 0;
		}
		
<? } ?>
		
	</style>
</head>
<body class="linesOn <? if ($startInDarkMode) { ?>darkMode<? } ?>">
	<div id="codeNums"><pre></pre></div>
	<div id="codeLines"><pre><?= $foutput; ?></pre></div>
	<div id="nav">
		<? if ($showFilesMenu) echo fileMenu(); ?>
		<span id="filename"><span><?= $fpassed; ?></span> <button onclick="loadFile();">Reload</button> <button onclick="window.open(baseFile);">Open</button></span>
		<? if ($foutput != $noFile) { ?>
			<span id="stats"><button id="btnToggle" onclick="toggleNums();">Hide Line #s</button> <button onclick="selectCode()">Select Code</button></span>
		<? } ?>
		<span id="visualMode"><button onclick="toggleVisualMode()"><? if ($startInDarkMode) { ?>Lite<? } else { ?>Dark<? } ?> Mode</button></span>
		<span id="logout"><? if ($passwordRequired) { ?><form method="POST"><input type="hidden" value="1" name="logout" id="logout"><button type="submit">Log Out</button></form><? } ?></span>
		<span id="user"><a href="mailto:<?= $userEmail; ?>"><button>Email</button></a></span>
	</div>
</body>
</html>