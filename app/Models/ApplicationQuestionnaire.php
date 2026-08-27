<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_application_id', 'schema_payload', 'answer_payload', 'status', 'requested_at', 'answered_at'])]
class ApplicationQuestionnaire extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'schema_payload' => 'encrypted:array', 'answer_payload' => 'encrypted:array',
            'requested_at' => 'datetime', 'answered_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
