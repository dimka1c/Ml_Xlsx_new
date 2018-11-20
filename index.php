<?php

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Controller\AppController;
use Model\CsvModel;

new \vendor\OmegaApp\ErrorHandler();

$app = new AppController();
$app->createML();