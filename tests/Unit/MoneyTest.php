<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_xof_without_decimals(): void
    {
        $this->assertSame("15\u{202F}000\u{00A0}FCFA", Money::xof(15000));
        $this->assertSame("0\u{00A0}FCFA", Money::xof(0));
        $this->assertSame("1\u{202F}250\u{202F}000\u{00A0}FCFA", Money::xof(1250000));
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::xof(-1);
    }
}
