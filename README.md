# PlinkerRPC - Iptables

Control iptables for pre routing rules (port forwarding). Specifically suited for forwarding ports to internal LXC containers.

## Install

Require this package with composer using the following command:

``` bash
$ composer require plinker/iptables
```

Then navigate to `./vendor/plinker/iptables/scripts` and run `bash install.sh`.


## Client

Creating a client instance is done as follows:


    <?php
    require 'vendor/autoload.php';

    /**
     * Initialize plinker client.
     *
     * @param string $server - URL to server listener.
     * @param string $config - server secret, and/or a additional component data
     */
    $client = new \Plinker\Core\Client(
        'http://example.com/server.php',
        [
            'secret' => 'a secret password',
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
        ]
    );
    
    // or using global function
    $client = plinker_client('http://example.com/server.php', 'a secret password', [
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
    ]);
    

## Methods

Once setup, you call the class though its namespace to its method.


### Setup

Applies build tasks to plinker/tasks queue.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| options     | array          | Task options |  |

**Call**

    $client->iptables->setup([
        'build_sleep' => 5,
        // LXD settings *required
        'lxd' => [
            'bridge' => 'lxdbr0',
            'ip' => '10.158.250.0/24'
        ],
        // Docker settings *optional
        'docker' => [
            'bridge' => 'docker0',
            'ip' => '172.17.0.0/16'
        ]
    ]);

**Response**
``` text
```

### Create

**Call**

    $route = [
        'label' => 'Example',
        'domains' => [
            'example.com',
            'www.example.com'
        ],
        'upstreams' => [
            ['ip' => '127.0.0.1', 'port' => '80']
        ],
        'letsencrypt' => 0,
        'enabled' => 1
    ];
    $client->iptables->add($route);

**Response**
``` text
```

### Update

**Call**

    $route = [
        'label' => 'Example Changed',
        'domains' => [
            'example.com',
            'www.example.com',
            'new.example.com',
        ],
        'upstreams' => [
            ['ip' => 10.0.0.1', 'port' => '8080']
        ],
        'letsencrypt' => 0,
        'enabled' => 1
    ];
    // column, value, $data
    $client->iptables->update('id = ?', [1], $route);
    
**Response**
``` text
```

### Fetch
    
**Call**

    $client->iptables->fetch('route');
    $client->iptables->fetch('route', 'id = ?', [1]);
    $client->iptables->fetch('route', 'name = ?', ['some-guidV4-value'])

**Response**
``` text
```

### Remove

**Call**

    $client->iptables->remove('name = ?', [$route['name']]);
    
**Response**
``` text
```

### Rebuild

**Call**

    $client->iptables->rebuild('name = ?', [$route['name']]);
    
**Response**
``` text
```

### Reset

**Call**

    // dont remove tasks
    $client->iptables->reset();
    
    // remove tasks
    $client->iptables->reset(true);

**Response**
``` text
```

## Testing

There are no tests setup for this component.

## Contributing

Please see [CONTRIBUTING](https://github.com/plinker-rpc/files/blob/master/CONTRIBUTING) for details.

## Security

If you discover any security related issues, please contact me via [https://cherone.co.uk](https://cherone.co.uk) instead of using the issue tracker.

## Credits

- [Lawrence Cherone](https://github.com/lcherone)
- [All Contributors](https://github.com/plinker-rpc/files/graphs/contributors)


## Development Encouragement

If you use this project and make money from it or want to show your appreciation,
please feel free to make a donation [https://www.paypal.me/lcherone](https://www.paypal.me/lcherone), thanks.

## Sponsors

Get your company or name listed throughout the documentation and on each github repository, contact me at [https://cherone.co.uk](https://cherone.co.uk) for further details.

## License

The MIT License (MIT). Please see [License File](https://github.com/plinker-rpc/files/blob/master/LICENSE) for more information.

See the [organisations page](https://github.com/plinker-rpc) for additional components.
