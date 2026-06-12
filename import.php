<?php
require_once 'db.php';

$file = fopen('students (1).csv', 'r');
$header = fgetcsv($file); // skip header

$stmt = db()->prepare("
    INSERT INTO students
    (student_id, first_name, last_name, middle_initial, sex, section, email)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

while ($row = fgetcsv($file)) {
    $stmt->execute($row);
}

echo "Import complete";