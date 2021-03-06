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
 
namespace Plinker\Iptables {

    use Plinker\Tasks\Tasks as TasksManager;
    use Plinker\Redbean\RedBean as Model;

    /**
     * Plinker Iptables Manager class
     *
     * @example
     * <code>
        <?php
        $config = [
            // plinker connection
            'plinker' => [
                'endpoint' => 'http://127.0.0.1:88',
                'public_key'  => 'makeSomethingUp',
                'private_key' => 'againMakeSomethingUp'
            ],

            // database connection
            'database' => [
                'dsn'      => 'sqlite:./.plinker/database.db',
                'host'     => '',
                'name'     => '',
                'username' => '',
                'password' => '',
                'freeze'   => false,
                'debug'    => false,
            ]
        ];

        // init plinker endpoint client
        $iptables = new \Plinker\Core\Client(
            // where is the plinker server
            $config['plinker']['endpoint'],

            // component namespace to interface to
            'Iptables\Manager',

            // keys
            $config['plinker']['public_key'],
            $config['plinker']['private_key'],

            // construct values which you pass to the component
            $config
        );
       </code>
     *
     * @package Plinker\Iptables
     */
    class Iptables
    {
        public $config = array();

        public function __construct(array $config = array())
        {
            $this->config = array_merge([
                // database connection
                'database' => [
                    'dsn'      => 'sqlite:./.plinker/database.db',
                    'host'     => '',
                    'name'     => '',
                    'username' => '',
                    'password' => '',
                    'freeze'   => false,
                    'debug'    => false,
                ],
                'tmp_dir' => './.plinker'
            ], $config);

            // load models
            $this->model = new Model($this->config['database']);
            $this->tasks = new TasksManager($this->config);
        }

        /**
         * Sets up tasks into \Plinker\Tasks
         *
         * @example
         * <code>
            <?php
            $client->iptables->setup([
                'build_sleep' => 5,
                'lxd' => [
                    'bridge' => 'lxcbr0',
                    'ip' => '10.171.90.0/8'
                ],
                'docker' => [
                    'bridge' => 'docker0',
                    'ip' => '172.17.0.0/16'
                ]
            ])
           </code>
         *
         * @param array $params
         * @return array
         */
        public function setup(array $params = array())
        {
            try {
                // create setup task
                if ($this->model->count(['tasks', 'name = "iptables.setup" AND run_count > 0']) > 0) {
                    $this->model->exec(['DELETE from tasks WHERE name = "iptables.setup" AND run_count > 0']);
                }
                // add task
                $task['iptables.setup'] = $this->tasks->create(
                    // name
                    'iptables.setup',
                    // source
                    file_get_contents(__DIR__.'/tasks/setup.php'),
                    // type
                    'php',
                    // description
                    'Configures iptables module.',
                    // default params
                    []
                );
                // queue task for run once
                $this->tasks->run('iptables.setup', (array) $params, 0);
                
                // create build task
                if ($this->model->count(['tasks', 'name = "iptables.build" AND run_count > 0']) > 0) {
                    $this->model->exec(['DELETE from tasks WHERE name = "iptables.build" AND run_count > 0']);
                }
                $task['iptables.build'] = $this->tasks->create(
                    // name
                    'iptables.build',
                    // source
                    file_get_contents(__DIR__.'/tasks/build.php'),
                    // type
                    'php',
                    // description
                    'Builds iptables configuration.',
                    // default params
                    (array) $params
                );
                // queue task to run every second
                $this->tasks->run(
                    'iptables.build',
                    (array) $params,
                    ($params['build_sleep'] ? (int) $params['build_sleep'] : 5)
                 );

                // create composer update task
                if ($this->model->count(['tasks', 'name = "iptables.auto_update" AND run_count > 0']) > 0) {
                    $this->model->exec(['DELETE from tasks WHERE name = "iptables.auto_update" AND run_count > 0']);
                }
                // add
                $task['iptables.auto_update'] = $this->tasks->create(
                    // name
                    'iptables.auto_update',
                    // source
                    "#!/bin/bash\ncomposer update plinker/iptables",
                    // type
                    'bash',
                    // description
                    'Auto update iptables module code.',
                    // default params
                    $params
                );
                // queue task to run every second
                $this->tasks->run(
                    'iptables.auto_update',
                    $params,
                    86400
                 );
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'errors' => [
                        'global' => $e->getMessage()
                    ]
                ];
            }

            return [
                'status' => 'success'
            ];
        }

        /**
         * Runs composer update to update package
         *
         * @example
         * <code>
            <?php
            $iptables->update_package()
           </code>
         *
         * @param array $params
         * @return array
         */
        public function update_package()
        {
            return $this->tasks->run('iptables.auto_update', [], 0);
        }

        /**
         * Fetch iptable rules
         *
         * @usage:
         *  all           - $iptables->fetch();
         *  ruleById(1)   - $iptables->fetch('id = ? ', [1]);
         *  ruleByName(1) - $iptables->fetch('name = ? ', ['guidV4-value'])
         *
         * @return array
         */
        public function fetch($placeholder = null, array $values = [])
        {
            $table = 'iptable';

            if (!empty($placeholder) && !empty($values)) {
                $result = $this->model->findAll([$table, $placeholder, $values]);
            } elseif (!empty($placeholder)) {
                $result = $this->model->findAll([$table, $placeholder]);
            } else {
                $result = $this->model->findAll([$table]);
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
            $iptables->count();
            $iptables->count('id = ? ', [1]);
            $iptables->count('name = ? ', ['guidV4-value']);
           </code>
         *
         * @param array $params
         * @return array
         */
        public function count($placeholder = null, array $values = [])
        {
            $table = 'iptable';

            if (!empty($placeholder) && !empty($values)) {
                $result = $this->model->count([$table, $placeholder, $values]);
            } elseif (!empty($placeholder)) {
                $result = $this->model->count([$table, $placeholder]);
            } else {
                $result = $this->model->count([$table]);
            }

            return (int) $result;
        }

        /**
         * Trigger rebuild
         *
         <code>
            $iptables->rebuild('name = ?', [$row['name']]);
         </code>
         *
         */
        public function rebuild($placeholder = null, array $values = [])
        {
            if (!is_string($placeholder)) {
                return [
                    'status' => 'error',
                    'errors' => ['global' => 'First param must be a string']
                ];
            }

            if (!is_array($values)) {
                return [
                    'status' => 'error',
                    'errors' => ['global' => 'Second param must be an array']
                ];
            }

            $iptable = $this->model->findOne(['iptable', $placeholder, $values]);

            if (empty($iptable)) {
                return [
                    'status' => 'error',
                    'errors' => ['global' => 'Not found']
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
         *
         <code>
            $iptables->remove('name = ?', [$row['name']]);
         </code>
         */
        public function remove($placeholder = null, array $values = [])
        {
            if (!is_string($placeholder)) {
                return [
                    'status' => 'error',
                    'errors' => ['global' => 'First param must be a string']
                ];
            }

            if (!is_array($values)) {
                return [
                    'status' => 'error',
                    'errors' => ['global' => 'Second param must be an array']
                ];
            }

            $iptable = $this->model->findOne(['iptable', $placeholder, $values]);

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
         * Deletes iptables and [related tasks]
         *
         * @example
         * <code>
            <?php
            $iptables->reset();     // deletes rows
            $iptables->reset(true); // deletes rows and tasks (purge)
           </code>
         *
         *
         * @param bool $purge - remove tasks
         * @return array
         */
        public function reset($purge = false)
        {
            $this->model->exec(['DELETE FROM iptable']);

            if (!empty($purge)) {
                $this->model->exec(['DELETE FROM tasks WHERE name = "iptables.setup"']);
                $this->model->exec(['DELETE FROM tasks WHERE name = "iptables.build"']);
                $this->model->exec(['DELETE FROM tasks WHERE name = "iptables.auto_update"']);
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

            $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // set version to 0100
            $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        }

        /**
         * Add IP block
         *
         <code>
            $iptables->addBlock([
                'ip'      => '123.123.123.123',
                'range'   => 32, // 8, 16. 24. 32
                'note'    => 'Port scanned server',
                'enabled' => 1
            ]);
         </code>
         *
         */
        public function addBlock(array $data = array())
        {
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
         <code>
            $iptables->updateBlock('id=?', [1], [
                'label' => '',
                'enabled' => 1,
                'ip' => '212.123.123.123',
                'range' => 32,
                'note' => 'FooBar',
                'bantime' => 0
            ])
         </code>
         */
        public function updateBlock($placeholder = '', array $values = [], array $data = [])
        {
            $errors = [];

            $values[] = 'block';

            $iptable = $this->model->findOne(['iptable', $placeholder.' AND type = ?', $values]);

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
         <code>
            $iptables-status()
         </code>
         *
         * @return array
         */
        public function status()
        {
            return [
                'blocked_rules' => $this->count('type = ?', ['block']),
                'forward_rules' => $this->count('type = ?', ['forward']),
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
         * Get raw current iptables rules.
         *
         <code>
            $iptables-raw()
         </code>
         *
         * @return string
         */
        public function raw()
        {
            return file_get_contents($this->config['tmp_dir'].'/iptables/rules.v4');
        }

        /**
         * Fetch available ports within a range type.
         *
         * @param string $type
         * @return array
         */
        public function availablePorts($type = 'all')
        {
            switch ((string) strtolower($type)) {
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
        public function checkPortInUse(int $port = 0)
        {
            return ($this->model->count(['iptable', 'port = ?', [$port]]) > 0);
        }

        /**
         * Check if port is within allowed range
         *
         * @param array $params
         * @return bool
         */
        public function checkAllowedPort(int $port = 0)
        {
            return (
                in_array($port, array_merge(
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
        public function addForward(array $data = [])
        {
            $errors = [];

            // validate port - needs to be change to accept an array
            if (isset($data['port'])) {
                $data['port'] = trim($data['port']);
                if (empty($data['port'])) {
                    $errors['port'] = 'Leave blank or enter a numeric port number to use this option';
                }
                if (!empty($data['port']) && !is_numeric($data['port'])) {
                    $errors['port'] = 'Invalid port number';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] > 65535) {
                    $errors['port'] = 'Invalid port number';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] == 0) {
                    $errors['port'] = 'Invalid port number';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $this->checkPortInUse($data['port'])) {
                    $errors['port'] = 'Port already in use';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && !$this->checkAllowedPort($data['port'])) {
                    $errors['port'] = 'Invalid port number';
                }
            }

            // validate port - needs to be change to accept an array
            if (isset($data['srv_port'])) {
                $data['srv_port'] = trim($data['srv_port']);
                if (empty($data['srv_port'])) {
                    $errors['srv_port'] = 'Leave blank or enter a numeric port number to use this option';
                }
                if (!empty($data['srv_port']) && !is_numeric($data['srv_port'])) {
                    $errors['srv_port'] = 'Invalid service port number';
                }
                if (!empty($data['srv_port']) && is_numeric($data['srv_port']) && $data['srv_port'] > 65535) {
                    $errors['srv_port'] = 'Invalid service port number';
                }
                if (!empty($data['srv_port']) && is_numeric($data['srv_port']) && $data['srv_port'] == 0) {
                    $errors['srv_port'] = 'Invalid service port number';
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
                        'srv_type'   => (!empty($data['srv_type']) ? strtolower($data['srv_type']) : ''),
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
        public function updateForward($placeholder = '', array $values = [], array $data = [])
        {
            $errors = [];
            
            $values[] = 'forward';

            $iptable = $this->model->findOne(['iptable', $placeholder.' AND type = ?', $values]);

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

            // validate port - needs to be change to accept an array
            if (isset($data['port'])) {
                $data['port'] = trim($data['port']);
                if (empty($data['port'])) {
                    $errors['port'] = 'Leave blank or enter a numeric port number to use this option';
                }
                if (!empty($data['port']) && !is_numeric($data['port'])) {
                    $errors['port'] = 'Invalid port number';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] > 65535) {
                    $errors['port'] = 'Invalid port number';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $data['port'] == 0) {
                    $errors['port'] = 'Invalid port number';
                }
                if (!empty($data['port']) && is_numeric($data['port']) && $this->checkPortInUse($data['port'])) {
                    if ($iptable->name != $data['name']) {
                        $errors['port'] = 'Port already in use';
                    }
                }
                if (!empty($data['port']) && is_numeric($data['port']) && !$this->checkAllowedPort($data['port'])) {
                    $errors['port'] = 'Invalid available port';
                }
            }

            // validate port - needs to be change to accept an array
            if (isset($data['srv_port'])) {
                $data['srv_port'] = trim($data['srv_port']);
                if (empty($data['srv_port'])) {
                    $errors['srv_port'] = 'Leave blank or enter a numeric port number to use this option';
                }
                if (!empty($data['srv_port']) && !is_numeric($data['srv_port'])) {
                    $errors['srv_port'] = 'Invalid service port number';
                }
                if (!empty($data['srv_port']) && is_numeric($data['srv_port']) && $data['srv_port'] > 65535) {
                    $errors['srv_port'] = 'Invalid service port number';
                }
                if (!empty($data['srv_port']) && is_numeric($data['srv_port']) && $data['srv_port'] == 0) {
                    $errors['srv_port'] = 'Invalid service port number';
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
                'enabled'
            ]);

            // set updated date and set has change
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
