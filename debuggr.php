<? 
/*

Debuggr version 0.9 by Tor de Vries

Copy this PHP code into a "debuggr.php" file in the root directory of your PHP coding, so your instructor can study your code.
If you set a password (below) don't forget to tell your instructor.

Some explanation of this code is at the end.

Sample URL: 
https://dtc477.net/unit3/debuggr.php?file=debuggr.php
*/

// CONFIGURATION

$userName = "Tor de Vries"; // put in your own name
$userEmail = "tor.devries@wsu.edu"; // put in your own email address
$pagePassword = "477demo"; // set a password string

$passwordRequired = true; // if true, requires a password and temporary session authorization to view the file
$accessCurrentDirectoryOnly = false; // if true, restricts users only to files in this same directory as Debuggr
$accessParentDirectories = false; // if true, allows users to enter pathnames to parent directories





// CODE -- ignore everything below this line unless you know what you're doing

// for security, redirect to HTTPS if it's not HTTPS
if (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on") {
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"], true, 301);
    die();
}

// for security, kill the output if password is required but not set
if ($passwordRequired && ($pagePassword == "")) {
	die("ERROR: No password set.");
}

session_start();

// has a logout command been passed?
if ($_POST["logout"]) {
	session_unset();
	session_destroy();
	$_SESSION["authorized"] = false;
}

// for security, if the session is not authorized, show login form if necessary, or check submitted password
if ($passwordRequired && !$_SESSION["authorized"]) {
	if ($_POST["pwd"] == $pagePassword) {
		$_SESSION["authorized"] = true;
	} else {
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
		input, button { padding: 4px; }
	</style>
</head>
<body>
	<div id="pageBox">
		<div><form method="POST">
			<input type="password" id="pwd" name="pwd" value="">
			<button type="submit">LOG IN</button>
		</form></div>
	</div>
</body>
</html>
<?
		die();
	}
}

$noFile = "Nothing found."; // in case the file does not exist or is empty
$fpassed = $_REQUEST["file"];
if ($accessCurrentDirectoryOnly) $fpassed = basename($fpassed);
if (!$accessParentDirectories) $fpassed = ltrim( str_replace("..", "", $fpassed), '/'); // if the passed file starts with a slash, remove it, and don't allow ".." directory traversal
$foutput = htmlspecialchars( file_get_contents( $fpassed ) ); // convert HTML code to special characters than can be displayed
$c = 2;

if (!$foutput) {
	$foutput = $noFile; // no file there, or it's completely empty
	
} else { 
	$x = 1;
	$fx = explode("\n", trim($foutput));
	$fcount = count($fx);
	if ($fcount > 99) $c = 3;
	if ($fcount > 999) $c = 4;
	foreach($fx as &$fline) $fline = "<div class='ro'><span class='no'>" . str_pad($x++, $c, "0", STR_PAD_LEFT) . ":</span><span class='co'>" . $fline . "</span></div>";
	$foutput = implode("\n", $fx);
}

?><!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Cache-Control" content="no-store" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Debuggr: <?= $fpassed; ?> by <?= $userName; ?></title>
	<link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400&display=swap" rel="stylesheet">
	<style>
		* { 
			font-family: 'Source Code Pro', monospace;
			tab-size: 3;
			margin: 0; 
			padding: 0; 
			box-sizing: border-box; 
		}
		#nav {
			background-color: #555;
			color: #ddd;
			position: fixed;
			bottom: 0;
			left: 0;
			width: 100vw;
			font-size: 18px;
			height: 44px;
			padding: 8px;
		}
		#nav span {
			margin-right: 50px;
		}
		#nav span a {
			color: #ddd;
		}
		#nav span#user, #nav span#reload, #nav span#logout {
			float: right;
			margin: 0 0 0 20px;
		}
		#code {
			position: absolute;
			overflow: auto;
			width: 100vw;
			height: calc(100vh - 44px);
			white-space: pre;
		}
		#code .ro {
			position: relative;
			float: left;
			width: 100vw;
		}
		#code .no {
			position: relative;
			width: <?= $c; ?>rem;
			background-color: #fff;
			color: #aaa;
			font-weight: 300;
			display: inline-block;
			margin-left: 0.5rem;
			overflow: hidden;
			transition: width 0.5s;
		}
		#code.gone .no { 
			width: 0rem;
		}
		#code .co {
			position: relative;
			display: inline-block;
			overflow: hidden;
			margin: 0 5vw 0 0.5vw;
		}
		input, button { padding: 4px; }
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
			#nav a { display: none; }
			#code {
				position: relative;
				display: inline;
			}
		}
	</style>
</head>
<body class="linesOn">
	<div id="nav">
		<? if ($foutput != $noFile) {
		?><span id="filename">File: <?= $fpassed; ?> <button onclick="window.location.href=window.location.href; return false;">RELOAD</button> <button onclick="window.open('<?= $fpassed; ?>');">OPEN</button></span>
		<span id="stats">Lines: <?= $fcount; ?> <button onclick="document.getElementById('code').classList.toggle('gone');">HIDE #s</button></span>
		<? } ?>
		<span id="logout"><? if ($passwordRequired) { ?><form method="POST"><input type="hidden" value="1" name="logout" id="logout"><button type="submit">LOG OUT</button></form><? } ?></span>
		<span id="user"><a href="mailto:<?= $userEmail; ?>">Email <?= $userName; ?></a></span>
	</div>
	<div id="code"><?= $foutput; ?></div>
</body>
</html>
<?
/*

Here are some explanations of the PHP statements and functions above:

$_REQUEST["file"] -- gets the value of what's passed in the URL with the "file" variable, hopefully a file name

str_replace() -- in a specified string, search for a specified string and replace it with another specified string

ltrim() -- remove characters from the beginning (left side) of a string

file_get_contents() -- opens and reads that file into PHP as a string, returning an empty string if the file is not found

htmlspecialchars() -- converts HTML characters (like <) into displayable character references (like &lt;) so they can be viewed in a browser; helps eliminate hacking too

explode() -- converts a string into an array of strings based on a delimiter character (in this case, a \n line break) to split it 

foreach -- a way to loop through each of the elements in the array; uses "&" to tell the loop to edit the actual array values and not just reference copies of the values

str_pad() -- tests the length of a string and, if necessary, pads it with the specified characters at the left or right ends to reach the specified length

implode() -- opposite of explode(): converts an array into one single string with an optional delimiter character

*/
