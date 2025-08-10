<?php
/*
--------------------------------------------------------------------------------
-- File: /includes/header.php
-- Description: Reusable header for all authenticated pages.
--              Includes HTML head, Tailwind CSS, and page structure start.
--------------------------------------------------------------------------------
*/

// Every protected page will include this file.
// It ensures the db connection is available and the user is logged in.
require_once __DIR__ . '/../config/db.php';
require_login(); // This function is from db.php

?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The $pageTitle variable should be set on each page before including the header -->
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>HR & Finance</title>
    <link rel="stylesheet" href="<?php echo url_for('assets/css/app.css'); ?>">
</head>
<body class="h-full font-sans">
    <div class="flex h-screen bg-gray-200">
        <?php include 'sidebar.php'; // The sidebar is included within the main layout ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex justify-between items-center p-4 bg-white border-b-2 border-gray-200">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo isset($pageTitle) ? e($pageTitle) : 'Dashboard'; ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm font-medium text-gray-600">Welcome, <?php echo e($_SESSION['user_name']); ?></span>
                    <a href="<?php echo url_for('logout.php'); ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Logout</a>
                </div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <!-- Page content will go here -->