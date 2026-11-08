<?php

return [
    // The faculty sample is the baseline approved for all programs. Add an
    // explicit null entry to withhold it while a program supplies its format.
    'standard' => [
        'label' => 'Format Fakultas Teknik',
        'word' => 'berkas-sidang.docx',
        'view' => 'sidang.print.packet',
    ],

    // Program slug => ['label' => ..., 'word' => ..., 'view' => ...] or null.
    // Paths are relative to resources/templates/sidang. Views live in the repo.
    'programs' => [],
];
