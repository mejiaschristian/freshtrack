<?php
session_start();
require_once 'auth.php';

// Call logout function
logout();

// Redirect to login page
header('Location: index.php');
exit();
