
<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['department_id']) || !ctype_digit($_GET['department_id'])) {
    echo json_encode([]);
    exit;
}

$departmentId = (int) $_GET['department_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT id, course_name, course_code
         FROM courses
         WHERE department_id = ?
         ORDER BY course_name"
    );

    $stmt->execute([$departmentId]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load courses']);
}
