# view

The View is a template generation for PHP. It allows for creating and managing template files with template tags and variables

## Installation

It's recommended that you use [Composer](https://getcomposer.org/) to install View.

```bash
composer require auroralumina/view
```

## Example

```php
<?php

require_once 'vendor/autoload.php';

$configuration = new AuroraLumina\View\ViewConfiguration([
    __DIR__ . '/views/',
]);

$view = new AuroraLumina\View\View($configuration);

echo $view->render('index');
```
