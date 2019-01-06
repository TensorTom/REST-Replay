<?php
date_default_timezone_set('UTC');

function httpPost($url, $queryString){
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POST, 1);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $queryString);
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $ch, CURLOPT_HEADER, 0);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

    return curl_exec( $ch );
}

function getBetween($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return false;
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

if(isset($argv[1]) && (strtolower($argv[1]) == '--help' || strtolower($argv[1]) == '-h') ){
    echo "----------------------------------------\n
    
    
    ";
}

$host = 'localhost';
$db   = 'database';
$user = 'dbUser';
$pass = 'dbPass';
$charset = 'utf8mb4';

$apiHost = 'example.com';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$dateIn = (isset($argv[1]) && strlen($argv[1]) > 1) ? $argv[1] : '2018-12-16 01:07:07';
$delIn = (isset($argv[2]) && strlen($argv[2]) > 0) ? $argv[2] : false;
$replaceIn = (isset($argv[3]) && strlen($argv[3]) > 3 && strpos($argv[3], '|') !== false) ? explode('|', $argv[3]) : false;

if(strtolower(trim($delIn)) == 'y'){
    $pdo->query("delete from botTrades");
    $pdo->query("delete from userTrades");
    $pdo->query("delete from orders");
}

$keysQ = $pdo->query("SELECT * FROM restLog where `epoch` > '$dateIn' order by `id` ASC");
echo "\nStarted.\n---------------------------------------\n";
while($key = $keysQ->fetch() ) {
    $endpoint = $key['endpoint'];
    $queryString = 'debug_user23=49&rest=1&'.$key['queryString'];
    $queryStringOrig = '';
    $i = 0;
    if($replaceIn){
        foreach($replaceIn as $replItem){
            if(strpos($replItem, '=') !== false){
                $param = $replItem[0];
                $newVal = $replItem[1];
                $toRepl = getBetween($queryString, "$param=", "&");
                if($toRepl === false) continue;
                $i++;
                $queryString = str_replace("$param=$toRepl", "$param=$newVal", $queryString);
            }
        }
    }
    echo "\nReplaced: $i query parameters.\n";
    echo "Endpoint: $endpoint\n";
    echo "Query String: $queryString\n";
    echo "(C)ontinue submitting this step? ";
    while($confirmIn = strtolower(trim(fgets(STDIN))) ){
        if($confirmIn == 'c') {
                $stepDone = false;
                while (!$stepDone) {
                    echo "\nExecuting step...\n";
                    $result = httpPost("https://$apiHost$endpoint", $queryString);
                    $resultO = json_decode($result);
                    if (!$resultO->error) {
                        $stepDone = true;
                        echo "\nResponse:\n";
                        var_dump($resultO);
                        echo "\nStep successfully executed. Moving on.\n";
                        break 2;
                    } else {
                        echo "\nServer request:\n";
                        echo "https://dev1.pinebot.com$endpoint\n";
                        echo "$queryString\n";
                        echo "\nServer response:\n";
                        var_dump($resultO);
                        echo "\nError executing step. Retry? ";
                        $retryIn = strtolower(trim(fgets(STDIN)));
                        switch ($retryIn) {
                            case 'y':
                                echo "\nRetrying step execution.\n";
                                break;
                            case 'n':
                                $stepDone = true;
                                echo "\nMoving on...\n";
                                break;
                            case 'q':
                                die("\nGoodbye\n");
                                break;
                            default:
                                echo "\nPlease answer (Y)es, (N)o, or (Q)uit: ";
                                break;
                        }
                    }
                }
        }elseif($confirmIn == 'q') {
            die("\nGoodbye\n");
        }else{
                echo "\nPlease (C)ontinue or (Q)uit: ";
                continue;
        }
    }
    echo "\n---------------------------------------\n";
}
