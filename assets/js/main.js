// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    initializePage();

    // Ensure teacher-item click event is always attached
    document.addEventListener('click', function(e) {
        const teacherItem = e.target.closest('.teacher-item');
        if (teacherItem) {
            const teacherId = teacherItem.getAttribute('data-teacher-id');
            if (teacherId) {
                startEvaluation(teacherId);
            }
        }
    });
});

function initializePage() {
    initializeTeacherManagement();
    initializeEvaluationForm();
    initializeExportButtons();
}

// Teacher Management Functions
function initializeTeacherManagement() {
    // Edit Teacher Modal
    const editTeacherButtons = document.querySelectorAll('.edit-teacher');
    editTeacherButtons.forEach(button => {
        button.addEventListener('click', function() {
            const teacherId = this.getAttribute('data-teacher-id');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            
            document.getElementById('edit_teacher_id').value = teacherId;
            document.getElementById('edit_name').value = name || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_phone').value = phone || '';
            
            const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
            editModal.show();
        });
    });

    // Clear add modal when closed
    const addTeacherModal = document.getElementById('addTeacherModal');
    if (addTeacherModal) {
        addTeacherModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('phone').value = '';
        });
    }

    // Teacher selection for evaluation
    const teacherItems = document.querySelectorAll('.teacher-item');
    teacherItems.forEach(item => {
        item.addEventListener('click', function() {
            const teacherId = this.getAttribute('data-teacher-id');
            startEvaluation(teacherId);
        });
    });
}

// Evaluation Form Functions
function initializeEvaluationForm() {
    // Rating change listeners
    document.addEventListener('change', function(e) {
        if (e.target.type === 'radio' && e.target.name.includes('rating')) {
            calculateAverages();
            generateAIRecommendations();
        }
    });

    // Back to teachers button
    const backToTeachersBtn = document.getElementById('backToTeachers');
    if (backToTeachersBtn) {
        backToTeachersBtn.addEventListener('click', function() {
            showTeacherSelection();
        });
    }

    // Initialize evaluation tables if they exist
    if (document.getElementById('communicationsCompetence')) {
        initializeEvaluationTables();
    }
}

// Export Functions
function initializeExportButtons() {
    // PDF Export
    const pdfButtons = document.querySelectorAll('[onclick*="exportToPDF"], .btn-pdf');
    pdfButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            exportToPDF();
        });
    });

    // Excel Export
    const excelButtons = document.querySelectorAll('[onclick*="exportToExcel"], .btn-excel');
    excelButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            exportToExcel();
        });
    });
}

// Teacher Selection and Evaluation Functions
function startEvaluation(teacherId) {
    // Show evaluation form and hide teacher list
    const teacherList = document.getElementById('teacherList');
    const evaluationForm = document.getElementById('evaluationFormContainer');
    if (teacherList) teacherList.classList.add('d-none');
    if (evaluationForm) evaluationForm.classList.remove('d-none');

    // Always clear previous values
    const facultyName = document.getElementById('facultyName');
    const department = document.getElementById('department');
    if (facultyName) facultyName.value = '';
    if (department) department.value = '';

    // Load teacher data and fill fields
    fetch(`../controllers/EvaluationController.php?action=get_teacher&id=${teacherId}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                if (facultyName) facultyName.value = data.teacher.name;
                if (department) department.value = data.teacher.department;
                // Set hidden teacher_id field
                const teacherIdInput = document.getElementById('selected_teacher_id');
                if (teacherIdInput) teacherIdInput.value = teacherId;
            }
        })
        .catch(error => {
            console.error('Error loading teacher data:', error);
        });
}

function showTeacherSelection() {
    const teacherList = document.getElementById('teacherList');
    const evaluationForm = document.getElementById('evaluationFormContainer');
    
    if (teacherList) teacherList.classList.remove('d-none');
    if (evaluationForm) evaluationForm.classList.add('d-none');
}

function calculateAverages() {
    // Communications average
    let commTotal = 0;
    let commCount = 0;
    
    for (let i = 0; i < 5; i++) {
        const selected = document.querySelector(`input[name="communications${i}"]:checked`);
        if (selected) {
            commTotal += parseInt(selected.value);
            commCount++;
        }
    }
    
    const commAvg = commCount > 0 ? (commTotal / commCount).toFixed(1) : '0.0';
    const commAvgElement = document.getElementById('communicationsAverage');
    if (commAvgElement) commAvgElement.textContent = commAvg;
    
    // Management average
    let mgmtTotal = 0;
    let mgmtCount = 0;
    
    for (let i = 0; i < 12; i++) {
        const selected = document.querySelector(`input[name="management${i}"]:checked`);
        if (selected) {
            mgmtTotal += parseInt(selected.value);
            mgmtCount++;
        }
    }
    
    const mgmtAvg = mgmtCount > 0 ? (mgmtTotal / mgmtCount).toFixed(1) : '0.0';
    const mgmtAvgElement = document.getElementById('managementAverage');
    if (mgmtAvgElement) mgmtAvgElement.textContent = mgmtAvg;
    
    // Assessment average
    let assessTotal = 0;
    let assessCount = 0;
    
    for (let i = 0; i < 6; i++) {
        const selected = document.querySelector(`input[name="assessment${i}"]:checked`);
        if (selected) {
            assessTotal += parseInt(selected.value);
            assessCount++;
        }
    }
    
    const assessAvg = assessCount > 0 ? (assessTotal / assessCount).toFixed(1) : '0.0';
    const assessAvgElement = document.getElementById('assessmentAverage');
    if (assessAvgElement) assessAvgElement.textContent = assessAvg;
    
    // Overall average
    const totalCount = commCount + mgmtCount + assessCount;
    const totalSum = commTotal + mgmtTotal + assessTotal;
    const overallAvg = totalCount > 0 ? (totalSum / totalCount).toFixed(1) : '0.0';
    
    const totalAvgElement = document.getElementById('totalAverage');
    if (totalAvgElement) totalAvgElement.textContent = overallAvg;
    
    // Rating interpretation
    let interpretation = '';
    let interpretationClass = '';
    
    if (overallAvg >= 4.6) {
        interpretation = 'Excellent';
        interpretationClass = 'text-success';
    } else if (overallAvg >= 3.6) {
        interpretation = 'Very Satisfactory';
        interpretationClass = 'text-primary';
    } else if (overallAvg >= 2.9) {
        interpretation = 'Satisfactory';
        interpretationClass = 'text-info';
    } else if (overallAvg >= 1.8) {
        interpretation = 'Below Satisfactory';
        interpretationClass = 'text-warning';
    } else if (overallAvg >= 1.0) {
        interpretation = 'Needs Improvement';
        interpretationClass = 'text-danger';
    } else {
        interpretation = 'Not Rated';
        interpretationClass = 'text-muted';
    }
    
    const ratingInterpretationElement = document.getElementById('ratingInterpretation');
    if (ratingInterpretationElement) {
        ratingInterpretationElement.textContent = interpretation;
        ratingInterpretationElement.className = interpretationClass;
    }
    
    return {
        communications: parseFloat(commAvg),
        management: parseFloat(mgmtAvg),
        assessment: parseFloat(assessAvg),
        overall: parseFloat(overallAvg)
    };
}

function generateAIRecommendations() {
    const averages = calculateAverages();
    const aiRecommendationsElement = document.getElementById('aiRecommendations');
    
    if (!aiRecommendationsElement) return;
    
    let recommendationsHTML = '';
    
    // Communications recommendations
    if (averages.communications < 3.0) {
        recommendationsHTML += `
            <div class="mb-3">
                <strong>Communication Skills:</strong>
                <p class="mb-1">Consider voice projection exercises and practice speaking more slowly and clearly. Incorporate more interactive questioning techniques to engage students.</p>
            </div>
        `;
    }
    
    // Management recommendations
    if (averages.management < 3.0) {
        recommendationsHTML += `
            <div class="mb-3">
                <strong>Lesson Management:</strong>
                <p class="mb-1">Focus on creating clearer learning objectives and connecting lessons to real-world examples. Consider using more visual aids and interactive activities.</p>
            </div>
        `;
    }
    
    // Assessment recommendations
    if (averages.assessment < 3.0) {
        recommendationsHTML += `
            <div class="mb-3">
                <strong>Student Assessment:</strong>
                <p class="mb-1">Implement more formative assessment techniques to monitor student understanding throughout the lesson. Consider using quick polls or exit tickets.</p>
            </div>
        `;
    }
    
    // Overall recommendations
    if (averages.overall < 3.0) {
        recommendationsHTML += `
            <div class="mb-3">
                <strong>Overall Teaching Performance:</strong>
                <p class="mb-1">Consider attending professional development workshops on classroom management and instructional strategies. Peer observation of highly-rated faculty may provide valuable insights.</p>
            </div>
        `;
    }
    
    if (recommendationsHTML === '' && averages.overall > 0) {
        recommendationsHTML = `
            <div class="alert alert-success mb-0">
                <i class="fas fa-check-circle me-2"></i>
                Excellent performance! Continue with your current teaching strategies and consider mentoring other faculty members.
            </div>
        `;
    } else if (recommendationsHTML === '') {
        recommendationsHTML = `<p class="mb-0">Complete the evaluation to receive AI-powered recommendations for improvement.</p>`;
    }
    
    aiRecommendationsElement.innerHTML = recommendationsHTML;
}

function initializeEvaluationTables() {
    // This function would initialize the evaluation tables with criteria
    // Implementation depends on your specific table structure
}

// Export Functions
function exportToPDF() {
    // Simple PDF export simulation
    const element = document.getElementById('reportTable') || document.body;
    const teacherName = document.getElementById('facultyName') ? document.getElementById('facultyName').value : 'Evaluation Report';
    
    alert(`PDF export for "${teacherName}" would be generated here.\n\nIn a real implementation, this would:\n1. Collect all evaluation data\n2. Generate a formatted PDF\n3. Download the file`);
    
    // In real implementation, you would use a library like jsPDF or make an AJAX call to a PHP script
    // window.open('../controllers/export.php?type=pdf&data=' + encodeURIComponent(JSON.stringify(data)), '_blank');
}

function exportToExcel() {
    // Simple Excel export simulation
    const element = document.getElementById('reportTable') || document.body;
    const teacherName = document.getElementById('facultyName') ? document.getElementById('facultyName').value : 'Evaluation Report';
    
    alert(`Excel export for "${teacherName}" would be generated here.\n\nIn a real implementation, this would:\n1. Collect all evaluation data\n2. Generate an Excel file\n3. Download the file`);
    
    // In real implementation, you would use a library like SheetJS or make an AJAX call to a PHP script
    // window.open('../controllers/export.php?type=excel&data=' + encodeURIComponent(JSON.stringify(data)), '_blank');
}   

// Form validation
function validateEvaluationForm() {
    const requiredFields = document.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        alert('Please fill in all required fields.');
    }
    
    return isValid;
}

// Save draft functionality
function saveEvaluationDraft() {
    if (confirm('Save evaluation as draft? You can continue later.')) {
        // Collect form data and save via AJAX
        const formData = new FormData(document.getElementById('evaluationForm'));
        formData.append('action', 'save_draft');
        
        fetch('../controllers/EvaluationController.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Draft saved successfully!');
            } else {
                alert('Error saving draft: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving draft. Please try again.');
        });
    }
}