<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingJournalNumberService
{
    /**
     * 技術註解：以公司 + 年月作 lockForUpdate 序號邊界，可避免併發建立草稿時產生重複 JE 編號。
     */
    public function generate(int $companyId, ?\DateTimeInterface $entryDate = null): string
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('無效的公司識別，無法產生傳票編號。');
        }

        $date = $entryDate ? \Illuminate\Support\Carbon::instance($entryDate) : now();
        $period = $date->format('Ym');

        return DB::transaction(function () use ($companyId, $period): string {
            $sequence = DB::table('accounting_journal_number_sequences')
                ->where('company_id', $companyId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                DB::table('accounting_journal_number_sequences')->insert([
                    'company_id' => $companyId,
                    'period' => $period,
                    'seq' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = DB::table('accounting_journal_number_sequences')
                    ->where('company_id', $companyId)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextSeq = (int) $sequence->seq + 1;

            DB::table('accounting_journal_number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'seq' => $nextSeq,
                    'updated_at' => now(),
                ]);

            return sprintf('JE-%s-%04d', $period, $nextSeq);
        });
    }
}