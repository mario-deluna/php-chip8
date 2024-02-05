<?php

use VISU\Quickstart;
use VISU\Quickstart\QuickstartOptions;

$container = require __DIR__ . '/../bootstrap.php';

$quickstart = new Quickstart(function(QuickstartOptions $options) use ($container)
{
    $options->appClass = \App\Application::class;
    $options->container = $container;
    $options->windowTitle = $container->getParameter('project.name'); // defined in: /app.ctn 
});

// forwards the command line arguments to the app container
$container->setParameter('argv', array_slice($argv, 1));

$quickstart->run();