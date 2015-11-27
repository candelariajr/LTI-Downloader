<?php
/**
 * Version 0.3b
 * Created by PhpStorm.
 * User: candelariajr
 * Date: 11/19/2015
 * Time: 12:45 PM
 */

/** VERSION MAP
 *
 * This will be written procedurally as opposed to objectively...because it IS a procedure. It is a console app
 * designed to be triggered by a batch process.
 *
 * Version 0.0a is PHP behaving on my test system? If not- then system has taken too much abuse and needs
 *     to be nuked and paved.
 * Version 0.1a can write to log file: log file name is in config
 * Version 0.2a can connect to server
 * Version 0.2a.1 detect whether command line or web-server invocation
 * Version 0.2a.2 log files written to visual output on web-server invocation
 * Version 0.2b handle connection errors (the ones WITHIN the program that will most likely to be encountered)
 * Version 0.2c pull from config file local directory and use it to determine which files to grab
 * Version 0.2c.1 run check against config and recent files to determine success of process
 * Version 0.3a detect most recent files
 * Version 0.3b determine the files to download
 * Version 0.4a write to config file for next upload
 * Version 0.5a verify verify verify!
 * Version 0.5b create error list (aggregate of failures drafted into email)
 * Version 0.6a email whether successful or not
 * Version 0.7a log file management!
 * Version 0.8a clean up errors
 * Version 0.9a External system config (DOCUMENT THE REQUIREMENTS AND SETUP PROCESS!)
 * Version 0.9a.1 System config interface map
 * Version 1.0 Final tests and deployment!
 *
 */

//fixes log time so it log the time at the meridian.
date_default_timezone_set('EST');
//are you the web server?
$httpRunState = false;

//EOL is an end of line function that manages the CLI and HTTP output-
if(!isset($_SERVER['argc']))
{
    $httpRunState = true;
    echo("Process started from HTTP request");
    echo(EOL());
}
else
{
    echo("Process started from CLI");
    echo(EOL());
}

//open and verify config file
if(!file_exists("LTIconfig.cfg"))
{
    createCrashFile(1);
}
else
{
    $configurationArray = file("LTIconfig.cfg");
    if(sizeof($configurationArray) != 4)
    {
        createCrashFile(2);
    }
    else
    {
        //The meat and bones of the application
        runApplication();
    }
}

//function to create master crash file. This is to handle absolutely fatal errors
function createCrashFile($crashCode)
{
    $crashFileName = "CRASH_OUT_".date("Y-m-d").".txt";
    $crashFileName = trim($crashFileName);
    $crashFile = fopen($crashFileName, "w");
    if($crashCode == 1)
    {
        $noConfigurationFileText = "Can not detect config file! See readme for instructions on how to create config file!";
        fwrite($crashFile, $noConfigurationFileText);
    }
    if($crashCode == 2)
    {
        $badConfigurationFileText = "Wrong number of elements in config file! See readme for instructions on how to create config file!";
        fwrite($crashFile, $badConfigurationFileText);
    }
    else
    {
        $unknownText = "Crash call: Unknown Reason. Try reconfiguring server's environment";
        fwrite($crashFile, $unknownText);
    }
    fclose($crashFile);
}

//start the application processes, called when no early crash detected.
function runApplication()
{
    openLogFile();
    connectToFTP();
}

//at start of application. This opens the log file and puts the header info on it
function openLogFile()
{
    global $configurationArray;
    global $httpRunState;
    $logName = substr($configurationArray[2], 0 , -2); //remove the NL CR chars at the end of the line
    //$logName = "LTIapp.log";
    //create the file if it's not there.
    if(!file_exists($logName))
    {
        file_put_contents($logName, "");
    }
    //Start a string in case the log has been cleared
    if(!file_get_contents($logName))
    {
        $logContents = "";
    }
    else
    {
        //grab the log data that's there
        $logContents = file_get_contents($logName);
    }
    $logContents.= PHP_EOL."----------------------------------------------------------------------------------------------".PHP_EOL;
    $logContents.= "Log Entry Started for App Version 0.3.b".PHP_EOL;
    if($httpRunState)
    {
        $logContents.="Process was started from HTTP Request".PHP_EOL;
    }
    else
    {
        $logContents.="Process was started from CLI".PHP_EOL;
    }
    $dateTime = "Current time and date is: ".date("Y-m-d H:i:s");
    $logContents.= $dateTime.PHP_EOL;
    echo $dateTime.EOL();
    file_put_contents($logName, $logContents);
}

//Takes a String (arg) and puts it in the log as a single line.
//Also outputs to either console or web page as it goes.
function appendLogFile($appendedString)
{
    global $configurationArray;
    $logName = substr($configurationArray[2], 0 , -2); //remove the NL CR chars at the end of the line
    $logContents = file_get_contents($logName);
    $logContents .= $appendedString;
    file_put_contents($logName, $logContents.PHP_EOL);
    echo($appendedString.EOL());
}

//function to connect to FTP server. Called by runApplication()
//generates the conn object
//grabs the file name by calling determineFileName
//subsequent argument is fed to download_begin()
function connectToFTP()
{
    global $configurationArray;
    $serverName = substr($configurationArray[0], 0, -2);
    appendLogFile("Attempting to connect to ".$serverName."...");
    $conn = ftp_connect("ftp.librarytech.com");
    if(!$conn)
    {
        appendLogFile("Server name is invalid. Check config file.");
    }
    $connectionParams = getConnectionParamsFromINI();

    $login_result = ftp_login($conn, $connectionParams[0], $connectionParams[1]);
    if($login_result)
    {
        appendLogFile("Connection Successful!");
        $retrievedFileName = determineFileName();
        if($retrievedFileName != "")
        {
            download_begin($conn, $retrievedFileName);
        }
    }
    else
    {
        appendLogFile("Connected but unable to authenticate. Check user/pw config");
    }
}

//returns what we think the file number on the server should be
//this is called by connectToFTP() as it tries to
function determineFileName()
{
    //I know this looks stupid, but it really checks to see if the file "name"
    //can be converted to a VALID integer. If it can't, then something's wrong with
    //the config.
    global $configurationArray;
    $fileName = substr($configurationArray[1], 0 , -2);
    if(!is_int(intval($fileName)))
    {
        appendLogFile("Cannot determine the file name!");
        return "";
    }
    return intval($fileName);
}

//returns an array
//[numerical component of current server file, numerical component of 2nd most recent server file]
function getServerFileName($conn)
{
    ftp_pasv($conn, true);
    $files = ftp_nlist($conn, ".");
    $fileBuffer = 0;
    $fileBufferLow = 0;
    for($i = 0; $i < count($files); $i++)
    {
        //echo "<li>".$files[$i]."<br>".substr($files[$i],4, -4 )."</li>";
        //echo "<li>".substr($files[$i],5,-4)."</li>";
        $fileNameString = substr($files[$i],5,-4);
        if(intval($fileNameString) > intval($fileBuffer))
        {
            $fileBufferLow = intval($fileBuffer);
            $fileBuffer = intval($fileNameString);
        }
        else if(intval($fileNameString) > intval($fileBufferLow) && intval($fileNameString) != $fileBuffer)
        {
            $fileBufferLow = intval($fileNameString);
        }
    }
    if(!$files)
    {
        appendLogFile("Connected to FTP server and detected no files! Check network connection and status of FTP directory!");
    }
    $fileBufferArray = [intval($fileBuffer), intval($fileBufferLow)];
    return $fileBufferArray;
}

//takes the passed $conn object and the current file name
function download_begin($conn, $fileName)
{
    //The moment we've all been waiting for.
    $serverFileArray = getServerFileName($conn);
    appendLogFile("Server filename is: ".$serverFileArray[0]);
    appendLogFile("Last filename is: ".$serverFileArray[1]);
    appendLogFile("Config filename should be: ".($serverFileArray[1]));
    appendLogFile("Config filename is: ".$fileName);
    if($fileName != $serverFileArray[1])
    {
        appendLogFile("Consider running process manually. User input will be gathered in the next version to help handle this programmatically!");
        //call function to reconcile differences between client config and server files
        //it will be a procedure at first, but will be more interactive in the HTTP run state and CLI
        //instantiations.
        reconcileDownload($fileName, $serverFileArray[1]);
    }
    else
    {
        transferFiles();
    }
}

//This makes an End of line output to the screen. It is dependant on the run state
//I got sick of putting in these freaking characters, so I functionalized it.
function EOL()
{
    global $httpRunState;
    if ($httpRunState)
    {
        return "<br>";
    }
    else
    {
        return "\n";
    }
}

//This grabs the user and password from locally stored INI.
//The best way for encrypting locally stored data would be using a REST API.
//This is a feature that can be explored later on, but not impertinent at this time.
function getConnectionParamsFromINI()
{
    //Do NOT put passwords on GIT!
    $ini_filename = 'connection.ini';
    $ini = parse_ini_file($ini_filename, true);
    $returnParams = [$ini['global']['ftpUser'], $ini['global']['ftpPW']];
    return $returnParams;
}

function transferFiles()
{

}

function reconcileDownload($localName, $serverName)
{

}