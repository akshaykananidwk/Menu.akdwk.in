<?php
require_once dirname(__DIR__) . '/config/config.php';
if (isSuperAdmin()) { logActivity('super_admin', (int)$_SESSION['admin_id'], 'Logged out'); }
session_unset(); session_destroy();
redirect(BASE_URL . '/admin/login.php');
