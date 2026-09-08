<?php

include(__DIR__ . '/webdata/init.inc.php');

class MyDispatcher extends Pix_Controller_Dispatcher
{
    public function dispatch($path)
    {
        if ($path == '/swagger.json') {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            readfile(__DIR__ . '/webdata/swagger.json');
            exit;
        }
        if ($path == '/skill.md') {
            header('Content-Type: text/markdown; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            readfile(__DIR__ . '/webdata/skill.md');
            exit;
        }
        if ($path == '/knowledge.md') {
            header('Content-Type: text/markdown; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            readfile(__DIR__ . '/webdata/knowledge.md');
            exit;
        }
        return null;
    }
}
Pix_Controller::addDispatcher(new MyDispatcher);
Pix_Controller::addCommonHelpers();
Pix_Controller::dispatch(__DIR__ . '/webdata/');
