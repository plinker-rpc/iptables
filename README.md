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
| options     | array          | Build options |                |

**Call**

    $client->iptables->setup([
        'build_sleep' => 5,
        'reconcile_sleep' => 5,
    ]);

**Response**
``` text
```

### Update Package

Runs composer update to update package.

**Call**

    $client->iptables->update_package();

**Response**
``` text
```

### Fetch

Fetch currently configured forward or blocked rules from database.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| placeholder | string         | Query placeholder | |
| values      | array          | Match values  |              |

**Call**

    all           - $client->iptables->fetch();
    ruleById(1)   - $client->iptables->fetch('id = ?', [1]);
    ruleByName(1) - $client->iptables->fetch('name = ?', ['guidV4-value'])
    
**Response**
``` text
Array
(
    [0] => Array
        (
            [id] => 1
            [type] => forward
            [name] => 5b1b63cd-0106-4fde-ba3f-8b252ae400a1
            [label] => Example
            [ip] => 10.100.200.2
            [port] => 2251
            [srv_type] => SSH
            [srv_port] => 22
            [enabled] => 1
            [added_date] => 2018-01-25 02:18:26
            [has_change] => 0
            [updated_date] => 2018-01-25 02:18:26
            [range] => 
            [note] => 
            [bantime] => 
        )

)
```


### Count

Fetch count of currently configured forward or blocked rules from database.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| placeholder | string         | Query placeholder | |
| values      | array          | Match values  |              |

**Call**

    all           - $client->iptables->count();
    ruleById(1)   - $client->iptables->count('id = ?', [1]);
    ruleByName(1) - $client->iptables->count('name = ?', ['guidV4-value'])
    
**Response**
``` text
1
```

### Rebuild

Rebuild forward or blocked rule.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| placeholder | string         | Query placeholder | |
| values      | array          | Match values  |              |

**Call**

    ruleById(1)   - $client->iptables->rebuild('id = ?', [1]);
    ruleByName(1) - $client->iptables->rebuild('name = ?', ['guidV4-value'])
    
**Response**
``` text
Array
(
    [status] => success
)
```

### Remove

Remove forward or blocked rule.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| placeholder | string         | Query placeholder | |
| values      | array          | Match values  |              |

**Call**

    ruleById(1)   - $client->iptables->remove('id = ?', [1]);
    ruleByName(1) - $client->iptables->remove('name = ?', ['guidV4-value'])
    
**Response**
``` text
Array
(
    [status] => success
)
```

### Reset

Remove all forwards and blocked rules.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| purge       | bool           | Also remove tasks | `false`    |

**Call**

    $client->iptables->reset();     // remove just rules
    $client->iptables->reset(true); // remove rules and tasks (purge)
  
**Response**
``` text
Array
(
    [status] => success
)
```

@todo

### addBlock
### updateBlock
### status
### raw
### availablePorts
### checkPortInUse
### checkAllowedPort
### addForward
### updateForward


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
