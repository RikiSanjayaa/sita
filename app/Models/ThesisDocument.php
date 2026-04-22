<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title_version_id',
        'defense_id',
        'revision_id',
        'source_workspace_document_id',
        'uploaded_by_user_id',
        'kind',
        'status',
        'version_no',
        'title',
        'notes',
        'storage_disk',
        'storage_path',
        'stored_file_name',
        'file_name',
        'mime_type',
        'file_size_kb',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'file_size_kb' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ThesisProject::class, 'project_id');
    }

    public function titleVersion(): BelongsTo
    {
        return $this->belongsTo(ThesisProjectTitle::class, 'title_version_id');
    }

    public function defense(): BelongsTo
    {
        return $this->belongsTo(ThesisDefense::class, 'defense_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ThesisRevision::class, 'revision_id');
    }

    public function sourceWorkspaceDocument(): BelongsTo
    {
        return $this->belongsTo(MentorshipDocument::class, 'source_workspace_document_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
