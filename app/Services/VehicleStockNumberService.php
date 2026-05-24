<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VehicleStockNumberService
{
    /**
     * 技術註解：以 company_id + period 產生庫存編號，並在交易中使用 lockForUpdate，
     * 可確保同一邊界下的 next_number 讀取與遞增是原子操作，避免併發請求拿到相同序號。
     */
    public function generate(int $companyId, ?Carbon $date = null): string
    {
        // 技術註解：先阻擋非法 company_id，可避免 tenant 邊界錯誤時寫入 company_id=0 的序列表資料。
        if ($companyId <= 0) {
            throw new InvalidArgumentException('companyId 必須大於 0');
        }

        $period = $date?->format('Ym') ?? now()->format('Ym');

        return DB::transaction(function () use ($companyId, $period): string {
            $sequence = DB::table('vehicle_stock_number_sequences')
                ->where('company_id', $companyId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    DB::table('vehicle_stock_number_sequences')->insert([
                        'company_id' => $companyId,
                        'period' => $period,
                        'next_number' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    // 技術註解：併發首次建立時可能命中 unique constraint；此時忽略並改為重新加鎖讀取，
                    // 讓後續流程仍在同一交易內取得正確單一序號，避免重號競態。
                    if (! in_array($exception->getCode(), ['23000', '23505'], true)) {
                        throw $exception;
                    }
                }

                $sequence = DB::table('vehicle_stock_number_sequences')
                    ->where('company_id', $companyId)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextNumber = (int) $sequence->next_number;

            DB::table('vehicle_stock_number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_number' => $nextNumber + 1,
                    'updated_at' => now(),
                ]);

            return sprintf('VH-%s-%04d', $period, $nextNumber);
        });
    }
}
