<?php
/**
 * AUTO HUB - Edit Profile Forwarder
 * Automatically redirects or renders the Edit Profile mode of Customer Profile
 */

require_once __DIR__ . '/config/helpers.php';
require_login('login.php');

// Redirect to profile.php with the edit action activated
header('Location: profile.php?action=edit#edit');
exit;
