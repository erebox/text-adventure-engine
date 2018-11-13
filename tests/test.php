<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Erebox\TextAdventureEngine\Engine;

$quest = __DIR__."/test.03.json";
$game_json = file_get_contents($quest);
$adv = new Engine($game_json);

echo $adv->debug();
exit();
#echo "-----------------------\n";
$lst = [
    "prendi la chiave",
    "n",
    "vai nord",
    "saluta la bella ragazza",
    "indossa l'anello",
    "parla a ciccio di un libro",
    "a",
    ""
];
foreach ($lst as $s) {
    $s2= implode(":", $adv->parseString($s));
    echo $s." - ".$s2."\n";
}
