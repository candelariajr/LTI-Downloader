# WNCLN
WNCLN Projects - LTI Loader

Note: A batch file with admin rights or a CLI command from a console with admin rights can start this.

All files (even batch files) need to be in the Xampp directory in htdocs.
If this reaches a state of publishing, it will be modified to change its directory params accordingly.

Commands must be run as Admin (this is to minimize failures with file r/w/rw permissions

When running from a CLI- just point : c:\xampp\php\php.exe -f C:\xampp\htdocs\LTIProcess.php
When running from HTTP- just point your browser at the page with respect to your apache HTTP root. 

A CRASH_OUT.txt file appears
A config file should be present. It should be named LTIconfig.cfg You can create this with any text editor:

Setup: Config file contains 4 lines:
 * 1 - FTP Server address
 * 2 - The name of the last set of files received
 * 3 - The name of the log file (future versions may have more sophisticated log management)
 * 4 - The date this process was last run successfully (either in the logs- or just create one yourself)
 The date is just to make sure that time has passed since the program was run.

Example:

ftp://ftp.librarytech.com/
WNCLN1067
LTIapp.log
11-02-2015