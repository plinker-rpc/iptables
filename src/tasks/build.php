<?php
/*
 +------------------------------------------------------------------------+
 | Plinker-RPC PHP                                                        |
 +------------------------------------------------------------------------+
 | Copyright (c)2017-2018 (https://github.com/plinker-rpc/core)           |
 +------------------------------------------------------------------------+
 | This source file is subject to MIT License                             |
 | that is bundled with this package in the file LICENSE.                 |
 |                                                                        |
 | If you did not receive a copy of the license and are unable to         |
 | obtain it through the world-wide-web, please send an email             |
 | to license@cherone.co.uk so we can send you a copy immediately.        |
 +------------------------------------------------------------------------+
 | Authors: Lawrence Cherone <lawrence@cherone.co.uk>                     |
 +------------------------------------------------------------------------+
 */

/**
 * Task Build Iptables
 */

if (!empty($this->task->config['debug']) && !defined('DEBUG')) {
    define('DEBUG', true);
}

if (!defined('TMP_DIR')) {
    define('TMP_DIR', (!empty($this->task->config['tmp_dir']) ? $this->task->config['tmp_dir'] : './.plinker'));
}

if (!empty($this->task->config['log']) && !defined('LOG')) {
    define('LOG', true);
}

$params = (array) json_decode($task->tasksource->params, true);

if (!empty($params['lxd']) && !defined('LXD')) {
    define('LXD', $params['lxd']);
}

if (!empty($params['docker']) && !defined('DOCKER')) {
    define('DOCKER', $params['docker']);
}

if (!class_exists('Iptables')) {
    class Iptables
    {
        /**
         *
         */
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
                if (!file_exists(TMP_DIR.'/logs')) {
                    mkdir(TMP_DIR.'/logs', 0755, true);
                }
                $log  = '['.date("c").'] '.$message.PHP_EOL;
                file_put_contents(TMP_DIR.'/logs/'.date("d-m-Y").'.txt', $log, FILE_APPEND);
                
                shell_exec('chown www-data:www-data '.TMP_DIR.'/logs -R');
            }
            
            echo DEBUG ? " - ".$message."\n" : null;
        }

        /**
         *
         */
        public function build()
        {
            $rows = $this->task->count('iptable', 'has_change = 1');
            
            // LXD config must be set
            if (!defined('LXD') || empty(LXD)) {
                return;
            }

            if (empty($rows)) {
                return;
            }
            
            $rows = $this->task->find('iptable');

            $rules = "# Generated on ".date('D M j H:i:s Y')."\n";
            /* MANGLE */
            $rules .= "*mangle\n";
            $rules .= ":PREROUTING ACCEPT [0:0]\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":FORWARD ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":POSTROUTING ACCEPT [0:0]\n";
            $rules .= "-A POSTROUTING -o ".LXD['bridge']." -p udp -m udp --dport 68 -j CHECKSUM --checksum-fill\n";
            $rules .= "COMMIT\n";
            
            /* NAT */
            $rules .= "*nat\n";
            $rules .= ":PREROUTING ACCEPT [0:0]\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":POSTROUTING ACCEPT [0:0]\n";
            if (defined('DOCKER') && !empty(DOCKER)) {
                $rules .= ":DOCKER - [0:0]\n";
                $rules .= "-A PREROUTING -m addrtype --dst-type LOCAL -j DOCKER\n";
                $rules .= "-A OUTPUT ! -d 127.0.0.0/8 -m addrtype --dst-type LOCAL -j DOCKER\n";
                $rules .= "-A POSTROUTING -s ".DOCKER['ip']." ! -o ".DOCKER['bridge']." -j MASQUERADE\n";
            }
            
            /* PREROUTING - Port Forwarding */
            foreach ($rows as $row) {
                if (empty($row['enabled']) || empty($row['type']) || $row['type'] != 'forward') {
                    continue;
                }
                
                echo DEBUG ? $this->log('IPTable rule (PREROUTING): '.$row['label']) : null;
    
                // if server preset type not set
                if (empty($row['srv_type'])) {
                    if (!empty($row['srv_port']) && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":".(int) $row['srv_port']."\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":".(int) $row['srv_port']."\n";
                    }
                } else {
                    // ssh preset range
                    if ($row['srv_type'] == 'ssh' && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":22\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":22\n";
                    }
                    // mySQL preset range
                    elseif ($row['srv_type'] == 'mysql' && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":3306\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":3306\n";
                    }
                    // http preset range
                    elseif ($row['srv_type'] == 'http' && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":80\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":80\n";
                    } else {
                        // custom
                        if (!empty($row['srv_port']) && !empty($row['port']) && !empty($row['ip'])) {
                            $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":".(int) $row['srv_port']."\n";
                            $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":".(int) $row['srv_port']."\n";
                        }
                    }
                }
                
                $row->has_change = 0;
                $this->task->store($row);
            }
            $rules .= "-A POSTROUTING -s ".LXD['ip']." ! -d ".LXD['ip']." -j MASQUERADE\n";
            // iptables -A FORWARD -s 172.16.1.4 -m mac ! --mac-source 00:11:22:33:44:55 -j DROP
            if (defined('DOCKER') && !empty(DOCKER)) {
                $rules .= "-A DOCKER -i ".LXD['bridge']." -j RETURN\n";
            }
            $rules .= "COMMIT\n";
            
            /* FILTER */
            $rules .= "*filter\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":FORWARD ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":fail2ban-ssh - [0:0]\n";
            if (defined('DOCKER') && !empty(DOCKER)) {
                $rules .= ":DOCKER - [0:0]\n";
                $rules .= ":DOCKER-ISOLATION - [0:0]\n";
                $rules .= ":DOCKER-USER - [0:0]\n";
            }
            $rules .= "-A INPUT -p tcp -m multiport --dports 2020 -j fail2ban-ssh\n";
            $rules .= "-A INPUT -p tcp -m multiport --dports 22 -j fail2ban-ssh\n";
            $rules .= "-A INPUT -p tcp -m multiport --dports 2200:2299 -j fail2ban-ssh\n";
            $rules .= "-A INPUT -i ".LXD['bridge']." -p tcp -m tcp --dport 53 -j ACCEPT\n";
            $rules .= "-A INPUT -i ".LXD['bridge']." -p udp -m udp --dport 53 -j ACCEPT\n";
            $rules .= "-A INPUT -i ".LXD['bridge']." -p tcp -m tcp --dport 67 -j ACCEPT\n";
            $rules .= "-A INPUT -i ".LXD['bridge']." -p udp -m udp --dport 67 -j ACCEPT\n";
            $rules .= "-A INPUT -i lo -j ACCEPT\n";
            $rules .= "-A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A INPUT -m conntrack --ctstate INVALID -j DROP\n";
            $rules .= "-A INPUT -p tcp -m tcp --dport 80 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A INPUT -p tcp -m tcp --dport 443 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT\n";
            $rules .= "-A INPUT -p tcp -m tcp --dport 8443 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT\n";
            if (defined('DOCKER') && !empty(DOCKER)) {
                $rules .= "-A FORWARD -j DOCKER-USER\n";
                $rules .= "-A FORWARD -j DOCKER-ISOLATION\n";
                $rules .= "-A FORWARD -o ".DOCKER['bridge']." -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT\n";
                $rules .= "-A FORWARD -o ".DOCKER['bridge']." -j DOCKER\n";
                $rules .= "-A FORWARD -i ".DOCKER['bridge']." ! -o ".DOCKER['bridge']." -j ACCEPT\n";
                $rules .= "-A FORWARD -i ".DOCKER['bridge']." -o ".DOCKER['bridge']." -j ACCEPT\n";
            }
            $rules .= "-A FORWARD -o ".LXD['bridge']." -j ACCEPT\n";
            $rules .= "-A FORWARD -i ".LXD['bridge']." -j ACCEPT\n";
            $rules .= "-A OUTPUT -o lo -j ACCEPT\n";
            $rules .= "-A OUTPUT -p tcp -m tcp --sport 80 -m conntrack --ctstate ESTABLISHED -j ACCEPT\n";
            $rules .= "-A OUTPUT -p tcp -m tcp --sport 443 -m conntrack --ctstate ESTABLISHED -j ACCEPT\n";
            $rules .= "-A OUTPUT -p tcp -m tcp --sport 8443 -m conntrack --ctstate ESTABLISHED -j ACCEPT\n";
            $rules .= "-A OUTPUT -o ".LXD['bridge']." -p tcp -m tcp --sport 53 -j ACCEPT\n";
            $rules .= "-A OUTPUT -o ".LXD['bridge']." -p udp -m udp --sport 53 -j ACCEPT\n";
            $rules .= "-A OUTPUT -o ".LXD['bridge']." -p udp -m udp --sport 67 -j ACCEPT\n";
            if (defined('DOCKER') && !empty(DOCKER)) {
                $rules .= "-A DOCKER-ISOLATION -j RETURN\n";
                $rules .= "-A DOCKER-USER -j RETURN\n";
            }
            
            // blocked hosts
            foreach ($rows as $row) {
                if (empty($row['enabled']) || empty($row['type']) || $row['type'] != 'block') {
                    continue;
                }

                echo DEBUG ? $this->log('IPTable rule (BLOCK): '.$row['ip']) : null;
                
                $rules .= "-A INPUT -s {$row['ip']}/{$row['range']} -j REJECT\n";
                $row->has_change = 0;
                $this->task->store($row);
            }
            
            $rules .= "-A fail2ban-ssh -j RETURN\n";
            $rules .= "COMMIT\n";
            $rules .= "# Completed on ".date('D M j H:i:s Y');

            // write to iptables rules file
            echo DEBUG ? $this->log('Applying IPTables rules') : null;
            
            // check tmp path exists
            if (!file_exists(TMP_DIR.'/iptables')) {
                mkdir(TMP_DIR.'/iptables', 0755, true);
                shell_exec('chown www-data:www-data '.TMP_DIR.'/iptables -R');
            }

            file_put_contents(TMP_DIR.'/iptables/rules.v4', $rules);
    
            //apply iptables
            exec('/sbin/iptables-restore < '.TMP_DIR.'/iptables/rules.v4');
            return;
        }
    }
}

$iptables = new Iptables($this);

$iptables->build();
