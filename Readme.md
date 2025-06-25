# Bangladeshi Courier Fraud Checker

## Requirements

- PHP >=7.4
- Laravel >= 8

## Installation

```bash
composer require programmertowheed/bd-courier-fraud-checker
```

### vendor publish (config)

```bash
php artisan vendor:publish --provider="Programmertowheed\BdCourierFraudChecker\BdCourierFraudCheckerServiceProvider"
```

or

```bash
php artisan vendor:publish --tag=bdcourierfraudchecker-config
```

After publish config file setup your credential. you can see this in your config directory
bdcourierfraudchecker-config.php file

```
"pathao_user" => env("PATHAO_USER", ""),
"pathao_password" => env("PATHAO_PASSWORD", ""),

"redx_phone" => env("REDX_PHONE", ""),
"redx_password" => env("REDX_PASSWORD", ""),

"steedfast_user" => env("STEADFAST_USER", ""),
"steedfast_password" => env("STEADFAST_PASSWORD", ""),

'message' => [
    "pathao_user" => 'PATHAO_USER',
    "pathao_password" => 'PATHAO_PASSWORD',
    "redx_phone" => 'REDX_PHONE',
    "redx_password" => 'REDX_PASSWORD',
    "steedfast_user" => 'STEADFAST_USER',
    "steedfast_password" => 'STEADFAST_PASSWORD',
],
```

### Set .env configuration

```
PATHAO_USER=""
PATHAO_PASSWORD=""

REDX_PHONE=""
REDX_PASSWORD=""

STEADFAST_USER=""
STEADFAST_PASSWORD=""
```

---

## Usage

### Basic Usage

```
use Programmertowheed\BdCourierFraudChecker\Facade\BdCourierFraudChecker;

$response = BdCourierFraudChecker::check("01991858371");
print_r($response);
```

**Output:**

```php
[
    'steadfast' => [
        'status' => true,
        'message' => 'Successful.',
        'data' => [
            'success' => 2,
            'cancel' => 0,
            'total' => 2,
            'deliveredPercentage' => 100,
            'returnPercentage' => 0,
        ],
    ],
    'pathao' => [
        'status' => true,
        'message' => 'Successful.',
        'data' => [
            'success' => 3,
            'cancel' => 0,
            'total' => 3,
            'deliveredPercentage' => 100,
            'returnPercentage' => 0,
        ],
    ],
    'redx' => [
        'status' => true,
        'message' => 'Successful.',
        'data' => [
            'success' => 0,
            'cancel' => 0,
            'total' => 0,
            'deliveredPercentage' => 0,
            'returnPercentage' => 0,
        ],
    ],
]
```

## 🛠️ Advanced Usage

### Using Individual Services

```php
use Programmertowheed\BdCourierFraudChecker\Courier\Pathao;
use Programmertowheed\BdCourierFraudChecker\Courier\Steadfast;
use Programmertowheed\BdCourierFraudChecker\Courier\Redx;

$pathao = (new Pathao)->pathao("01991858371");
$steadfast = (new Steadfast())->steadfast("01991858371");
$redx = (new Redx())->redx("01991858371");
```

## License

This repository is licensed under the [MIT License](http://opensource.org/licenses/MIT).

Copyright 2025 [Md Towheedul Islam](https://github.com/programmertowheed).
