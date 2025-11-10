<?php
require_once '../auth/session-check.php';
require_once '../config/database.php';
require_once '../models/Evaluation.php';

$db = (new Database())->getConnection();
$evaluationModel = new Evaluation($db);

// Simple auth: only allowed roles can export
if (!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator', 'president', 'vice_president'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit();
}

$type = $_GET['type'] ?? 'csv';
$reportType = $_GET['report_type'] ?? 'filter';

if ($type !== 'csv') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Only CSV export is supported for now.';
    exit();
}

// Helper: send CSV download headers
function send_csv_headers($filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if ($reportType === 'single') {
    $evaluationId = isset($_GET['evaluation_id']) ? (int)$_GET['evaluation_id'] : 0;
    if (!$evaluationId) {
        header('HTTP/1.1 400 Bad Request');
        echo 'evaluation_id is required for single report_type';
        exit();
    }

    $eval = $evaluationModel->getEvaluationById($evaluationId);
    if (!$eval) {
        header('HTTP/1.1 404 Not Found');
        echo 'Evaluation not found';
        exit();
    }

    // Prepare CSV
    $filename = 'evaluation_' . $evaluationId . '.csv';
    send_csv_headers($filename);

    $out = fopen('php://output', 'w');
    // Header row
    fputcsv($out, ['Field', 'Value']);

    $rows = [
        ['Evaluation ID', $eval['id']],
        ['Teacher', $eval['teacher_name'] ?? ''],
        ['Teacher Department', $eval['department'] ?? ''],
        ['Evaluator', $eval['evaluator_name'] ?? ''],
        ['Academic Year', $eval['academic_year'] ?? ''],
        ['Semester', $eval['semester'] ?? ''],
        ['Subject Observed', $eval['subject_observed'] ?? ''],
        ['Observation Date', $eval['observation_date'] ?? ''],
        ['Observation Type', $eval['observation_type'] ?? ''],
        ['Seat Plan', $eval['seat_plan'] ?? ''],
        ['Course Syllabi', $eval['course_syllabi'] ?? ''],
        ['Communications Avg', $eval['communications_avg'] ?? ''],
        ['Management Avg', $eval['management_avg'] ?? ''],
        ['Assessment Avg', $eval['assessment_avg'] ?? ''],
        ['Overall Avg', $eval['overall_avg'] ?? ''],
        ['Strengths', $eval['strengths'] ?? ''],
        ['Improvement Areas', $eval['improvement_areas'] ?? ''],
        ['Recommendations', $eval['recommendations'] ?? '']
    ];

    foreach ($rows as $r) {
        fputcsv($out, $r);
    }

    // Append detail rows (ratings/comments)
    fputcsv($out, []);
    fputcsv($out, ['Details']);
    fputcsv($out, ['Category', 'Criterion Index', 'Criterion Text', 'Rating', 'Comments']);
    $details = $evaluationModel->getEvaluationDetails($evaluationId);
    while ($d = $details->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$d['category'], $d['criterion_index'], $d['criterion_text'], $d['rating'], $d['comments']]);
    }

    fclose($out);
    exit();

} else {
    // report_type = filter (export list matching filters)
    $academic_year = $_GET['academic_year'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $teacher_id = $_GET['teacher_id'] ?? '';

    // Leader roles see all; others only their own evaluations
    $evaluatorFilter = in_array($_SESSION['role'], ['president', 'vice_president']) ? null : $_SESSION['user_id'];

    $evaluations = $evaluationModel->getEvaluationsForReport($evaluatorFilter, $academic_year, $semester, $teacher_id);

    $filename = 'evaluations_' . ($academic_year ?: date('Y')) . '.csv';
    send_csv_headers($filename);
    $out = fopen('php://output', 'w');

    // Header row
    fputcsv($out, ['ID', 'Teacher', 'Evaluator', 'Academic Year', 'Semester', 'Subject', 'Observation Date', 'Communications Avg', 'Management Avg', 'Assessment Avg', 'Overall Avg']);

    while ($e = $evaluations->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $e['id'],
            $e['teacher_name'] ?? '',
            $e['evaluator_name'] ?? '',
            $e['academic_year'] ?? '',
            $e['semester'] ?? '',
            $e['subject_observed'] ?? '',
            $e['observation_date'] ?? '',
            $e['communications_avg'] ?? '',
            $e['management_avg'] ?? '',
            $e['assessment_avg'] ?? '',
            $e['overall_avg'] ?? ''
        ]);
    }

    fclose($out);
    exit();
}

?>