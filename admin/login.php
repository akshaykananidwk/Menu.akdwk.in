<?php
/** Old Super Admin login URL — everyone now signs in on the single login page. */
require_once dirname(__DIR__) . '/config/config.php';
if (isSuperAdmin()) { redirect(BASE_URL . '/admin/index.php'); }
redirect(BASE_URL . '/client/login.php');
