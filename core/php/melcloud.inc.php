<?php

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (isset($argv)) {
    foreach ($argv as $arg) {
        $argList = explode('=', $arg);
        if (isset($argList[0]) && isset($argList[1])) {
            $_GET[$argList[0]] = $argList[1];
        }
    }
}

//template::createFromDef('florian');
    
    
    


?>