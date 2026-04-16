<?php

use App\Support\CellValueFormatter;

it('formats json strings with pretty printing', function () {
    $r = CellValueFormatter::format('{"a":1,"b":"x"}');

    expect($r['kind'])->toBe('json')
        ->and($r['plain'])->toContain("\n")
        ->and($r['plain'])->toContain('"a"');
});

it('formats arrays as json', function () {
    $r = CellValueFormatter::format(['x' => true]);

    expect($r['kind'])->toBe('json')
        ->and($r['plain'])->toContain('"x"');
});

it('uses plain text for non-json strings', function () {
    $r = CellValueFormatter::format('hello');

    expect($r['kind'])->toBe('text')
        ->and($r['plain'])->toBe('hello');
});
