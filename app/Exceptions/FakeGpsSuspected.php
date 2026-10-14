<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\DTOs\Courier\GpsAssessment;
use App\Enums\GpsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The fix window carried strong evidence of a simulated location, so the action
 * was refused. The courier app renders the reasons and offers a review request.
 */
final class FakeGpsSuspected extends RuntimeException
{
    /**
     * @param  list<array{code: string, label: string, detail: string, severity: string}>  $reasons
     */
    public function __construct(
        public readonly array $reasons,
        public readonly GpsAction $action,
        public readonly ?int $lockRequestId = null,
    ) {
        parent::__construct('Lokasi perangkat terindikasi tidak valid. Hubungi hub untuk meninjau.');
    }

    public static function fromAssessment(GpsAssessment $assessment, GpsAction $action, ?int $lockRequestId): self
    {
        return new self($assessment->reasons, $action, $lockRequestId);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'FAKE_GPS_SUSPECTED',
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'reasons' => $this->reasons,
            'lock_request_id' => $this->lockRequestId,
        ], 422);
    }
}
