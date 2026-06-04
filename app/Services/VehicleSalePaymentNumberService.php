<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VehicleSalePaymentNumberService
{
    /**
     * 技術註解：以 company_id + period 原子遞增收款編號，避免同公司同月份併發建立時產生重號。
     */
    public function generate(int $companyId, ?Carbon $date = null): string
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('companyId 必須大於 0');
        }

        $period = $date?->format('Ym') ?? now()->format('Ym');

        return DB::transaction(function () use ($companyId, $period): string {
            $sequence = DB::table('vehicle_sale_payment_number_sequences')
                ->where('company_id', $companyId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    DB::table('vehicle_sale_payment_number_sequences')->insert([
                        'company_id' => $companyId,
                        'period' => $period,
                        'seq' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    if (! in_array($exception->getCode(), ['23000', '23505'], true)) {
                        throw $exception;
                    }
                }

                $sequence = DB::table('vehicle_sale_payment_number_sequences')
                    ->where('company_id', $companyId)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextSeq = ((int) $sequence->seq) + 1;

            DB::table('vehicle_sale_payment_number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'seq' => $nextSeq,
                    'updated_at' => now(),
                ]);

            return sprintf('PAY-%s-%04d', $period, $nextSeq);
        });
    }
}