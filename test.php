<?php
// Use a strong password
$password = 'Zenevo@123';

// Generate the secure hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Output the hash to copy it
echo $hashed_password;
?>