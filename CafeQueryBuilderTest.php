<?php

namespace Tests;

use App\CafeQueryBuilder;
use PHPUnit\Framework\TestCase;

class CafeQueryBuilderTest extends TestCase
{
    public function testBuildQueryWithoutFilters(): void
    {
        $result = CafeQueryBuilder::build([]);

        $this->assertStringContainsString('SELECT cafe_shop.* FROM cafe_shop INNER JOIN label ON cafe_shop.id = label.cafe_id WHERE 1 = 1', $result['sql']);
        $this->assertStringContainsString('ORDER BY cafe_shop.id ASC', $result['sql']);
        $this->assertSame('', $result['types']);
        $this->assertSame([], $result['params']);
    }

    public function testBuildQueryWithSocketAndRating(): void
    {
        $result = CafeQueryBuilder::build([
            'socket' => '1',
            'rating' => '4.5',
        ]);

        $this->assertStringContainsString('label.`插座` = ?', $result['sql']);
        $this->assertStringContainsString('cafe_shop.`rating` >= ?', $result['sql']);
        $this->assertSame('id', $result['types'] ?? '');
        $this->assertSame([1, 4.5], $result['params']);
    }

    public function testBuildQueryWithPriceGroupsAndDistance(): void
    {
        $result = CafeQueryBuilder::build([
            'price' => ['1', '3'],
            'distance' => '1.2',
        ]);

        $this->assertStringContainsString('cafe_shop.`min_consumption` BETWEEN 1 AND 50', $result['sql']);
        $this->assertStringContainsString('cafe_shop.`min_consumption` BETWEEN 101 AND 150', $result['sql']);
        $this->assertStringContainsString('OR', $result['sql']);
        $this->assertStringContainsString('cafe_shop.`distance_meters` <= ?', $result['sql']);
        $this->assertSame('i', $result['types']);
        $this->assertSame([1200], $result['params']);
    }
}
