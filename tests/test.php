<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Erebox\TextAdventureEngine\Engine;

$adv = new Engine(null);

echo $adv->version();