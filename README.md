# identification

Middleware for identify user by backoffice webservice

## Need
config + session + log + input

## Use
constants domains {domain} limiter
constants domains {domain} defaultUrlSubscription

## Get
(cookie) u
(get param) u
(get param) userId

## Set
(session) limiterType
(session) subscriptionActive
(session) userId

## Install

You can install the package via composer:
``` bash
$ composer require msol/identification
```

Next, the `\Msol\Identification\Middleware\IdentificationMiddleware::class`-middleware must be registered in the kernel:

```php
//app/Http/Kernel.php

protected $middlewareGroups = [
    'web' => [
        ...
        \Msol\Identification\Middleware\IdentificationMiddleware::class,
    ]
];
```