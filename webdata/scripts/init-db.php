<?php

include(__DIR__ . '/../init.inc.php');

try {
    Elastic::dropIndex('entry');
} catch (Exception $e) {
}

Elastic::createIndex('entry', [
    'properties' => [
        'date' => ['type' => 'integer'],
        'title' => ['type' => 'text'],
    ],
]);
