/**
 * Main JavaScript - CardioRisk
 * General application functionality
 */

$(document).ready(function() {
    
    // ===================================
    // Initialize Bootstrap Tooltips
    // ===================================
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // ===================================
    // Range Slider Value Updates
    // ===================================
    
    // Blood Pressure
    $('#trestbps').on('input', function() {
        $('#trestbps_value').text($(this).val());
    });
    
    // Cholesterol
    $('#chol').on('input', function() {
        $('#chol_value').text($(this).val());
    });
    
    // Max Heart Rate
    $('#thalach').on('input', function() {
        $('#thalach_value').text($(this).val());
    });
    
    // ST Depression (Oldpeak)
    $('#oldpeak').on('input', function() {
        $('#oldpeak_value').text(parseFloat($(this).val()).toFixed(1));
    });
    
    // What-If Simulator Sliders
    $('#sim_chol').on('input', function() {
        $('#sim_chol_value').text($(this).val());
    });
    
    $('#sim_bp').on('input', function() {
        $('#sim_bp_value').text($(this).val());
    });
    
    $('#sim_hr').on('input', function() {
        $('#sim_hr_value').text($(this).val());
    });
    
    // ===================================
    // Form Validation Enhancement
    // ===================================
    $('form').on('submit', function(e) {
        var form = $(this);
        
        // Check if form has required fields
        var isValid = true;
        form.find('[required]').each(function() {
            if ($(this).val() === '') {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in all required fields.',
                confirmButtonColor: '#0891B2'
            });
        }
    });
    
    // ===================================
    // Dynamic Table Search
    // ===================================
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#dataTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // ===================================
    // Smooth Scroll for Anchor Links
    // ===================================
    $('a[href^="#"]').on('click', function(e) {
        var target = $(this.hash);
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 70
            }, 500);
        }
    });
    
    // ===================================
    // Auto-hide Alerts
    // ===================================
    setTimeout(function() {
        $('.alert.auto-hide').fadeOut('slow');
    }, 5000);
    
    // ===================================
    // Confirmation Dialogs
    // ===================================
    $('.confirm-action').on('click', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var message = $(this).data('message') || 'Are you sure you want to proceed?';
        
        Swal.fire({
            title: 'Confirmation',
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0891B2',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = href;
            }
        });
    });
    
    // ===================================
    // Print Functionality
    // ===================================
    $('.btn-print').on('click', function() {
        window.print();
    });
    
    // ===================================
    // Loading State for Buttons
    // ===================================
    $('form').on('submit', function() {
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true);
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');
        
        // Re-enable after 10 seconds as fallback
        setTimeout(function() {
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
        }, 10000);
    });
    
});

// ===================================
// Risk Simulation Function
// ===================================
function simulateRisk() {
    // Get original patient data
    var patientData = $('#patientData');
    
    var originalParams = {
        cp: parseInt(patientData.data('patient-cp')),
        trestbps: parseInt(patientData.data('patient-bp')),
        chol: parseInt(patientData.data('patient-chol')),
        fbs: parseInt(patientData.data('patient-fbs')),
        restecg: parseInt(patientData.data('patient-restecg')),
        thalach: parseInt(patientData.data('patient-hr')),
        exang: parseInt(patientData.data('patient-exang')),
        oldpeak: parseFloat(patientData.data('patient-oldpeak')),
        slope: parseInt(patientData.data('patient-slope')),
        ca: parseInt(patientData.data('patient-ca')),
        thal: parseInt(patientData.data('patient-thal'))
    };
    
    // Get simulated values
    var simParams = Object.assign({}, originalParams);
    simParams.chol = parseInt($('#sim_chol').val());
    simParams.trestbps = parseInt($('#sim_bp').val());
    simParams.thalach = parseInt($('#sim_hr').val());
    
    // Calculate new risk (client-side approximation)
    var newRiskScore = calculateRiskScore(simParams);
    var oldRiskScore = parseInt(patientData.data('patient-risk'));
    
    var difference = newRiskScore - oldRiskScore;
    var percentChange = oldRiskScore > 0 ? ((difference / oldRiskScore) * 100).toFixed(1) : 0;
    
    // Determine risk level
    var riskLevel = newRiskScore <= 33 ? 'Low' : (newRiskScore <= 66 ? 'Moderate' : 'High');
    var riskColor = newRiskScore <= 33 ? 'success' : (newRiskScore <= 66 ? 'warning' : 'danger');
    
    // Display result
    var resultText = '<div class="mb-2">';
    resultText += '<strong>New Risk Score: <span class="text-' + riskColor + '">' + newRiskScore + '% (' + riskLevel + ')</span></strong><br>';
    resultText += 'Original: ' + oldRiskScore + '%<br>';
    
    if (difference < 0) {
        resultText += '<span class="text-success"><i class="bi bi-arrow-down-circle"></i> Risk decreased by ' + Math.abs(difference) + '% (' + Math.abs(percentChange) + '%)</span>';
    } else if (difference > 0) {
        resultText += '<span class="text-danger"><i class="bi bi-arrow-up-circle"></i> Risk increased by ' + difference + '% (' + percentChange + '%)</span>';
    } else {
        resultText += '<span class="text-muted">No change in risk</span>';
    }
    
    resultText += '</div>';
    
    $('#simResultText').html(resultText);
    $('#simulationResult').removeClass('d-none').addClass('show');
}

// ===================================
// Reset Simulation
// ===================================
function resetSimulation() {
    var patientData = $('#patientData');
    
    $('#sim_chol').val(patientData.data('patient-chol')).trigger('input');
    $('#sim_bp').val(patientData.data('patient-bp')).trigger('input');
    $('#sim_hr').val(patientData.data('patient-hr')).trigger('input');
    
    $('#simulationResult').addClass('d-none').removeClass('show');
}

// ===================================
// Client-Side Risk Calculation
// (Simplified version for simulation)
// ===================================
function calculateRiskScore(params) {
    var score = 0;
    
    // Chest pain
    if (params.cp == 4) score += 20;
    else if (params.cp == 3) score += 10;
    else if (params.cp == 2) score += 5;
    
    // Blood pressure
    if (params.trestbps >= 180) score += 15;
    else if (params.trestbps >= 160) score += 12;
    else if (params.trestbps >= 140) score += 8;
    else if (params.trestbps >= 130) score += 4;
    
    // Cholesterol
    if (params.chol >= 280) score += 15;
    else if (params.chol >= 240) score += 12;
    else if (params.chol >= 200) score += 6;
    
    // Fasting blood sugar
    if (params.fbs == 1) score += 8;
    
    // Resting ECG
    if (params.restecg == 2) score += 10;
    else if (params.restecg == 1) score += 6;
    
    // Max heart rate
    if (params.thalach < 100) score += 12;
    else if (params.thalach < 120) score += 8;
    else if (params.thalach < 140) score += 4;
    
    // Exercise angina
    if (params.exang == 1) score += 10;
    
    // ST depression
    if (params.oldpeak >= 3.0) score += 20;
    else if (params.oldpeak >= 2.0) score += 15;
    else if (params.oldpeak >= 1.0) score += 8;
    else if (params.oldpeak > 0) score += 3;
    
    // Slope
    if (params.slope == 3) score += 8;
    else if (params.slope == 2) score += 5;
    
    // Major vessels
    score += params.ca * 5;
    
    // Thalassemia
    if (params.thal == 7) score += 12;
    else if (params.thal == 6) score += 8;
    
    // Normalize to 0-100
    score = Math.min(100, Math.round(score * 0.75));
    
    return score;
}
