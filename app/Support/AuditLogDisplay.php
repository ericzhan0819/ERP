<?php

namespace App\Support;

use App\Models\ActivityLog;

class AuditLogDisplay
{
    /**
     * 技術註解：event key 必須維持英文且穩定，中文化只在顯示層處理，避免破壞歷史稽核可追溯性。
     *
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        'accounting_account.created' => '新增會計科目',
        'accounting_account.updated' => '更新會計科目',
        'vehicle.created' => '新增車輛',
        'vehicle.updated' => '更新車輛資料',
        'vehicle_cost.created' => '新增車輛成本',
        'vehicle_cost.updated' => '更新車輛成本',
        'vehicle_sale.created' => '新增車輛銷售',
        'vehicle_sale.updated' => '更新車輛銷售',
        'vehicle_sale_payment.created' => '新增銷售收款',
        'vehicle_sale_payment.voided' => '作廢銷售收款',
        'vehicle_sale.marked_sold_from_receivable' => '收款標記成交',
        'customer.created' => '新增客戶',
        'customer.updated' => '更新客戶資料',
        'company_settings.updated' => '更新公司設定',
        'company-settings.updated' => '更新公司設定',
        'staff-permission.role.updated' => '更新員工角色',
        'staff-permission.permission.updated' => '更新員工權限',
    ];

    /**
     * 技術註解：module key 不回寫成中文，僅透過集中對照表轉譯，避免 module registry 與 audit metadata 混用。
     *
     * @var array<string, string>
     */
    private const MODULE_LABELS = [
        'accounting_accounts' => '會計科目',
        'vehicles' => '車輛管理',
        'vehicle' => '車輛管理',
        'vehicle_sales' => '車輛銷售',
        'vehicle_costs' => '車輛成本',
        'receivables' => '收款管理',
        'customers' => '客戶管理',
        'audit' => '稽核紀錄',
        'company-settings' => '公司設定',
        'company_settings' => '公司設定',
        'permissions' => '權限管理',
        'staff-permission' => '員工權限管理',
    ];

    /**
     * 技術註解：舊英文描述只在顯示 payload 轉中文，不覆寫 DB，避免 destructive backfill 影響稽核證據鏈。
     *
     * @var array<string, string>
     */
    private const DESCRIPTION_LABELS = [
        'Vehicle created' => '新增車輛',
        'Vehicle updated' => '更新車輛資料',
        'Vehicle cost created' => '新增車輛成本',
        'Vehicle cost updated' => '更新車輛成本',
        'Vehicle sale created' => '新增車輛銷售',
        'Vehicle sale updated' => '更新車輛銷售',
        'Vehicle sale payment created' => '新增銷售收款',
        'Vehicle sale payment voided' => '作廢銷售收款',
        'Vehicle sale marked sold from receivable' => '收款標記成交',
    ];

    /** @return array<string, mixed> */
    public static function payload(ActivityLog $log): array
    {
        $eventKey = self::eventKey($log);

        return [
            'event_key' => $eventKey,
            'event_label' => self::eventLabel($eventKey),
            'module_label' => self::moduleLabel($log, $eventKey),
            'description_label' => self::descriptionLabel($log, $eventKey),
        ];
    }

    public static function eventLabel(?string $eventKey): string
    {
        if ($eventKey === null || $eventKey === '') {
            return '-';
        }

        return self::EVENT_LABELS[$eventKey] ?? $eventKey;
    }

    public static function moduleLabel(ActivityLog $log, ?string $eventKey = null): string
    {
        $resolvedEventKey = $eventKey ?? self::eventKey($log);
        $eventModule = self::moduleLabelFromEvent($resolvedEventKey);

        if ($eventModule !== null) {
            return $eventModule;
        }

        $metadataModule = trim((string) data_get($log->metadata, 'module', ''));

        if ($metadataModule !== '') {
            return self::MODULE_LABELS[$metadataModule] ?? $metadataModule;
        }

        return self::moduleLabelFromActionOrDescription($log) ?? '-';
    }

    public static function descriptionLabel(ActivityLog $log, ?string $eventKey = null): string
    {
        $description = trim((string) ($log->description ?? ''));

        if ($description !== '' && isset(self::DESCRIPTION_LABELS[$description])) {
            return self::DESCRIPTION_LABELS[$description];
        }

        if ($description !== '' && ! self::looksLikeGenericEnglishDescription($description)) {
            return $description;
        }

        return self::eventLabel($eventKey ?? self::eventKey($log));
    }

    private static function eventKey(ActivityLog $log): ?string
    {
        $event = trim((string) ($log->event ?? ''));

        if ($event !== '') {
            return $event;
        }

        $action = trim((string) ($log->action ?? ''));

        return $action !== '' ? $action : null;
    }

    private static function moduleLabelFromEvent(?string $eventKey): ?string
    {
        if ($eventKey === null || $eventKey === '') {
            return null;
        }

        return match (true) {
            str_starts_with($eventKey, 'vehicle_sale_payment.') => '收款管理',
            str_starts_with($eventKey, 'accounting_account.') => '會計科目',
            $eventKey === 'vehicle_sale.marked_sold_from_receivable' => '收款管理',
            str_starts_with($eventKey, 'vehicle_sale.') => '車輛銷售',
            str_starts_with($eventKey, 'vehicle_cost.') => '車輛成本',
            str_starts_with($eventKey, 'vehicle.') => '車輛管理',
            str_starts_with($eventKey, 'customer.') => '客戶管理',
            str_starts_with($eventKey, 'company_settings.') || str_starts_with($eventKey, 'company-settings.') => '公司設定',
            str_starts_with($eventKey, 'staff-permission.') => '員工權限管理',
            default => null,
        };
    }

    private static function moduleLabelFromActionOrDescription(ActivityLog $log): ?string
    {
        $text = trim(((string) ($log->action ?? '')).' '.((string) ($log->description ?? '')));

        return match (true) {
            str_contains($text, '新增車輛') || str_contains($text, '車輛更新') || str_contains($text, '更新車輛資料') => '車輛管理',
            default => null,
        };
    }

    private static function looksLikeGenericEnglishDescription(string $description): bool
    {
        return (bool) preg_match('/^[A-Z][A-Za-z ]+(created|updated|voided|receivable)$/', $description);
    }
}