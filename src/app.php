<?php

// Load dependencies
require_once __DIR__ .
             DIRECTORY_SEPARATOR . '..'.
             DIRECTORY_SEPARATOR . 'vendor' .
             DIRECTORY_SEPARATOR . 'autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ .
        DIRECTORY_SEPARATOR . '..'.
        DIRECTORY_SEPARATOR . 'config'.
        DIRECTORY_SEPARATOR . (
            isset($argv[1]) ? $argv[1] : 'example.json'
        )
    )
);  if (!$config) throw new \Exception();

// Start server
$server = \Ratchet\Server\IoServer::factory(
    new \Kevachat\Npsapp\Server\Ratchet(
        $config
    ),
    $config->nps->server->port,
    $config->nps->server->host
);

$server->run();
