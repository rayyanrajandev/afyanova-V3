<?php

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A generic, parametrized structural check standing in for a much harder
 * problem: building live two-tenant/two-facility fixtures for every one of
 * the ~60 Eloquent models in this codebase isn't tractable (most need
 * complex, model-specific setup no generic test can construct safely), so
 * this asserts the one thing that IS mechanically checkable for all of them
 * at once — that the trait is actually present — rather than leaving the
 * next tenant-scoped or facility-scoped table to be caught only if someone
 * remembers to write it up by hand.
 *
 * This is exactly the gap that produced this test: the original audit
 * flagged 4 models needing HasFacilityScope; a manual schema sweep during
 * the fix turned up 14 more with the same facility_id + patient_id shape
 * that had gone unaudited. A model added after this test exists gets
 * caught the day its migration lands, not the next time someone happens to
 * sweep the schema by hand.
 *
 * TenantIsolationTest and FacilityScopeTest cover the live, "does this
 * actually block a second tenant/facility over real HTTP" dimension for
 * the highest-traffic models — this covers "is the trait present at all,"
 * for every model, automatically. The two are complementary, not
 * duplicates: this test would never have caught a Dashboard-style bug
 * (the trait was present and correct; the controller just didn't apply it
 * to every prop), because that's not a schema-shape question — this only
 * catches "the invariant this model's own table shape demands isn't wired
 * up at all."
 *
 * @return list<class-string<Model>>
 */
function allModelClasses(): array
{
    $classes = [];

    foreach (glob(base_path('app/Domains/*/Models/*.php')) ?: [] as $file) {
        $relative = Str::after($file, base_path('app/Domains/'));
        [$domain, , $filename] = explode('/', $relative);
        $class = "App\\Domains\\{$domain}\\Models\\".Str::before($filename, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
            continue;
        }

        /** @var class-string<Model> $class */
        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}

test('every model whose table has a tenant_id column uses BelongsToTenant', function () {
    $missing = [];

    foreach (allModelClasses() as $class) {
        $model = new $class;
        $table = $model->getTable();

        if (! Schema::hasTable($table) || ! in_array('tenant_id', Schema::getColumnListing($table), true)) {
            continue;
        }

        if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
            $missing[] = "{$class} (table: {$table})";
        }
    }

    expect($missing)->toBe([]);
});

test('every model whose table has both facility_id and patient_id columns uses HasFacilityScope', function () {
    $missing = [];

    foreach (allModelClasses() as $class) {
        $model = new $class;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            continue;
        }

        $columns = Schema::getColumnListing($table);
        if (! in_array('facility_id', $columns, true) || ! in_array('patient_id', $columns, true)) {
            continue;
        }

        if (! in_array(HasFacilityScope::class, class_uses_recursive($class), true)) {
            $missing[] = "{$class} (table: {$table})";
        }
    }

    expect($missing)->toBe([]);
});
