<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerNumberService
{
    /**
     * 技術註解：以 company_id + period 產生客戶編號，交易內 lockForUpdate 可防止併發重複取號。
     */
    public function generate(int $companyId, ?Carbon $date = null): string
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('companyId 必須大於 0');
        }

        $period = $date?->format('Ym') ?? now()->format('Ym');

        return DB::transaction(function () use ($companyId, $period): string {
            $sequence = DB::table('customer_number_sequences')
                ->where('company_id', $companyId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    DB::table('customer_number_sequences')->insert([
                        'company_id' => $companyId,
                        'period' => $period,
                        'next_number' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    // 技術註解：併發首次建立可能撞 unique constraint，改為重新加鎖讀取即可維持單一正確序號。
                    if (! in_array($exception->getCode(), ['23000', '23505'], true)) {
                        throw $exception;
                    }
                }

                $sequence = DB::table('customer_number_sequences')
                    ->where('company_id', $companyId)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextNumber = (int) $sequence->next_number;

            DB::table('customer_number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_number' => $nextNumber + 1,
                    'updated_at' => now(),
                ]);

            return sprintf('CU-%s-%04d', $period, $nextNumber);
        });
    }
}

