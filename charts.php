<?php 
// Load isolated processing pipeline elements
require_once 'dashflux.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashburn Weather - Historical Analysis</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts/chart.js"></script>
    <?php require_once 'dashboard_styles.php'; ?>
</head>
<body>
    <div class="dashboard-header">
        <div style="font-size: 1.5rem; font-weight: bold; color: #000000;">Ashburn Weather</div>
        <div style="font-size: 1rem; font-weight: bold; color: #4a5568;">as of: <?= $pageLoadTimeString ?></div>
    </div>

    <!-- RENAMED TABS (Charts side is active) -->
    <div class="tab-bar">
        <a href="index.php" class="tab-button" style="text-decoration: none;">Current and Forecast</a>
        <a href="charts.php" class="tab-button active" style="text-decoration: none;">Historical Charts</a>
    </div>

    <div class="dashboard-grid" style="margin-top: 20px;">
        <!-- Card 1: 72-Hour Temperature Multi-Trend -->
        <div class="chart-card">
            <h3>Temperature Trends (72 Hours)</h3>
            <div class="canvas-container"><canvas id="tempChart"></canvas></div>
            <div class="analytics-grid" style="margin-top: 15px;"> 
                <div class="metric-box"><small>Current</small><span><?= $latestTemp ?>°F</span></div> 
                <div class="metric-box"><small>24h Max</small><span><?= $analytics['m1Temp']['max'] ?>°F</span></div> 
                <div class="metric-box"><small>24h Min</small><span><?= $analytics['m1Temp']['min'] ?>°F</span></div> 
                <div class="metric-box"><small>24h Mean</small><span><?= $analytics['m1Temp']['mean'] ?>°F</span></div> 
            </div> 
        </div>

        <!-- Card 2: 72-Hour Single-Line Humidity Trend -->
        <div class="chart-card">
            <h3>Humidity Trends (72 Hours)</h3>
            <div class="canvas-container"><canvas id="humChart"></canvas></div>
            <div class="analytics-grid" style="margin-top: 15px;"> 
                <div class="metric-box"><small>Current</small><span><?= $latestHum ?>%</span></div> 
                <div class="metric-box"><small>24h Max</small><span><?= $analytics['m1Hum']['max'] ?>%</span></div> 
                <div class="metric-box"><small>24h Min</small><span><?= $analytics['m1Hum']['min'] ?>%</span></div> 
                <div class="metric-box"><small>24h Mean</small><span><?= $analytics['m1Hum']['mean'] ?>%</span></div> 
            </div> 
        </div>

        <!-- Card 3: 8-Day Single-Line Barometric Pressure Trend -->
        <div class="chart-card">
            <h3>Barometric Pressure (8 Days)</h3>
            <div class="canvas-container"><canvas id="pressChart"></canvas></div>
            <div class="analytics-grid" style="margin-top: 15px;"> 
                <div class="metric-box"><small>Current</small><span><?= $latestPress ?></span></div> 
                <div class="metric-box"><small>24h Max</small><span><?= $analytics['02pressure']['max'] ?></span></div> 
                <div class="metric-box"><small>24h Min</small><span><?= $analytics['02pressure']['min'] ?></span></div> 
                <div class="metric-box"><small>24h Mean</small><span><?= $analytics['02pressure']['mean'] ?></span></div> 
            </div> 
        </div>

        <!-- Card 4: 72-Hour Single-Line AQI Trend with Color Alert Ribbon -->
        <div class="chart-card">
            <h3>Air Quality Index (72 Hours)</h3>
            <div class="canvas-container"><canvas id="aqiChart"></canvas></div>
            <div class="analytics-grid" style="background-color: <?= $aqiMetrics['bg'] ?>; border-radius: 4px; padding: 8px; margin-top:15px;"> 
                <div class="metric-box"><small style="color:<?= $aqiMetrics['color'] ?>;">Current (AQI)</small><span style="color:<?= $aqiMetrics['color'] ?>;"><?= $latestAQI ?></span></div> 
                <div class="metric-box"><small style="color:<?= $aqiMetrics['color'] ?>;">24h Max</small><span style="color:<?= $aqiMetrics['color'] ?>;"><?= $analytics['06pmAQI']['max'] ?></span></div> 
                <div class="metric-box"><small style="color:<?= $aqiMetrics['color'] ?>;">24h Min</small><span style="color:<?= $aqiMetrics['color'] ?>;"><?= $analytics['06pmAQI']['min'] ?></span></div> 
                <div class="metric-box"><small style="color:<?= $aqiMetrics['color'] ?>;">Status</small><span style="color:<?= $aqiMetrics['color'] ?>; font-size:0.95rem; font-weight:bold;"><?= $aqiMetrics['text'] ?></span></div> 
            </div> 
        </div>

        <!-- Card 5: Long Term History Matrix -->
        <div class="chart-card">
            <h3>Daily Temperature History (60 Days)</h3>
            <div class="canvas-container"><canvas id="temp60dChart"></canvas></div>
            <div class="analytics-grid" style="margin-top: 15px;">
                <div class="metric-box"><small>Latest Mean</small><span id="latestMean60d">--</span></div>
                <div class="metric-box"><small>Period Max</small><span id="periodMax60d">--</span></div>
                <div class="metric-box"><small>Period Min</small><span id="periodMin60d">--</span></div>
                <div class="metric-box"><small>Data Points</small><span id="points60d">--</span></div>
            </div>
        </div>
    </div>

    <script>
    const data72h = <?= json_encode($chartData72h) ?>;
    const data8d = <?= json_encode($chartData8d) ?>;
    const data60d = <?= json_encode($chartData60d) ?>;
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { grid: { display: false }, ticks: { maxTicksLimit: 6, maxRotation: 0, autoSkip: true } },
            y: { grid: { color: '#e1e4e8' } }
        },
        plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 8 } } }
    };

    // Temp Configuration
    new Chart(document.getElementById('tempChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: data72h.labels,
            datasets: [
                { label: 'Temperature', data: data72h.m1Temp, borderColor: '#ef233c', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.15 },
                { label: 'Heat Index', data: data72h.m1hi, borderColor: '#d90429', backgroundColor: 'transparent', borderWidth: 1.5, pointRadius: 0, pointHoverRadius: 4, tension: 0.1, spanGaps: false },
                { label: 'Dew Point', data: data72h.m1dew, borderColor: '#00b4d8', backgroundColor: 'transparent', borderWidth: 1.8, pointRadius: 0, pointHoverRadius: 4, tension: 0.15 }
            ]
        },
        options: commonOptions
    });

    // Humidity Configuration
    new Chart(document.getElementById('humChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: data72h.labels,
            datasets: [{ label: 'Humidity', data: data72h.m1Hum, borderColor: '#3a86ff', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.15 }]
        },
        options: commonOptions
    });

    // Pressure Configuration
    new Chart(document.getElementById('pressChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: data8d.labels,
            datasets: [{ label: 'Pressure', data: data8d['02pressure'], borderColor: '#70e000', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.15 }]
        },
        options: { ...commonOptions, spanGaps: true }
    });

    // AQI Configuration
    new Chart(document.getElementById('aqiChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: data72h.labels,
            datasets: [{ label: 'AQI Index', data: data72h['06pmAQI'], borderColor: '#ff007f', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.15 }]
        },
        options: commonOptions
    });

    // 60-Day High / Low Map
    (function() {
        const means = data60d.mean.filter(v => v !== null);
        const highs = data60d.high.filter(v => v !== null);
        const lows = data60d.low.filter(v => v !== null);
        document.getElementById('latestMean60d').textContent = means.length ? means[means.length-1] + '°F' : '--';
        document.getElementById('periodMax60d').textContent = highs.length ? Math.max(...highs).toFixed(1) + '°F' : '--';
        document.getElementById('periodMin60d').textContent = lows.length ? Math.min(...lows).toFixed(1) + '°F' : '--';
        document.getElementById('points60d').textContent = data60d.labels.length;
    })();

    new Chart(document.getElementById('temp60dChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: data60d.labels,
            datasets: [
                { label: 'High', data: data60d.high, borderColor: '#ff0000', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4, tension: 0.15, spanGaps: true },
                { label: 'Mean', data: data60d.mean, borderColor: '#000000', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4, tension: 0.15, spanGaps: true },
                { label: 'Low', data: data60d.low, borderColor: '#0000ff', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4, tension: 0.15, spanGaps: true }
            ]
        },
        options: { ...commonOptions, spanGaps: true, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0, autoSkip: true } }, y: { grid: { color: '#e1e4e8' } } } }
    });
    </script>
</body>
</html>

