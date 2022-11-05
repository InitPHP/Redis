## InitPHP Redis Management

This library was born to facilitate and customize the use of getter and setter of PHP and Redis.

## Requirements

- PHP 7.4 or later
- PHP Redis Extension

## Installation

```
composer require initphp/redis
```

## Usage

```php
require_once "vendor/autoload.php";
use \InitPHP\Redis\Redis;

// Provide your connection information;
$redis = new Redis([
        'prefix'        => 'i_',
        'host'          => '127.0.0.1',
        'password'      => null,
        'port'          => 6379,
        'timeout'       => 0,
        'database'      => 0,
]);

// Use Setter and Getter;
$redis->set('name', 'muhammet');
if($redis->has('name')){
    echo $redis->get('name'); // "muhammet"
}

/**
 * or tell the get method what it will 
 * do if it can't find it, 
 * or a default value it will return;
 */
echo $redis->get('username', 'Undefined'); // "Undefined"

echo $redis->get('surname', function () use ($redis) {
    $value = 'ŞAFAK';
    $redis->set('surname', $value);
    return $value;
}); // "ŞAFAK"
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE) 
