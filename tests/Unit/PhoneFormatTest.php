<?php

namespace Tests\Unit;

use App\Support\PhoneFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phone numbers grouped by pairs on the receipts (client request, 2026-11-10).
 */
class PhoneFormatTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'local, typed together' => ['0546533414', '05 46 53 34 14'],
            'local, typed with dots' => ['05.46.53.34.14', '05 46 53 34 14'],
            'local, already spaced' => ['07 11 22 33 44', '07 11 22 33 44'],
            'international' => ['+2250546533414', '+225 05 46 53 34 14'],
            'international, spaced' => ['+225 07 11 22 33 44', '+225 07 11 22 33 44'],
            '00225 prefix' => ['002250546533414', '+225 05 46 53 34 14'],
            'foreign: as typed' => ['+33 6 12 34 56 78', '+33 6 12 34 56 78'],
            'unusual length: as typed' => ['12345', '12345'],
            'trimmed' => ['  0102030405 ', '01 02 03 04 05'],
            'empty' => ['', ''],
            'null' => [null, ''],
        ];
    }

    #[DataProvider('numbers')]
    public function test_display(?string $typed, string $shown): void
    {
        $this->assertSame($shown, PhoneFormat::display($typed));
    }

    public function test_search_text_holds_every_way_of_typing_it(): void
    {
        $this->assertSame('05 46 53 34 14 0546533414', PhoneFormat::searchText('05 46 53 34 14'));
        $this->assertSame('0546533414 05 46 53 34 14', PhoneFormat::searchText('0546533414'));
        $this->assertSame('+2250546533414 +225 05 46 53 34 14 2250546533414', PhoneFormat::searchText('+2250546533414'));
        $this->assertSame('', PhoneFormat::searchText(''));
    }
}
