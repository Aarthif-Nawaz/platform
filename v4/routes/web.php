<?php
/**
 * API version number
 */
$apiVersion = '4';
$apiBase = 'api/v' . $apiVersion;

$router->group([
    'prefix' => $apiBase,
], function () use ($router) {
    // Forms
    $router->group([
        // 'namespace' => 'Forms',
        'prefix' => 'surveys',
        'middleware' => ['scope:forms', 'expiration']
    ], function () use ($router) {
        // Public access
        $router->get('/', 'SurveyController@index');
        $router->get('/{id}', 'SurveyController@show');
    });

    $router->group([
        'prefix' => 'categories',
        'middleware' => ['scope:tags', 'expiration']
    ], function () use ($router) {
        // Public access
        $router->get('/', 'CategoryController@index');
        $router->get('/{id}', 'CategoryController@show');
    });

    // Restricted access
    $router->group([
        'prefix' => 'surveys',
        'middleware' => ['auth:api', 'scope:forms']
    ], function () use ($router) {
        $router->post('/', 'SurveyController@store');
        $router->put('/{id}', 'SurveyController@update');
        $router->delete('/{id}', 'SurveyController@delete');
    });

    // Restricted access
    $router->group([
        'prefix' => '',
    ], function () use ($router) {
        $router->get('/languages', 'LanguagesController@index');
    });
});
