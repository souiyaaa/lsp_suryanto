<?php

namespace Tests\Unit\Requests;

use App\Enums\ReportView;
use App\Http\Requests\Admin\ReportRequest;
use App\Http\Requests\Admin\VerificationRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AdminRequestsTest extends TestCase
{
    private function errors(string $class, array $data): array
    {
        return Validator::make($data, (new $class)->rules())->errors()->toArray();
    }

    public function test_verification_requires_the_ktp_checkbox_to_be_ticked(): void
    {
        $this->assertSame([], $this->errors(VerificationRequest::class, ['is_ktp_checked' => '1']));
        $this->assertSame([], $this->errors(VerificationRequest::class, ['is_ktp_checked' => 'on']));
        $this->assertSame([], $this->errors(VerificationRequest::class, ['is_ktp_checked' => true]));

        $this->assertArrayHasKey('is_ktp_checked', $this->errors(VerificationRequest::class, []));
        $this->assertArrayHasKey('is_ktp_checked', $this->errors(VerificationRequest::class, ['is_ktp_checked' => '0']));
        $this->assertArrayHasKey('is_ktp_checked', $this->errors(VerificationRequest::class, ['is_ktp_checked' => 'no']));
    }

    public function test_report_view_enum_values(): void
    {
        $this->assertSame(['per_month', 'per_day', 'per_garage_type', 'per_penyewa'], ReportView::values());
    }

    public function test_report_request_accepts_empty_and_valid_input(): void
    {
        $this->assertSame([], $this->errors(ReportRequest::class, []));

        foreach (ReportView::values() as $view) {
            $this->assertSame([], $this->errors(ReportRequest::class, ['view' => $view, 'year' => 2026, 'month' => 11]));
        }
    }

    public function test_report_request_rejects_bad_view_year_and_month(): void
    {
        $this->assertArrayHasKey('view', $this->errors(ReportRequest::class, ['view' => 'weekly']));

        $this->assertArrayHasKey('year', $this->errors(ReportRequest::class, ['year' => 1999]));
        $this->assertArrayHasKey('year', $this->errors(ReportRequest::class, ['year' => 2101]));
        $this->assertArrayHasKey('year', $this->errors(ReportRequest::class, ['year' => 'abc']));
        $this->assertSame([], $this->errors(ReportRequest::class, ['year' => 2000]));
        $this->assertSame([], $this->errors(ReportRequest::class, ['year' => 2100]));

        $this->assertArrayHasKey('month', $this->errors(ReportRequest::class, ['month' => 0]));
        $this->assertArrayHasKey('month', $this->errors(ReportRequest::class, ['month' => 13]));
        $this->assertSame([], $this->errors(ReportRequest::class, ['month' => 1]));
        $this->assertSame([], $this->errors(ReportRequest::class, ['month' => 12]));
    }
}
