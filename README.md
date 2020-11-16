# debuggr
A single self-contained PHP file to support reading any text file on another's server.  It was originally designed to allow a programming instructor to read server-side code written by his students. 

The code includes very basic security options, such as simple password protection, and forced SSL. If a password is required (and it should be!), access will be authorized via a session. As a result, this requires use of a cookie.

## Installation and Use
Whether you use the verbose or minified versions, installation is the same: upload the debuggr.php file to your hosting, and configure the variable options at the beginning of the document.

Then, when you access file, add a parameter named "file" set to the filename (or pathname) of the file you want to read.Then

For example: this URL...

https://yourdomain.com/debuggr.php?file=readme.txt

...would read the file "readme.txt" in the same directory as "debuggr.php" (assuming you had the password, etc.)


## Options
There are several PHP variables to configure access and output.

#### userName
A string variable for the host coder's name

#### userEmail
A string variable for the host coder's email address, to enable the "Email" button.

#### pagePassword
A string variable for the password. It's strongly suggested that you use this. Once authorized, PHP will set a session variable to keep the same user/browser authorized for awhile (typically ~24 minutes since the last access, for most PHP session settings).
However, it's worth noting that if you allow users to read this file directly (see preventAccessToThisFile, below) then they will be able to read your password.

#### passwordRequired
A Boolean value which, if true, requires the user to enter the password and, and then initiates temporary session authorization to view the file. The default is true.

#### forceSSL
A Boolean value which, if true, will redirect all HTTP requests to HTTPS for security purposes. The default is true.

#### accessCurrentDirectoryOnly
A Boolean value which, if true, restricts user access to only files in this same directory as this file, with no subdirectories allowed in the parameter pathname. The default is true.A

#### accessParentDirectories
A Boolean value which, if true, allows users to enter pathnames to parent directories in the "?file=" parameter, using '../' to navigate directories. The default is false.A

#### preventAccessToThisFile
A Boolean value which, if true, prevents users from reading this PHP file with itself. The default is true.

#### showFilesMenu
A Boolean value which, if true, will display a "Files" menu in the UI that links to all the files in the current directory. The default is false.

(If accessCurrentDirectoryOnly is false, the "Files" menu will also include local folders and their files/subdirectories, but not parent directories.)

#### startInDarkMode
A Boolean value which, if true, will start the UI in dark mode by default, rather than lite mode. The default is true.