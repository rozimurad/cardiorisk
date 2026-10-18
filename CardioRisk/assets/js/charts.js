/**
 * Chart.js Visualizations - CardioRisk
 * Radar chart and risk gauge implementations
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function () {

    // Get patient data from hidden div
    var dataEl = document.getElementById('patientData');

    if (!dataEl) {
        return; // Not on patient detail page
    }

    // Extract patient metrics
    var patientData = {
        bp: parseFloat(dataEl.dataset.patientBp),
        chol: parseFloat(dataEl.dataset.patientChol),
        hr: parseFloat(dataEl.dataset.patientHr),
        oldpeak: parseFloat(dataEl.dataset.patientOldpeak),
        fbs: parseFloat(dataEl.dataset.patientFbs),
        riskScore: parseFloat(dataEl.dataset.patientRisk)
    };

    // Extract healthy averages
    var healthyData = {
        bp: parseFloat(dataEl.dataset.healthyBp),
        chol: parseFloat(dataEl.dataset.healthyChol),
        hr: parseFloat(dataEl.dataset.healthyHr),
        oldpeak: parseFloat(dataEl.dataset.healthyOldpeak),
        fbs: parseFloat(dataEl.dataset.healthyFbs)
    };

    // ===================================
    // RADAR CHART - Comparative Analysis
    // ===================================
    var radarCtx = document.getElementById('radarChart');

    if (radarCtx) {
        var radarChart = new Chart(radarCtx, {
            type: 'radar',
            data: {
                labels: [
                    'Kan Basıncı',
                    'Kolesterol',
                    'Maks. Kalp Hızı',
                    'ST Çökmesi',
                    'Kan Şekeri'
                ],
                datasets: [
                    {
                        label: 'Hasta Değerleri',
                        data: [
                            patientData.bp,
                            patientData.chol,
                            patientData.hr,
                            patientData.oldpeak * 50, // Scale for visibility
                            patientData.fbs * 100 // Scale for visibility
                        ],
                        backgroundColor: 'rgba(239, 68, 68, 0.2)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(239, 68, 68, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(239, 68, 68, 1)',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Sağlıklı Ortalama',
                        data: [
                            healthyData.bp,
                            healthyData.chol,
                            healthyData.hr,
                            healthyData.oldpeak * 50, // Scale for visibility
                            healthyData.fbs * 100 // Scale for visibility
                        ],
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(16, 185, 129, 1)',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false // We have custom legend below
                    },
                    tooltip: {
                        backgroundColor: 'rgba(30, 41, 59, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function (context) {
                                var label = context.dataset.label || '';
                                var value = context.parsed.r;

                                // Unscale values for display
                                if (context.dataIndex === 3) { // ST Depression
                                    value = (value / 50).toFixed(1);
                                    return label + ': ' + value + ' mm';
                                } else if (context.dataIndex === 4) { // Blood Sugar
                                    value = value > 50 ? '>120 mg/dL' : '<120 mg/dL';
                                    return label + ': ' + value;
                                } else if (context.dataIndex === 0) { // BP
                                    return label + ': ' + Math.round(value) + ' mmHg';
                                } else if (context.dataIndex === 1) { // Cholesterol
                                    return label + ': ' + Math.round(value) + ' mg/dL';
                                } else if (context.dataIndex === 2) { // Heart Rate
                                    return label + ': ' + Math.round(value) + ' bpm';
                                }

                                return label + ': ' + value;
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 50,
                            backdropColor: 'transparent',
                            color: '#64748B',
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: '#E2E8F0'
                        },
                        pointLabels: {
                            color: '#1E293B',
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });
    }

    // ===================================
    // RISK GAUGE - Semi-Circle Doughnut
    // ===================================
    var gaugeCtx = document.getElementById('riskGauge');

    if (gaugeCtx) {
        // Determine color based on risk score
        var gaugeColor;
        if (patientData.riskScore <= 33) {
            gaugeColor = '#10B981'; // Green
        } else if (patientData.riskScore <= 66) {
            gaugeColor = '#F59E0B'; // Orange
        } else {
            gaugeColor = '#EF4444'; // Red
        }

        var riskGauge = new Chart(gaugeCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [patientData.riskScore, 100 - patientData.riskScore],
                    backgroundColor: [gaugeColor, '#E2E8F0'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '75%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            },
            plugins: [{
                id: 'gaugeText',
                afterDraw: function (chart) {
                    var width = chart.width;
                    var height = chart.height;
                    var ctx = chart.ctx;

                    ctx.restore();

                    // Risk Score Number
                    var fontSize = (height / 100).toFixed(2);
                    ctx.font = fontSize + "em sans-serif";
                    ctx.textBaseline = "middle";
                    ctx.fillStyle = gaugeColor;

                    var text = patientData.riskScore + "%";
                    var textX = Math.round((width - ctx.measureText(text).width) / 2);
                    var textY = height / 1.8;

                    ctx.fillText(text, textX, textY);

                    // Label Below
                    ctx.font = (fontSize * 0.4) + "em sans-serif";
                    ctx.fillStyle = "#64748B";
                    var labelText = "Risk Skoru";
                    var labelX = Math.round((width - ctx.measureText(labelText).width) / 2);
                    var labelY = height / 1.4;

                    ctx.fillText(labelText, labelX, labelY);

                    ctx.save();
                }
            }]
        });
    }

});

// ===================================
// PROGRESS TIMELINE CHART - YENI!
// İlerleme Takibi Line Chart
// ===================================
document.addEventListener('DOMContentLoaded', function () {
    var progressCtx = document.getElementById('progressChart');

    if (progressCtx) {
        // PHP'den veriyi al (bu veriyi patient_detail.php'de hazırlayacağız)
        var progressData = window.visitHistoryData || null;

        if (progressData) {
            new Chart(progressCtx, {
                type: 'line',
                data: {
                    labels: progressData.dates,
                    datasets: [
                        {
                            label: 'Risk Skoru (%)',
                            data: progressData.riskScores,
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: '#EF4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Kolesterol (mg/dL)',
                            data: progressData.cholesterol,
                            borderColor: '#F59E0B',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Kan Basıncı (mmHg)',
                            data: progressData.bloodPressure,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                title: function (context) {
                                    return 'Ziyaret: ' + context[0].label;
                                },
                                label: function (context) {
                                    var label = context.dataset.label || '';
                                    var value = context.parsed.y;

                                    if (label.includes('Risk')) {
                                        return label + ': ' + value + '%';
                                    } else if (label.includes('Kolesterol')) {
                                        return label + ': ' + value + ' mg/dL';
                                    } else if (label.includes('Kan Basıncı')) {
                                        return label + ': ' + value + ' mmHg';
                                    }
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Risk Skoru (%)',
                                color: '#EF4444',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                color: '#EF4444'
                            },
                            grid: {
                                color: 'rgba(239, 68, 68, 0.1)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Kol. / Kan Bas. (mg/dL, mmHg)',
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                color: '#64748b'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        x: {
                            grid: {
                                color: '#E2E8F0'
                            },
                            ticks: {
                                color: '#1E293B',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});
