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
// init fatfree instance
$f3 = \Base::instance();

// load config
$f3->config('config.ini');

$client = new \Plinker\Core\Client('http://127.0.0.1:88', [
  'secret' => $f3->get('AUTH.secret'),
    'database' => $f3->get('db')
]);

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
]);
