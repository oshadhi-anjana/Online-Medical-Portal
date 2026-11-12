<?php
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
date_default_timezone_set('Asia/Colombo');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function url(string $page = 'home', array $params = []): string {
    return app_base() . '/index.php?' . http_build_query(array_merge(['page' => $page], $params));
}
function app_base(): string { return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/.'); }
function redirect(string $page, array $params = []): void { header('Location: ' . url($page, $params)); exit; }
function db(): mysqli {
    static $db;
    if (!$db) {
        $db = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', getenv('DB_NAME') ?: 'online_medical_protal', (int) (getenv('DB_PORT') ?: 3306));
        $db->set_charset('utf8mb4');
    }
    return $db;
}
function query(string $sql, array $params = []): mysqli_stmt {
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $value) { $types .= is_int($value) ? 'i' : 's'; }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
function rows(string $sql, array $params = []): array { return query($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC); }
function first(string $sql, array $params = []): ?array { return rows($sql, $params)[0] ?? null; }
function csrf(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function flash(string $text): void { $_SESSION['flash'] = $text; }
function account(): ?array { return $_SESSION['account'] ?? null; }
function require_account(?string $role = null): array {
    $user = account();
    if (!$user) { flash('Please sign in to continue.'); redirect('login'); }
    if ($role !== null && $user['role'] !== $role) { http_response_code(403); throw new RuntimeException('This action is not available for your account.'); }
    return $user;
}
function input(string $key, int $max = 255, bool $required = true): string {
    $value = trim((string) ($_POST[$key] ?? ''));
    if (($required && $value === '') || strlen($value) > $max) { throw new RuntimeException('Please enter a valid ' . str_replace('_', ' ', $key) . '.'); }
    return $value;
}
function email_input(): string {
    $email = input('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Please enter a valid email address.'); }
    return $email;
}
function appointment_input(): array {
    $name = input('appointment_name');
    $phone = input('phone_number', 30);
    if (!preg_match('/^[+()0-9 .-]{7,30}$/', $phone)) { throw new RuntimeException('Please enter a valid phone number.'); }
    $date = input('date', 10);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < date('Y-m-d')) { throw new RuntimeException('Choose today or a future appointment date.'); }
    $session = input('session', 50);
    if (!in_array($session, ['Morning', 'Afternoon', 'Evening'], true)) { throw new RuntimeException('Choose a valid session.'); }
    return [$name, $phone, $session, $date];
}

function handle_action(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? '')) || empty($_SESSION['csrf'])) {
        http_response_code(419);
        throw new RuntimeException('Your form has expired. Refresh the page and try again.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('You have been signed out.'); redirect('home');
    }
    if ($action === 'register') {
        $name = input('full_name'); $username = input('username', 100); $email = email_input();
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/', $username)) { throw new RuntimeException('Use at least 3 letters, numbers, dots, underscores or hyphens for your username.'); }
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8 || strlen($password) > 72) { throw new RuntimeException('Use a password between 8 and 72 characters.'); }
        if ($password !== ($_POST['confirm_password'] ?? '')) { throw new RuntimeException('Your passwords do not match.'); }
        query('INSERT INTO users (p_name, username, email, password, phone_number, location, special_note, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), input('phone_number', 30, false), input('location', 100, false), '', 'user']);
        flash('Your account is ready. Sign in to book your first appointment.'); redirect('login');
    }
    if ($action === 'login') {
        $role = input('role', 20); $username = input('username', 100); $password = (string) ($_POST['password'] ?? '');
        if ($password === '' || strlen($password) > 1024) { throw new RuntimeException('Please enter a valid password.'); }
        $tables = ['user' => ['users', 'p_id', 'p_name'], 'doctor' => ['doctor', 'd_id', 'd_name'], 'admin' => ['admin', 'a_id', 'a_name']];
        if (!isset($tables[$role])) { throw new RuntimeException('Choose a valid account type.'); }
        [$table, $id, $name] = $tables[$role];
        $user = first("SELECT * FROM `$table` WHERE username = ?", [$username]);
        $valid = $user && password_verify($password, $user['password']);
        // Accept existing project accounts once, then replace their legacy hash.
        if (!$valid && $user) {
            $stored = $user['password'];
            $isMd5 = (bool) preg_match('/^[a-f0-9]{32}$/i', $stored);
            $isSha1 = (bool) preg_match('/^[a-f0-9]{40}$/i', $stored);
            $valid = ($isMd5 && hash_equals(strtolower($stored), md5($password))) ||
                ($isSha1 && hash_equals(strtolower($stored), sha1($password))) ||
                (!$isMd5 && !$isSha1 && substr($stored, 0, 1) !== '$' && hash_equals($stored, $password));
        }
        if (!$valid) { throw new RuntimeException('The username or password is incorrect for this account type.'); }
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) { query("UPDATE `$table` SET password = ? WHERE `$id` = ?", [password_hash($password, PASSWORD_DEFAULT), (int) $user[$id]]); }
        session_regenerate_id(true);
        $_SESSION['account'] = ['id' => (int) $user[$id], 'role' => $role, 'name' => $user[$name], 'username' => $user['username'], 'email' => $user['email']];
        // Preserve the session fields used by the original specialist pages.
        $_SESSION['name'] = $user[$name]; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $role; $_SESSION['email'] = $user['email']; $_SESSION['id'] = $user[$id];
        if ($role === 'user') { $_SESSION['pid'] = $user[$id]; }
        flash('Welcome back, ' . $user[$name] . '.'); redirect($role === 'user' ? 'appointments' : 'dashboard');
    }
    $user = require_account();
    if ($action === 'profile') {
        $name = input('full_name'); $email = email_input();
        $tables = ['user' => ['users', 'p_id', 'p_name'], 'doctor' => ['doctor', 'd_id', 'd_name'], 'admin' => ['admin', 'a_id', 'a_name']];
        [$table, $id, $nameColumn] = $tables[$user['role']];
        if ($user['role'] === 'user') { query('UPDATE users SET p_name = ?, email = ?, phone_number = ?, location = ? WHERE p_id = ?', [$name, $email, input('phone_number', 30, false), input('location', 100, false), $user['id']]); }
        else { query("UPDATE `$table` SET `$nameColumn` = ?, email = ? WHERE `$id` = ?", [$name, $email, $user['id']]); }
        $_SESSION['account']['name'] = $_SESSION['name'] = $name; $_SESSION['account']['email'] = $_SESSION['email'] = $email;
        flash('Your profile has been updated.'); redirect('profile');
    }
    if ($action === 'favourite') {
        require_account('user'); $doctor = (int) ($_POST['doctor_id'] ?? 0);
        if (!first('SELECT d_id FROM doctor WHERE d_id = ?', [$doctor])) { throw new RuntimeException('That doctor is no longer available.'); }
        if (first('SELECT f_id FROM favourite WHERE p_id = ? AND d_id = ?', [$user['id'], $doctor])) { query('DELETE FROM favourite WHERE p_id = ? AND d_id = ?', [$user['id'], $doctor]); flash('Doctor removed from favourites.'); }
        else { query('INSERT INTO favourite (p_id, d_id) VALUES (?, ?)', [$user['id'], $doctor]); flash('Doctor saved to your favourites.'); }
        redirect(($_POST['return_page'] ?? '') === 'favourites' ? 'favourites' : 'doctors');
    }
    if ($action === 'book' || $action === 'edit_appointment') {
        require_account('user'); $values = appointment_input();
        if ($action === 'book') {
            $doctor = (int) ($_POST['doctor_id'] ?? 0);
            if (!first('SELECT d_id FROM doctor WHERE d_id = ?', [$doctor])) { throw new RuntimeException('Choose an available doctor.'); }
            if (first('SELECT a_id FROM appointment WHERE p_id = ? AND d_id = ? AND date = ? AND session = ?', [$user['id'], $doctor, $values[3], $values[2]])) { throw new RuntimeException('You already have an appointment with this doctor in that session.'); }
            query('INSERT INTO appointment (appointment_name, phone_number, session, date, d_id, p_id) VALUES (?, ?, ?, ?, ?, ?)', array_merge($values, [$doctor, $user['id']]));
            flash('Your appointment has been booked.');
        } else {
            $id = (int) ($_POST['appointment_id'] ?? 0);
            $existing = first('SELECT * FROM appointment WHERE a_id = ? AND p_id = ? AND date >= ?', [$id, $user['id'], date('Y-m-d')]);
            if (!$existing) { throw new RuntimeException('This appointment cannot be changed.'); }
            if (first('SELECT a_id FROM appointment WHERE p_id = ? AND d_id = ? AND date = ? AND session = ? AND a_id <> ?', [$user['id'], (int) $existing['d_id'], $values[3], $values[2], $id])) { throw new RuntimeException('You already have an appointment in that session.'); }
            query('UPDATE appointment SET appointment_name = ?, phone_number = ?, session = ?, date = ? WHERE a_id = ? AND p_id = ?', array_merge($values, [$id, $user['id']])); flash('Your appointment has been updated.');
        }
        redirect('appointments');
    }
    if ($action === 'cancel') {
        require_account('user');
        $id = (int) ($_POST['appointment_id'] ?? 0);
        $existing = first('SELECT a_id FROM appointment WHERE a_id = ? AND p_id = ? AND date >= ?', [$id, $user['id'], date('Y-m-d')]);
        if (!$existing) { throw new RuntimeException('This appointment cannot be cancelled.'); }
        query('DELETE FROM appointment WHERE a_id = ? AND p_id = ?', [$id, $user['id']]); flash('Your appointment has been cancelled.'); redirect('appointments');
    }
    if ($action === 'add_doctor') {
        require_account('admin'); $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8 || strlen($password) > 72) { throw new RuntimeException('Use a password between 8 and 72 characters.'); }
        $price = input('price', 15);
        if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) { throw new RuntimeException('Enter a valid appointment fee.'); }
        query('INSERT INTO doctor (d_name, username, email, password, doctor_type, location, gender, price_per_appointment, description, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [input('full_name'), input('username', 100), email_input(), password_hash($password, PASSWORD_DEFAULT), input('speciality', 100), input('location', 100), input('gender', 20), $price, input('description', 4000, false), 'doctor']);
        flash('Doctor added. They can now sign in with their new account.'); redirect('dashboard');
    }
    throw new RuntimeException('Unknown action. Please refresh and try again.');
}
