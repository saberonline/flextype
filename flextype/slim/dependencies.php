<?php

// DIC configuration
$container = $app->getContainer();

// Register Twig View helper
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig(PATH['themes'] . '/default/views/', [
        'cache' => false
    ]);

    // Instantiate and add Slim specific extension
    $router = $c->get('router');
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));

    return $view;
};
