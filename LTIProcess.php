<?php
/**
 * Version 0.4a
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
//declare emailString
$emailMid = "";


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
    sendEmail();
}

//at start of application. This opens the log file and puts the header info on it
function openLogFile()
{
    global $configurationArray;
    global $httpRunState;
    $logName = substr($configurationArray[2], 0 , -2); //remove the NL CR chars at the end of the line
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
    appendEmailString("Process run on ".date("m-d-Y")."<br>");
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

function appendEmailString($appendedString)
{
    global $emailMid;
    $emailMid.=$appendedString;
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
    if($fileName < $serverFileArray[1])
    {
        //call function to reconcile differences between client config and server files
        //it will be a procedure at first, but will be more interactive in the HTTP run state and CLI
        //instantiations.
        @reconcileDownload($conn, $fileName, $serverFileArray[0]);
    }
    else if($fileName > $serverFileArray)
    {
        appendLogFile("Consider running process manually. Config Filename > Server Filename!");
    }
    else
    {
        transferFiles($conn, $serverFileArray[0]);
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

//If everything is normal, then this will run, and download the proper files accordingly.
function transferFiles($conn, $serverName)
{
    //WNCLN.$serverName.ACF
    //WNCLN.$serverName.ULH
    $acfString = "WNCLN".$serverName.".ACF";
    $ulhString = "WNCLN".$serverName.".ULH";
    if(ftp_get($conn, "download\\".$acfString, $acfString, FTP_ASCII))
    {
        appendLogFile($acfString." has been downloaded successfully!");
        if(ftp_get($conn, "download\\".$ulhString, $acfString, FTP_ASCII))
        {
            appendLogFile($ulhString." has been downloaded successfully!");
            //update the config file
            writeConfigUpdate($serverName);
        }
        else
        {
            appendLogFile($ulhString." failed to download!");
        }
    }
    else
    {
        appendLogFile($acfString." failed to download!");
    }

}

//called by download_begin
//This guy is going to get a little (actually very) complicated
//In this version, grab connection object, match recent server name against config name
//This is a multipart function and will be modified with interactive php pages and text streams so that
//during a manual re-run, a user can decide how to proceed if there is an issue. As far as the V1 map of this
//goes, it will just download the files it doesn't detect in the download folder.
function reconcileDownload($conn, $localName, $serverName)
{

    appendLogFile("reconciling download...");
    $lastSuccessfulFileName = getLastSuccess();
    appendLogFile("serverName: ".$serverName." lastSuccessfulFileName: ".$lastSuccessfulFileName);
    appendLogFile("The last file set successfully downloaded ".$lastSuccessfulFileName);
    for($i = $lastSuccessfulFileName; $i <= $serverName; $i++)
    {
        $acfString = "WNCLN".$i.".ACF";
        $ulhString = "WNCLN".$i.".ULH";
        appendLogFile("Attempting to download". $acfString);
        appendLogFile("Attempting to download". $ulhString);
        if(ftp_get($conn, "download\\".$acfString, $acfString, FTP_ASCII))
        {
            appendLogFile($acfString." downloaded successfully");
        }
        else
        {
            appendLogFile($acfString." DOWNLOAD FAILED!");
        }
        if(ftp_get($conn, "download\\".$ulhString, $ulhString, FTP_ASCII))
        {
            appendLogFile($ulhString." downloaded successfully");
        }
        else
        {
            appendLogFile($ulhString." DOWNLOAD FAILED!");
        }
    }
    writeConfigUpdate($serverName);
}

//writes the new determined server name (the name of the files just now downloaded)
//this is the same config file that is called upon when the application runs
function writeConfigUpdate($serverName)
{
    global $configurationArray;
    $configurationArray[1] = $serverName.substr($configurationArray[1], -2); //Keep the NL CR chars as they are
    $fileContents = "";
    for($i = 0; $i < sizeof($configurationArray); $i++)
    {
        $fileContents.= $configurationArray[$i];
    }
    file_put_contents("LTIconfig.cfg", $fileContents);
    appendLogFile("Config file updated");

}

//simply returns the number of the filename that was last downloaded successfully.
//This is part of the reconcileDownload function.
function getLastSuccess()
{
    /*
     * This idea was incredibly stupid.
     *
    //iterate through log file to allow us to get the name of the last successful download.
    //the next section will go through the downloads folder itself (it'll be compared to this)
    global $configurationArray;
    $logName = substr($configurationArray[2], 0 , -2); //remove the NL CR chars at the end of the line
    $logContents = file($logName);
    $logEntryPlace = 0;
    $lastSuccessfulLogEntryPlace = 0;
    for($i = 1; $i < sizeof($logContents);$i ++)
    {
        if(substr($logContents[$i], 0 , -2) == "----------------------------------------------------------------------------------------------")
        {
            $logEntryPlace = $i;
        }
        if($i > $logEntryPlace && substr($logContents[$i],-35, -2) == "has been downloaded successfully!")
        {
            $lastSuccessfulLogEntryPlace = $logEntryPlace;
        }
    }
    return substr($logContents[$lastSuccessfulLogEntryPlace + 6], -6, -2);
    */

    /*
     * This idea is hopefully less stupid.
     */
    $lastSuccess = 1040;
    $directory = 'download/';
    if(!is_dir($directory))
    {
        appendLogFile("directory doesn't exist");
    }
    else
    {
        $files = scandir($directory);
        foreach($files as $file)
        {
            //This will be made into a validator
            echo($file);
        }
    }
    return 1050;
}

function sendEmail()
{
    //setting up email config.
    //There is not much to commit here. It's about 90% server-side config/setup
    //This is for testing
    //$emailStr = "<div style='font-family: arial, verdana, sans-serif'>This is an email!";
    //$emailStr.= date("Y-m-d H:i:s")."</div>";
    $headers = 'From: candelariajr@appstate.edu' . "\r\n" .
        'Reply-To: candelariajr@appstate.edu' . "\r\n" .
        'MIME-Version: 1.0' . "\r\n".
        'Content-Type: text/html; charset=ISO-8859-1' . "\r\n".
        'X-Mailer: PHP/' . phpversion() .

        $emailHead = "<div style = 'font-family: arial, verdana, sans-serif'>
    <div style = 'font-size: 20px; text-shadow: 1px 1px 4px #000000; text-align: center; background-color: #00cbcc; color: white; padding: 1%;'>
        LTI Automation Email: Version 0.6a
    </div>
    <div style = ''>
        <br>";

    global $emailMid;
    /*
    concat to test-

    Process run on 12-08-2015<br>Latest files determined to be WNCLN1072.ACF and WNCLN1072.ULH<hr><br><b>WNCLN1072.ACF downloaded successfully<br>WNCLN1072.ULH downloaded successfully</b>";
    */
    $emailFoot = "</div></div>";

    mail("candelariajr@appstate.edu", "LTI Automated Email", $emailHead.$emailMid.$emailFoot, $headers); //, benshirley.wncln@gmail.com
}