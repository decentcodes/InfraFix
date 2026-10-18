<?php

/*
 * Settings applied before any InfraFix page or API does real work.
 * Errors are written to the server log, never shown to visitors
 * (error text can reveal file paths and database details).
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
