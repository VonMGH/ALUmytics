<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../database.php';

$conn = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

/** ISO codes treated as not having state/province dropdowns (free-text instead). */
const NO_STATES_CODES = ['SG', 'HK'];

/** Minimum rows expected for a complete country list (triggers re-sync below this). */
const MIN_COUNTRY_COUNT = 200;

function httpGet(string $url, array $headers = []): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response) && $response !== '') {
            return $response;
        }

        return null;
    }

    $headerLines = $headers ? implode("\r\n", $headers) . "\r\n" : '';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'header' => $headerLines,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    return ($response !== false && $response !== '') ? $response : null;
}

function formatCountry(string $code, string $name): array
{
    return [
        'code' => $code,
        'name' => $name,
        'has_states' => !in_array($code, NO_STATES_CODES, true),
    ];
}

function fetchCountriesFromRestCountriesV5(string $apiKey): array
{
    $countries = [];
    $offset = 0;
    $limit = 100;

    do {
        $url = 'https://api.restcountries.com/countries/v5'
            . '?limit=' . $limit
            . '&offset=' . $offset
            . '&response_fields=names.common,codes.alpha_2';

        $response = httpGet($url, ['Authorization: Bearer ' . $apiKey]);
        if ($response === null) {
            break;
        }

        $payload = json_decode($response, true);
        $batch = $payload['data'] ?? [];
        if (!is_array($batch) || $batch === []) {
            break;
        }

        foreach ($batch as $country) {
            $code = $country['codes']['alpha_2'] ?? '';
            $name = $country['names']['common'] ?? '';
            if ($code !== '' && $name !== '') {
                $countries[] = formatCountry($code, $name);
            }
        }

        $offset += count($batch);
    } while (count($batch) === $limit);

    return $countries;
}

function fetchCountriesFromDr5hn(): array
{
    $url = 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/countries.json';
    $response = httpGet($url);
    if ($response === null) {
        return [];
    }

    $rows = json_decode($response, true);
    if (!is_array($rows)) {
        return [];
    }

    $countries = [];
    foreach ($rows as $row) {
        $code = strtoupper(trim($row['iso2'] ?? ''));
        $name = trim($row['name'] ?? '');
        if ($code !== '' && $name !== '') {
            $countries[] = formatCountry($code, $name);
        }
    }

    usort($countries, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return $countries;
}

function fetchCountriesFromRemote(): array
{
    $apiKey = getenv('RESTCOUNTRIES_API_KEY') ?: '';
    if ($apiKey !== '') {
        $countries = fetchCountriesFromRestCountriesV5($apiKey);
        if ($countries !== []) {
            return $countries;
        }
    }

    return fetchCountriesFromDr5hn();
}

function ensureCountriesTable(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS `countries` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `code` char(2) NOT NULL,
            `name` varchar(100) NOT NULL,
            `has_states` tinyint(1) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function getCountryCount(mysqli $conn): int
{
    $result = $conn->query('SELECT COUNT(*) AS cnt FROM countries');
    if ($result === false) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['cnt'] ?? 0);
}

function syncCountriesToDb(mysqli $conn, array $countries): bool
{
    if ($countries === []) {
        return false;
    }

    $conn->begin_transaction();

    try {
        $conn->query('DELETE FROM countries');
        $stmt = $conn->prepare(
            'INSERT INTO countries (code, name, has_states) VALUES (?, ?, ?)'
        );

        foreach ($countries as $country) {
            $hasStates = $country['has_states'] ? 1 : 0;
            $stmt->bind_param('ssi', $country['code'], $country['name'], $hasStates);
            $stmt->execute();
        }

        $stmt->close();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function ensureCountriesPopulated(mysqli $conn): bool
{
    ensureCountriesTable($conn);

    if (getCountryCount($conn) >= MIN_COUNTRY_COUNT) {
        return true;
    }

    $countries = fetchCountriesFromRemote();
    if ($countries === []) {
        return getCountryCount($conn) > 0;
    }

    return syncCountriesToDb($conn, $countries);
}

function getCountriesFromDb(mysqli $conn): array
{
    $result = $conn->query('SELECT code, name, has_states FROM countries ORDER BY name ASC');
    if ($result === false) {
        return [];
    }

    $countries = [];
    while ($row = $result->fetch_assoc()) {
        $countries[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'has_states' => (bool) $row['has_states'],
        ];
    }

    return $countries;
}

function prioritizePhilippines(array $countries): array
{
    $phIndex = array_search('PH', array_column($countries, 'code'), true);
    if ($phIndex !== false) {
        $ph = array_splice($countries, $phIndex, 1)[0];
        array_unshift($countries, $ph);
    }

    return $countries;
}

switch ($action) {
    case 'countries':
        if (!ensureCountriesPopulated($conn)) {
            echo json_encode([
                'success' => false,
                'error' => 'Unable to load countries. Please try again later.',
            ]);
            break;
        }

        $countries = prioritizePhilippines(getCountriesFromDb($conn));
        echo json_encode(['success' => true, 'data' => $countries]);
        break;

    case 'states':
        $countryCode = $_GET['country_code'] ?? '';
        if ($countryCode === '') {
            echo json_encode(['success' => false, 'error' => 'Country code required']);
            exit;
        }

        if ($countryCode === 'PH') {
            echo json_encode(['success' => true, 'data' => [], 'use_psgc' => true]);
        } else {
            echo json_encode(['success' => true, 'data' => [], 'use_psgc' => false]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
