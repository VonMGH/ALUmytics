<?php
/**
 * LocationCoordinates Class
 * Manages real coordinates for locations found in the database
 * Replaces the old psgc_city_coords.csv system
 */

class LocationCoordinates {
    
    /**
     * Real coordinates mapping based on actual locations in the database
     * Format: [latitude, longitude]
     */
    private static $coordinates = [
        // Metro Manila Cities
        'Makati' => [14.5547, 121.0244],
        'Pasig' => [14.5764, 121.0851],
        'Quezon City' => [14.6760, 121.0437],
        'Taguig' => [14.5176, 121.0509],
        'Mandaluyong' => [14.5794, 121.0359],
        'Manila' => [14.5995, 120.9842],
        
        // Laguna Cities
        'San Pablo' => [14.0683, 121.3256],
        'Los Baños' => [14.1693, 121.2416],
        'Calamba' => [14.2118, 121.1653],
        'Santa Rosa' => [14.3129, 121.1115],
        
        // La Union Cities
        'Bacnotan' => [16.7194, 120.3681],
        'San Fernando' => [16.6162, 120.3170],
        'Bauang' => [16.5328, 120.3367],
        'Agoo' => [16.3194, 120.3681],
        
        // International Locations
        'Singapore' => [1.3521, 103.8198],
        'Hong Kong' => [22.3193, 114.1694],
        'Suwon' => [37.2636, 127.0286], // South Korea
        'Central' => [22.2828, 114.1579], // Hong Kong Central
    // Brunei locations
    'Brunei-Muara' => [4.9031, 114.9398], // Brunei-Muara District (Bandar Seri Begawan area)
    'Bandar Seri Begawan' => [4.9403, 114.9481],
        
        // Additional Philippine Cities (common locations)
        'Cebu City' => [10.3157, 123.8854],
        'Davao City' => [7.1907, 125.4553],
        'Iloilo City' => [10.7202, 122.5621],
        'Cagayan de Oro' => [8.4542, 124.6319],
        'Zamboanga City' => [6.9214, 122.0790],
        'Bacolod' => [10.6767, 122.9548],
        'General Santos' => [6.1164, 125.1716],
        'Butuan' => [8.9470, 125.5406],
        'Iligan' => [8.2280, 124.2452],
        'Cotabato City' => [7.2233, 124.2467],
        
        // NCR Additional Cities
        'Caloocan' => [14.6488, 120.9668],
        'Las Piñas' => [14.4377, 120.9758],
        'Marikina' => [14.6507, 121.1029],
        'Muntinlupa' => [14.3870, 121.0378],
        'Navotas' => [14.6564, 120.9463],
        'Parañaque' => [14.4793, 121.0198],
        'Pasay' => [14.5378, 120.9896],
        'Pateros' => [14.5436, 121.0710],
        'San Juan' => [14.6019, 121.0355],
        'Valenzuela' => [14.7000, 120.9829],
        'Malabon' => [14.6575, 120.9668],
        
        // Other Major Philippine Cities
        'Baguio' => [16.4023, 120.5960],
        'Angeles' => [15.1452, 120.5859],
        'Olongapo' => [14.8294, 120.2824],
        'Cabanatuan' => [15.4856, 120.9645],
        'Tarlac City' => [15.4754, 120.5969],
        'Dagupan' => [16.0430, 120.3341],
        'Laoag' => [18.1967, 120.5934],
        'Vigan' => [17.5747, 120.3869],
        'Tuguegarao' => [17.6132, 121.7270],
        'Legazpi' => [13.1391, 123.7436],
        'Naga' => [13.6218, 123.1948],
        'Sorsogon City' => [12.9736, 123.9933],
        'Masbate City' => [12.3693, 123.6178],
        'Catbalogan' => [11.7750, 124.8814],
        'Tacloban' => [11.2447, 125.0048],
        'Ormoc' => [11.0059, 124.6074],
        'Maasin' => [10.1297, 125.0342],
        'Borongan' => [11.6333, 125.4333],
        'Catarman' => [12.5000, 124.6333],
        'Dumaguete' => [9.3063, 123.3018],
        'Tagbilaran' => [9.6496, 123.8566],
        'Lapu-Lapu' => [10.3103, 123.9494],
        'Mandaue' => [10.3238, 123.9224],
        'Toledo' => [10.3773, 123.6533],
        'Danao' => [10.5204, 124.0258],
        'Talisay' => [10.2449, 123.8493],
        'Carcar' => [10.1073, 123.6364],
        
        // Mindanao Cities
        'Butuan' => [8.9470, 125.5406],
        'Surigao City' => [9.7914, 125.5072],
        'Tandag' => [9.0708, 126.1956],
        'Bislig' => [8.2158, 126.3211],
        'Malaybalay' => [8.1537, 125.1178],
        'Valencia' => [7.9064, 125.0941],
        'Ozamiz' => [8.1500, 123.8444],
        'Tangub' => [8.0667, 123.7500],
        'Oroquieta' => [8.4833, 123.8000],
        'Dipolog' => [8.5833, 123.3417],
        'Dapitan' => [8.6594, 123.4206],
        'Pagadian' => [7.8308, 123.4350],
        'Ipil' => [7.7833, 122.5833],
        'Molave' => [8.0833, 123.4833],
        'Kidapawan' => [7.0108, 125.0889],
        'Koronadal' => [6.5000, 124.8500],
        'Tacurong' => [6.6906, 124.6769],
        'Isulan' => [6.6333, 124.6000],
        'Marawi' => [8.0000, 124.2833],
        'Jolo' => [6.0542, 121.0036],
        'Bongao' => [5.0306, 119.7722],
    ];
    
    /**
     * Province to region mapping for fallback coordinates (Philippines)
     */
    private static $provinceCoordinates = [
        // Luzon Regions
        'Metro Manila' => [14.5995, 120.9842],
        'Rizal' => [14.6037, 121.3084],
        'Cavite' => [14.4791, 120.8970],
        'Laguna' => [14.2691, 121.3618],
        'Batangas' => [13.7565, 121.0583],
        'Quezon' => [13.9414, 121.6234],
        'Bulacan' => [14.7942, 120.8794],
        'Pampanga' => [15.0794, 120.6200],
        'Bataan' => [14.6417, 120.4681],
        'Zambales' => [15.5093, 119.9712],
        'Tarlac' => [15.4754, 120.5969],
        'Nueva Ecija' => [15.5784, 121.1113],
        'Aurora' => [15.7495, 121.5410],
        'La Union' => [16.6162, 120.3170],
        'Pangasinan' => [15.8980, 120.2792],
        'Ilocos Norte' => [18.1967, 120.5934],
        'Ilocos Sur' => [17.5747, 120.3869],
        'Abra' => [17.5333, 120.6167],
        'Benguet' => [16.4023, 120.5960],
        'Mountain Province' => [17.1000, 121.1000],
        'Ifugao' => [16.9500, 121.1500],
        'Kalinga' => [17.6000, 121.4667],
        'Apayao' => [18.0167, 121.1500],
        'Cagayan' => [17.6132, 121.7270],
        'Isabela' => [16.9754, 121.8046],
        'Nueva Vizcaya' => [16.3778, 121.0889],
        'Quirino' => [16.2500, 121.5500],
        
        // Visayas Regions
        'Albay' => [13.1391, 123.7436],
        'Camarines Norte' => [14.1019, 122.9540],
        'Camarines Sur' => [13.6218, 123.1948],
        'Catanduanes' => [13.9167, 124.2667],
        'Masbate' => [12.3693, 123.6178],
        'Sorsogon' => [12.9736, 123.9933],
        'Aklan' => [11.5564, 122.0119],
        'Antique' => [11.2333, 122.1500],
        'Capiz' => [11.4333, 122.6000],
        'Guimaras' => [10.5667, 122.5833],
        'Iloilo' => [10.7202, 122.5621],
        'Negros Occidental' => [10.6767, 122.9548],
        'Bohol' => [9.6496, 123.8566],
        'Cebu' => [10.3157, 123.8854],
        'Negros Oriental' => [9.3063, 123.3018],
        'Siquijor' => [9.2167, 123.5167],
        'Eastern Samar' => [11.6333, 125.4333],
        'Leyte' => [11.2447, 125.0048],
        'Northern Samar' => [12.5000, 124.6333],
        'Samar' => [11.7750, 124.8814],
        'Southern Leyte' => [10.1297, 125.0342],
        'Biliran' => [11.4667, 124.4833],
        
        // Mindanao Regions
        'Zamboanga del Norte' => [8.5833, 123.3417],
        'Zamboanga del Sur' => [7.8308, 123.4350],
        'Zamboanga Sibugay' => [7.7833, 122.5833],
        'Bukidnon' => [8.1537, 125.1178],
        'Camiguin' => [9.1667, 124.7333],
        'Lanao del Norte' => [8.0000, 124.2833],
        'Misamis Occidental' => [8.1500, 123.8444],
        'Misamis Oriental' => [8.4542, 124.6319],
        'Agusan del Norte' => [8.9470, 125.5406],
        'Agusan del Sur' => [8.5167, 125.9833],
        'Dinagat Islands' => [10.1167, 126.3833],
        'Surigao del Norte' => [9.7914, 125.5072],
        'Surigao del Sur' => [9.0708, 126.1956],
        'Davao de Oro' => [7.6667, 125.9167],
        'Davao del Norte' => [7.4167, 125.6500],
        'Davao del Sur' => [6.7500, 125.2500],
        'Davao Occidental' => [6.4167, 125.8333],
        'Davao Oriental' => [7.0000, 126.5000],
        'Cotabato' => [7.2233, 124.2467],
        'Sarangani' => [5.9333, 125.1333],
        'South Cotabato' => [6.1164, 125.1716],
        'Sultan Kudarat' => [6.5000, 124.8500],
        'Lanao del Sur' => [7.8333, 124.2500],
        'Maguindanao' => [6.9667, 124.4000],
        'Basilan' => [6.5000, 121.9833],
        'Sulu' => [6.0542, 121.0036],
        'Tawi-Tawi' => [5.0306, 119.7722],
        
    ];

    /**
     * Country-level coordinates for international mobility
     */
    private static $countryCoordinates = [
        'Singapore' => [1.3521, 103.8198],
        'Hong Kong' => [22.3193, 114.1694],
        'South Korea' => [36.5, 127.8],
        'Japan' => [36.2048, 138.2529],
        'United States' => [39.8283, -98.5795],
        'Canada' => [56.1304, -106.3468],
        'Australia' => [-25.2744, 133.7751],
        'United Kingdom' => [55.3781, -3.4360],
        'Malaysia' => [4.2105, 101.9758],
        'Thailand' => [15.8700, 100.9925],
        'Vietnam' => [14.0583, 108.2772],
        'Indonesia' => [-0.7893, 113.9213],
        'Germany' => [51.1657, 10.4515],
        'France' => [46.2276, 2.2137],
        'Netherlands' => [52.1326, 5.2913],
        'Switzerland' => [46.8182, 8.2275],
        'New Zealand' => [-40.9006, 174.8860],
        'United Arab Emirates' => [23.4241, 53.8478],
        'UAE' => [23.4241, 53.8478],
        'Qatar' => [25.3548, 51.1839],
        'Brunei' => [4.5353, 114.7277],
        'Brunei-Muara' => [4.9031, 114.9398],
        'China' => [35.8617, 104.1954],
        'Taiwan' => [23.6978, 120.9605],
    ];

    // Runtime cache for dynamic country map
    private static $countryMapCache = null;

    /**
     * Load country coordinates via REST Countries with persistent file cache
     * Returns [ 'Country Name' => [lat, lng], ... ]
     */
    private static function getCountryCoordinatesMap() {
        if (is_array(self::$countryMapCache)) return self::$countryMapCache;

        $map = [];
        // Cache directory: db/cache
        $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'countries.json';
        $ttl = 30 * 24 * 60 * 60; // 30 days

        $cachedData = null; $fresh = false;
        if (is_file($cacheFile)) {
            $mtime = @filemtime($cacheFile);
            $fresh = $mtime && (time() - $mtime < $ttl);
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $cachedData = $decoded;
            }
            if ($fresh && is_array($cachedData)) {
                self::$countryMapCache = $cachedData;
                return self::$countryMapCache;
            }
        }

        // Fetch from REST Countries with short timeout; fields limited
        $url = 'https://restcountries.com/v3.1/all?fields=name,latlng';
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 3],
            'https' => ['method' => 'GET', 'timeout' => 3],
        ]);
        try {
            $resp = @file_get_contents($url, false, $context);
            if ($resp) {
                $json = json_decode($resp, true);
                if (is_array($json)) {
                    foreach ($json as $c) {
                        $name = $c['name']['common'] ?? null;
                        $latlng = $c['latlng'] ?? null;
                        if ($name && is_array($latlng) && count($latlng) >= 2) {
                            $lat = (float)$latlng[0];
                            $lng = (float)$latlng[1];
                            $map[$name] = [$lat, $lng];
                        }
                    }
                    if (!empty($map)) {
                        // Persist and return
                        @file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        self::$countryMapCache = $map;
                        return self::$countryMapCache;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore network errors */ }

        // Fallback to stale cache, then built-in static list
        if (is_array($cachedData)) { self::$countryMapCache = $cachedData; return self::$countryMapCache; }
        self::$countryMapCache = self::$countryCoordinates; // static fallback
        return self::$countryMapCache;
    }

    /**
     * Aliases mapping to canonical country names
     */
    private static $countryAliases = [
        "People's Republic of China" => 'China',
        'PRC' => 'China',
        'Mainland China' => 'China',
        'Korea, South' => 'South Korea',
        'Republic of Korea' => 'South Korea',
        'U.S.A.' => 'United States',
        'USA' => 'United States',
        'UAE' => 'United Arab Emirates',
        'United Arab Emirates (UAE)' => 'United Arab Emirates',
    ];
    
    /**
     * Get coordinates for a specific city and province/country
     */
    public static function getCoordinates($city, $province = null) {
        // Normalize the city name
        $city = $city ? trim($city) : null;
        $province = $province ? trim($province) : null;
        
        // If no city provided, use province coordinates directly
        if (!$city && $province) {
            $provCoords = self::findProvinceCoordinates($province);
            if ($provCoords !== null) return $provCoords;
        }
        
        // First try exact city match
        if ($city && isset(self::$coordinates[$city])) {
            return self::$coordinates[$city];
        }
        
        // Try province/country fallback
        if ($province) {
            $provCoords = self::findProvinceCoordinates($province);
            if ($provCoords !== null) return $provCoords;
        }
        
        // Try partial city matching
        if ($city) {
            foreach (self::$coordinates as $key => $coords) {
                if (stripos($key, $city) !== false || stripos($city, $key) !== false) {
                    return $coords;
                }
            }
        }
        
        // Default to Philippines center if nothing found
        return [12.8797, 121.7740];
    }

    /**
     * Case-insensitive province lookup for coordinates
     */
    private static function findProvinceCoordinates($provinceName) {
        // Merge province and country maps
        $dynamicCountries = self::getCountryCoordinatesMap();
        $maps = [self::$provinceCoordinates, self::$countryCoordinates, $dynamicCountries];
        // Apply alias for country names
        if (isset(self::$countryAliases[$provinceName])) {
            $provinceName = self::$countryAliases[$provinceName];
        }
        // Exact match first
        foreach ($maps as $map) {
            if (isset($map[$provinceName])) {
                return $map[$provinceName];
            }
        }
        // Case-insensitive match across both maps
        $needle = self::normalizeName($provinceName);
        foreach ($maps as $map) {
            foreach ($map as $name => $coords) {
                if (self::normalizeName($name) === $needle) {
                    return $coords;
                }
            }
        }
        // Partial match attempt (normalized contains)
        foreach ($maps as $map) {
            foreach ($map as $name => $coords) {
                if (strpos(self::normalizeName($name), $needle) !== false || strpos($needle, self::normalizeName($name)) !== false) {
                    return $coords;
                }
            }
        }
        return null;
    }

    private static function normalizeName($str) {
        $s = mb_strtolower(trim($str));
        // Remove punctuation and excess spaces
        $s = preg_replace('/[^a-z0-9\s]/u', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }
    
    /**
     * Get all unique locations from the database with their coordinates (grouped by province)
     */
    public static function getProvinceDataFromDatabase($conn) {
        $locations = [];
        
        // Query to get all unique provinces from company_address and employment tables
        $sql = "
            SELECT DISTINCT 
                COALESCE(ca.company_province, e.company_province) as province,
                COUNT(*) as count
            FROM employment e
            LEFT JOIN company_address ca ON e.user_id = ca.user_id
            WHERE COALESCE(ca.company_province, e.company_province) IS NOT NULL
            GROUP BY COALESCE(ca.company_province, e.company_province)
            ORDER BY count DESC
        ";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $province = $row['province'];
                $count = intval($row['count']);
                
                $coordinates = self::getCoordinates(null, $province);
                
                $locations[] = [
                    'province' => $province,
                    'count' => $count,
                    'lat' => $coordinates[0],
                    'lng' => $coordinates[1],
                    'label' => $province
                ];
            }
        }
        
        return $locations;
    }
    
    /**
     * Get all unique locations from the database with their coordinates
     */
    public static function getLocationDataFromDatabase($conn) {
        $locations = [];
        
        // Query to get all unique locations from company_address and employment tables
        $sql = "
            SELECT DISTINCT 
                COALESCE(ca.company_city, e.company_city) as city,
                COALESCE(ca.company_province, e.company_province) as province,
                COUNT(*) as count
            FROM employment e
            LEFT JOIN company_address ca ON e.user_id = ca.user_id
            WHERE COALESCE(ca.company_city, e.company_city) IS NOT NULL
            AND COALESCE(ca.company_province, e.company_province) IS NOT NULL
            GROUP BY 
                COALESCE(ca.company_city, e.company_city),
                COALESCE(ca.company_province, e.company_province)
            ORDER BY count DESC
        ";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $city = $row['city'];
                $province = $row['province'];
                $count = intval($row['count']);
                
                $coordinates = self::getCoordinates($city, $province);
                
                $locations[] = [
                    'city' => $city,
                    'province' => $province,
                    'count' => $count,
                    'lat' => $coordinates[0],
                    'lng' => $coordinates[1],
                    'label' => $city . ', ' . $province
                ];
            }
        }
        
        return $locations;
    }
    
    /**
     * Get heatmap points for Leaflet
     */
    public static function getHeatmapPoints($conn, $whereConditions = []) {
        $locations = self::getLocationDataFromDatabase($conn);
        $heatmapPoints = [];
        
        foreach ($locations as $location) {
            // Add intensity weight based on count
            $intensity = min($location['count'] / 10, 1.0); // Normalize to 0-1
            $heatmapPoints[] = [
                $location['lat'], 
                $location['lng'], 
                $intensity
            ];
        }
        
        return $heatmapPoints;
    }
    
    /**
     * Get marker points with counts for Leaflet
     */
    public static function getMarkerPoints($conn, $whereConditions = []) {
        $locations = self::getLocationDataFromDatabase($conn);
        $markerPoints = [];
        
        foreach ($locations as $location) {
            $markerPoints[] = [
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'count' => $location['count'],
                'label' => $location['label'],
                'city' => $location['city'],
                'province' => $location['province']
            ];
        }
        
        return $markerPoints;
    }
    
    /**
     * Add a new location coordinate (for admin use)
     */
    public static function addCoordinate($city, $lat, $lng) {
        // This would typically save to a database table for dynamic updates
        // For now, it's a static array but can be extended
        self::$coordinates[$city] = [(float)$lat, (float)$lng];
    }
    
    /**
     * Validate if coordinates are within reasonable bounds
     */
    public static function validateCoordinates($lat, $lng) {
        return ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
    }
    
    /**
     * Get country code for international locations
     */
    public static function getCountryCode($province) {
        $countryCodes = [
            'Singapore' => 'SG',
            'Hong Kong' => 'HK',
            'South Korea' => 'KR',
            'Brunei' => 'BN',
            'Malaysia' => 'MY',
            'Thailand' => 'TH',
            'Japan' => 'JP',
            'United States' => 'US',
            'Canada' => 'CA',
            'Australia' => 'AU',
            'United Kingdom' => 'GB',
        ];
        
        return $countryCodes[$province] ?? 'PH'; // Default to Philippines
    }
}
?>
