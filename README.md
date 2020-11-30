# debuggr
A PHP file to support reading any text file on another's server.  It was originally designed to allow a programming instructor to read server-side code written by his students. 
By design, it is a single self-contained file with all the HTML, CSS, JavaScript, and PHP necessary to accomplish its task. This makes it easier to install and manage, with less file clutter, especially for beginner coders.

Debuggr includes basic security options, such as simple password protection, file access restrictions, and forced SSL. If a password is required (and it should be!), access will be authorized via a session. As a result, this requires use of a cookie. 

Debuggr is licensed under the GNU General Public License, as noted below and detailed in the LICENSE.txt file.

---
## Installation and Use
Whether you use the verbose or minified versions, installation is the same: upload the debuggr.php file to your hosting, and configure the variable options at the beginning of the document.

Then, when you access file, add a parameter named "file" set to the filename (or pathname) of the file you want to read.

For example: this URL...

https://yourdomain.com/debuggr.php?file=readme.txt

...would read the file "readme.txt" in the same directory as "debuggr.php" (assuming you had the password, etc.)

---
## Options
There are several PHP variables to configure access and output.

#### userName
A string variable for the host coder's name.

#### userEmail
A string variable for the host coder's email address, to enable the "Email" button.

#### pagePassword
A string variable for the password. It's strongly suggested that you use this. Once authorized, PHP will set a session variable to keep the same user/browser authorized for awhile (typically ~24 minutes since the last access, for most PHP session settings). If you change the password, existing authorized sessions will have to reauthorize. However, if you allow users to read this file directly (see preventAccessToThisFile, below) then they will be able to read your password.

#### passwordRequired
A Boolean value which, if true, requires the user to enter the password and, and then initiates temporary session authorization to view the file. The default is true.

#### forceSSL
A Boolean value which, if true, will redirect all HTTP requests to HTTPS for security purposes. The default is true.

#### accessCurrentDirectoryOnly
A Boolean value which, if true, restricts user access to only files in this same directory as this file, with no subdirectories allowed in the parameter pathname. The default is true.

#### accessParentDirectories
A Boolean value which, if true, allows users to enter pathnames to parent directories in the "?file=" parameter, using '../' to navigate directories. The default is false.

#### preventAccessToThisFile
A Boolean value which, if true, prevents users from reading this PHP file with itself. The default is true. The only scenario where you want this to be "false" is if you have configured a set of default values of this code that you want someone else to copy.

#### showFilesMenu
A Boolean value which, if true, will update the File menu with links to all the files in the current directory. The default is false. If accessCurrentDirectoryOnly is false (see above), the "Files" menu will _also_ include local folders and their files/subdirectories. Also, the reload and auto-reload functions will check and dynamically reload updated file menus via AJAX.

#### highlightCode
A Boolean value which, if true, will include references to load Highlight.js and a collection of CSS to provide basic code styling/highlighting. The default is true.

#### startInDarkMode
A Boolean value which, if true, will start the UI in dark mode by default, rather than lite mode. The default is true.

#### startWithLinesOn
A Boolean value which, if true, will start with the line numbers showing on the left, rather than hidden. The default is true.

#### $showDebuggrLink
A Boolean value which, if true, includes a link in the options menu to this project's Github page. The default is true.

---
## Future Features

Here are a few of the ideas on the future radar:
- **Whitelisted file names for the Files menu.** Instead of reading all the local files, provide a list of specific files to be viewed, and prevent access outside that list. Useful within a distributed package.
- **Allow loading of remote files.** This would allow viewing the source of pages elsewhere using cURL, though obviously only HTML/CSS/JS. Useful on mobile where it is otherwise difficult to read source code.

--
## License

Copyright (C) 2020 Tor de Vries (tor.devries@wsu.edu)

This program is free software: you can redistribute it and/or modify it under the terms of the 
GNU General Public License as published by the Free Software Foundation, either version 3 of 
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

The complete license is available in the LICENSE.txt file accompanying this project, or online
at <https://www.gnu.org/licenses/gpl-3.0.en.html>.