<? 
/*

Debuggr version 0.99 by Tor de Vries

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

$showFilesMenu = false; // if true, will show a "Files" menu that links to files in the current directory
// note: if $accessCurrentDirectoryOnly is false, the "Files" menu will include local folders and their files/subdirectories

$highlightCode = true; // true to load Highlight.js for coloring text
$startInDarkMode = true; // true to start in dark mode by default; false to start in lite mode
$startWithLinesOn = true; // true to start with the line numbers visible
$showDebuggrLink = true; // true to include a link to Debuggr on Github in the options menu


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

// create an unordered list CSS-based file menu
function fileMenu($dir = '.') {
	global $showFilesMenu;
	if ($showFilesMenu) $list = findAllFiles($dir);
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
		lineNumbersOn = <?= json_encode($startWithLinesOn); ?>;
		darkModeOn = <?= json_encode($startInDarkMode); ?>;
		
		// toggle the numbers using CSS and changing the button
		function toggleNums() {
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
			if (darkModeOn) {
				document.body.classList.remove("darkMode");
				document.querySelector("#optDarkMode span").innerHTML = "&nbsp;";
			} else {
				document.body.classList.add("darkMode");
				document.querySelector("#optDarkMode span").innerHTML = "&check;";
			}
			darkModeOn = !darkModeOn;
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
		
		// use AJAX to reload the file or to load files from the Files menu (if enabled)
		function loadFile(fileToLoad = baseFile) {
			closeMenus();
			codeLinesPre = document.querySelector("#codeLines pre");
			codeLinesPre.innerHTML = "Loading...";
			document.querySelector("#codeNums pre").innerHTML = "";
			ajax = new XMLHttpRequest();
			ajax.onreadystatechange = function() {
					if ((this.readyState == 4) && (this.status == 200)) {
						codeLinesPre.innerHTML = this.responseText;
						styleCode();
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
			outputLines = "";
			padTo = numLines.toString().length + 1;
			for (x=1; x<=numLines; x++) {
				line = x + ":";
				outputLines += line.padStart(padTo, "0") + "\n";
			}
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
			for (x=0; x<allLI.length; x++) {
				allLI[x].classList.remove("showSub");
			}
			document.querySelector("#optionsNav li").classList.remove("showSub");
		}
		
		function styleCode() { // apply Highlights.js
			<? if ($highlightCode) { ?>hljs.highlightBlock( document.querySelector('#codeLines pre') );<? } ?>
		}
		
		function logout() { document.getElementById("logoutForm").submit(); }
		
		// when the window loads, prep line numbers, and connect the scrollTops of #codeLines to #codeNums
		window.onload = function() {
			prepLineNumbers(document.querySelector("#codeLines pre").innerHTML.split("\n").length);
			document.getElementById("codeLines").onscroll = function() { 
				document.getElementById("codeNums").scrollTop = document.getElementById("codeLines").scrollTop; 
			}
			document.querySelector('#optionsNav li').onclick = function() { 
				closeMenus();
				document.querySelector('#optionsNav li').classList.toggle('showSub');
			}
			
			// set file menu to open on click, not just hover
			allLI = document.querySelectorAll("#fileNav li");
			for (x=0; x<allLI.length; x++) {
				allLI[x].onclick = function(event) {
					document.querySelector('#optionsNav li').classList.remove('showSub');
					this.classList.toggle("showSub");
					event.stopPropagation();
				}
			}
						
			document.getElementById("codeLines").onclick = function() { closeMenus(); }
			document.getElementById("codeNums").onclick = function() { closeMenus(); }

			styleCode();
			
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
		
		#codeNums pre, #codeLines pre {
			padding-top: 0.5em;
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
		<?= fileMenu(); ?>
		<? if ($fpassed != "") { ?><span id="filename"><span><?= $fpassed; ?></span> <a class="uicon" title="Reload file" href="javascript:loadFile();">&#8635;</a> <a class="uicon"  title="Open file in new tab" href="javascript:window.open(baseFile);">&#10162;</a></span><? } ?>
		<ul id="optionsNav">
			<li><a id="menuIcon">&#9776;</a>
				<ul>
					<li><a href="javascript:selectCode()"><span>&nbsp;</span> Select All Code</a>
					<li id="optDarkMode" class="menuLine"><a href="javascript:toggleVisualMode()"><span><?= ($startInDarkMode ? "&check;" : "&nbsp;") ?></span> Dark Mode</a></li>
					<li id="optLineNumbers"><a href="javascript:toggleNums();"><span><?= ($startWithLinesOn ? "&check;" : "&nbsp;") ?></span> Line Numbers</a></li>
					<li class="menuLine"><a href="mailto:<?= $userEmail; ?>"><span>&nbsp;</span> Email <?= $userName; ?></a></li>
					<? if ($passwordRequired) { ?><li><a href="javascript:logout()"><span>&nbsp;</span> Log Out</a></li><? } ?>
					<? if ($showDebuggrLink) { ?><li class="menuLine"><a href="https://github.com/tordevries/debuggr" target="_blank"><span>&nbsp;</span> Debuggr Info</a></li><? } ?>
				</ul>
			</li>
		</ul>
	</div>
	<div id="codeNums"><pre></pre></div>
	<div id="codeLines"><pre><?= $foutput; ?></pre></div>
	<? if ($passwordRequired) { ?><form method="POST" id="logoutForm"><input type="hidden" value="1" name="logout" id="logout"></form><? } ?>
</body>
</html>