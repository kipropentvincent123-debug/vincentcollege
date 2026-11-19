
<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-exams.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: manage-exams.php?error=invalid');
    exit;
}

try {
    // Check whether the examination exists
    $stmt = $pdo->prepare(
        "SELECT id FROM examinations WHERE id = :id"
    );
    $stmt->execute(['id' => $id]);

    if (!$stmt->fetch()) {
        header('Location: manage-exams.php?error=notfound');
        exit;
    }

    // Protect examinations that already have marks
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM exam_marks
         WHERE examination_id = :id"
    );
    $stmt->execute(['id' => $id]);

    $marksCount = (int) $stmt->fetchColumn();

    if ($marksCount > 0) {
        header('Location: manage-exams.php?error=hasmarks');
        exit;
    }

    // Delete only if no marks exist
    $stmt = $pdo->prepare(
        "DELETE FROM examinations WHERE id = :id"
    );
    $stmt->execute(['id' => $id]);

    header('Location: manage-exams.php?deleted=1');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: manage-exams.php?error=deletefailed');
    exit;
}
