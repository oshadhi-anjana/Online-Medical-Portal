<?php
// Only a local operator can provision an administrator; never expose this as a form.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
function prompt(string $label): string { echo $label . ': '; return trim((string) fgets(STDIN)); }
$name = prompt('Full name'); $username = prompt('Username'); $email = prompt('Email');
echo "Use a new password. Input is visible in this local terminal.\n";
$password = prompt('Password (8-72 characters)');
if (!$name || !preg_match('/^[a-zA-Z0-9_.-]{3,100}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "Invalid account details. Nothing was created.\n"); exit(1);
}
try {
    query('INSERT INTO admin (a_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)', [$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
    echo "Administrator created. Sign in to the portal with account type Administrator.\n";
} catch (mysqli_sql_exception $exception) { fwrite(STDERR, "Could not create account. Check MySQL and use a unique username/email.\n"); exit(1); }
