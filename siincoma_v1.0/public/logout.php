<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_destroy();
go('login.php');
