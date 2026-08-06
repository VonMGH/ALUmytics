<?php
header('Content-Type: application/json');
require_once '../db/Database.php';

$action = $_GET['action'] ?? '';
$countriesApiUrl = 'https://restcountries.com/v3.1/all?fields=name,cca2';

switch($action) {
    case 'countries':
        // Fetch countries from REST Countries API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $countriesApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $countries = json_decode($response, true);
            // Format the response to match our needs
            $formattedCountries = array_map(function($country) {
                return [
                    'code' => $country['cca2'],
                    'name' => $country['name']['common'],
                    // Most countries have administrative divisions
                    'has_states' => $country['cca2'] !== 'SG' && $country['cca2'] !== 'HK'
                ];
            }, $countries);
            
            // Sort by name
            usort($formattedCountries, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            // Move Philippines to top if exists
            $phIndex = array_search('PH', array_column($formattedCountries, 'code'));
            if ($phIndex !== false) {
                $ph = array_splice($formattedCountries, $phIndex, 1)[0];
                array_unshift($formattedCountries, $ph);
            }
            
            echo json_encode(['success' => true, 'data' => $formattedCountries]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch countries']);
        }
        break;
        
    case 'regions':
        $countryCode = $_GET['country_code'] ?? '';
        if (empty($countryCode)) {
            echo json_encode(['success' => false, 'error' => 'Country code required']);
            exit;
        }

        if ($countryCode === 'PH') {
            // For Philippines, indicate to use PSGC API
            echo json_encode(['success' => true, 'use_psgc' => true]);
            exit;
        }

        // For other countries, we'll implement state/region data later
        // For now, return empty array to allow free text input
        echo json_encode([
            'success' => true,
            'use_psgc' => false,
            'data' => []
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}