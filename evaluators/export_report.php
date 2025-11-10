<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Evaluation.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();

$evaluation = new Evaluation($db);
$teacher = new Teacher($db);

// Get filter parameters
$academic_year = $_GET['academic_year'] ?? '2023-2024';
$semester = $_GET['semester'] ?? '';
$teacher_id = $_GET['teacher_id'] ?? '';
$export_type = $_GET['type'] ?? 'pdf';
$report_type = $_GET['report_type'] ?? 'summary';

// Get data for export
$evaluations = $evaluation->getEvaluationsForReport($_SESSION['user_id'], $academic_year, $semester, $teacher_id);
$stats = $evaluation->getDepartmentStats($_SESSION['department'], $academic_year, $semester);

if($export_type == 'pdf') {
    exportToPDF($evaluations, $stats, $academic_year, $semester, $report_type);
} elseif($export_type == 'csv' || $export_type == 'excel') {
    exportToExcel($evaluations, $stats, $academic_year, $semester, $report_type);
}

function exportToPDF($evaluations, $stats, $academic_year, $semester, $report_type) {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="evaluation_report_' . date('Y-m-d') . '.pdf"');
    
    // In a real implementation, you would use FPDF or TCPDF
    // This is a simplified version
    
    echo "%PDF-1.4\n";
    echo "1 0 obj\n";
    echo "<< /Type /Catalog /Pages 2 0 R >>\n";
    echo "endobj\n";
    echo "2 0 obj\n";
    echo "<< /Type /Pages /Kids [3 0 R] /Count 1 >>\n";
    echo "endobj\n";
    echo "3 0 obj\n";
    echo "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\n";
    echo "endobj\n";
    echo "4 0 obj\n";
    echo "<< /Length 100 >>\n";
    echo "stream\n";
    echo "BT\n";
    echo "/F1 12 Tf\n";
    echo "50 750 Td\n";
    echo "(Classroom Evaluation Report) Tj\n";
    echo "0 -20 Td\n";
    echo "(Academic Year: $academic_year) Tj\n";
    echo "0 -20 Td\n";
    echo "(Semester: " . ($semester ?: 'All') . ") Tj\n";
    echo "0 -20 Td\n";
    echo "(Total Evaluations: " . $stats['total_evaluations'] . ") Tj\n";
    echo "0 -20 Td\n";
    echo "(Average Rating: " . number_format($stats['avg_rating'], 1) . ") Tj\n";
    echo "ET\n";
    echo "endstream\n";
    echo "endobj\n";
    echo "xref\n";
    echo "0 5\n";
    echo "0000000000 65535 f \n";
    echo "0000000009 00000 n \n";
    echo "0000000058 00000 n \n";
    echo "0000000115 00000 n \n";
    echo "0000000224 00000 n \n";
    echo "trailer\n";
    echo "<< /Size 5 /Root 1 0 R >>\n";
    echo "startxref\n";
    echo "380\n";
    echo "%%EOF";
}

function exportToExcel($evaluations, $stats, $academic_year, $semester, $report_type) {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="evaluation_report_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='6'>Classroom Evaluation Report - " . $_SESSION['department'] . "</th></tr>";
    echo "<tr><th colspan='6'>Academic Year: $academic_year | Semester: " . ($semester ?: 'All') . "</th></tr>";
    echo "<tr><th colspan='6'>Generated on: " . date('Y-m-d H:i:s') . "</th></tr>";
    echo "<tr><td colspan='6'>&nbsp;</td></tr>";
    
    // Summary Statistics
    echo "<tr><th colspan='6'>Summary Statistics</th></tr>";
    echo "<tr>
            <th>Total Evaluations</th>
            <th>Average Rating</th>
            <th>Teachers Evaluated</th>
            <th>AI Recommendations</th>
            <th>Excellent Ratings</th>
            <th>Needs Improvement</th>
          </tr>";
    echo "<tr>
            <td>" . $stats['total_evaluations'] . "</td>
            <td>" . number_format($stats['avg_rating'], 1) . "</td>
            <td>" . $stats['teachers_evaluated'] . "</td>
            <td>" . $stats['ai_recommendations'] . "</td>
            <td>" . ($stats['excellent_ratings'] ?? 0) . "</td>
            <td>" . ($stats['needs_improvement'] ?? 0) . "</td>
          </tr>";
    echo "<tr><td colspan='6'>&nbsp;</td></tr>";
    
    // Detailed Evaluation Data
    echo "<tr><th colspan='10'>Evaluation Details</th></tr>";
    echo "<tr>
            <th>Teacher</th>
            <th>Date</th>
            <th>Subject</th>
            <th>Comm Avg</th>
            <th>Mgmt Avg</th>
            <th>Assess Avg</th>
            <th>Overall Avg</th>
            <th>Rating</th>
            <th>AI Recs</th>
            <th>Evaluator</th>
          </tr>";
    
    if($evaluations->rowCount() > 0) {
        while($eval = $evaluations->fetch(PDO::FETCH_ASSOC)) {
            $rating = 'Needs Improvement';
            if($eval['overall_avg'] >= 4.6) $rating = 'Excellent';
            elseif($eval['overall_avg'] >= 3.6) $rating = 'Very Satisfactory';
            elseif($eval['overall_avg'] >= 2.9) $rating = 'Satisfactory';
            elseif($eval['overall_avg'] >= 1.8) $rating = 'Below Satisfactory';
            
            echo "<tr>
                    <td>" . htmlspecialchars($eval['teacher_name']) . "</td>
                    <td>" . date('Y-m-d', strtotime($eval['observation_date'])) . "</td>
                    <td>" . htmlspecialchars($eval['subject_observed']) . "</td>
                    <td>" . number_format($eval['communications_avg'], 1) . "</td>
                    <td>" . number_format($eval['management_avg'], 1) . "</td>
                    <td>" . number_format($eval['assessment_avg'], 1) . "</td>
                    <td>" . number_format($eval['overall_avg'], 1) . "</td>
                    <td>$rating</td>
                    <td>" . $eval['ai_count'] . "</td>
                    <td>" . htmlspecialchars($eval['evaluator_name']) . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='10' style='text-align: center;'>No evaluation data found</td></tr>";
    }
    
    echo "</table>";
}
?>