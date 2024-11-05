<?php
    echo '<pre>';
    echo 'Data example' . PHP_EOL . PHP_EOL;

    $rconStatusOutput = <<<EOD
    hostname: #07-SAKURA-SERVER-[L4D2][TICK30]
    version : 2.2.2.9 8934 secure  (unknown)
    udp/ip  : 10.10.10.0:27999 [ public 100.100.0.0:27999 ]
    os      : Linux Dedicated
    map     : c3m3_shantytown
    players : 4 humans, 0 bots (16 max) (not hibernating) (reserved)

    #       userid  name                 uniqueid             connected  ping  loss  state   rate    adr
    # 1100  1000    "[BR] playerTest01"  STEAM_1:0:000000000  05:10      80    0     active  30000   100.200.65.90:13489
    # 1110  200     "[BR] palyerTest02"  STEAM_1:1:000000000  15:30      130   0     active  60000   100.100.60.80:3259
    # 1120  30      "[BR] palyerTest03"  STEAM_1:0:000000000  59:59      160   0     active  30000   100.200.55.70:27005
    # 1130  4       "[BR] palyerTest04"  STEAM_1:1:000000000  9:59:59    200   0     active  100000  100.100.50.60:36257
    #1068 "Charger" BOT active
    #1067 "Jockey" BOT active
    #1066 "Spitter" BOT active
    #end
    EOD;

    echo $rconStatusOutput . PHP_EOL . PHP_EOL;

    $lines = array_map('trim', explode("\n", trim($rconStatusOutput)));

    $patterns = [
        'hostname' => '/^hostname:\s*(.+)/',
        'version'  => '/^version\s*:\s*(\d+(\.\d+)*)\s*(\d+)\s*(in)?secure/',
        'udpip'    => '/^udp\/ip\s*:\s*([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\:?([0-9]{1,5})\s*\[\s*public\s*([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\:?([0-9]{1,5})\s*\]/',
        'os'       => '/^os\s*:\s*(.+)/',
        'map'      => '/^map\s*:\s*(.+)/',
        'players'  => '/^players\s*:\s*(\d+)\s*humans,\s*(\d+)\s*bots\s*\((\d+)\s*max\)\s*\((not\s*)?hibernating\)\s*\((un)?reserved.?(.*)?\)/',
            
        'testBot'     => '/#([1-9]\d*)/',
        'testPlayer'  => '/^(#\s*([1-9]\d*))\s*(\d+)\s*(\".*\")\s*(STEAM_[0-5]:[01]:\d+)\s*(?:(?:([01]?\d|2[0-3]):)?([0-5]?\d):)?([0-5]?\d)\s*(\d+)\s*(\d+)\s*(active|)\s*(\d+)\s*([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\:?([0-9]{1,5})?/',
        'findPlayers' => '/#\s*userid\s*name\s*uniqueid\s*connected\s*ping\s*loss\s*state\s*rate\s*adr(.*?)#end/s',
    ];

    if (
        !preg_match($patterns['hostname'], $lines[0], $hostnameTest) ||
        !preg_match($patterns['version'],  $lines[1], $versionTest)  ||
        !preg_match($patterns['players'],  $lines[5], $playerTest)   ||
        !preg_match($patterns['map'],      $lines[4], $mapTest)      ||
        !preg_match($patterns['udpip'],    $lines[2], $ipTest)       ||
        !preg_match($patterns['os'],       $lines[3], $osTest)
    ) {
        echo 'Primary regex failed';
        return;
    };

    $ServerInfo = [
        'hostname' => trim($hostnameTest[1]),
        'version'  => trim($versionTest[1]),
        'rev'      => intval($versionTest[3]),
        'secure'   => boolval(@trim($versionTest[4]) !== 'in'),
        'address'  => [
            'local'  => ['ip' => $ipTest[1], 'port' => intval($ipTest[2])],
            'public' => ['ip' => $ipTest[3], 'port' => intval($ipTest[4])]
        ],
        'os'       => trim($osTest[1]),
        'map'      => trim($mapTest[1]),
        'players'  => [
            'connected' => intval($playerTest[1]),
            'bots'      => intval($playerTest[2]),
            'slots'     => intval($playerTest[3])
        ],
        'active'   => boolval(trim($playerTest[4]) === 'not'),
        'reserved' => boolval(trim($playerTest[5]) !== 'un')
    ];

    if (!preg_match($patterns['findPlayers'], $rconStatusOutput, $playerListTest)) {
        echo 'Regex players failure';
        return;
    };

    $ServerInfo['players']['list'] = isset($playerListTest[1]) ?
        
        array_filter(array_map(function($line) use($patterns) {

            $player = trim($line);

            if (preg_match($patterns['testBot'],     $player, $testBot))    return null;
            if (!preg_match($patterns['testPlayer'], $player, $testPlayer)) return null;
            
            return [
                'id'      => intval($testPlayer[3]),
                'name'    => trim($testPlayer[4]),
                'steamid' => trim($testPlayer[5]),
                'time'    => [
                    'hours'   => intval($testPlayer[6]),
                    'minutes' => intval($testPlayer[7]),
                    'seconds' => intval($testPlayer[8]),
                ],
                'ping'     => intval($testPlayer[9]),
                'loss'     => intval($testPlayer[10]),
                'state'    => intval($testPlayer[11]),
                'tickrate' => intval($testPlayer[12]),
                'ipv4'     => $testPlayer[13],
                'port'     => intval($testPlayer[14])
            ];
            
        }, explode("\n", trim($playerListTest[1]))))
        
    :
        
        null;

    echo 'Processed output' . PHP_EOL . PHP_EOL;

    print_r($ServerInfo);
    
    echo '</pre>';
?>
