<?php
/**
 * AUTO HUB - Logout Handler
 */

require_once __DIR__ . '/config/helpers.php';

session_unset();
session_destroy();

header('Location: login.php?logged_out=1');
exit;
