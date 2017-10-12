<?php
namespace Plinker\Iptables {

    use Plinker\Tasks;
    use Plinker\Redbean\RedBean as Model;

    class Manager
    {
        public $config = array();

        public function __construct(array $config = array())
        {
            $this->config = $config;

            // load models
            $this->model = new Model($this->config['database']);
            $this->tasks = new Tasks\Manager($this->config);
        }

        /**
         *
         */
        public function setup(array $params = array())
        {
            try {
                // create setup task
                $task['iptables.setup'] = $this->tasks->create([
                    // name
                    'iptables.setup',
                    // source
                    file_get_contents(__DIR__.'/tasks/setup.php'),
                    // type
                    'php',
                    // description
                    'Sets up iptables for plinker',
                    // default params
                    []
                 ]);
                // queue task for run once
                $this->tasks->run(['iptables.setup', [], 0]);

                // create build task
                $task['iptables.build'] = $this->tasks->create([
                    // name
                    'iptables.build',
                    // source
                    file_get_contents(__DIR__.'/tasks/build.php'),
                    // type
                    'php',
                    // description
                    'Builds iptables',
                    // default params
                    []
                 ]);
                // queue task to run every second
                $this->tasks->run(
                    [
                        'iptables.build',
                        $params[0],
                        ($params[0]['build_sleep'] ? (int) $params[0]['build_sleep'] : 5)
                    ]
                 );
            } catch (\Exception $e) {
                return $e->getMessage();
            }
            
            // // clean up old setup tasks
            $this->model->exec(['DELETE from tasks WHERE name = "iptables.setup" AND run_count > 0']);

            return [
                'status' => 'success'
            ];
        }
        
        /**
         * Fetch iptables:
         * @usage:
         *  all           - $iptables->fetch('iptable');
         *  ruleById(1)   - $iptables->fetch('iptable', 'id = ? ', [1]);
         *  ruleByName(1) - $iptables->fetch('iptable', 'name = ? ', ['guidV4-value'])
         *
         * @return array
         */
        public function fetch(array $params = array())
        {
            if (!empty($params[0]) && !empty($params[1]) && !empty($params[2])) {
                $result = $this->model->findAll([$params[0], $params[1], $params[2]]);
            } elseif (!empty($params[0]) && !empty($params[1])) {
                $result = $this->model->findAll([$params[0], $params[1]]);
            } else {
                $result = $this->model->findAll([$params[0]]);
            }
            
            $return = [];
            foreach ($result as $row) {
                $return[] = $this->model->export($row)[0];
            }
            
            return $return;
        }
        
        /**
         *
         */
        public function rebuild(array $params = array())
        {
            if (!is_string($params[0])) {
                return [
                    'status' => 'error',
                    'errors' => ['params' => 'First param must be a string']
                ];
            }
            
            if (!is_array($params[1])) {
                return [
                    'status' => 'error',
                    'errors' => ['params' => 'Second param must be an array']
                ];
            }
            
            $iptable = $this->model->findOne(['iptable', $params[0], $params[1]]);

            if (empty($iptable)) {
                return [
                    'status' => 'error',
                    'errors' => ['iptable' => 'Not found']
                ];
            }
            
            $iptable->has_change = 1;

            $this->model->store($iptable);
            
            return [
                'status' => 'success'
            ];
        }

        /**
         *
         */
        public function remove(array $params = array())
        {
            if (!is_string($params[0])) {
                return [
                    'status' => 'error',
                    'errors' => ['params' => 'First param must be a string']
                ];
            }
            
            if (!is_array($params[1])) {
                return [
                    'status' => 'error',
                    'errors' => ['params' => 'Second param must be an array']
                ];
            }
            
            $iptable = $this->model->findOne(['iptable', $params[0], $params[1]]);

            if (empty($iptable)) {
                return [
                    'status' => 'error',
                    'errors' => ['iptable' => 'Not found']
                ];
            }
            
            $this->model->trash($iptable);
            
            return [
                'status' => 'success'
            ];
        }

        /**
         * Deletes all route, domain, upstreams and [related tasks]
         *
         * @param bool $param[0] - remove tasks
         */
        public function reset(array $params = array())
        {
            $this->model->exec(['DELETE FROM iptable']);

            if (!empty($params[0])) {
                $this->model->exec(['DELETE FROM tasks WHERE name = "iptables.setup"']);
                $this->model->exec(['DELETE FROM tasks WHERE name = "iptables.build"']);
                $this->model->exec(['DELETE FROM tasks WHERE name = "iptables.reconcile"']);
            }

            return [
                'status' => 'success'
            ];
        }
        
        /**
         * Generate a GUIv4
         */
        private function guidv4()
        {
            if (function_exists('com_create_guid') === true) {
                return trim(com_create_guid(), '{}');
            }
        
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
        
        /**
         *
         */
        public function addBlock(array $params = array())
        {
            $data = $params[0];
            
            $errors = [];
            
            // validate ip
            if (empty($data['ip'])) {
                $errors['ip'] = 'IP is a required field';
            } else {
                if ($this->model->count(['iptable', 'ip = ?', [$data['ip']]]) > 0) {
                    $errors['ip'] = 'IP already blocked';
                }
                
                if (!filter_var($data['ip'], FILTER_VALIDATE_IP)) {
                    $errors['ip'] = 'Invalid IP address';
                }
            }
            
            // validate range
            if (empty($data['range'])) {
                $errors['range'] = 'Range is a required field';
            } else {
                if (!in_array((int) $data['range'], [8, 16, 24, 32])) {
                    $errors['range'] = 'Invalid range. Only 8/16/24/32 is supported';
                }
            }

            // has error/s
            if (!empty($errors)) {
                return [
                    'status' => 'error',
                    'errors' => $errors,
                    'values' => $data
                ];
            }
            
            // set guid name
            $data['name'] = $this->guidv4();

            // create iptable
            $iptable = $this->model->create(
                [
                    'iptable',
                    [
                        'type'       => 'block',
                        'name'       => (!empty($data['name']) ? $data['name'] : '-'),
                        'label'      => (!empty($data['label']) ? $data['label'] : '-'),
                        'ip'         => (!empty($data['ip']) ? $data['ip'] : ''),
                        'range'      => (!empty($data['range']) ? $data['range'] : ''),
                        'note'       => (isset($data['note']) ? $data['note'] : ''),
                        'added_date' => date_create()->format('Y-m-d H:i:s'),
                        'bantime'    => (!empty($data['bantime']) ? (int) $data['bantime'] : 0),
                        'has_change' => 1
                    ]
                ]
            );

            try {
                $this->model->store($iptable);
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'errors' => ['store' => $e->getMessage()],
                    'values' => $data
                ];
            }
            
            $data = $this->model->export($iptable)[0];

            return [
                'status' => 'success',
                'values' => $data
            ];
        }
        
        /**
         * Lookup existing ports used, and return array containing unused ports within the range.
         */
        public function availablePorts(array $params = array())
        {
            switch ((string) strtolower($params[0])) {
                case "ssh": {
                    $range = range(2200, 2299);
                    $port  = 22;
                } break;

                case "http": {
                    $range = range(8000, 8099);
                    $port  = 80;
                } break;

                case "mysql": {
                    $range = range(3300, 3399);
                    $port  = 33;
                } break;

                case "shellinabox": {
                    $range = range(4200, 4299);
                    $port  = 42;
                } break;

                case "":
                case "all": {
                    $range = array_merge(
                        range(2200, 2299),
                        range(3300, 3399),
                        range(4200, 4299),
                        range(8000, 8099)
                    );
                    $port  = null;
                } break;
            }

            if ($port === null) {
                $current = $this->model->getCol([
                    'SELECT port FROM iptable'
                ]);
            } else {
                $current = $this->model->getCol([
                    'SELECT port FROM iptable WHERE port LIKE ?',
                    [
                        $port.'%'
                    ]
                ]);
            }

            return array_values(array_diff(
                (array) $range,
                (array) $current
            ));
        }

        /**
         * Check if a host/external port is in use
         */
        public function checkPortInUse(array $params = array())
        {
            return ($this->model->count(['iptable', 'port = ?', [$params[0]]]) > 0);
        }

        /**
         * Check if port is within allowed range
         */
        public function checkAllowedPort(array $params = array())
        {
            return (
                in_array($params[0], array_merge(
                    range(2200, 2299),
                    range(3300, 3399),
                    range(4300, 4399),
                    range(8000, 8099)
                ))
            );
        }

        /**
         *
         */
        public function addForward(array $params = array())
        {
            $data = $params[0];
            
            $errors = [];

            // validate port - needs to be change to accept an array
            if (isset($data['port'])) {
                $data['port'] = trim($data['port']);
                if (empty($data['port'])) {
                    $errors['port'] = 'Leave blank or enter a numeric port number to use this option.';
                }
                if (!empty($data['port']) && !is_numeric($data['port'])) {
                    $errors['port'] = 'Invalid port number.';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] > 65535) {
                    $errors['port'] = 'Invalid port number.';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] == 0) {
                    $errors['port'] = 'Invalid port number.';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $this->checkPortInUse([$data['port']])) {
                    $errors['port'] = 'Port already in use.';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && !$this->checkAllowedPort([$data['port']])) {
                    $errors['port'] = 'Invalid available port.';
                }
            }

            // validate port - needs to be change to accept an array
            if (isset($data['srv_port'])) {
                $data['srv_port'] = trim($data['srv_port']);
                if (empty($data['srv_port'])) {
                    $errors['srv_port'] = 'Leave blank or enter a numeric port number to use this option.';
                }
                if (!empty($data['srv_port']) && !is_numeric($data['srv_port'])) {
                    $errors['srv_port'] = 'Invalid service port number.';
                }
                if (!empty($data['srv_port']) && is_numeric($data['srv_port']) && $data['srv_port'] > 65535) {
                    $errors['srv_port'] = 'Invalid service port number.';
                }
                if (!empty($data['srv_port']) && is_numeric($data['srv_port']) && $data['srv_port'] == 0) {
                    $errors['srv_port'] = 'Invalid service port number.';
                }
            }
            
            // has error/s
            if (!empty($errors)) {
                return [
                    'status' => 'error',
                    'errors' => $errors,
                    'values' => $data
                ];
            }
            
            // set guid name
            $data['name'] = $this->guidv4();

            // create iptable
            $iptable = $this->model->create(
                [
                    'iptable',
                    [
                        'type'       => 'forward',
                        'name'       => (!empty($data['name']) ? $data['name'] : '-'),
                        'label'      => (!empty($data['label']) ? $data['label'] : '-'),
                        'ip'         => (!empty($data['ip']) ? $data['ip'] : ''),
                        'port'       => (!empty($data['port']) ? $data['port'] : ''),
                        'srv_type'   => (!empty($data['srv_type']) ? $data['srv_type'] : ''),
                        'srv_port'   => (!empty($data['srv_port']) ? $data['srv_port'] : ''),
                        'enabled'    => !empty($data['enabled']),
                        'has_change' => 1
                    ]
                ]
            );

            try {
                $this->model->store($iptable);
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'errors' => ['store' => $e->getMessage()],
                    'values' => $data
                ];
            }
            
            $data = $this->model->export($iptable)[0];

            return [
                'status' => 'success',
                'values' => $data
            ];
        }
        
        /**
         * Update webforward
         * - Treat as findOne with additional param for data,
         *   this allows to update based on any column.
         *
         * @usage: $nginx->update('id = ?', [1], $form['values'])
         *         $nginx->update('name = ?', ['0e5391ac-a37f-41cf-a36b-369df19e592f'], $form['values'])
         *         $nginx->update('id = ? AND name = ?', [23, '0e5391ac-a37f-41cf-a36b-369df19e592f'], $form['values'])
         *
         */
        public function update(array $params = array())
        {
            $query = $params[0];
            $id    = (array) $params[1];
            $data  = (array) $params[2];
            
            $errors = [];
            
            $route = $this->model->findOne('route', $query, $id);
            
            // check found
            if (empty($route->name)) {
                return [
                    'status' => 'error',
                    'errors' => ['query' => 'Route not found']
                ];
            }
            
            // dont allow name change
            if (isset($data['name']) && $data['name'] != $route->name) {
                return [
                    'status' => 'error',
                    'errors' => ['name' => 'Name cannot be changed']
                ];
            }

            // validate ip - needs to be change to accept an array
            if (isset($data['ip'])) {
                $data['ip'] = trim($data['ip']);
                if (empty($data['ip'])) {
                    $errors['ip'] = 'Leave blank or enter a correct IP address to use this option';
                }
                //if (!filter_var($data['ip'], FILTER_VALIDATE_IP)) {
                //	$errors['ip'] = 'Invalid IP address';
                //}
            }

            // validate port - needs to be change to accept an array
            if (isset($data['port'])) {
                $data['port'] = trim($data['port']);
                if (empty($data['port'])) {
                    $errors['port'] = 'Leave blank or enter a numeric port number to use this option';
                }
                if (!empty($data['port']) && !is_numeric($data['port'])) {
                    $errors['port'] = 'Invalid port number!';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] > 65535) {
                    $errors['port'] = 'Invalid port number!';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] == 0) {
                    $errors['port'] = 'Invalid port number!';
                }
            }

            // check ssl letsencrypt
            if (isset($data['letsencrypt'])) {
                if (!empty($data['letsencrypt'])) {
                    $data['ssl_type'] = 'letsencrypt';
                } else {
                    $data['ssl_type'] = '';
                }
            }

            // validate domains
            if (isset($data['domains'])) {
                foreach ((array) $data['domains'] as $key => $row) {
                    // filter
                    if (stripos($row, 'http') === 0) {
                        $row = substr($row, 4);
                    }
                    if (stripos($row, 's://') === 0) {
                        $row = substr($row, 4);
                    }
                    if (stripos($row, '://') === 0) {
                        $row = substr($row, 3);
                    }
                    if (stripos($row, '//') === 0) {
                        $row = substr($row, 2);
                    }
    
                    // check for no dots
                    if (!substr_count($row, '.')) {
                        $errors['domains'][$key] = 'Invalid domain name';
                    }
    
                    // has last dot
                    if (substr($row, -1) == '.') {
                        $errors['domains'][$key] = 'Invalid domain name';
                    }
    
                    // validate url
                    if (!filter_var('http://' . $row, FILTER_VALIDATE_URL)) {
                        $errors['domains'][$key] = 'Invalid domain name';
                    }
    
                    // domain already in use by another route
                    if ($this->model->count('domain', 'name = ? AND route_id != ?', [$row, $route->id]) > 0) {
                        $errors['domains'][$key] = 'Domain already in use';
                    }
                }
            }

            // validate upstream
            if (isset($data['upstreams'])) {
                foreach ((array) $data['upstreams'] as $key => $row) {
                    // validate ip
                    if (!filter_var($row['ip'], FILTER_VALIDATE_IP)) {
                        $errors['upstreams'][$key] = 'Invalid IP address';
                    }
                    if (empty($row['port']) || !is_numeric($row['port'])) {
                        $errors['upstreams'][$key] = 'Invalid port';
                    } else {
                        if ($row['port'] < 1 || $row['port'] > 65535) {
                            $errors['upstreams'][$key] = 'Invalid port';
                        }
                    }
                }
            }

            // has error/s
            if (!empty($errors)) {
                return [
                    'status' => 'error',
                    'errors' => $errors
                ];
            }

            // update route
            if (isset($data['label'])) {
                $route->label    = $data['label'];
            }
            if (isset($data['ssl_type'])) {
                $route->ssl_type = preg_replace('/[^a-z]/i', '', $data['ssl_type']);
            }
            if (isset($data['enabled'])) {
                $route->enabled  = !empty($data['enabled']);
            }
            
            $route->updated = date_create();
            $route->has_change = 1;

            // create domains
            if (isset($data['domains'])) {
                $route->xownDomainList = [];
                $domains = [];
                foreach ((array) $data['domains'] as $row) {
                    $domain = $this->model->create(
                        [
                            'domain',
                            'name' => str_replace(['http://', 'https://', '//'], '', $row)
                        ]
                    );
                    $domains[] = $domain;
                }
                $route->xownDomainList = $domains;
            }

            // upstreams
            // set first ip back into route
            if (isset($data['domains'])) {
                if (isset($data['upstreams'][0]['ip'])) {
                    $route->ip = $data['upstreams'][0]['ip'];
                } else {
                    $route->ip = !empty($data['ip']) ? $data['ip'] : '';
                }
    
                // set first port back into route
                if (isset($data['upstreams'][0]['port'])) {
                    $route->port = (int) $data['upstreams'][0]['port'];
                } else {
                    $route->port = !empty($data['port']) ? preg_replace('/[^0-9]/', '', $data['port']) : '';
                }
    
                // create upstreams
                $route->xownUpstreamList = [];
                $upstreams = [];
                foreach ((array) $data['upstreams'] as $row) {
                    $upstream = $this->model->create(
                        [
                            'upstream',
                            'ip' => $row['ip']
                        ]
                    );
                    $upstream->port = (int) $row['port'];
                    $upstreams[] = $upstream;
                }
                $route->xownUpstreamList = $upstreams;
            }

            try {
                $this->model->store($route);
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'errors' => ['store' => $e->getMessage()]
                ];
            }
            
            return [
                'status' => 'success'
            ];
        }
    }
}
