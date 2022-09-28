# <img alt="icon" src="https://raw.githubusercontent.com/tordevries/debuggr/main/images/debuggr-icon.png" />&nbsp;debuggr

<img alt="Screenshot of Debuggr examining itself" src="https://raw.githubusercontent.com/tordevries/debuggr/main/images/debuggr-screenshot.png" style="width: 100%; height: auto;" />

_Screenshot of Debuggr examining its own JavaScript code, with a few line numbers highlighted._

**A tool to read the source code of remote HTML, CSS, JavaScript, and other web-accessible files, as well as locally-available code and configuration files (PHP, etc.) also hosted by its web server.** It is mobile-friendly, which makes it useful for looking at remote source code (HTML, CSS, JavaScript, etc.) via smartphones and other mobile devices.

Debuggr is a single self-contained PHP file (debuggr.php) that generates its own HTML and favicon, and uses CDNs to load its supporting CSS and JavaScript files. It was originally developed by a programming instructor to enable remote reading of student server-side coding without administrative server access. As a single file, it is easy to install and manage, especially for beginner coding students.

Debuggr is licensed under the GNU General Public License, as [noted below](#license) and detailed in the LICENSE.txt file.

#### Security Notice
Debuggr includes basic security options such as simple password protection, file access restrictions, and forced SSL. If a password is required (and it should be!), access will be authorized via a session (which requires use of a browser cookie). However, installing Debuggr may expose your server to security risks if someone is able read files that contain passwords or other sensitive information. As a result, it is meant to be installed for individual use, and not as a system-wide resource or on mission-critical systems. It should also be removed or disabled once it is no longer needed. As noted in the license, _your use of this code is entirely at your own risk_.

---
## Installation

To install, upload the debuggr.php file to your web hosting. Then, configure the variable options near the beginning of the document. If nothing else, you _must_ change the values of these settings:
- **userName**: set to your name
- **userEmail**: set to your email
- **pagePassword**: set to a secure password that you only share with trusted contacts

Debuggr will show an error if these settings are unchanged from their defaults.

Additional configuration options are described further below. Note that Debuggr requires that your web server runs a recent version of PHP, and remote URL access additionally requires the cURL libraries, which are commonly available on PHP-enabled servers.

Once installed and configured, simply access debuggr.php at the URL to which you installed it.

---
## Features and Use

The main Debuggr interface shows the loaded code with line and column numbers (if enabled), with a navigation bar across the bottom.
 
Line numbers can be clicked to highlight them. These clicks are tracked in the hash of Debuggr's URL, thus making the Debuggr file and highlights shareable.

Debuggr's navigation bar lists the current filename or URL with a reload icon. It also offers two menus: the Files menu in the lower left corner, and the Options menu in the lower right.

The Files menu (left side of the nav bar) offers these commands:
- **Reload File**. Checks if the file and/or menu has been updated, and if so, reloads new data via AJAX. Remote URLs are always reloaded.
- **Reload and Tidy (beta)**. Reloads the current file and parses it with the PHP Tidy library in an attempt to beautify the code. Results may vary.
- **Execute File**. Opens the file directly in a new browser tab, so your browser is reading/executing it directly.
- **Download File**. Downloads the current file to your device as a text file.
- **Copy Shareable Link**. Copies a shareable link to the currently-displayed Debuggr screen, preserving line highlights.
- **Select All Text**. Selects all the text/code in the browser without selecting line numbers or other UI elements.
- **Go To Line...**. Asks you for a line number, then scrolls to it.
- **Open File/URL...**. Asks you for a file name or a complete URL (if _allowRemoteFileReading_ is set to true) to read.

In addition, if the configuration setting _showFilesMenu_ is set to true, the Files menu will include a list of files in its same directory. And if _accessCurrentDirectoryOnly_ is set to false, the file list will include folders in a hierarchical menu.

The Options menu (right side of the nav bar) offers these commands:
- **Dark Mode**. Toggles the UI between Dark Mode and Lite Mode.
- **Line Numbers**. Toggles the display of line numbers on the left margin.
- **Column Markers**. Toggles the display of column markers every 10 characters.
- **Auto-load updates**. Enables an automatic reload check (see above) every 5 seconds.
- **Email User**. Provides a mailto link with the host's name and email, if configured in the options.
- **Log Out**. Logs you out, ending your session.
- **Debuggr Info**. Links to this page on GitHub.

When you access debuggr.php, you can add a parameter named "file" or "f" set to the filename (or pathname) of the file you want to read. For example, these URLs...

https://yourdomain.com/debuggr.php?file=error_log

https://yourdomain.com/debuggr.php?f=error_log

...would tell Debuggr to display PHP's protected "error_log" file in the same directory as Debuggr, which normally cannot be read by a web browser.

It also accepts just the file name after the question mark:

https://yourdomain.com/debuggr.php?error_log

If the configuration _allowRemoteFileReading_ is set to true (which it is by default), a complete URL can be substituted for the filename, and Debuggr will scrape its source. For example, this URL would display the HTML source code of this project on GitHub:

https://yourdomain.com/debuggr.php?file=https://github.com/tordevries/debuggr

Debuggr's remote scraping will automatically follow most redirects. However, it may not follow HTTP to HTTPS redirects. If your URL is HTTP (e.g. not secure) and Debuggr returns a blank page, try it with HTTPS. _Note: Debuggr can only scrape what is publicly accessible through any web browser, so it cannot read any server-side code remotely (e.g. PHP and other files); it cannot access pages/files that require passwords; and some sites block such scraping._

In addition, the debuggr.php file can be renamed to any other .php filename. For example, you can rename it to index.php and place it in a directory, which allows a URL format like this:

https://yourdomain.com/code/?error_log

With both local and remote files, Debuggr attempts to recognize and render image, audio, and video formats in a usable format: images and video are displayed visually, and audio and video are displayed with HTML5 player controls.


---
## Configuration Options

There are several PHP variables to configure access and output.

#### userName (required)
A string variable for the host coder's name. Debuggr will not operate if this is not changed from the default.

#### userEmail (required)
A string variable for the host coder's email address, to enable the "Email" option. Debuggr will not operate if this is not changed from the default.

#### pagePassword (required)
A string variable for the password. It's strongly suggested that you use this. Once authorized, PHP will set a session variable to keep the same user/browser authorized for awhile (typically ~24 minutes since the last access, for most PHP session settings). If you change the password, existing authorized sessions will have to reauthorize. However, if you allow users to read this file directly (see _preventAccessToThisFile_ below) then they will be able to read your password. There are many password generators online, such as [this one from LastPass](https://www.lastpass.com/password-generator) (though no endorsement is implied by linking to it). Debuggr will not operate if this is not changed from the default.

#### passwordRequired
A Boolean value which, if true, requires the user to enter the password and, and then initiates temporary session authorization to view the file. The default is true.

#### forceSSL
A Boolean value which, if true, will redirect all HTTP requests to HTTPS for security purposes. The default is true. (You really ought to have an SSL certificate installed and be using HTTPS!)

#### accessCurrentDirectoryOnly
A Boolean value which, if true, restricts user access to only files in this same directory as this file, with no subdirectories allowed in the parameter pathname. The default is true.

#### accessParentDirectories
A Boolean value which, if true, allows users to enter pathnames to parent directories in the "?file=" parameter, using '../' to navigate directories. The default is false.

#### preventAccessToThisFile
A Boolean value which, if true, prevents users from reading this PHP file with itself. The default is true. The only scenario where you want this to be "false" is if you have configured a set of default values of this code that you want someone else to copy.

#### allowRemoteFileReading
A Boolean value which, if true, allows Debuggr to attempt to scrape content from remote URLs. The default is true. There are some limitations: this requires your server's PHP to include standard cURL libraries (it probably does); this can only read the same source code you could see in a web browser (not any remote server-side code); by default this bypasses HTTPS security checks (so there is a chance of man-in-the middle attacks), but see the cURL options below; not every web site responds consistently to cURL calls; and finally, this can only read publicly-available pages and not any remote web page that requires a password. Note that Debuggr copies your browser's user agent when accessing other sites.

#### showFilesMenu
A Boolean value which, if true, will update the File menu with links to all the files in the current directory. The default is false. If _accessCurrentDirectoryOnly_ is false (see above), the "Files" menu will also include local folders and their files/subdirectories in hierarchical menu. Note that the reload and auto-reload functions will check and dynamically reload updated menu contents via AJAX.

#### highlightCode
A Boolean value which, if true, will include references to load [Highlight.js](https://highlightjs.org/) (also on [Github](https://github.com/highlightjs/highlight.js)) and a collection of CSS to provide basic code syntax highlighting. Debuggr also will attempt to hyperlink URLs in href and src attributes, with the links loading into Debuggr. The default is true.

#### startInDarkMode
A Boolean value which, if true, will start the UI in dark mode by default, rather than lite mode. The default is true.

#### startWithLinesOn
A Boolean value which, if true, will start with the line numbers showing on the left, rather than hidden. The default is true.

#### startWithColsOn
A Boolean value which, if true, will start with the column markers showing every 10 characters, rather than hidden. The default is true.

#### logTimings
A Boolean value which, if true, instructs Debuggr to record how long processing each request has taken. The default is false.

#### logTimingsFilename
A string variable for the path and filename of the log file, if logTimings is true. The default is "debuggr-timing.txt".

#### logTimingsTimestamp
A string variable for the format of the PHP date function used in the timing logs, if logTimings is true. The default is month/day/year plus 24-hour hour:minute:second, or "m/d/Y H:i:s".

#### allowCURLtoBypassHTTPS (advanced)
A Boolean value which, if true, and if _allowRemoteFileReading_ is true, will instruct cURL to load remote HTTPS pages without a complete SSL certificate check. _This is a security risk;_ you may be subject to a MITM (man in the middle) HTTPS attack. However, this is a low security risk as long as you are reading publicly-accessible URLs without passing usernames or other identifiable information. The default is true. If set to false, you should configure _certificatePathForCURL_ as noted below.

#### certificatePathForCURL (advanced)
A string variable containing an absolute path to your web server's SSL security certificate for use by cURL when _allowCURLtoBypassHTTPS_ is false. The default is "/etc/ssl/certs", though it is impossible to predict if that will work for your server. This setting has no effect if _allowCURLtoBypassHTTPS_ is true.

---
## Future Features

Some ideas on the future radar:
- **Whitelisted or blacklisted file names for the Files menu.** Instead of reading all the local files, provide a list of specific files to be viewed, and prevent access outside that list. Useful within a distributed package.
- **Timeout management.** Allow a specified timeout with a forced logout, not just passively relying on PHP's session timeout setting.
- **Better security.** I'd like to add limits on wrong passwords, maybe IP velocity checks, to prevent brute-force hacks on the password.
- **Analytics.** Could be interesting to integrate with your own Google Analytics account so you could track usage.

---
## License

Copyright (C) 2020-2022 Tor de Vries (tor.devries@wsu.edu)

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Your use of this code is entirely at your own risk. See the GNU General Public License for more details.

The complete license is available in the LICENSE.txt file accompanying this project, or online
at <https://www.gnu.org/licenses/gpl-3.0.en.html>.

The [Highlight.js](https://highlightjs.org/) library is under the BSD-3 license.
