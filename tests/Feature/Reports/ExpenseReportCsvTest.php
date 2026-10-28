<?php

namespace Tests\Feature\Reports;

use App\Services\ExpenseReportCsv;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseReportCsvTest extends TestCase
{
    public function test_negative_amounts_stay_numeric(): void
    {
        $csv = app(ExpenseReportCsv::class)->build([
            'totals' => [[
                'currency' => 'USD',
                'maintenance' => -12.0,
                'fuel' => 0.0,
                'total' => -12.0,
                'maintenance_entries' => 1,
                'fuel_entries' => 0,
            ]],
            'vehicles' => [],
            'monthly' => [],
            'billing' => [],
        ], Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertStringContainsString("USD,-12.00,0.00,-12.00,1,0\n", $csv);
    }
}
