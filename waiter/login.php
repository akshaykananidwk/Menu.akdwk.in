<?php
/** Waiter login: Restaurant Code + 4-digit PIN. */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/_staff_auth.php';
if (!empty($_SESSION['staff_id']) && ($_SESSION['staff_role'] ?? '') === 'waiter') { redirect(BASE_URL . '/waiter/index.php'); }
$error = staffLoginHandler('waiter');
render_staff_login('waiter', 'Waiter Login', 'bi-person-badge', $error);
