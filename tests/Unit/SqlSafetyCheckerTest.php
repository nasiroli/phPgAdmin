<?php

use App\Services\SqlSafetyChecker;

describe('SqlSafetyChecker', function () {
    it('treats select and explain as read-only', function () {
        $c = new SqlSafetyChecker;
        expect($c->isReadOnlyStatement('SELECT 1'))->toBeTrue();
        expect($c->isReadOnlyStatement('select * from users'))->toBeTrue();
        expect($c->isReadOnlyStatement('EXPLAIN SELECT 1'))->toBeTrue();
        expect($c->isReadOnlyStatement('WITH x AS (SELECT 1) SELECT * FROM x'))->toBeTrue();
    });

    it('treats mutating statements as not read-only', function () {
        $c = new SqlSafetyChecker;
        expect($c->isReadOnlyStatement('INSERT INTO t VALUES (1)'))->toBeFalse();
        expect($c->isReadOnlyStatement('UPDATE t SET a = 1'))->toBeFalse();
        expect($c->isReadOnlyStatement('DELETE FROM t'))->toBeFalse();
        expect($c->isReadOnlyStatement('TRUNCATE t'))->toBeFalse();
        expect($c->isReadOnlyStatement('CREATE TABLE t (id int)'))->toBeFalse();
        expect($c->isReadOnlyStatement('ALTER TABLE t ADD b int'))->toBeFalse();
        expect($c->isReadOnlyStatement('DROP TABLE t'))->toBeFalse();
    });
});
