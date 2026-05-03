<?php

namespace App;

class CafeQueryBuilder
{
    public static function build(array $query): array
    {
        $hasSocket    = isset($query['socket']) ? 1 : 0;
        $hasNoLimit   = isset($query['no_limit']) ? 1 : 0;
        $hasParking   = isset($query['parking']) ? 1 : 0;
        $hasWiFi      = isset($query['wifi']) ? 1 : 0;
        $hasOutdoor   = isset($query['outdoor']) ? 1 : 0;
        $hasDessert   = isset($query['dessert']) ? 1 : 0;
        $hasToilet    = isset($query['toilet']) ? 1 : 0;
        $noMinConsume = isset($query['no_min_consume']) ? 1 : 0;
        $hasSeat      = isset($query['seats']) ? 1 : 0;

        $selectedRating = isset($query['rating']) ? (float)$query['rating'] : 0;
        $selectedDistance = isset($query['distance']) ? (float)$query['distance'] : 0;
        $selectedPriceGroups = self::normalizePriceGroups($query['price'] ?? []);

        $sql = "SELECT cafe_shop.* FROM cafe_shop INNER JOIN label ON cafe_shop.id = label.cafe_id WHERE 1 = 1";
        $params = [];
        $types = "";

        if ($hasSocket) { $sql .= " AND label.`插座` = ?"; $params[] = 1; $types .= "i"; }
        if ($hasNoLimit) { $sql .= " AND label.`不限時` = ?"; $params[] = 1; $types .= "i"; }
        if ($hasParking) { $sql .= " AND label.`停車位` = ?"; $params[] = 1; $types .= "i"; }
        if ($hasWiFi) { $sql .= " AND label.`wifi` = ?"; $params[] = 1; $types .= "i"; }
        if ($hasOutdoor) { $sql .= " AND label.`戶外座位` = ?"; $params[] = 1; $types .= "i"; }
        if ($hasDessert) { $sql .= " AND label.`甜點` = ?"; $params[] = 1; $types .= "i"; }
        if ($hasToilet) { $sql .= " AND label.`廁所` = ?"; $params[] = 1; $types .= "i"; }
        if ($noMinConsume) { $sql .= " AND cafe_shop.`min_consumption` = ?"; $params[] = 0; $types .= "i"; }
        if ($hasSeat) { $sql .= " AND label.`室內座位` = ?"; $params[] = 1; $types .= "i"; }

        if ($selectedRating > 0) { $sql .= " AND cafe_shop.`rating` >= ?"; $params[] = $selectedRating; $types .= "d"; }

        $priceClauses = [];
        foreach ($selectedPriceGroups as $group) {
            switch ((string)$group) {
                case '1':
                    $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 1 AND 50";
                    break;
                case '2':
                    $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 51 AND 100";
                    break;
                case '3':
                    $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 101 AND 150";
                    break;
                case '4':
                    $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 151 AND 200";
                    break;
                case '5':
                    $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 201 AND 500";
                    break;
            }
        }

        if (!empty($priceClauses)) {
            $sql .= " AND (" . implode(" OR ", $priceClauses) . ")";
        }

        if ($selectedDistance > 0) {
            $sql .= " AND cafe_shop.`distance_meters` <= ?";
            $params[] = (int)round($selectedDistance * 1000);
            $types .= "i";
        }

        $sql .= " ORDER BY cafe_shop.id ASC";

        return [
            'sql' => $sql,
            'types' => $types,
            'params' => $params,
        ];
    }

    private static function normalizePriceGroups($priceGroups): array
    {
        if (is_array($priceGroups)) {
            return array_values($priceGroups);
        }

        if ($priceGroups === null || $priceGroups === '') {
            return [];
        }

        return [(string)$priceGroups];
    }
}
