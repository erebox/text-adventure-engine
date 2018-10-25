<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Erebox\TextAdventureEngine\Engine;

$quest = __DIR__."/test.01.json";
$game_json = file_get_contents($quest);
$adv = new Engine($game_json);

echo $adv->debug();