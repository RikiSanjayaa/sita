<?php

use App\Support\PhoneNumber;

test('phone numbers are normalized to the canonical 08 form', function (?string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'already canonical' => ['081234567890', '081234567890'],
    'spaces and dashes' => ['0812 3456-7890', '081234567890'],
    'dots and parentheses' => ['(0812).3456.7890', '081234567890'],
    'plus 62 prefix' => ['+62 812-3456-7890', '081234567890'],
    '62 prefix without plus' => ['6281234567890', '081234567890'],
    'blank becomes null' => ['   ', null],
    'null becomes null' => [null, null],
    'invalid input stays trimmed for the validator' => ['  12345  ', '12345'],
]);

test('phone number validity follows the canonical format', function (string $input, bool $valid) {
    expect(PhoneNumber::isValid($input))->toBe($valid);
})->with([
    'ten digits' => ['0812345678', true],
    'fifteen digits' => ['081234567890123', true],
    'plus 62 with spaces' => ['+62 812 3456 7890', true],
    'nine digits' => ['081234567', false],
    'sixteen digits' => ['0812345678901234', false],
    'landline prefix' => ['0215551234', false],
    'contains letters' => ['08123abc4567', false],
    'empty' => ['', false],
]);
