<?php

namespace Erebox\TextAdventureEngine;

class DefaultConfig
{
    protected $config = null;

    protected $lang = [];

    public function __construct($l = "en")
    {
        $conf = [];
        $file2load = __DIR__.'/config/'.strtolower($l).'.json';
        if (file_exists($file2load)) {
            $conf = json_decode(file_get_contents($file2load), true);
        } else {
            $file2load = __DIR__.'/config/en.json';
            $conf = json_decode(file_get_contents($file2load), true);
        }
        if (!$conf) {
            $conf = [];
        }
        $this->config = $conf;
    }

    public function get() 
    {
        return $this->config;
    }
}