<?php

namespace src;

require_once __DIR__ . '/Env.php';

if (!defined('LOG_DIR')) define('LOG_DIR', '../logs/');
if (!defined('LOG_SQL'))  define('LOG_SQL', false);

class Config
{
    const BASE_DIR = BASE_DIR;
    const URL_ADMIN = URL_ADMIN;
    const SENTRY_DSN = SENTRY_DSN;

    const ERROR_CONTROLLER = ERROR_CONTROLLER;
    const DEFAULT_ACTION = DEFAULT_ACTION;

    const FOCCO_DRIVER = FOCCO_DRIVER;
    const FOCCO_HOST = FOCCO_HOST;
    const FOCCO_PORT = FOCCO_PORT;
    const FOCCO_DATABASE = FOCCO_DATABASE;
    const FOCCO_USER = FOCCO_USER;
    const FOCCO_PASS = FOCCO_PASS;

    const LOG_DIR = LOG_DIR;
    const LOG_SQL  = LOG_SQL;
}
