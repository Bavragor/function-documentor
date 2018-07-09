# function-documentor
Generates formatted documentation of usages of given functions by provided exporter

Current function calls that will be detected:
- ${variable}->${property}->${class}->${function}
- ${class}::${function}

Current exports:
- ArrayExport: Provides an array of detected function calls with their arguments

Example (ArrayExport, no formatters):

Code:
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$functionDocumentor = new \Bavragor\FunctionDocumentor\FunctionDocumentor(
    new \Bavragor\FunctionDocumentor\Export\ArrayExport(),
    'directoryToSourceCode'
);

$functions = $functionDocumentor->retrieve([
    'isys_settings' => [
        'get'
    ],
    'isys_tenantsettings' => [
        'get'
    ],
    'settingsSystem' => [
        'get'
    ]
], ['src/tests'], true);

var_export($functions);
```
Will produce a output like this:
```php
array (
  'isys_settings::get' =>
  array (
    0 =>
    array (
      0 => '$p_key',
      1 => '$p_default',
    ),
  ),
  'isys_tenantsettings::get' =>
  array (
    0 =>
    array (
      0 => 'system.devmode',
    ),
    1 =>
    array (
      0 => '$p_key',
      1 => '$p_default',
    ),
  ),
  'settingsSystem->get' =>
  array (
    0 =>
    array (
      0 => 'system.timezone',
      1 => 'Europe/Berlin',
    ),
    1 =>
    array (
      0 => 'ldap.debug',
      1 => 'true',
    ),
    2 =>
    array (
      0 => 'system.dir.file-upload',
      1 => '$this->app_path/upload/files/$this->app_path/upload/files/',
    ),
    3 =>
    array (
      0 => 'system.dir.image-upload',
      1 => '$this->app_path/upload/images/$this->app_path/upload/images/',
    ),
  ),
)%
```
