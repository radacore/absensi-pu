<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Audit — helper untuk mencatat aksi sensitif ke tabel audit_logs.
 *
 * Contoh:
 *   Audit::log('employee.reset_password',
 *       subject: $employee,
 *       label: $employee->name,
 *       description: "Reset password karyawan ke NIK",
 *       meta: ['nik' => $employee->nik]);
 */
class Audit
{
    public static function log(
        string $action,
        mixed $subject = null,
        ?string $label = null,
        ?string $description = null,
        array $meta = []
    ): void {
        $actor = Auth::guard('web')->user() ?? Auth::guard('employee')->user();
        $actorType = null;
        $actorId = null;
        $actorName = null;

        if ($actor) {
            $actorType = Auth::guard('web')->check() ? 'user' : 'employee';
            $actorId = $actor->id;
            $actorName = $actor->name ?? $actor->nama ?? $actor->email ?? null;
        }

        $subjectType = null;
        $subjectId = null;

        if ($subject !== null) {
            $subjectType = is_object($subject) ? class_basename($subject) : null;
            $subjectId = is_object($subject) && isset($subject->id) ? $subject->id : null;
        }

        AuditLog::create([
            'action' => $action,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $label,
            'description' => $description,
            'meta' => $meta ?: null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }
}
