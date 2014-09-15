<?php

require_once __DIR__.'/vendor/autoload.php';

spl_autoload_register(function ($file) {
    if(substr($file,0,9) === "alojaweb\\")
        $file = substr($file,9);

    $file = str_replace('\\','/',$file).'.php';
    require_once $file;
});
