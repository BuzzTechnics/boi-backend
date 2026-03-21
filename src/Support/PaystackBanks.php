<?php

namespace Boi\Backend\Support;

use Illuminate\Support\Facades\Http;

/**
 * Syncs Nigerian banks from Paystack into an Eloquent model (name, code, short_name).
 * Used by consuming apps' seeders and by boi-api.
 */
class PaystackBanks
{
    private const SHORT_NAMES = [
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

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $bankModel
     * @param  callable(string):void|null  $onError  e.g. fn (string $m) => $this->command->error($m)
     */
    public static function sync(string $bankModel, ?callable $onError = null): bool
    {
        if (! class_exists($bankModel)) {
            $onError !== null && $onError("Bank model class does not exist: {$bankModel}");

            return false;
        }

        $response = Http::get('https://api.paystack.co/bank');

        if (! $response->successful()) {
            $onError !== null && $onError('Failed to fetch banks from Paystack API: '.$response->status());

            return false;
        }

        $json = $response->json();

        if (! isset($json['data']) || ! is_array($json['data'])) {
            $onError !== null && $onError('Invalid Paystack response: expected "data" array.');

            return false;
        }

        foreach ($json['data'] as $bank) {
            $code = $bank['code'] ?? null;
            $name = $bank['name'] ?? null;
            if ($code === null || $name === null) {
                continue;
            }

            $shortName = self::SHORT_NAMES[(string) $code] ?? self::generateShortName((string) $name);

            $bankModel::updateOrCreate(
                ['code' => (string) $code],
                [
                    'name' => (string) $name,
                    'short_name' => $shortName,
                ]
            );
        }

        return true;
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
