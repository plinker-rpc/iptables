<?php

/**
 * Task Build NGINX
 */

if (!empty($this->task->config['debug']) && !defined('DEBUG')) {
    define('DEBUG', true);
}

if (!empty($this->task->config['log']) && !defined('LOG')) {
    define('LOG', true);
}

if (!empty($params['nat_postrouting']) && !defined('NAT_POSTROUTING')) {
    define('NAT_POSTROUTING', $params['nat_postrouting']);
}

if (!class_exists('Iptables')) {
    class Iptables
    {
        public function __construct($task)
        {
            $this->task = $task;
        }
        
        /**
         *
         */
        private function log($message)
        {
            if (LOG) {
                if (!file_exists('./logs')) {
                    mkdir('./logs', 0775, true);
                }
                $log  = '['.date("c").'] '.$message.PHP_EOL;
                file_put_contents('./logs/'.date("d-m-Y").'.txt', $log, FILE_APPEND);
            }
            
            echo DEBUG ? "   - ".$message."\n" : null;
        }

        /**
         *
         */
        public function build($rows)
        {
            // //process fail2ban log - deprecated
        
            // if (file_exists('/var/log/fail2ban.log')) {
            // 	foreach (file('/var/log/fail2ban.log', FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES) as $line) {
    
            // 		preg_match('/(\d+-\d+-\d+\s\d+:\d+:\d+),\d+\sfail2ban.actions:\s(INFO|WARNING)\s\[(.+)\]\s(Ban|Unban)\s(.+)$/', $line, $matches);
            // 		$banDate = !empty($matches[1]) ? $matches[1] : null;
            // 		$banLogType = !empty($matches[2]) ? $matches[2] : null;
            // 		$banJail = !empty($matches[3]) ? $matches[3] : null;
            // 		$banType = !empty($matches[4]) ? $matches[4] : null;
            // 		$banIp = !empty($matches[5]) ? $matches[5] : null;
    
            // 		if (!filter_var($banIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 			continue;
            // 		}
    
            // 		// ban
            // 		if ($banType == 'Ban') {
            // 			$ipban = R::findOne('ipban', ' ip = ?', [ $banIp ]);
            // 			// ban not found in database
            // 			if (empty($ipban)) {
            // 				$ipban = R::dispense('ipban');
            // 				$ipban->import(array(
            // 					'bantime' => $banDate,
            // 					'unbantime' => '0000-00-00 00:00:00',
            // 					'log_type' => $banLogType,
            // 					'jail' => $banJail,
            // 					'type' => $banType,
            // 					'ip' => $banIp,
            // 					'whitelist' => (in_array($banIp.'/32', $this->trustedHosts) ? 1 : 0),
            // 				));
            // 				R::store($ipban);
    
            // 				$this->task->log('Task BuildIPTables - IP blocked: '.$banIp.' '.$banJail);
            // 			}
            // 		}
            // 		// // unban - removed so admin needs to unblock all ips, uncomment to allow fail2ban log to determine
            // 		// if ($banType == 'Unban') {
            // 		//     $ipban = R::findOne('ipban', ' ip = ?', [ $banIp ]);
            // 		//     if (!empty($ipban)) {
            // 		//         R::exec('DELETE FROM ipban WHERE ip = ?', [$banIp]);
            // 		//     }
            // 		// }
            // 	}
            // }
    
            // whitelisted host rules
            // foreach ($ipban as $host) {
            // 	//already added trused whitelist
            // 	if (in_array($host->ip.'/32', $this->trustedHosts)) {
            // 		continue;
            // 	}
            // 	if ($host->whitelist == 1) {
            // 		$rules .= "-A INPUT -s {$host->ip}/32 -p tcp -j ACCEPT\n";
            // 	}
            // }
    
            // // blocked hosts
            // foreach ($ipban as $host) {
            // 	//already added as trused ip
            // 	if (in_array($host->ip.'/32', $this->trustedHosts)) {
            // 		continue;
            // 	}
            // 	//already done whitelist
            // 	if ($host->whitelist == 1) {
            // 		continue;
            // 	}
            // 	$rules .= "-A INPUT -s {$host->ip}/32 -j DROP\n";
            // }
    
            //$ipban = R::findAll('ipban');
    
            $rules = "# Generated on ".date('D M j H:i:s Y')."\n";
            $rules .= "*mangle\n";
            $rules .= ":PREROUTING ACCEPT [0:0]\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":FORWARD ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":POSTROUTING ACCEPT [0:0]\n";
            $rules .= "-A POSTROUTING -o lxcbr0 -p udp -m udp --dport 68 -j CHECKSUM --checksum-fill\n";
            $rules .= "COMMIT\n";
    
            $rules .= "*nat\n";
            $rules .= ":PREROUTING ACCEPT [0:0]\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":POSTROUTING ACCEPT [0:0]\n";
    
            foreach ($rows as $task) {
                // always need an ip
                if (empty($task['type']) || $task['type'] != 'forward') {
                    continue;
                }
    
                // if type not set
                if (empty($task['type'])) {
                    if (!empty($task['srv_port']) && !empty($task['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":".(int) $task['srv_port']."\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":".(int) $task['srv_port']."\n";
                    }
                    continue;
                }
    
                if ($task['type'] == 'SSH') {
                    $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":22\n";
                    $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":22\n";
                } elseif ($task['type'] == 'mySQL') {
                    $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":3306\n";
                    $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":3306\n";
                } elseif ($task['type'] == 'HTTP') {
                    $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":80\n";
                    $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":80\n";
                } else {
                    if (!empty($task['srv_port'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":".(int) $task['srv_port']."\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $task['port']." -j DNAT --to-destination ".$task['ip'].":".(int) $task['srv_port']."\n";
                    }
                }
            }
    
            // intergrate MAC accociation - shoul stop ARP spoofin
            // iptables -A FORWARD -s 172.16.1.4 -m mac ! --mac-source 00:11:22:33:44:55 -j DROP
    
            $rules .= "-A POSTROUTING -s ".NAT_POSTROUTING." ! -d ".NAT_POSTROUTING." -j MASQUERADE\n";
            $rules .= "COMMIT\n";
            $rules .= "*filter\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":FORWARD ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":fail2ban-ssh - [0:0]\n";
            $rules .= "-A INPUT -p tcp -m multiport --dports 2020 -j fail2ban-ssh\n";
            $rules .= "-A INPUT -p tcp -m multiport --dports 22 -j fail2ban-ssh\n";
            $rules .= "-A INPUT -p tcp -m multiport --dports 2200:2299 -j fail2ban-ssh\n";
            $rules .= "-A INPUT -i lxcbr0 -p tcp -m tcp --dport 53 -j ACCEPT\n";
            $rules .= "-A INPUT -i lxcbr0 -p udp -m udp --dport 53 -j ACCEPT\n";
            $rules .= "-A INPUT -i lxcbr0 -p tcp -m tcp --dport 67 -j ACCEPT\n";
            $rules .= "-A INPUT -i lxcbr0 -p udp -m udp --dport 67 -j ACCEPT\n";
            $rules .= "-A INPUT -i lo -j ACCEPT\n";
            $rules .= "-A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A INPUT -m conntrack --ctstate INVALID -j DROP\n";
            $rules .= "-A INPUT -p tcp -m tcp --dport 80 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A INPUT -p tcp -m tcp --dport 443 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A INPUT -p tcp -m tcp --dport 8443 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A FORWARD -o lxcbr0 -j ACCEPT\n";
            $rules .= "-A FORWARD -i lxcbr0 -j ACCEPT\n";
            $rules .= "-A OUTPUT -o lo -j ACCEPT\n";
            $rules .= "-A OUTPUT -p tcp -m tcp --sport 80 -m conntrack --ctstate ESTABLISHED -j ACCEPT\n";
            $rules .= "-A OUTPUT -p tcp -m tcp --sport 443 -m conntrack --ctstate ESTABLISHED -j ACCEPT\n";
            $rules .= "-A OUTPUT -p tcp -m tcp --sport 8443 -m conntrack --ctstate ESTABLISHED -j ACCEPT\n";
            $rules .= "-A fail2ban-ssh -j RETURN\n";
            $rules .= "COMMIT\n";
            $rules .= "# Completed on ".date('D M j H:i:s Y');

            //write to iptables rules file
            //
            echo DEBUG ? $this->log('Applying IPTables rules') : null;

            file_put_contents('iptables.rules.v4', $rules);
    
            //apply iptables
            //exec('/sbin/iptables-restore < /root/host-agent/iptables.rules.v4');
            return;
        }
        
    }
}

$iptables = new Iptables($this);

$rules = $this->find('iptables', 'has_change = 1');

$iptables->build($rules);
