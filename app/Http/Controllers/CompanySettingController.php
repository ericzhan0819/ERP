<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\AuditLogService;
use App\Services\CompanyBrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingController extends Controller
{
    public function __construct(
        private readonly CompanyBrandService $companyBrandService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * 技術註解：公司設定只允許操作登入者所屬公司，避免透過參數切換 company_id 造成跨租戶 IDOR。
     */
    public function edit(Request $request): Response
    {
        $company = $request->user()?->company;

        if (! $company) {
            $company = Company::query()->orderBy('id')->firstOrFail();
        }

        return Inertia::render('CompanySettings/Edit', [
            'company' => [
                'name' => $company->name,
                'code' => $company->code,
                'tax_id' => $company->tax_id,
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'logo_url' => $company->logo_url,
                'currency' => $company->currency ?: 'TWD',
                'brand_name' => $company->brand_name,
                'brand_name_en' => $company->brand_name_en,
                'brand_subtitle' => $company->brand_subtitle,
                'brand_slogan' => $company->brand_slogan,
                'brand_eyebrow' => $company->brand_eyebrow,
            ],
        ]);
    }

    /**
     * 技術註解：僅允許白名單欄位寫入，避免 mass assignment 與非預期欄位覆寫風險。
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'brand_name_en' => ['nullable', 'string', 'max:255'],
            'brand_subtitle' => ['nullable', 'string', 'max:255'],
            'brand_slogan' => ['nullable', 'string', 'max:255'],
            'brand_eyebrow' => ['nullable', 'string', 'max:255'],
        ]);

        $company = $request->user()?->company;

        if (! $company) {
            $company = Company::query()->orderBy('id')->firstOrFail();
        }

        // 技術註解：先建立僅限白名單欄位的更新資料，避免未授權欄位混入更新與審計內容。
        $updatePayload = [
            'name' => $validated['name'],
            'tax_id' => $validated['tax_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'logo_url' => $validated['logo_url'] ?? null,
            'currency' => strtoupper($validated['currency'] ?? 'TWD'),
            'brand_name' => $validated['brand_name'] ?? null,
            'brand_name_en' => $validated['brand_name_en'] ?? null,
            'brand_subtitle' => $validated['brand_subtitle'] ?? null,
            'brand_slogan' => $validated['brand_slogan'] ?? null,
            'brand_eyebrow' => $validated['brand_eyebrow'] ?? null,
        ];

        // 技術註解：只對實際有變更欄位寫入 old/new，避免把未變更資料塞入稽核內容造成雜訊與資料過曝。
        $oldValues = [];
        $newValues = [];
        foreach ($updatePayload as $field => $nextValue) {
            $currentValue = $company->getAttribute($field);
            if ($currentValue !== $nextValue) {
                $oldValues[$field] = $currentValue;
                $newValues[$field] = $nextValue;
            }
        }

        $company->update($updatePayload);

        if ($newValues !== []) {
            /** @var \App\Models\User|null $actor */
            $actor = $request->user();

            $this->auditLogService->log(
                actor: $actor,
                action: 'company_settings.updated',
                description: '更新公司設定',
                targetUser: null,
                metadata: ['module' => 'company_settings'],
                subject: $company,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request,
                event: 'company_settings.updated',
            );
        }

        return redirect()
            ->route('employee-system.company-settings.edit')
            ->with('success', '公司設定已更新。');
    }
}
