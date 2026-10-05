<?php
require __DIR__ . '/includes/init.php';
$_SESSION = [];
session_destroy();
redirect('/login.php');
