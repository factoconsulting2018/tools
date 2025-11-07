<?php
/**
 * Application requirement checker script.
 */
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/requirements/YiiRequirementChecker.php';

$checker = new YiiRequirementChecker();
$requirements = [
    [
        'name' => 'PHP version',
        'mandatory' => true,
        'condition' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'by' => 'Yii2 Framework',
        'memo' => 'PHP 7.4.0 or higher is required.',
    ],
];
$checker->checkYii()->check($requirements)->render();

