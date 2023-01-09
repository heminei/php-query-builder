<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
;

$config = new PhpCsFixer\Config();
$config->setUsingCache(true);
$config->setCacheFile(sys_get_temp_dir() . "/php-cs-" . md5(__DIR__) . ".cache");
return $config->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
;
