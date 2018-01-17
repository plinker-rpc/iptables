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
         * Apply component tasks
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
         * Fetch iptable rules
         *
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
            foreach ((array) $result as $row) {
                $return[] = $this->model->export($row)[0];
            }
            
            return $return;
        }

        /**
         * Count
         *
         * @example
         * <code>
            <?php
            $iptables->count('iptable');
            $iptables->count('iptable', 'id = ? ', [1]);
            $iptables->count('iptable', 'name = ? ', ['guidV4-value']);
           </code>
         *
         * @param array $params
         * @return array
         */
        public function count(array $params = array())
        {
            if (!empty($params[0]) && !empty($params[1]) && !empty($params[2])) {
                $result = $this->model->count([$params[0], $params[1], $params[2]]);
            } elseif (!empty($params[0]) && !empty($params[1])) {
                $result = $this->model->count([$params[0], $params[1]]);
            } else {
                $result = $this->model->count([$params[0]]);
            }

            return (int) $result;
        }
        
        /**
         * Trigger rebuild
         *
         * $iptables->rebuild('name = ?', [$row['name']]);
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
         * Remove iptable rule
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
         * Generate a GUIDv4
         */
        private function guidv4()
        {
            if (function_exists('random_bytes') === true) {
                $bytes = random_bytes(16);
            } elseif (function_exists('openssl_random_pseudo_bytes') === true) {
                $bytes = openssl_random_pseudo_bytes(16);
            } elseif (function_exists('mcrypt_create_iv') === true) {
                $bytes = mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
            } elseif (function_exists('com_create_guid') === true) {
                return trim(com_create_guid(), '{}');
            } else {
                return sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 65535),
                    mt_rand(0, 65535),
                    mt_rand(0, 65535),
                    mt_rand(16384, 20479),
                    mt_rand(32768, 49151),
                    mt_rand(0, 65535),
                    mt_rand(0, 65535),
                    mt_rand(0, 65535)
                );
            }
            $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
            $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        }
        
        /**
         * Add IP block
         *
         * $iptables->addBlock([
         *   'ip'      => '123.123.123.123',
         *   'range'   => 32, // 8, 16. 24. 32
         *   'note'    => 'Port scanned server',
         *   'enabled' => 1
         * ]);
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
                        'added_date' => date_create(),
                        'bantime'    => (!empty($data['bantime']) ? (int) $data['bantime'] : 0),
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
         * Update IP Block
         *
         * $iptables->updateBlock('id=?', [1], [
         *     'label' => ''
         *     'enabled' => 1
         *     'has_change' => 1
         *     'ip' => 212.123.123.123
         *     'range' => 32
         *     'note' => FooBar
         *     'bandate' =>
         *     'bantime' => 0
         * ])
         */
        public function updateBlock(array $params = array())
        {
            $query = $params[0];
            $id    = (array) $params[1];
            $data  = (array) $params[2];
            
            $errors = [];
            
            $iptable = $this->model->findOne(['iptable', $query, $id]);
            
            // check found
            if (empty($iptable->name)) {
                return [
                    'status' => 'error',
                    'errors' => ['query' => 'Rule not found'],
                    'values' => $data
                ];
            }
            
            // dont allow name change
            if (isset($data['name']) && $data['name'] != $iptable->name) {
                return [
                    'status' => 'error',
                    'errors' => ['name' => 'Name cannot be changed'],
                    'values' => $data
                ];
            }
            
            // validate ip
            if (empty($data['ip'])) {
                $errors['ip'] = 'IP is a required field';
            } else {
                if ($this->model->count(['iptable', 'ip = ?', [$data['ip']]]) > 0) {
                    if ($iptable->name != $data['name']) {
                        $errors['ip'] = 'IP already blocked';
                    }
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
            
            // import update
            $iptable->import($data, [
                'label',
                'ip',
                'range',
                'note',
                'bantime',
                'enabled',
                'has_change'
            ]);
            
            // setupdated date and set has change
            $iptable->updated_date = date_create();
            $iptable->has_change = 1;

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
         * Enumarate and return status of ports used.
         *
         * @param array $params
         * @return array
         */
        public function status(array $params = array())
        {
            return [
                'blocked_ip_rules' => $this->count(['iptable', 'type=?', ['block']]),
                'forward_rules' => $this->count(['iptable', 'type=?', ['forward']]),
                'total' => (int) count(array_merge(
                    range(2200, 2299),
                    range(3300, 3399),
                    range(4300, 4399),
                    range(8000, 8099)
                )),
                'available' => (int) count($this->availablePorts())
            ];
        }

        /**
         * Enumarate existing ports used.
         *
         * @param array $params
         * @return array
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
         *
         * @param array $params
         * @return bool
         */
        public function checkPortInUse(array $params = array())
        {
            return ($this->model->count(['iptable', 'port = ?', [$params[0]]]) > 0);
        }

        /**
         * Check if port is within allowed range
         *
         * @param array $params
         * @return bool
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
         * Add port forward
         *
         *  $iptables->addForward([
         *      'label' => 'Example',
         *      'ip' => '10.158.250.5',
         *      'port' => 2251,
         *      'srv_type' => 'SSH',
         *      'srv_port' => 22,
         *      'enabled' => 1
         *  ])
         *
         * @return array
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
                        'added_date' => date_create(),
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
         * Update port forward
         *
         * $iptables->updateForward('id=?', [$forwards[0]['id']], [
         *      'label' => 'Example',
         *      'ip' => '10.158.250.5',
         *      'port' => 2251,
         *      'srv_type' => 'SSH',
         *      'srv_port' => 22,
         *      'enabled' => 1
         * ]);
         *
         * @return array
         */
        public function updateForward(array $params = array())
        {
            $query = $params[0];
            $id    = (array) $params[1];
            $data  = (array) $params[2];
            
            $errors = [];
            
            $iptable = $this->model->findOne(['iptable', $query, $id]);
            
            // check found
            if (empty($iptable->name)) {
                return [
                    'status' => 'error',
                    'errors' => ['query' => 'Forward not found'],
                    'values' => $data
                ];
            }
            
            // dont allow name change
            if (isset($data['name']) && $data['name'] != $iptable->name) {
                return [
                    'status' => 'error',
                    'errors' => ['name' => 'Name cannot be changed'],
                    'values' => $data
                ];
            }
            
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
                    if ($iptable->name != $data['name']) {
                        $errors['port'] = 'Port already in use.';
                    }
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
            
            // import update
            $iptable->import($data, [
                'label',
                'ip',
                'port',
                'srv_type',
                'srv_port',
                'enabled',
                'has_change'
            ]);
            
            // setupdated date and set has change
            $iptable->updated_date = date_create()->format('Y-m-d H:i:s');
            $iptable->has_change = 1;

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
    }
}
