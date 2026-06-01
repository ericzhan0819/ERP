<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyBrandService;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

it('admin 可查看公司設定頁', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.company-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('CompanySettings/Edit')
            ->has('company.name')
            ->has('company.code')
            ->has('company.brand_name')
            ->has('company.brand_name_en')
            ->has('company.brand_subtitle')
            ->has('company.brand_slogan')
            ->has('company.brand_eyebrow')
        );
});

it('無 module.company-settings.view 者不可查看公司設定頁', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::query()->where('email', 'staff@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('employee-system.company-settings.edit'))
        ->assertForbidden();
});

it('admin 可更新自身公司基本資料', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('employee-system.company-settings.update'), [
            'name' => '新公司名稱',
            'tax_id' => '12345678',
            'phone' => '02-1234-5678',
            'email' => 'company@example.com',
            'address' => '台北市中正區測試路 1 號',
            'logo_url' => 'https://example.com/logo.png',
            'currency' => 'usd',
            'brand_name' => '新品牌中文',
            'brand_name_en' => 'NEW BRAND EN',
            'brand_subtitle' => '透明化營運中樞',
            'brand_slogan' => '我們只做可驗證的車',
            'brand_eyebrow' => 'EST. 2030',
        ])
        ->assertRedirect(route('employee-system.company-settings.edit'));

    $company = Company::query()->findOrFail($admin->company_id);

    expect($company->name)->toBe('新公司名稱')
        ->and($company->tax_id)->toBe('12345678')
        ->and($company->currency)->toBe('USD')
        ->and($company->brand_name)->toBe('新品牌中文')
        ->and($company->brand_name_en)->toBe('NEW BRAND EN')
        ->and($company->brand_subtitle)->toBe('透明化營運中樞')
        ->and($company->brand_slogan)->toBe('我們只做可驗證的車')
        ->and($company->brand_eyebrow)->toBe('EST. 2030');
});

it('admin 更新公司設定後會寫入僅含變更欄位的稽核紀錄', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $company = Company::query()->findOrFail($admin->company_id);

    $company->update([
        'name' => '舊公司名稱',
        'tax_id' => '11112222',
        'phone' => '02-1111-2222',
        'email' => 'old-company@example.com',
        'address' => '台北市舊地址 1 號',
        'logo_url' => 'https://example.com/old-logo.png',
        'currency' => 'TWD',
        'brand_name' => '舊品牌中文',
        'brand_name_en' => 'OLD BRAND EN',
        'brand_subtitle' => '舊副標',
        'brand_slogan' => '舊口號',
        'brand_eyebrow' => 'EST. 2020',
    ]);

    $this->actingAs($admin)
        ->put(route('employee-system.company-settings.update'), [
            'name' => '新公司名稱',
            'tax_id' => '11112222',
            'phone' => '02-9999-8888',
            'email' => 'old-company@example.com',
            'address' => '台北市舊地址 1 號',
            'logo_url' => 'https://example.com/new-logo.png',
            'currency' => 'twd',
            'brand_name' => '舊品牌中文',
            'brand_name_en' => 'OLD BRAND EN',
            'brand_subtitle' => '新副標',
            'brand_slogan' => '舊口號',
            'brand_eyebrow' => 'EST. 2020',
        ])
        ->assertRedirect(route('employee-system.company-settings.edit'));

    $log = ActivityLog::query()
        ->where('event', 'company_settings.updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log?->user_id)->toBe($admin->id)
        ->and($log?->company_id)->toBe($admin->company_id)
        ->and($log?->action)->toBe('company_settings.updated')
        ->and($log?->event)->toBe('company_settings.updated')
        ->and($log?->metadata['module'] ?? null)->toBe('company_settings')
        ->and($log?->description)->toBe('更新公司設定')
        ->and($log?->old_values)->toHaveKeys(['name', 'phone', 'logo_url', 'brand_subtitle'])
        ->and($log?->new_values)->toHaveKeys(['name', 'phone', 'logo_url', 'brand_subtitle'])
        ->and($log?->old_values)->not->toHaveKey('tax_id')
        ->and($log?->new_values)->not->toHaveKey('tax_id')
        ->and($log?->old_values['name'] ?? null)->toBe('舊公司名稱')
        ->and($log?->new_values['name'] ?? null)->toBe('新公司名稱')
        ->and($log?->old_values['logo_url'] ?? null)->toBe('https://example.com/old-logo.png')
        ->and($log?->new_values['logo_url'] ?? null)->toBe('https://example.com/new-logo.png');
});

it('update 不會跨 company 修改其他公司設定', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $otherCompany = Company::query()->create([
        'name' => 'Other Co',
        'code' => 'OTH',
    ]);

    $this->actingAs($admin)
        ->put(route('employee-system.company-settings.update'), [
            'name' => 'Only My Company',
            'tax_id' => '87654321',
            'phone' => null,
            'email' => null,
            'address' => null,
            'logo_url' => null,
            'currency' => 'TWD',
            'brand_name' => '我的品牌中文',
            'brand_name_en' => 'MY BRAND EN',
            'brand_subtitle' => '我的品牌副標',
            'brand_slogan' => '我的品牌口號',
            'brand_eyebrow' => 'EST. 2040',
        ])
        ->assertRedirect(route('employee-system.company-settings.edit'));

    $myCompany = Company::query()->findOrFail($admin->company_id);

    expect($myCompany->name)->toBe('Only My Company')
        ->and($otherCompany->fresh()->name)->toBe('Other Co')
        ->and($otherCompany->fresh()->brand_subtitle)->toBeNull()
        ->and($otherCompany->fresh()->brand_name_en)->toBeNull();
});

it('welcome 在無 company 資料時可安全 fallback', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('brand.name', 'OO國際車業')
            ->where('brand.code', 'OO')
            ->where('brand.brand_name', 'OO國際車業')
            ->where('brand.brand_name_en', 'OO INTERNATIONAL')
            ->where('brand.brand_subtitle', '以「絕對透明、系統秩序、專業可靠」為核心，建立擇車如擇友的中古車管理中樞。')
            ->where('brand.brand_slogan', '擇車如擇友，敘白如敘舊')
            ->where('brand.brand_eyebrow', 'EST. 2026')
            ->where('brand.currency', 'TWD')
        );
});

it('登入後共享 props 品牌資料來自使用者 company', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $company = Company::query()->create([
        'name' => '測試品牌公司',
        'code' => 'TBC',
        'currency' => 'JPY',
        'brand_name' => '測試品牌中文',
        'brand_name_en' => 'TEST BRAND EN',
        'brand_subtitle' => '客製副標',
        'brand_slogan' => '客製口號',
        'brand_eyebrow' => 'EST. 2088',
    ]);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $admin->forceFill(['company_id' => $company->id])->save();

    $this->actingAs($admin)
        ->get(route('employee-system.overview'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('brand.name', '測試品牌公司')
            ->where('brand.code', 'TBC')
            ->where('brand.currency', 'JPY')
            ->where('brand.brand_name', '測試品牌中文')
            ->where('brand.brand_name_en', 'TEST BRAND EN')
            ->where('brand.brand_subtitle', '客製副標')
            ->where('brand.brand_slogan', '客製口號')
            ->where('brand.brand_eyebrow', 'EST. 2088')
        );
});

it('CompanyBrandService fallback 不會把 company.code 當品牌英文小標', function (): void {
    /** @var CompanyBrandService $service */
    $service = app(CompanyBrandService::class);

    $brand = $service->resolveForPublic();

    expect($brand['brand_name'])->not->toBe($brand['code'])
        ->and($brand['brand_name_en'])->not->toBe($brand['code'])
        ->and($brand['brand_slogan'])->not->toBe('')
        ->and($brand['brand_eyebrow'])->not->toBe('');
});
