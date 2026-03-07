<?php

use Illuminate\Support\Facades\Schema;

it('creates the thesis project aggregate tables', function () {
    expect(Schema::hasTable('thesis_projects'))->toBeTrue()
        ->and(Schema::hasTable('thesis_project_titles'))->toBeTrue()
        ->and(Schema::hasTable('thesis_supervisor_assignments'))->toBeTrue()
        ->and(Schema::hasTable('thesis_defenses'))->toBeTrue()
        ->and(Schema::hasTable('thesis_defense_examiners'))->toBeTrue()
        ->and(Schema::hasTable('thesis_revisions'))->toBeTrue()
        ->and(Schema::hasTable('thesis_documents'))->toBeTrue()
        ->and(Schema::hasTable('thesis_project_events'))->toBeTrue();
});

it('creates the expected key columns for thesis project tables', function () {
    expect(Schema::hasColumns('thesis_projects', [
        'student_user_id',
        'program_studi_id',
        'phase',
        'state',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('program_studis', [
            'concentrations',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('mahasiswa_profiles', [
            'concentration',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('dosen_profiles', [
            'concentration',
            'supervision_quota',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_project_titles', [
            'project_id',
            'version_no',
            'title_id',
            'status',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_supervisor_assignments', [
            'project_id',
            'lecturer_user_id',
            'role',
            'status',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_defenses', [
            'project_id',
            'title_version_id',
            'type',
            'attempt_no',
            'status',
            'result',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_defense_examiners', [
            'defense_id',
            'lecturer_user_id',
            'order_no',
            'decision',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_revisions', [
            'project_id',
            'defense_id',
            'status',
            'due_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_documents', [
            'project_id',
            'kind',
            'status',
            'storage_path',
            'stored_file_name',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('thesis_project_events', [
            'project_id',
            'event_type',
            'label',
            'occurred_at',
        ]))->toBeTrue();
});
