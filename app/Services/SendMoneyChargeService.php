<?php

namespace App\Services;

class SendMoneyChargeService
{
    protected array $mobileMoneyChannels = ["00", "63903", "63902", "97"];
    protected string $propelChannel = "Propel";
    protected array $bankChannels = [
        "01", "02", "03", "07", "10", "11", "12", "14", "16", "18", "19", "23", "25",
        "31", "35", "36", "43", "50", "51", "53", "54", "55", "57", "61", "63", "65",
        "66", "68", "70", "72", "74", "75", "76", "78", "89"
    ];

    protected array $charges = [
        'mobile_money' => [
            'personal' => [
                [1, 49, 0], [50, 100, 0], [101, 500, 7], [501, 1000, 11], [1001, 1500, 19],
                [1501, 2500, 19], [2501, 3500, 24], [3501, 5000, 24], [5001, 7500, 24],
                [7501, 10000, 24], [10001, 15000, 29], [15001, 20000, 29], [20001, 25000, 39],
                [25001, 30000, 39], [30001, 35000, 39], [35001, 40000, 39], [40001, 45000, 39],
                [45001, 50000, 39], [50001, 70000, 39], [70001, 150000, 39],
            ],
            'business' => [
                [1, 49, 2], [50, 100, 2], [101, 500, 10], [501, 1000, 15], [1001, 1500, 25],
                [1501, 2500, 25], [2501, 3500, 30], [3501, 5000, 30], [5001, 7500, 35],
                [7501, 10000, 35], [10001, 15000, 40], [15001, 20000, 45], [20001, 25000, 50],
                [25001, 30000, 55], [30001, 35000, 60], [35001, 40000, 65], [40001, 45000, 70],
                [45001, 50000, 75], [50001, 70000, 80], [70001, 150000, 85],
            ],
        ],
        'propel' => [
            'personal' => [
                [1, 49, 0], [50, 100, 0], [101, 500, 8], [501, 1000, 12], [1001, 1500, 20],
                [1501, 2500, 22], [2501, 3500, 26], [3501, 5000, 26], [5001, 7500, 30],
                [7501, 10000, 32], [10001, 15000, 35], [15001, 20000, 40], [20001, 25000, 45],
                [25001, 30000, 50], [30001, 35000, 55], [35001, 40000, 60], [40001, 45000, 65],
                [45001, 50000, 70], [50001, 70000, 75], [70001, 150000, 80],
            ],
            'business' => [
                [1, 49, 5], [50, 100, 5], [101, 500, 12], [501, 1000, 18], [1001, 1500, 30],
                [1501, 2500, 35], [2501, 3500, 40], [3501, 5000, 45], [5001, 7500, 50],
                [7501, 10000, 55], [10001, 15000, 60], [15001, 20000, 65], [20001, 25000, 70],
                [25001, 30000, 75], [30001, 35000, 80], [35001, 40000, 85], [40001, 45000, 90],
                [45001, 50000, 95], [50001, 70000, 100], [70001, 150000, 110],
            ],
        ],
        'banks' => [
            'personal' => [
                [1, 49, 2], [50, 100, 3], [101, 500, 9], [501, 1000, 13], [1001, 1500, 21],
                [1501, 2500, 23], [2501, 3500, 28], [3501, 5000, 28], [5001, 7500, 33],
                [7501, 10000, 34], [10001, 15000, 37], [15001, 20000, 42], [20001, 25000, 47],
                [25001, 30000, 52], [30001, 35000, 57], [35001, 40000, 62], [40001, 45000, 67],
                [45001, 50000, 72], [50001, 70000, 77], [70001, 150000, 82],
            ],
            'business' => [
                [1, 49, 5], [50, 100, 6], [101, 500, 15], [501, 1000, 20], [1001, 1500, 30],
                [1501, 2500, 35], [2501, 3500, 40], [3501, 5000, 45], [5001, 7500, 50],
                [7501, 10000, 55], [10001, 15000, 60], [15001, 20000, 65], [20001, 25000, 70],
                [25001, 30000, 75], [30001, 35000, 80], [35001, 40000, 85], [40001, 45000, 90],
                [45001, 50000, 95], [50001, 70000, 100], [70001, 150000, 110],
            ],
        ],
    ];

    public function getCharge(string $channel, float $amount, bool $isBusiness): int
    {
        $type = $isBusiness ? 'business' : 'personal';

        if (in_array($channel, $this->mobileMoneyChannels)) {
            return $this->calculateCharge($amount, $this->charges['mobile_money'][$type]);
        }

        if ($channel === $this->propelChannel) {
            return $this->calculateCharge($amount, $this->charges['propel'][$type]);
        }

        if (in_array($channel, $this->bankChannels)) {
            return $this->calculateCharge($amount, $this->charges['banks'][$type]);
        }

        return 0; // Default charge if the channel is unknown
    }

    protected function calculateCharge(float $amount, array $chargeStructure): int
    {
        foreach ($chargeStructure as [$min, $max, $charge]) {
            if ($amount >= $min && $amount <= $max) {
                return $charge;
            }
        }
        return 39; // Default max charge if amount is too high
    }
}
