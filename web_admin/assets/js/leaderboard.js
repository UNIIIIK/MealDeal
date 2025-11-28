// leaderboard.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts with real data from the backend when available
    initializeCharts();
    
    window.refreshLeaderboard = function() { 
        location.reload(); 
    }
    
    window.viewUserDetails = function(userId) {
        alert('User details for: ' + userId);
    }
    
    window.sendReward = function(userId) {
        const input = document.getElementById('rewardUserId');
        if (input) input.value = userId;
        const modal = new bootstrap.Modal(document.getElementById('rewardModal'));
        modal.show();
    }
});

function initializeCharts() {
    // Prefer real data from PHP (LEADERBOARD_CHART_DATA); fall back to generated sample data.
    const backendData = window.LEADERBOARD_CHART_DATA;

    let labels;
    let foodSavedData;
    let ordersData;
    let usersData;
    let revenueData;

    if (backendData && Array.isArray(backendData.labels) && backendData.labels.length > 0) {
        labels       = backendData.labels;
        foodSavedData = backendData.food_saved || [];
        ordersData    = backendData.orders || [];
        usersData     = backendData.users || [];
        revenueData   = backendData.revenue || [];
    } else {
        // Fallback sample data for development
        labels        = generateTimeLabels();
        foodSavedData = generateSampleData(labels.length, 0, 50);
        ordersData    = generateSampleData(labels.length, 0, 20);
        usersData     = generateSampleData(labels.length, 5, 15);
        revenueData   = generateSampleData(labels.length, 100, 1000);
    }
    
    // Food Saved Chart
    const foodSavedCtx = document.getElementById('foodSavedChart');
    if (foodSavedCtx) {
        new Chart(foodSavedCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Food Saved (kg)',
                    data: foodSavedData,
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Orders Chart
    const ordersCtx = document.getElementById('ordersChart');
    if (ordersCtx) {
        new Chart(ordersCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Orders',
                    data: ordersData,
                    borderColor: 'rgb(255, 193, 7)',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Users Chart
    const usersCtx = document.getElementById('usersChart');
    if (usersCtx) {
        new Chart(usersCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Active Users',
                    data: usersData,
                    borderColor: 'rgb(13, 202, 240)',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: revenueData,
                    borderColor: 'rgb(111, 66, 193)',
                    backgroundColor: 'rgba(111, 66, 193, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

function generateTimeLabels() {
    const labels = [];
    const now = new Date();
    
    // Generate labels for the last 7 days
    for (let i = 6; i >= 0; i--) {
        const date = new Date(now);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    }
    
    return labels;
}

function generateSampleData(length, min, max) {
    const data = [];
    for (let i = 0; i < length; i++) {
        data.push(Math.floor(Math.random() * (max - min + 1)) + min);
    }
    return data;
}


