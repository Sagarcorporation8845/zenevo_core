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
    <link rel="stylesheet" href="<?php echo url_for('assets/css/custom-improvements.css'); ?>">
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
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notificationBell" class="relative p-2 text-gray-600 hover:text-gray-800 focus:outline-none focus:text-gray-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM9 17H4l5 5v-5zM12 3v9m0 0l3-3m-3 3l-3-3"></path>
                            </svg>
                            <span id="notificationCount" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full hidden"></span>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg z-50 hidden">
                            <div class="py-2 max-h-96 overflow-y-auto">
                                <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b">
                                    Recent Notifications
                                </div>
                                <div id="notificationList" class="divide-y divide-gray-100">
                                    <!-- Notifications will be loaded here -->
                                </div>
                                <div class="px-4 py-2 text-center border-t">
                                    <a href="<?php echo url_for('notifications.php'); ?>" class="text-sm text-indigo-600 hover:text-indigo-800">View All Notifications</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <span class="text-sm font-medium text-gray-600">Welcome, <?php echo e($_SESSION['user_name']); ?></span>
                    <a href="<?php echo url_for('logout.php'); ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Logout</a>
                </div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <!-- Page content will go here -->