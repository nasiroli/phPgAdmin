<?php

use App\Services\PostgresCatalogService;

it('formats varchar with length for alter', function () {
    $row = (object) [
        'data_type' => 'character varying',
        'udt_name' => 'varchar',
        'character_maximum_length' => 100,
    ];

    expect(PostgresCatalogService::columnTypeSqlForAlter($row))->toBe('character varying(100)');
});

it('maps int4 to integer for alter', function () {
    $row = (object) [
        'data_type' => 'integer',
        'udt_name' => 'int4',
        'character_maximum_length' => null,
        'numeric_precision' => null,
        'numeric_scale' => null,
    ];

    expect(PostgresCatalogService::columnTypeSqlForAlter($row))->toBe('integer');
});
