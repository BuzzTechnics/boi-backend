<?php

namespace Boi\Backend\Support\Concerns;

/**
 * Shared logic for syncing a Nigerian bank list into an Eloquent model:
 * short-name resolution, the Postgres serial-sequence guard, and the upsert.
 * Provider classes (PaystackBanks, MonnifyBanks) fetch the list and hand the
 * normalised [['code' => ..., 'name' => ...], ...] rows to upsertBanks().
 */
trait SyncsBankList
{
    /** Curated short names keyed by the standard CBN/NIP code (shared across providers). */
    protected static function shortNames(): array
    {
        return [
            '044' => 'Access',
            '063' => 'Access (Diamond)',
            '050' => 'Ecobank',
            '070' => 'Fidelity',
            '011' => 'FirstBank',
            '214' => 'FCMB',
            '058' => 'GTBank',
            '076' => 'Polaris',
            '221' => 'Stanbic IBTC',
            '068' => 'StanChart',
            '232' => 'Sterling',
            '032' => 'Union Bank',
            '033' => 'UBA',
            '215' => 'Unity',
            '035' => 'Wema',
            '057' => 'Zenith',
            '023' => 'Citibank',
            '302' => 'TAJ',
            '101' => 'Providus',
            '303' => 'Lotus',
            '100' => 'Suntrust',
            '104' => 'Parallex',
            '105' => 'PremiumTrust',
            '106' => 'Signature',
            '107' => 'Optimus',
            '108' => 'Alpha Morgan',
            '109' => 'Tatum',
            '102' => 'Titan',
            '00103' => 'Globus',
            '501' => 'FSDH',
            '559' => 'Coronation',
            '562' => 'Greenwich',
            '502' => 'Rand Merchant',
            '035A' => 'ALAT (Wema)',
            '999992' => 'OPay',
            '999991' => 'PalmPay',
            '50515' => 'Moniepoint',
            '565' => 'Carbon',
            '120001' => '9PSB',
            '120002' => 'HopePSB',
            '120003' => 'MTN MoMo',
            '120004' => 'Airtel Smartcash',
            '187' => 'Stanbic IBTC',
            '566' => 'VFD',
            '602' => 'Accion',
            '125' => 'Rubies',
            '561' => 'Nova',
            '00305' => 'Summit',
            '401' => 'ASO S&L',
            '404' => 'Abbey Mortgage',
        ];
    }

    protected static function resolveShortName(string $code, string $name): string
    {
        return self::shortNames()[$code] ?? self::generateShortName($name);
    }

    /**
     * Upsert normalised rows into the bank model.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $bankModel
     * @param  iterable<array{code: string, name: string}>  $banks
     */
    protected static function upsertBanks(string $bankModel, iterable $banks): void
    {
        self::ensurePostgresSerialNotBehindMaxId($bankModel);

        foreach ($banks as $bank) {
            $code = $bank['code'] ?? null;
            $name = $bank['name'] ?? null;
            if ($code === null || $name === null) {
                continue;
            }

            $bankModel::updateOrCreate(
                ['code' => (string) $code],
                [
                    'name' => (string) $name,
                    'short_name' => self::resolveShortName((string) $code, (string) $name),
                ]
            );
        }
    }

    /**
     * After restores or manual inserts, PostgreSQL serial sequences can lag behind MAX(id),
     * causing duplicate primary key errors on the next insert.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $bankModel
     */
    protected static function ensurePostgresSerialNotBehindMaxId(string $bankModel): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $bankModel;
        $connection = $instance->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $table = $instance->getTable();
        $key = $instance->getKeyName();

        $seqRow = $connection->selectOne(
            'select pg_get_serial_sequence(?, ?) as seq',
            [$table, $key]
        );

        if (! $seqRow || empty($seqRow->seq)) {
            return;
        }

        $max = $connection->table($table)->max($key);
        if ($max === null || (int) $max < 1) {
            return;
        }

        $connection->statement('select setval(?, ?, true)', [$seqRow->seq, (int) $max]);
    }

    private static function generateShortName(string $name): string
    {
        $short = $name;

        $suffixes = [
            'Microfinance Bank Limited',
            'Microfinance Bank Ltd.',
            'Microfinance Bank Ltd',
            'MICROFINANCE BANK LTD',
            'MICROFINANCE BANK LIMITED',
            'MICROFINANACE BANK',
            'Microfinance Bank',
            'MICROFINANCE BANK',
            'Mircofinance Bank Plc',
            'Finance Company Limited',
            'Finance Company Ltd',
            'FINANCE COMPANY LIMITED',
            'FINANCE LIMITED',
            'Finance Limited',
            'MORTAGE BANK',
            'Mortgage Bank LTD',
            'Mortgage Bank Nigeria',
            'Mortgage Bank',
            'Mortgage bank',
            'Bank Limited',
            'Bank Ltd',
            'Bank Plc',
            'Bank Nigeria',
            'Bank',
            'BANK',
            'MFB LTD',
            'MFB',
            'PSB',
            'Limited',
            'Ltd.',
            'Ltd',
            'LTD',
            'Plc',
        ];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($short, ' '.$suffix)) {
                $short = substr($short, 0, -strlen(' '.$suffix));
                break;
            }
        }

        $short = trim($short);

        return $short !== '' ? $short : $name;
    }
}
