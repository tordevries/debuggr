# debuggr
A single self-contained PHP file to support reading any text file on another's server.  It was originally designed to allow a programming instructor to read server-side code written by his students. 

The code includes very basic security options, such as simple password protection, and forced SSL. If a password is required (and it should be!), access will be authorized via a session. As a result, this requires use of a cookie. 

This is not compressed/minified. A compressed version was briefly offered, but as this was designed for use in a programming class, the verbose version is now the only version, for students interested in dissecting code.

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
A Boolean value which, if true, will display a "Files" menu in the UI that links to all the files in the current directory. The default is false.
If accessCurrentDirectoryOnly is false (see above), the "Files" menu will also include local folders and their files/subdirectories.

#### startInDarkMode
A Boolean value which, if true, will start the UI in dark mode by default, rather than lite mode. The default is true.

#### startWithLinesOn
A Boolean value which, if true, will start with the line numbers showing on the left, rather than hidden. The default is true.

---
## Future Features

Here are a few of the ideas on the future radar:
- **Better nav bar interface.** It's not particularly pretty, though it works for now.
- **Responsive layout.** Right now it's not great on mobile, but it'd be nice if it were.
- **Code styling/highlighting.** It'd be nice to color the displayed code. PHP's built-in highlight_string() leaves a lot to be desired. This could be accomplished with PHP (e.g. with PEAR's Text_Highlighter) or JavaScript (e.g. with Highlight.js). 
- **Whitelisted file names for the Files menu.** Instead of reading all the local files, provide a list of specific files to be viewed, and prevent access outside that list. Useful within a distributed package, perhaps.
- **Autoreloading.** Add a feature to auto-reload a file at intervals (5 seconds?). Useful for checking error_log, for example.
- **Allow loading of remote files.** This would allow viewing the source of pages elsewhere, though only HTML/CSS/JS. Useful on mobile where it is otherwise difficult to read source code.
- **Tabbed interface.** Maybe.
