<?php
require_once realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'ann_config.php';
require_once INCL_DIR . 'ann_functions.php';
require_once CLASS_DIR . 'class_bt_options.php';

/**
 * @param $err
 */
function error($err)
{
    header('Content-Type: text/plain; charset=UTF-8');
    header('Pragma: no-cache');
    die('d14:failure reason' . strlen($err) . ":{$err}ed5:flagsd20:min_request_intervali1800eeee");
}

/**
 * @return mixed
 */
function getip()
{
    foreach ([
                 'HTTP_CLIENT_IP',
                 'HTTP_X_FORWARDED_FOR',
                 'HTTP_X_FORWARDED',
                 'HTTP_X_CLUSTER_CLIENT_IP',
                 'HTTP_FORWARDED_FOR',
                 'HTTP_FORWARDED',
                 'REMOTE_ADDR',
             ] as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (array_map('trim', explode(',', $_SERVER[ $key ])) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return '';
}

/**
 * @param        $ip
 * @param string $reason
 *
 * @return bool
 */
function check_bans($ip, &$reason = '')
{
    global $cache;
    $key = 'bans_' . $ip;
    $ban = $cache->get($key);
    if ($ban === false || is_null($ban)) {
        $nip = ip2long($ip);
        $ban_sql = sql_query('SELECT comment FROM bans WHERE (first <= ' . $nip . ' AND last >= ' . $nip . ') LIMIT 1');
        if (mysqli_num_rows($ban_sql)) {
            $comment = mysqli_fetch_row($ban_sql);
            $reason = 'Manual Ban (' . $comment[0] . ')';
            $cache->set($key, $reason, 86400); // 86400 // banned

            return true;
        }
        ((mysqli_free_result($ban_sql) || (is_object($ban_sql) && (get_class($ban_sql) == 'mysqli_result'))) ? true : false);
        $cache->set($key, 0, 86400); // 86400 // not banned

        return false;
    } elseif (!$ban) {
        return false;
    } else {
        $reason = $ban;

        return true;
    }
}

if (empty($_SERVER['QUERY_STRING'])) {
    die("It takes 46 muscles to frown but only 4 to flip 'em the bird.");
}
$q = explode('&', $_SERVER['QUERY_STRING']);
$_GET = [];
foreach ($q as $p) {
    $ps = explode('=', $p, 2);
    $p1 = rawurldecode(trim($ps[0]));
    $p2 = rawurldecode(trim($ps[1]));
    if (strlen($p1) > 0) {
        if (!isset($_GET[ $p1 ])) {
            $_GET[ $p1 ] = $p2;
        } elseif (!is_array($_GET[ $p1 ])) {
            $temp = $_GET[ $p1 ];
            unset($_GET[ $p1 ]);
            $_GET[ $p1 ] = [];
            $_GET[ $p1 ][] = $temp;
            $_GET[ $p1 ][] = $p2;
        } else {
            $_GET[ $p1 ][] = $p2;
        }
    }
}

if (isset($_GET['torrent_pass']) && strlen($_GET['torrent_pass']) != 32) {
    $lentorrent_pass = strlen($_GET['torrent_pass']);
    if ($lentorrent_pass > 32 && preg_match('/^([0-9a-f]{32})\?(([0-9a-zA-Z]|_)+)\=/', $_GET['torrent_pass'], $matches)) {
        $lenget = strlen($matches[0]);
        $valget = substr($_GET['torrent_pass'], $lenget);
        if (!isset($_GET[ $matches[2] ])) {
            $_GET[ $matches[2] ] = $valget;
        } elseif (!is_array($_GET[ $matches[2] ])) {
            $temp = $_GET[ $matches[2] ];
            $_GET[ $matches[2] ] = [];
            $_GET[ $matches[2] ][] = $temp;
            $_GET[ $matches[2] ][] = $valget;
        } else {
            $_GET[ $matches[2] ][] = $valget;
        }

        $_GET['torrent_pass'] = $matches[1];
    } else {
        error('torrent pass not valid, please redownload your torrent file');
    }
}

$torrent_pass = isset($_GET['torrent_pass']) && ($_GET['torrent_pass']) ? $_GET['torrent_pass'] : '';
//$torrent_pass = $_GET['torrent_pass'];
if (!$torrent_pass) {
    die('scrape error');
}

if (!@($GLOBALS['___mysqli_ston'] = mysqli_connect($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']))) {
    die();
}
@((bool)mysqli_query($GLOBALS['___mysqli_ston'], "USE {$_ENV['DB_DATABASE']}")) or die();
$numhash = count($_GET['info_hash']);
$torrents = [];
if ($numhash < 1) {
    die('Scrape Error d5:filesdee');
} elseif ($numhash == 1) {
    $torrent = get_torrent_from_hash($_GET['info_hash']);
    if ($torrent) {
        $torrents[ $_GET['info_hash'] ] = $torrent;
    }
} else {
    foreach ($_GET['info_hash'] as $hash) {
        $torrent = get_torrent_from_hash($hash);
        if ($torrent) {
            $torrents[ $hash ] = $torrent;
        }
    }
}

$user = get_user_from_torrent_pass($torrent_pass);
if (!$user || !count($torrents)) {
    die('scrape user error');
}
/*
if (!$user['perms'] & bt_options::PERMS_BYPASS_BAN) {
    $rip = $_SERVER['REMOTE_ADDR'];
    $ip = getip();
    if (check_bans($rip, false))
        error('IP Banned');
    elseif ($ip != $rip) {
        if (check_bans($ip, false))
            error('IP Banned');
    }
}
*/
$r = 'd5:filesd';
foreach ($torrents as $info_hash => $torrent) {
    $r .= '20:' . $info_hash . 'd8:completei' . $torrent['seeders'] . 'e10:downloadedi' . $torrent['times_completed'] . 'e10:incompletei' . $torrent['leechers'] . 'ee';
}
$r .= 'ee';
header('Content-Type: text/plain; charset=UTF-8');
header('Pragma: no-cache');
echo $r;
die();
//die($r);
