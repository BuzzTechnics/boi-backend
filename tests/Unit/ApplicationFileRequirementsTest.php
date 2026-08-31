<?php

use Boi\Backend\Services\ApplicationFileRequirements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RequirementsCatalogFile extends Model
{
    protected $table = 'requirements_catalog_files';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Schema::create('requirements_catalog_files', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('template')->nullable();
        $table->boolean('required')->default(false);
        $table->boolean('enterprise')->default(false);
        $table->boolean('ltd')->default(false);
        $table->boolean('above_10m')->default(false);
    });

    // Representative slice of the ADF document catalog: flags are audience
    // selectors (enterprise / ltd ≤10m / anyone >10m); `required` is not.
    $rows = [
        // [name, required, enterprise, ltd, above_10m]
        ['Universal doc (application letter)', true, true, true, true],
        ['Generic Means of ID (≤10m)', true, true, true, false],
        ['Means of ID of Chief Promoter (>10m)', true, false, false, true],
        ['Certification of Registration (enterprise)', false, true, false, false],
        ['Certificate of Incorporation (ltd)', false, false, true, true],
        ['Company bank statement (ltd)', false, false, true, true],
        ['Audited Financial Statements (>10m)', false, false, false, true],
        ['Condition-only doc (guarantor/PLWD)', false, false, false, false],
    ];
    foreach ($rows as [$name, $required, $enterprise, $ltd, $above]) {
        RequirementsCatalogFile::query()->create([
            'name' => $name,
            'required' => $required,
            'enterprise' => $enterprise,
            'ltd' => $ltd,
            'above_10m' => $above,
        ]);
    }
});

function requirementNames(string $businessType, mixed $amount): array
{
    return array_column(
        ApplicationFileRequirements::getRequiredFiles(RequirementsCatalogFile::query(), $businessType, $amount),
        'name'
    );
}

it('gives an enterprise applicant the enterprise document set', function () {
    expect(requirementNames('sole_proprietorship', 500_000))->toEqualCanonicalizing([
        'Universal doc (application letter)',
        'Generic Means of ID (≤10m)',
        'Certification of Registration (enterprise)',
    ]);
});

it('gives a ltd applicant at or below 10m the ltd document set', function () {
    expect(requirementNames('ltd', 10_000_000))->toEqualCanonicalizing([
        'Universal doc (application letter)',
        'Generic Means of ID (≤10m)',
        'Certificate of Incorporation (ltd)',
        'Company bank statement (ltd)',
    ]);
});

it('swaps in the above-10m documents for a ltd applicant above 10m', function () {
    expect(requirementNames('ltd', 25_000_000))->toEqualCanonicalizing([
        'Universal doc (application letter)',
        'Means of ID of Chief Promoter (>10m)',
        'Certificate of Incorporation (ltd)',
        'Company bank statement (ltd)',
        'Audited Financial Statements (>10m)',
    ]);
});

it('treats a ltd applicant with no amount yet as at or below 10m', function () {
    expect(requirementNames('ltd', null))->toContain('Generic Means of ID (≤10m)')
        ->not->toContain('Means of ID of Chief Promoter (>10m)');
});

it('returns nothing for an unknown business type', function () {
    expect(requirementNames('', 5_000_000))->toBe([]);
});

it('marks every surfaced document required and never surfaces condition-only rows', function () {
    foreach ([['ltd', 25_000_000], ['ltd', 1_000_000], ['partnership', 2_000_000]] as [$type, $amount]) {
        $files = ApplicationFileRequirements::getRequiredFiles(RequirementsCatalogFile::query(), $type, $amount);
        expect(array_column($files, 'required'))->each->toBeTrue();
        expect(array_column($files, 'name'))->not->toContain('Condition-only doc (guarantor/PLWD)');
    }
});
