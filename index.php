<?php 
require_once 'dashflux.php'; 

function fetchNwsForecast() { 
    $cacheFile = __DIR__ . '/nws_forecast_cache.json'; 
    $cacheTime = 3600; 
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) { 
        return json_decode(file_get_contents($cacheFile), true); 
    } 
    $url = defined('NWS_URL') ? NWS_URL : "https://weather.gov"; 
    $ch = curl_init($url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 12); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 
        'User-Agent: AshburnWeatherDashboard/1.0 (admin@myorg.com)', 
        'Accept: application/geo+json' 
    ]); 
    $res = curl_exec($ch); 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch); 
    if ($httpCode === 200 && !empty($res)) { 
        if (@file_put_contents($cacheFile, $res) !== false) { 
            clearstatcache(true, $cacheFile); 
            return json_decode($res, true); 
        } 
    } 
    if (file_exists($cacheFile)) { 
        return json_decode(file_get_contents($cacheFile), true); 
    } 
    return null; 
} 

$forecastData = fetchNwsForecast(); 
$forecastPeriods = $forecastData['properties']['periods'] ?? [];
$groupedForecast = [];

if (!empty($forecastPeriods)) {
    $isFirstPeriodDay = $forecastPeriods[0]['isDaytime'];

    if ($isFirstPeriodDay) {
        // --- TYPE A: DAY START LAYOUT (7 Rows) ---
        for ($i = 0; $i < count($forecastPeriods); $i += 2) {
            $dayObj = $forecastPeriods[$i] ?? null;
            $nightObj = $forecastPeriods[$i + 1] ?? null;
            
            // Core Rule: Always favor the daytime name label if it exists
            $displayLabel = $dayObj ? $dayObj['name'] : ($nightObj['name'] ?? '');

            $groupedForecast[] = [
                'day'          => $dayObj,
                'night'        => $nightObj,
                'displayLabel' => $displayLabel
            ];
        }
    } else {
        // --- TYPE B: NIGHT START LAYOUT (8 Rows) ---
        // Row 1: Standalone active nighttime block (Preserves "Tonight")
        $groupedForecast[] = [
            'day'          => null,
            'night'        => $forecastPeriods[0],
            'displayLabel' => $forecastPeriods[0]['name']
        ];

        // Rows 2 through 7: Pair middle elements, prioritizing the Daytime label
        for ($i = 1; $i <= 11; $i += 2) {
            $dayObj = $forecastPeriods[$i] ?? null;
            $nightObj = $forecastPeriods[$i + 1] ?? null;
            
            // Core Rule: Favor the daytime name label (e.g., "Wednesday" instead of "Wednesday Night")
            $displayLabel = $dayObj ? $dayObj['name'] : ($nightObj['name'] ?? '');

            $groupedForecast[] = [
                'day'          => $dayObj,
                'night'        => $nightObj,
                'displayLabel' => $displayLabel
            ];
        }

        // Row 8: Standalone trailing daytime block at the bottom
        if (isset($forecastPeriods[13])) {
            $groupedForecast[] = [
                'day'          => $forecastPeriods[13],
                'night'        => null,
                'displayLabel' => $forecastPeriods[13]['name']
            ];
        }
    }
}


$cacheFileLocal = __DIR__ . '/nws_forecast_cache.json'; 
$cacheAgeString = file_exists($cacheFileLocal) ? date('m/d H:i', filemtime($cacheFileLocal)) : 'No Active Cache'; 

$filteredDew = array_filter($chartData72h['m1dew'], 'is_numeric'); 
$latestDew = !empty($filteredDew) ? round(end($filteredDew), 1) : '--'; 
?>
<!DOCTYPE html> 
<html lang="en"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Ashburn Weather - Current & Forecast</title> 
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>"> 
    <link rel="stylesheet" href="fc.css?v=<?= time(); ?>"> 
    <?php require_once 'dashboard_styles.php'; ?> 
</head> 
<body> 
    <div class="dashboard-header"> 
        <div style="font-size: 1.8rem; font-weight: 900; color: #000000;">Ashburn Weather</div> 
        <div style="font-size: 1.2rem; font-weight: 900; color: #000000;">as of: <?= $pageLoadTimeString ?></div> 
    </div> 
    <div class="tab-bar"> 
        <a href="index.php" class="tab-button active" style="text-decoration: none; font-weight: 900; font-size: 1.1rem;">Current and Forecast</a> 
        <a href="charts.php" class="tab-button" style="text-decoration: none; font-weight: 900; font-size: 1.1rem;">Historical Charts</a> 
    </div> 
    <div class="version-changelog-container" style="text-align: left;">
        <details class="version-accordion">
            <summary class="version-trigger">
                <span class="version-badge">Version 0.08b</span> 
                <span class="version-link">Click for details</span>
            </summary>
            <div class="version-content">
                <ul class="changelog-list">
                    <li><strong>08/14/26</strong> - Fixed NWS weekend / afternoon label pairing error via true timestamp mapping</li>
                    <li><strong>08/14/26</strong> - Added css force cache miss</li>
                    <li><strong>08/14/26</strong> - Added change list tracker accordion matrix</li>
                    <li><strong>08/14/26</strong> - Color coded night forecast narrative blocks</li>
                    <li><strong>08/13/26</strong> - Fixed heat index metric disappearing threshold issue</li>
                </ul>
            </div>
        </details>
    </div>
    <div class="sensor-attribution-banner"> 
        Current atmospheric readings are provided directly by local station sensors. 
    </div> 
    <div class="current-metrics-deck"> 
        <div class="current-metric-tile"> 
            <small>Temperature</small> 
            <span><?= $latestTemp ?>°F</span> 
        </div> 
        <div class="current-metric-tile"> 
            <small>Humidity</small> 
            <span><?= $latestHum ?>%</span> 
        </div> 
        <div class="current-metric-tile"> 
            <small>Dew Point</small> 
            <span><?= $latestDew ?>°F</span> 
        </div> 
        <?php 
        $latestHI = null; 
        if (!empty($timelineMap)) { 
            $chronologicalTimeline = $timelineMap; 
            uasort($chronologicalTimeline, function($a, $b) { 
                return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0); 
            }); 
            foreach (array_reverse($chronologicalTimeline) as $metrics) { 
                if (isset($metrics['m1hi']) && is_numeric($metrics['m1hi'])) { 
                    $latestHI = $metrics['m1hi']; 
                    break; 
                } 
            } 
        } 
        if ($latestHI !== null && $latestHI >= 80.0 && is_numeric($latestTemp) && $latestTemp >= 80.0): ?> 
            <div class="current-metric-tile highlight-heat"> 
                <small>Heat Index</small> 
                <span><?= round($latestHI, 1) ?>°F</span> 
            </div> 
        <?php endif; ?> 
        <div class="current-metric-tile"> 
            <small>Barometric Pressure</small> 
            <span><?= $latestPress ?></span> 
        </div> 
        <div class="current-metric-tile" style="background-color: <?= $aqiMetrics['bg'] ?>;"> 
            <small style="color: <?= $aqiMetrics['color'] ?>;">Air Quality (AQI)</small> 
            <span style="color: <?= $aqiMetrics['color'] ?>;"><?= $latestAQI ?> - <?= $aqiMetrics['text'] ?></span> 
        </div> 
    </div> 
    <hr class="section-divider-stark" /> 
    <div class="cache-info-banner-accessible"> 
        Regional outlook guidance provided by National Weather Service. Last updated: <span style="text-decoration: underline;"><?= $cacheAgeString ?></span> 
    </div> 
    <div class="recap-container"> 
        <?php if (empty($groupedForecast)): ?> 
            <p class="no-data-msg">No regional forecast indicators extracted. Check connection profiles.</p> 
        <?php else: ?> 
            <?php foreach ($groupedForecast as $dayKey => $data): 
                $dayName = $data['displayLabel']; 
                $activePeriod = $data['day'] ?? $data['night']; 
                $iconUrl = str_replace('size=medium', 'size=large', $activePeriod['icon']); 
                $highTemp = $data['day'] ? $data['day']['temperature'] . '°' : '--'; 
                $lowTemp = $data['night'] ? $data['night']['temperature'] . '°' : '--'; 
                $summary = $data['day'] ? $data['day']['shortForecast'] : $data['night']['shortForecast']; 
                $popDay = $data['day']['probabilityOfPrecipitation']['value'] ?? 0; 
                $popNight = $data['night']['probabilityOfPrecipitation']['value'] ?? 0; 
                $maxPop = max($popDay, $popNight); 
                $dayDetail = $data['day'] ? $data['day']['detailedForecast'] : ''; 
                $nightDetail = $data['night'] ? $data['night']['detailedForecast'] : ''; 
            ?> 
                <div class="recap-row"> 
                    <div class="recap-day-label"><?= htmlspecialchars($dayName) ?></div> 
                    <div class="recap-temp-bounds"> 
                        <span class="recap-high"><?= htmlspecialchars($highTemp) ?></span> 
                        <span class="recap-low"><?= htmlspecialchars($lowTemp) ?></span> 
                    </div> 
                    <div class="recap-icon-block"> 
                        <img src="<?= htmlspecialchars($iconUrl) ?>" alt="Weather Icon" class="recap-icon-small" /> 
                        <div class="recap-pop-container"> 
                            <?php if ($maxPop > 0): ?> 
                                <span class="recap-raindrop">💧</span> 
                                <span class="recap-pop-badge"><?= htmlspecialchars($maxPop) ?>%</span> 
                            <?php else: ?> 
                                <span class="recap-pop-badge empty-pop">--</span> 
                            <?php endif; ?> 
                        </div> 
                    </div> 
                    <div class="recap-summary-text"> 
                        <details class="forecast-accordion"> 
                            <summary class="accordion-trigger"> 
                                <span class="short-summary-link"><?= htmlspecialchars($summary) ?></span> 
                            </summary> 
                            <div class="accordion-content"> 
                                <?php if (!empty($dayDetail)): ?> 
                                    <span class="forecast-day-text"><?= htmlspecialchars($dayDetail) ?></span> 
                                <?php endif; ?> 
                                <?php if (!empty($nightDetail)): ?> 
                                    <span class="forecast-night-text"><?= htmlspecialchars($nightDetail) ?></span> 
                                <?php endif; ?> 
                            </div> 
                        </details> 
                    </div> 
                </div> 
            <?php endforeach; ?> 
        <?php endif; ?> 
    </div> 
</body> 
</html>
