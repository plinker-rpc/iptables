**Plinker-RPC - Iptables**
=========

Plinker PHP RPC client/server makes it really easy to link and execute PHP 
component classes on remote systems, while maintaining the feel of a local 
method call.

WIP: control iptables though rpc

## ::Installing::

Bring in the project with composer:

    {
    	"require": {
    		"plinker/iptables": ">=v0.1"
    	}
    }
    
    
Then navigate to `./vendor/plinker/iptables/scripts` and run `bash install.sh`


::Client::
---------

    /**
     * Plinker Config
     */
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
    
        // construct values which you pass to the component, which the component
        //  will use, for RedbeanPHP component you would send the database connection
        //  dont worry its AES encrypted. see: encryption-proof.txt
        $config
    );
    
::Calls::
---------

**Setup**

Applies build tasks to plinker/tasks queue.

    $iptables->setup([
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

**Create**

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
    $iptables->add($route);

**Update**

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
    $iptables->update('id = ?', [1], $route);

**Fetch**
    
    $iptables->fetch('route');
    $iptables->fetch('route', 'id = ?', [1]);
    $iptables->fetch('route', 'name = ?', ['some-guidV4-value'])

**Remove**

    $iptables->remove('name = ?', [$route['name']]);

**Rebuild**

    $iptables->rebuild('name = ?', [$route['name']]);

**Reset**

    // dont remove tasks
    $iptables->reset();
    
    // remove tasks
    $iptables->reset(true);
    

See the [organisations page](https://github.com/plinker-rpc) for additional 
components and examples.
