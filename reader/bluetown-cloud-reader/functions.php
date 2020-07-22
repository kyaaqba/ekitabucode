<?php
function get_string_between($string, $start, $end){
    $string = " ".$string;
    $ini = strpos($string,$start);
    if ($ini == 0) return "";
    $ini += strlen($start);   
    $len = strpos($string,$end,$ini) - $ini;
    return substr($string,$ini,$len);
}

//Taken from https://stackoverflow.com/questions/9339619/php-checking-if-the-last-character-is-a-if-not-then-tack-it-on
function endsWith($FullStr, $needle)
{
    $StrLen = strlen($needle);
    $FullStrEnd = substr($FullStr, strlen($FullStr) - $StrLen);
    return $FullStrEnd == $needle;
}

//Taken from https://stackoverflow.com/questions/11121817/how-to-check-an-ip-address-is-within-a-range-of-two-ips-in-php
/**
 * Check if a given ip is in a network
 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
 * @return boolean true if the ip is in this range / false if not.
 */
function ip_in_range( $ip, $range ) {
    if ( strpos( $range, '/' ) == false ) {
        $range .= '/32';
    }
    // $range is in IP/CIDR format eg 127.0.0.1/24
    list( $range, $netmask ) = explode( '/', $range, 2 );
    $range_decimal = ip2long( $range );
    $ip_decimal = ip2long( $ip );
    $wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

function ip_in_ranges( $ip, $ranges)
{
    $in_range = false;
    foreach ($ranges as $range)
    {
	if (ip_in_range($ip,$range))
        {
	  $in_range = true;
        }
    }
    return $in_range;
}


function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP')) {
        $ipaddress = getenv('HTTP_CLIENT_IP');
    } elseif(getenv('HTTP_X_FORWARDED_FOR')) {
	$ips = explode(', ', getenv('HTTP_X_FORWARDED_FOR'));
	if (count($ips) > 1) {
		$ipaddress = $ips[0];
	} else {
		$ipaddress = $ips;
	}
    } elseif(getenv('HTTP_X_FORWARDED')) {
        $ipaddress = getenv('HTTP_X_FORWARDED');
    } elseif(getenv('HTTP_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    } elseif(getenv('HTTP_FORWARDED')) {
       $ipaddress = getenv('HTTP_FORWARDED');
    } elseif(getenv('REMOTE_ADDR')) {
        $ipaddress = getenv('REMOTE_ADDR');
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}
function getPdo($server,$db_name,$user,$password)
{   
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
   return new PDO(
   'mysql:host='. $server . ';dbname=' . $db_name,
   $user,
   $password,
   $opt);
}   



function activeUsers($pdo,$book_uri)
{
    $statement = $pdo->prepare("
        SELECT * FROM usage_log 
        WHERE book_uri = :book_uri 
        AND last_used > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND book_closed = 0
        AND within_concurrent_user_limit = 1
        AND within_allowed_ip = 1
        ");
    $statement->bindParam(":book_uri",$book_uri);
    $statement->execute();
    return $statement->fetchAll();
}
function session_exists($pdo,$session_id)
{
    $statement = $pdo->prepare("
        SELECT * FROM usage_log
        WHERE session_id = :session_id");
    $statement->bindParam(":session_id",$session_id);
    $statement->execute();
    if (count($statement->fetchAll()) > 0)
    {
        return true;
    }
}

function log_new_user($pdo,$params)
{
    $statement = $pdo->prepare("INSERT INTO usage_log 
        (
            ip,
            within_concurrent_user_limit,
            within_allowed_ip,
            session_id,
            created_at,
            last_used,
            book_uri,
            book_closed
        ) 
        VALUES
        (
            :ip,
            :within_concurrent_user_limit,
            :within_allowed_ip,
            :session_id,
            NOW(),
            NOW(),
            :book_uri,
            :book_closed
        )");


    $statement->bindParam(":ip"                          , $params['ip']);
    $statement->bindParam(":within_concurrent_user_limit", $params['within_concurrent_user_limit']);
    $statement->bindParam(":within_allowed_ip"           , $params['within_allowed_ip']);
    $statement->bindParam(":session_id"                  , $params['session_id']);
    $statement->bindParam(":book_uri"                    , $params['book_uri']);
    $statement->bindParam(":book_closed"                 , $params['book_closed']);
    $statement->execute();
    $results = $statement->fetchAll();
    return $results;
}

function update_active_user($pdo,$params)
{
    $statement = $pdo->prepare("
        UPDATE usage_log
        SET 
        last_used = NOW()
        WHERE session_id = :session_id 
        AND   last_used > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND   book_uri   = :book_uri
        ");
    $statement->bindParam(":session_id",$params['session_id']);
    $statement->bindParam(":book_uri",$params['book_uri']);
    $statement->execute();
    $results = $statement->fetchAll();
    return $results;
}
function update_user($pdo,$params)
{
    $statement = $pdo->prepare("
        UPDATE usage_log
        SET 
        last_used = NOW(),
        within_concurrent_user_limit = :within_concurrent_user_limit,
        within_allowed_ip = :within_allowed_ip
        WHERE session_id = :session_id 
        AND   book_uri   = :book_uri
        ");
    $statement->bindParam(":session_id",$params['session_id']);
    $statement->bindParam(":book_uri",$params['book_uri']);
    $statement->bindParam(":within_concurrent_user_limit",$params['within_concurrent_user_limit']);
    $statement->bindParam(":within_allowed_ip",$params['within_allowed_ip']);
    $statement->execute();
    $results = $statement->fetchAll();
    return $results;
}

function current_user_authorized($pdo,$book_uri, $authorized, &$messages = [] , &$is_an_active_user = false, &$params = [])
{
    $activeUsers = activeUsers($pdo,$book_uri);
    $is_an_active_user = false;
    foreach ($activeUsers as $activeUser)
    {
        if (session_id() == $activeUser['session_id'])
        {
            $is_an_active_user = true;
        }
    }
    if ((count($activeUsers) >= MAX_CONCURRENT_USERS) && !$is_an_active_user)
    {
        $params["within_concurrent_user_limit"] = 0;
        $authorized = false;
        $messages[] = "The limit for # of users reading this book has been reached.";
    }

    $params["book_closed"] = 0;

    return $authorized;
}

?>
