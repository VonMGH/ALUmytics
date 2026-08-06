<?php

if (!function_exists('getCityDisplayName')) {
    function getCityDisplayName(string $cityCode): string
    {
        $cityCode = trim($cityCode);
        if ($cityCode === '') {
            return '';
        }

        static $cityNameCache = [];
        if (isset($cityNameCache[$cityCode])) {
            return $cityNameCache[$cityCode];
        }

        if (!preg_match('/^\d+$/', $cityCode)) {
            $cityNameCache[$cityCode] = $cityCode;
            return $cityCode;
        }

        $display = $cityCode;
        $url = 'https://psgc.gitlab.io/api/cities-municipalities/' . rawurlencode($cityCode) . '/';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
            ],
        ]);
        $json = @file_get_contents($url, false, $context);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['name'])) {
                $display = $data['name'];
            }
        }

        $cityNameCache[$cityCode] = $display;
        return $display;
    }
}
