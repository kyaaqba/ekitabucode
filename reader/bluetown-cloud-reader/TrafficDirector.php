<?php


require_once("functions.php");
error_reporting(E_ALL);
ini_set("display_errors", 0);
DEFINE("BOOK_DIRECTORY", "epub_content");
date_default_timezone_set('UTC');

//$ip_ranges = array(
//	"0.0.0.0/0"
//);
if (file_exists(".env"))
{
    $config_parameters = parse_ini_file(".env");
}
else
{
    throw new Exception("Config file missing.");
}

if (isset($config_parameters['user']) &&
    isset($config_parameters['pass']) &&
    isset($config_parameters['database'])
   )
{
    $servername      = "localhost";
    $username        = $config_parameters['user'];
    $password        = $config_parameters['pass'];
    $database_name   = $config_parameters['database'];
    $pdo = getPdo($servername,$database_name,$username,$password);
}
else
{
    throw new Exception("Config file missing database config info");
}
if (isset($config_parameters['max_concurrent_users']) &&
    isset($config_parameters['allowed_ips'])
   )
{
    DEFINE("MAX_CONCURRENT_USERS", $config_parameters['max_concurrent_users']);
    $ip_ranges = explode(",",$config_parameters['allowed_ips']);
}
else
{
    throw new Exception("Config file missing connection info");
}

$ip = get_client_ip();
$authorized = true;
$within_allowed_ip = 1;
if (!ip_in_ranges($ip,$ip_ranges))
{

    $authorized = false;
    $within_allowed_ip = 0;
    $messages[] = "Not in allowed organizations.";
}
$messages = [];


session_start();

$within_concurrent_user_limit = 1;
//$id = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1);
$pathInfo = pathinfo($_SERVER['REQUEST_URI']);
$fileExtension = "";
if (isset($pathInfo['extension']))
{
    $fileExtension = $pathInfo['extension'];
}


if (isset($_GET['canViewBook']) && isset($_GET['uri']))
{
    $book_uri = str_replace("../..","", $_GET['uri'] . "/"); 
    $messages = [];
    $authorized = current_user_authorized($pdo,$book_uri,$authorized,$messages);
    $return_info = [
        "authorized" => $authorized,
        "messages" => $messages
        ];
    die(json_encode($return_info));
}

if (!($fileExtension == 'json'))
{
    $book_uri = substr($_SERVER['REQUEST_URI'], 0,strrpos($_SERVER['REQUEST_URI'], '/') + 1);
    $params = [
        "ip" => $ip,
        "within_concurrent_user_limit" => $within_concurrent_user_limit,
        "within_allowed_ip" => $within_allowed_ip,
        "session_id" => session_id(),
        "book_uri" => $book_uri,
        ];

    $messages = [];
    $authorized = current_user_authorized($pdo,$book_uri,$authorized,$messages,$is_an_active_user,$params);

    if (!$is_an_active_user)
    {
        if ($authorized)
        {
            log_new_user($pdo,$params);
        }
    }
    else
    {
        if ($authorized)
        {
            update_active_user($pdo,$params);
        }
    }

    if (!$authorized)
    {
        print("This book is currently unaivalable. Please try again later.<BR>");
        log_new_user($pdo,$params);
        foreach($messages as $message)
        {
            print($message . "<BR>");
        }
        die;
    }

}// if (!($id == "epub_library.json"))
else
{
}


//Load the URL/file that was supposed to be loaded
$parsed_url = parse_url("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
$internalFilepath = $_SERVER['DOCUMENT_ROOT'] .$parsed_url['path'];
if (file_exists($internalFilepath) && !is_dir($internalFilepath))
{
}
else if (substr($internalFilepath, -1) == "/") 
{
    $internalFilepath .= "index.html";
    if (file_exists($internalFilepath))
    {
    }
}
$doc = file_get_contents($internalFilepath);
echo $doc;

?>
