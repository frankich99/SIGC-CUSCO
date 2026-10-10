<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class AttendanceQrService
{
    /**
     * Build a short-lived, signed attendance URL and a standards-compliant QR SVG.
     *
     * @return array{url: string, svg: string, expires_at: string}
     */
    public function forSession(Course $course, int $session): array
    {
        $expiresAt = now()->addMinutes(5);
        $url = URL::temporarySignedRoute(
            'attendance.scan',
            $expiresAt,
            [
                'course' => $course,
                'session' => $session,
            ],
        );

        return [
            'url' => $url,
            'svg' => CertificateService::generateQrSvg($url, 300),
            'expires_at' => Carbon::parse($expiresAt)->toIso8601String(),
        ];
    }
}
