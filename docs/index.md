# IPtables

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
| placeholder | string         | Query placeholder |            |
| values      | array          | Match values  |                |

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

### Add Block

Add an IP address to blocked rules.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| data        | array          | Rule data     |                |

**Call**

    $client->iptables->addBlock([
        'ip'      => '123.123.123.123',
        'range'   => 32,
        'note'    => 'Port scanned server',
        'enabled' => 1
    ]);
    
**Response**

``` text
Array
(
    [status] => success
    [values] => Array
        (
            [id] => 3
            [type] => block
            [name] => 7bb82b0c-617d-4343-bca5-f8055a7e3087
            [label] => -
            [ip] => 123.123.123.123
            [range] => 32
            [note] => Port scanned server
            [added_date] => 2018-05-09 22:46:30
            [bantime] => 0
            [enabled] => 1
            [has_change] => 1
        )

)
```

### Update Block

Update a blocked IP address rule.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| placeholder | string         | Query placeholder |            |
| values      | array          | Match values      |            |
| data        | array          | Updated rule data |            |

**Call**

    $client->iptables->updateBlock('id = ?', [3], [
        'label' => '',
        'enabled' => 1,
        'ip' => '212.123.123.123',
        'range' => 32,
        'note' => 'FooBar',
        'bantime' => 0
    ]);
    
**Response**

``` text
Array
(
    [status] => success
    [values] => Array
        (
            [id] => 3
            [type] => block
            [name] => 7bb82b0c-617d-4343-bca5-f8055a7e3087
            [label] => 
            [ip] => 212.123.123.123
            [port] => 
            [srv_type] => 
            [srv_port] => 
            [enabled] => 1
            [added_date] => 2018-05-09 22:46:30
            [has_change] => 1
            [updated_date] => 2018-05-09 22:54:15
            [range] => 32
            [note] => FooBar
            [bantime] => 0
        )

)
```

### Status

Enumarate and return status of used and available ports.

**Call**

    $client->iptables->status();
    
**Response**

``` text
Array
(
    [blocked_rules] => 1
    [forward_rules] => 0
    [total] => 400
    [available] => 400
)
```

### Raw

Fetch raw iptables, equivalent to `iptables-save`.

**Call**

    $client->iptables->raw();
    
**Response**

``` text
# Generated on Thu Jan 25 12:34:56 2018
*mangle
:PREROUTING ACCEPT [0:0]
:INPUT ACCEPT [0:0]
:FORWARD ACCEPT [0:0]
:OUTPUT ACCEPT [0:0]
:POSTROUTING ACCEPT [0:0]
-A POSTROUTING -o lxcbr0 -p udp -m udp --dport 68 -j CHECKSUM --checksum-fill
COMMIT
*nat
:PREROUTING ACCEPT [0:0]
:INPUT ACCEPT [0:0]
:OUTPUT ACCEPT [0:0]
:POSTROUTING ACCEPT [0:0]
:DOCKER - [0:0]
-A PREROUTING -m addrtype --dst-type LOCAL -j DOCKER
-A OUTPUT ! -d 127.0.0.0/8 -m addrtype --dst-type LOCAL -j DOCKER
-A POSTROUTING -s 172.17.0.0/16 ! -o docker0 -j MASQUERADE
-A PREROUTING -p tcp -m tcp --dport 2251 -j DNAT --to-destination 10.158.250.6:22
-A PREROUTING -p udp -m udp --dport 2251 -j DNAT --to-destination 10.158.250.6:22
-A POSTROUTING -s 10.158.250.0/8 ! -d 10.158.250.0/8 -j MASQUERADE
-A DOCKER -i lxcbr0 -j RETURN
COMMIT
*filter
:INPUT ACCEPT [0:0]
:FORWARD ACCEPT [0:0]
:OUTPUT ACCEPT [0:0]
:fail2ban-ssh - [0:0]
:DOCKER - [0:0]
:DOCKER-ISOLATION - [0:0]
:DOCKER-USER - [0:0]
-A INPUT -p tcp -m multiport --dports 2020 -j fail2ban-ssh
-A INPUT -p tcp -m multiport --dports 22 -j fail2ban-ssh
-A INPUT -p tcp -m multiport --dports 2200:2299 -j fail2ban-ssh
-A INPUT -i lxcbr0 -p tcp -m tcp --dport 53 -j ACCEPT
-A INPUT -i lxcbr0 -p udp -m udp --dport 53 -j ACCEPT
-A INPUT -i lxcbr0 -p tcp -m tcp --dport 67 -j ACCEPT
-A INPUT -i lxcbr0 -p udp -m udp --dport 67 -j ACCEPT
-A INPUT -i lo -j ACCEPT
-A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
-A INPUT -m conntrack --ctstate INVALID -j DROP
-A INPUT -p tcp -m tcp --dport 80 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT
-A INPUT -p tcp -m tcp --dport 443 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT
-A INPUT -p tcp -m tcp --dport 8443 -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT
-A FORWARD -j DOCKER-USER
-A FORWARD -j DOCKER-ISOLATION
-A FORWARD -o docker0 -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
-A FORWARD -o docker0 -j DOCKER
-A FORWARD -i docker0 ! -o docker0 -j ACCEPT
-A FORWARD -i docker0 -o docker0 -j ACCEPT
-A FORWARD -o lxcbr0 -j ACCEPT
-A FORWARD -i lxcbr0 -j ACCEPT
-A OUTPUT -o lo -j ACCEPT
-A OUTPUT -p tcp -m tcp --sport 80 -m conntrack --ctstate ESTABLISHED -j ACCEPT
-A OUTPUT -p tcp -m tcp --sport 443 -m conntrack --ctstate ESTABLISHED -j ACCEPT
-A OUTPUT -p tcp -m tcp --sport 8443 -m conntrack --ctstate ESTABLISHED -j ACCEPT
-A OUTPUT -o lxcbr0 -p tcp -m tcp --sport 53 -j ACCEPT
-A OUTPUT -o lxcbr0 -p udp -m udp --sport 53 -j ACCEPT
-A OUTPUT -o lxcbr0 -p udp -m udp --sport 67 -j ACCEPT
-A DOCKER-ISOLATION -j RETURN
-A DOCKER-USER -j RETURN
-A INPUT -s 212.123.123.123/32 -j REJECT
-A fail2ban-ssh -j RETURN
COMMIT
# Completed on Thu Jan 25 12:34:56 2018
```

### Available Ports

Fetch available ports within a range type.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| type        | string         | Port range type | `all`        |

The following port ranges (400 ports) are externally available for forwarding.

| Type        | Range          | Description   |
| ----------  | -------------  | ------------- |
| all         | 2200 - 8099    | Returns all available ports |
| ssh         | 2200 - 2299    | Returns available ssh ports |
| http        | 8000 - 8099    | Returns available http ports |
| mysql       | 3300 - 3399    | Returns available mysql ports |
| shellinabox | 4200 - 4299    | Returns available shellinabox ports |


**Call**

    $client->iptables->availablePorts('http');
    
**Response**

``` text
Array
(
    [0] => 8000
    [1] => 8001
    [2] => 8002
    [3] => 8003
    [4] => 8004
    [5] => 8005
    [6] => 8006
    [7] => 8007
    [8] => 8008
    [9] => 8009
    [10] => 8010
    ... snip
    [99] => 8099
)
```

### Check Port In Use

Check if a port is already in use by a rule.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| port        | int            | Port to check | `0`            |


**Call**

    $client->iptables->checkPortInUse(8000);
    
**Response**

``` text
boolean
```


### Check Allowed Port

Check if a port is in allowed ranges.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| port        | int            | Port to check | `0`            |


**Call**

    $client->iptables->checkAllowedPort(12345);
    
**Response**

``` text
boolean - false in the above case
```

### Add Forward

Add new port forward rule.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| data        | array          | Rule data     |                |

**Call**

    $client->iptables->addForward([
        'label' => 'Example',
        'ip' => '10.158.250.5',
        'port' => 2252,
        'srv_type' => 'SSH',
        'srv_port' => 22,
        'enabled' => 1
    ]);
    
**Response**

``` text
Array
(
    [status] => success
    [values] => Array
        (
            [id] => 5
            [type] => forward
            [name] => d82025df-fc3f-4a2e-bbbd-dde6fddab4cb
            [label] => Example
            [ip] => 10.158.250.5
            [port] => 2252
            [srv_type] => ssh
            [srv_port] => 22
            [enabled] => 1
            [added_date] => 2018-05-10 01:01:46
            [has_change] => 1
        )

)
```

### Update Forward

Update port forward rule.

| Parameter   | Type           | Description   | Default        |
| ----------  | -------------  | ------------- |  ------------- | 
| placeholder | string         | Query placeholder |            |
| values      | array          | Match values      |            |
| data        | array          | Updated rule data |            |

**Call**

    $client->iptables->updateForward('id = ?', [4], [
        'name' => '8610e47a-cf06-4806-964b-c5a3642954bb', // always use, to bypass port in use check
        'label' => 'Example',
        'ip' => '10.158.250.5',
        'port' => 2252,
        'srv_type' => 'SSH',
        'srv_port' => 22,
        'enabled' => 1
    ]);
    
**Response**

``` text
Array
(
    [status] => success
    [values] => Array
        (
            [id] => 4
            [type] => forward
            [name] => 8610e47a-cf06-4806-964b-c5a3642954bb
            [label] => Example
            [ip] => 10.158.250.5
            [port] => 2252
            [srv_type] => SSH
            [srv_port] => 22
            [enabled] => 1
            [added_date] => 2018-05-10 01:01:25
            [has_change] => 1
            [updated_date] => 2018-05-10 01:16:46
            [range] => 
            [note] => 
            [bantime] => 
        )

)
```

## Testing

There are no tests setup for this component.

## Contributing

Please see [CONTRIBUTING](https://github.com/plinker-rpc/iptables/blob/master/CONTRIBUTING) for details.

## Security

If you discover any security related issues, please contact me via [https://cherone.co.uk](https://cherone.co.uk) instead of using the issue tracker.

## Credits

- [Lawrence Cherone](https://github.com/lcherone)
- [All Contributors](https://github.com/plinker-rpc/iptables/graphs/contributors)


## Development Encouragement

If you use this project and make money from it or want to show your appreciation,
please feel free to make a donation [https://www.paypal.me/lcherone](https://www.paypal.me/lcherone), thanks.

## Sponsors

Get your company or name listed throughout the documentation and on each github repository, contact me at [https://cherone.co.uk](https://cherone.co.uk) for further details.

## License

The MIT License (MIT). Please see [License File](https://github.com/plinker-rpc/iptables/blob/master/LICENSE) for more information.

See the [organisations page](https://github.com/plinker-rpc) for additional components.
