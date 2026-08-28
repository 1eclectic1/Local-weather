<?php
// 1. Load global configurations from your verified config.php
require_once 'notme/config.php';

$orgName = "myOrg";
$targetBucket = "weather";

// Grab time stamp when web page loads for the dashboard header
$dateTimeHeader = new DateTime('now', new DateTimeZone('America/New_York'));
$pageLoadTimeString = $dateTimeHeader->format('m/d H:i');

// Rebuild connection string cleanly targeting the 8086 container port
$cleanHost = preg_replace('#^https?://#', '', INFLUX_HOST);
$url = "http://" . $cleanHost . "/api/v2/query?org=" . urlencode($orgName);

// --- CONSTRUCT THE LOOKBACK QUERY PACKETS ---
$fluxQuery72h = <<<FLUX
from(bucket: "{$targetBucket}")
  |> range(start: -72h)
  |> filter(fn: (r) => r["_field"] == "m1Temp" or r["_field"] == "m1hi" or r["_field"] == "m1dew" or r["_field"] == "m1Hum" or r["_field"] == "06pmAQI")
  |> aggregateWindow(every: 10m, fn: mean, createEmpty: false)
  |> yield(name: "mean")
FLUX;

$fluxQuery8d = <<<FLUX
from(bucket: "{$targetBucket}")
  |> range(start: -8d)
  |> filter(fn: (r) => r["_field"] == "02pressure")
  |> aggregateWindow(every: 10m, fn: mean, createEmpty: false)
  |> yield(name: "mean")
FLUX;

// --- HIGH-SPEED INGESTION ENGINE FUNCTION ---
function executeInfluxQuery($url, $query, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token " . $token,
        "Content-Type: application/vnd.flux",
        "Accept: application/csv"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$response72h = executeInfluxQuery($url, $fluxQuery72h, INFLUX_TOKEN);
$response8d = executeInfluxQuery($url, $fluxQuery8d, INFLUX_TOKEN);

// --- CHRONOLOGICAL TIMELINE BUFFER AND PARSER LAYER ---
$timelineMap = [];
$metricKeys = ['m1Temp', 'm1hi', 'm1dew', 'm1Hum', '02pressure', '06pmAQI'];

function parseInfluxCsv($csvString, &$timelineMap, $metricKeys) {
    $lines = explode("\n", trim($csvString));
    $headers = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        $fields = str_getcsv($line);
        if (isset($fields[1]) && $fields[1] === 'result' && isset($fields[2]) && $fields[2] === 'table') {
            $headers = $fields;
            continue;
        }
        if (!$headers || count($fields) < 7) continue;
        $row = array_combine($headers, $fields);
        $fieldName = $row['_field'] ?? null;
        $utcTime = $row['_time'] ?? null;
        $value = $row['_value'] ?? null;
        if (!$fieldName || !in_array($fieldName, $metricKeys) || $value === null || $value === '') continue;
        $dateTime = new DateTime($utcTime);
        $dateTime->setTimezone(new DateTimeZone('America/New_York'));
        $localTimeLabel = $dateTime->format('m/d H:i');
        $timelineMap[$localTimeLabel]['timestamp'] = $dateTime->getTimestamp();
        $timelineMap[$localTimeLabel][$fieldName] = floatval($value);
    }
}

parseInfluxCsv($response72h, $timelineMap, $metricKeys);
parseInfluxCsv($response8d, $timelineMap, $metricKeys);
ksort($timelineMap);

// --- ROLLING 24-HOUR ANALYTICS EVALUATION MATRIX ---
$currentTimestamp = time();
$cutoff24h = $currentTimestamp - (24 * 60 * 60);
$analytics = [];
foreach ($metricKeys as $key) {
    $analytics[$key] = ['last' => '--', 'min' => null, 'max' => null, 'sum' => 0, 'count' => 0, 'mean' => '--'];
}

foreach ($timelineMap as $timeLabel => $metrics) {
    $isWithin24h = ($metrics['timestamp'] >= $cutoff24h);
    foreach ($metricKeys as $key) {
        if (isset($metrics[$key])) {
            $val = $metrics[$key];
            $analytics[$key]['last'] = $val;
            if ($isWithin24h) {
                if ($analytics[$key]['min'] === null || $val < $analytics[$key]['min']) $analytics[$key]['min'] = $val;
                if ($analytics[$key]['max'] === null || $val > $analytics[$key]['max']) $analytics[$key]['max'] = $val;
                $analytics[$key]['sum'] += $val;
                $analytics[$key]['count']++;
            }
        }
    }
}

foreach ($metricKeys as $key) {
    if ($analytics[$key]['count'] > 0) {
        $analytics[$key]['mean'] = round($analytics[$key]['sum'] / $analytics[$key]['count'], 1);
        $analytics[$key]['min'] = round($analytics[$key]['min'], 1);
        $analytics[$key]['max'] = round($analytics[$key]['max'], 1);
    } else {
        $analytics[$key]['mean'] = '--';
        $analytics[$key]['min'] = '--';
        $analytics[$key]['max'] = '--';
    }
}

// --- BUILD CHART DATA ARRAYS WITH INDEPENDENT TIMELINES ---
$chartData72h = ['labels' => [], 'm1Temp' => [], 'm1hi' => [], 'm1dew' => [], 'm1Hum' => [], '06pmAQI' => []];
$chartData8d = ['labels' => [], '02pressure' => []];

foreach ($timelineMap as $timeLabel => $metrics) {
    // 1. Build the 72-Hour Timeline Scale Separately
    if ($metrics['timestamp'] >= ($currentTimestamp - (72 * 60 * 60))) {
        if (isset($metrics['m1Temp']) || isset($metrics['m1Hum']) || isset($metrics['06pmAQI'])) {
            $chartData72h['labels'][] = $timeLabel;
            $temp = isset($metrics['m1Temp']) ? $metrics['m1Temp'] : null;
            $hiVal = isset($metrics['m1hi']) ? $metrics['m1hi'] : null;
            if ($temp !== null && $temp < 80.0) {
                $hiVal = null;
            }
            $chartData72h['m1Temp'][] = $temp !== null ? round($temp, 1) : null;
            $chartData72h['m1hi'][] = $hiVal !== null ? round($hiVal, 1) : null;
            $chartData72h['m1dew'][] = isset($metrics['m1dew']) ? round($metrics['m1dew'], 1) : null;
            $chartData72h['m1Hum'][] = isset($metrics['m1Hum']) ? round($metrics['m1Hum'], 1) : null;
            $chartData72h['06pmAQI'][] = isset($metrics['06pmAQI']) ? round($metrics['06pmAQI'], 1) : null;
        }
    }
    // 2. Build the 8-Day Barometric Scale Separately
    if (isset($metrics['02pressure'])) {
        $chartData8d['labels'][] = $timeLabel;
        $chartData8d['02pressure'][] = round($metrics['02pressure'], 2);
    }
}

// --- FIXED CURRENT VALUE ROUNDING RULES ---
$filteredTemp = array_filter($chartData72h['m1Temp'], 'is_numeric');
$filteredHum = array_filter($chartData72h['m1Hum'], 'is_numeric');
$filteredPress = array_filter($chartData8d['02pressure'], 'is_numeric');
$filteredAQI = array_filter($chartData72h['06pmAQI'], 'is_numeric');

$latestTemp = !empty($filteredTemp) ? round(end($filteredTemp), 1) : '--';
$latestHum = !empty($filteredHum) ? round(end($filteredHum), 1) : '--';
$latestPress = !empty($filteredPress) ? round(end($filteredPress), 2) : '--';
$latestAQI = !empty($filteredAQI) ? round(end($filteredAQI), 1) : 0;

function getAQIRibbonStyle($aqi) {
    if ($aqi === '--' || $aqi <= 0) return ['text' => 'No Data', 'bg' => '#f8f9fa', 'color' => '#495057'];
    if ($aqi <= 50) return ['text' => 'Good', 'bg' => '#00e400', 'color' => '#000'];
    if ($aqi <= 100) return ['text' => 'Moderate', 'bg' => '#ffff00', 'color' => '#000'];
    if ($aqi <= 150) return ['text' => 'Unhealthy for Sensitive','bg' => '#ff7e00', 'color' => '#fff'];
    if ($aqi <= 200) return ['text' => 'Unhealthy', 'bg' => '#ff0000', 'color' => '#fff'];
    return ['text' => 'Hazardous', 'bg' => '#7e0023', 'color' => '#fff'];
}
$aqiMetrics = getAQIRibbonStyle($latestAQI);

// ============================================================
// 60-DAY TEMPERATURE HIGH / LOW / MEAN (pivoted from "hourly")
// ============================================================
$fluxQuery60d = <<<FLUX
from(bucket: "daily")
  |> range(start: -60d)
  |> filter(fn: (r) => r["_field"] == "meantemp" or r["_field"] == "maxtemp" or r["_field"] == "mintemp")
  |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
  // FIX: Truncate times to day boundaries and filter out today's incomplete trailing data point
  |> truncateTimeColumn(unit: 1d)
  |> filter(fn: (r) => r["_time"] < now())
  |> yield(name: "mean")
FLUX;

$response60d = executeInfluxQuery($url, $fluxQuery60d, INFLUX_TOKEN);

// FIX: Completely clean, isolated timeline container initialized here
$timeline60d = [];

// FIX: Refactored parser dynamically targets the pivoted response format headers
function parseInfluxCsv60dPivoted($csvString, &$timelineMap) {
    $lines = explode("\n", trim($csvString));
    $headers = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        $fields = str_getcsv($line);
        
        // Dynamically find and store the accurate header layout row
        if (in_array('_time', $fields)) {
            $headers = $fields;
            continue;
        }
        
        if (!$headers || count($fields) < count($headers)) continue;
        $row = @array_combine($headers, $fields);
        if ($row === false) continue;
        
        $utcTime = $row['_time'] ?? null;
        if (!$utcTime) continue;
        
        $hasData = (isset($row['meantemp']) && $row['meantemp'] !== '') || 
                   (isset($row['maxtemp']) && $row['maxtemp'] !== '') || 
                   (isset($row['mintemp']) && $row['mintemp'] !== '');
        if (!$hasData) continue;
        
        try {
            $dateTime = new DateTime($utcTime);
            $dateTime->setTimezone(new DateTimeZone('America/New_York'));
            $localTimeLabel = $dateTime->format('m/d');
            
            $timelineMap[$localTimeLabel]['timestamp'] = $dateTime->getTimestamp();
            
            if (isset($row['meantemp']) && $row['meantemp'] !== '') {
                $timelineMap[$localTimeLabel]['meantemp'] = floatval($row['meantemp']);
            }
            if (isset($row['maxtemp']) && $row['maxtemp'] !== '') {
                $timelineMap[$localTimeLabel]['maxtemp'] = floatval($row['maxtemp']);
            }
            if (isset($row['mintemp']) && $row['mintemp'] !== '') {
                $timelineMap[$localTimeLabel]['mintemp'] = floatval($row['mintemp']);
            }
        } catch (Exception $e) {continue;}
}
}
// FIX: Parsed cleanly away from the global $timelineMap structure
parseInfluxCsv60dPivoted($response60d, $timeline60d);
ksort($timeline60d);
$chartData60d = ['labels' => [],'mean' => [],'high' => [],'low' => []];
foreach ($timeline60d as $timeLabel => $metrics) {
$chartData60d['labels'][] = $timeLabel;
$chartData60d['mean'][] = isset($metrics['meantemp']) ? round($metrics['meantemp'], 1) : null;
$chartData60d['high'][] = isset($metrics['maxtemp']) ? round($metrics['maxtemp'], 1) : null;
$chartData60d['low'][] = isset($metrics['mintemp']) ? round($metrics['mintemp'], 1) : null;
}
?>

