<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Registrar un evento de auditoría.
     */
    public function log(
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $user = auth()->user();
        $request = request();

        return AuditLog::create([
            'business_id' => $user?->business_id,
            'user_id' => $user?->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
