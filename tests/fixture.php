<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$mode = $argv[1] ?? ''; $token = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{16}$/', $token)) { exit(1); }
$prefix = 'qa_' . $token; $password = 'Test-' . $token;
if ($mode === 'setup') {
    query('INSERT INTO admin (a_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)', ['QA Admin', $prefix . '_admin', $prefix . '_admin@example.test', password_hash($password, PASSWORD_DEFAULT), 'admin']);
    query('INSERT INTO doctor (d_name, username, email, password, doctor_type, location, gender, price_per_appointment, description, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ['QA Doctor ' . $token, $prefix . '_doctor', $prefix . '_doctor@example.test', password_hash($password, PASSWORD_DEFAULT), 'QA Cardiology', 'QA Colombo', 'Female', '1500.00', '<script>qa_test</script>', 'doctor']);
    $doctor = (int) db()->insert_id;
    foreach (['md5', 'sha1', 'plain'] as $type) {
        $hash = $type === 'md5' ? md5($password) : ($type === 'sha1' ? sha1($password) : $password);
        query('INSERT INTO users (p_name, username, email, password) VALUES (?, ?, ?, ?)', ['QA Legacy', $prefix . '_' . $type, $prefix . '_' . $type . '@example.test', $hash]);
    }
    echo json_encode(['doctor' => $doctor]);
} elseif ($mode === 'inspect') {
    $users = rows('SELECT username, password FROM users WHERE username LIKE ?', [$prefix . '%']);
    $result = [];
    foreach ($users as $u) { $result[$u['username']] = password_verify($password, $u['password']); }
    echo json_encode($result);
} elseif ($mode === 'cleanup') {
    db()->begin_transaction();
    query('DELETE FROM favourite WHERE p_id IN (SELECT p_id FROM users WHERE username LIKE ?) OR d_id IN (SELECT d_id FROM doctor WHERE username LIKE ?)', [$prefix . '%', $prefix . '%']);
    query('DELETE FROM appointment WHERE p_id IN (SELECT p_id FROM users WHERE username LIKE ?) OR d_id IN (SELECT d_id FROM doctor WHERE username LIKE ?)', [$prefix . '%', $prefix . '%']);
    query('DELETE FROM users WHERE username LIKE ?', [$prefix . '%']);
    query('DELETE FROM doctor WHERE username LIKE ?', [$prefix . '%']);
    query('DELETE FROM admin WHERE username LIKE ?', [$prefix . '%']);
    db()->commit();
}
