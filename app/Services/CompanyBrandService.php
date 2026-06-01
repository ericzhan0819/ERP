<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyBrandService
{
    /**
     * 技術註解：fallback 文案固定取自上一版首頁硬編碼內容，避免公司基本資料覆蓋品牌語意。
     */
    private const FALLBACK_NAME = 'OO國際車業';

    /**
     * 技術註解：英文小標需獨立於 company.code，防止代碼被誤當品牌英文名稱。
     */
    private const FALLBACK_BRAND_NAME_EN = 'OO INTERNATIONAL';

    private const FALLBACK_BRAND_SUBTITLE = '以「絕對透明、系統秩序、專業可靠」為核心，建立擇車如擇友的中古車管理中樞。';

    private const FALLBACK_BRAND_SLOGAN = '擇車如擇友，敘白如敘舊';

    private const FALLBACK_BRAND_EYEBROW = 'EST. 2026';

    /**
     * 技術註解：集中品牌欄位 fallback，避免 Welcome、Middleware、Controller 各自硬編碼造成漂移。
     *
     * @return array<string, string|null>
     */
    public function resolveForUser(?User $user): array
    {
        $company = null;

        if ($user?->company_id) {
            $company = Company::query()->find($user->company_id);
        }

        if (! $company instanceof Company) {
            $company = Company::query()->orderBy('id')->first();
        }

        return $this->toBrandPayload($company);
    }

    /**
     * 技術註解：public 頁面不可依賴登入者，改用第一筆公司做展示並保留安全預設值。
     *
     * @return array<string, string|null>
     */
    public function resolveForPublic(): array
    {
        return $this->toBrandPayload(Company::query()->orderBy('id')->first());
    }

    /**
     * @return array<string, string|null>
     */
    private function toBrandPayload(?Company $company): array
    {
        $brandName = $company?->brand_name ?: self::FALLBACK_NAME;

        return [
            'name' => $company?->name ?: $brandName,
            'code' => $company?->code ?: 'OO',
            'logo_url' => $company?->logo_url,
            'currency' => $company?->currency ?: 'TWD',
            'brand_name' => $brandName,
            'brand_name_en' => $company?->brand_name_en ?: self::FALLBACK_BRAND_NAME_EN,
            'brand_subtitle' => $company?->brand_subtitle ?: self::FALLBACK_BRAND_SUBTITLE,
            'brand_slogan' => $company?->brand_slogan ?: self::FALLBACK_BRAND_SLOGAN,
            'brand_eyebrow' => $company?->brand_eyebrow ?: self::FALLBACK_BRAND_EYEBROW,
        ];
    }
}
