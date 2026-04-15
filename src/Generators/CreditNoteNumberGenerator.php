<?php

namespace PictaStudio\Venditio\Generators;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Contracts\CreditNoteNumberGeneratorInterface;

use function PictaStudio\Venditio\Helpers\Functions\query;

class CreditNoteNumberGenerator implements CreditNoteNumberGeneratorInterface
{
    public function generate(Model $creditNote): string
    {
        $issuedAt = $creditNote->issued_at ?? now();
        $year = $issuedAt->year;
        $month = $issuedAt->format('m');

        $latestIdentifier = query('credit_note')
            ->selectRaw('MAX(identifier) as identifier')
            ->whereYear('issued_at', $year)
            ->whereMonth('issued_at', $month)
            ->value('identifier');

        $increment = 1;

        if ($latestIdentifier) {
            $segments = explode('-', $latestIdentifier);

            if (count($segments) === 4) {
                $increment = (int) end($segments) + 1;
            }
        }

        while (
            query('credit_note')
                ->where('identifier', $this->buildIdentifier($year, $month, $increment))
                ->exists()
        ) {
            $increment++;
        }

        return $this->buildIdentifier($year, $month, $increment);
    }

    private function buildIdentifier(int $year, string $month, int $increment): string
    {
        return str('CN-{year}-{month}-{increment}')
            ->swap([
                '{year}' => $year,
                '{month}' => $month,
                '{increment}' => mb_str_pad($increment, 6, '0', STR_PAD_LEFT),
            ])
            ->toString();
    }
}
