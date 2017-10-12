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
            
            echo DEBUG ? " - ".$message."\n" : null;
        }

        /**
         *
         */
        public function build()
        {
            $rows = $this->task->count('iptable', 'has_change = 1');

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
            $rules .= "-A POSTROUTING -o lxcbr0 -p udp -m udp --dport 68 -j CHECKSUM --checksum-fill\n";
            $rules .= "COMMIT\n";
            /* NAT */
            $rules .= "*nat\n";
            $rules .= ":PREROUTING ACCEPT [0:0]\n";
            $rules .= ":INPUT ACCEPT [0:0]\n";
            $rules .= ":OUTPUT ACCEPT [0:0]\n";
            $rules .= ":POSTROUTING ACCEPT [0:0]\n";
            /* PREROUTING - Port Forwarding */
            foreach ($rows as $row) {
                if (empty($row['enabled']) || empty($row['type']) || $row['type'] != 'forward' ) {
                    continue;
                }
                
                echo DEBUG ? $this->log('IPTable rule (PREROUTING): '.$row) : null;
    
                // if server preset type not set
                if (empty($row['srv_type'])) {
                    if (!empty($row['srv_port']) && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":".(int) $row['srv_port']."\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":".(int) $row['srv_port']."\n";
                    }
                } else {
                    // ssh preset range
                    if ($row['srv_type'] == 'SSH' && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":22\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":22\n";
                    } 
                    // mySQL preset range
                    elseif ($row['srv_type'] == 'mySQL' && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":3306\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":3306\n";
                    }
                    // http preset range
                    elseif ($row['srv_type'] == 'HTTP' && !empty($row['port']) && !empty($row['ip'])) {
                        $rules .= "-A PREROUTING -p tcp -m tcp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":80\n";
                        $rules .= "-A PREROUTING -p udp -m udp --dport ".(int) $row['port']." -j DNAT --to-destination ".$row['ip'].":80\n";
                    } 
                    else {
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
            $rules .= "-A POSTROUTING -s ".NAT_POSTROUTING." ! -d ".NAT_POSTROUTING." -j MASQUERADE\n";
            // iptables -A FORWARD -s 172.16.1.4 -m mac ! --mac-source 00:11:22:33:44:55 -j DROP
            $rules .= "COMMIT\n";
            
            /* FILTER */
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
            // blocked hosts
            foreach ($rows as $row) {
                if (empty($row['enabled']) || empty($row['type']) || $row['type'] != 'block') {
                    continue;
                }
                $rules .= "-A INPUT -s {$row['ip']}/{$row['range']} -j REJECT\n";
                $row->has_change = 0;
                $this->task->store($row);
            }
            $rules .= "-A fail2ban-ssh -j RETURN\n";
            $rules .= "COMMIT\n";
            $rules .= "# Completed on ".date('D M j H:i:s Y');

            // write to iptables rules file
            echo DEBUG ? $this->log('Applying IPTables rules') : null;

            file_put_contents(getcwd().'/iptables.rules.v4', $rules);
    
            //apply iptables
            exec('/sbin/iptables-restore < '.getcwd().'/iptables.rules.v4');
            return;
        }
    }
}

$iptables = new Iptables($this);

$iptables->build();
