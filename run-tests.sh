#!/bin/sh

phpunitPath='vendor/phpunit/phpunit/phpunit'

if [ ! -f $phpunitPath ]; then
    composer install
fi

$phpunitPath