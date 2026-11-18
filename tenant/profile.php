<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('tenant');
require __DIR__ . '/../includes/profile_page.php';
