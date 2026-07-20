<?php
/** Kitchen login: Restaurant Code + 4-digit PIN. */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/waiter/_staff_auth.php';
if (!empty($_SESSION['staff_id']) && ($_SESSION['staff_role'] ?? '') === 'kitchen') { redirect(BASE_URL . '/kitchen/index.php'); }
$error = staffLoginHandler('kitchen');
render_staff_login('kitchen', 'Kitchen Login', 'bi-fire', $error);
