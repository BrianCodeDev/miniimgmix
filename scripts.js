jQuery(document).ready(function($) {
    // Get the context of the canvas element
    var ctx = document.getElementById('uploadChart').getContext('2d');

    // Fetch chart data from PHP
    var chartData = '<?php echo wp_json_encode(get_upload_statistics()); ?>;'

    // Convert data into labels and values
    var labels = Object.keys(chartData);
    var data = Object.values(chartData);

    // Initialize the Chart.js instance
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Uploads Per Day',
                data: data,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    beginAtZero: true
                },
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
