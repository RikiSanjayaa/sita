<?php

/*
 * Guards case-insensitive search. Production runs PostgreSQL, where a plain
 * `where(..., 'like', ...)` is case-sensitive, while SQLite (local and CI) is
 * not. Use whereLike() / orWhereLike() so the query compiles to ILIKE on
 * PostgreSQL and LIKE elsewhere.
 */

const LIKE_OPERATOR_PATTERN = '/->\s*(?:or)?where\s*\([^;]*?[\'"]like[\'"]/i';

it('uses whereLike instead of a like operator in app code', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! preg_match('/\.php$/', $file->getFilename())) {
            continue;
        }

        if (preg_match(LIKE_OPERATOR_PATTERN, file_get_contents($file->getPathname()))) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([], "where(..., 'like', ...) is case-sensitive on PostgreSQL; use whereLike() or orWhereLike() instead");
});

it('detects a like operator in a where clause', function (string $snippet): void {
    expect(preg_match(LIKE_OPERATOR_PATTERN, $snippet))->toBe(1);
})->with([
    "\$query->where('name', 'like', \"%{\$search}%\");",
    "\$query->orWhere('email', \"like\", \$like);",
    "\$query\n    ->where(\n        'name',\n        'like',\n        \$like,\n    );",
]);
