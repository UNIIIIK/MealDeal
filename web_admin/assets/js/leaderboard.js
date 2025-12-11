// leaderboard.js

const chartPalette = (() => {
    const styles = getComputedStyle(document.documentElement);
    const read = (name, fallback) => (styles.getPropertyValue(name) || '').trim() || fallback;
    return {
        green: read('--chart-green', '#28a745'),
        teal: read('--chart-teal', '#20c997'),
        yellow: read('--chart-yellow', '#ffc107'),
        red: read('--chart-red', '#dc3545'),
        blue: read('--chart-blue', '#0d6efd'),
        purple: read('--chart-purple', '#6f42c1'),
        grid: read('--chart-grid', 'rgba(0,0,0,0.1)'),
        greenSurface: read('--chart-green-surface', 'rgba(40, 167, 69, 0.1)'),
        tealSurface: read('--chart-teal-surface', 'rgba(32, 201, 151, 0.1)'),
        yellowSurface: read('--chart-yellow-surface', 'rgba(255, 193, 7, 0.1)'),
        redSurface: read('--chart-red-surface', 'rgba(220, 53, 69, 0.1)'),
        blueSurface: read('--chart-blue-surface', 'rgba(13, 110, 253, 0.1)'),
        purpleSurface: read('--chart-purple-surface', 'rgba(111, 66, 193, 0.1)')
    };
})();

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
                        borderColor: chartPalette.green,
                        backgroundColor: chartPalette.greenSurface,
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
                            color: chartPalette.grid
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
                        borderColor: chartPalette.yellow,
                        backgroundColor: chartPalette.yellowSurface,
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
                            color: chartPalette.grid
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
                    borderColor: chartPalette.teal,
                    backgroundColor: chartPalette.tealSurface,
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
                            color: chartPalette.grid
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
                    borderColor: chartPalette.purple,
                    backgroundColor: chartPalette.purpleSurface,
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
                            color: chartPalette.grid
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


