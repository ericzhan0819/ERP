<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewAccountingEventRequest;
use App\Http\Requests\VoidAccountingEventRequest;
use App\Models\AccountingEvent;
use App\Services\AuditLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingEventController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * 技術註解：只讀列表只輸出畫面必要 allowlist，不回傳 payload JSON 或 tenant/actor 原始欄位，避免候選會計摘要外洩敏感資訊。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingEvent::class);

        $sourceTypes = config('accounting_events.source_types', []);
        $eventTypes = config('accounting_events.event_types', []);
        $statuses = config('accounting_events.statuses', []);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'source_type' => ['nullable', 'string', Rule::in(array_keys($sourceTypes))],
            'event_type' => ['nullable', 'string', Rule::in(array_keys($eventTypes))],
            'status' => ['nullable', 'string', Rule::in(array_keys($statuses))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $events = $this->scopedEventQuery($request->user())
            ->with([
                'creator:id,name',
                'reviewer:id,name',
                'voider:id,name',
                'convertedJournalEntry:id,journal_number,status,entry_date',
            ])
            ->when(trim((string) ($filters['q'] ?? '')) !== '', function (Builder $query) use ($filters): void {
                $q = trim((string) $filters['q']);
                $query->where(function (Builder $subQuery) use ($q): void {
                    $subQuery->where('source_number', 'like', "%{$q}%")
                        ->orWhere('source_type', 'like', "%{$q}%")
                        ->orWhere('event_type', 'like', "%{$q}%")
                        ->orWhere('status', 'like', "%{$q}%");
                });
            })
            ->when(($filters['source_type'] ?? '') !== '', fn (Builder $query) => $query->where('source_type', $filters['source_type']))
            ->when(($filters['event_type'] ?? '') !== '', fn (Builder $query) => $query->where('event_type', $filters['event_type']))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('event_date', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('event_date', '<=', $filters['date_to']))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AccountingEvent $event): array => $this->eventListPayload($event, $sourceTypes, $eventTypes, $statuses));

        return Inertia::render('Accounting/Events/Index', [
            'events' => $events,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'source_type' => $filters['source_type'] ?? '',
                'event_type' => $filters['event_type'] ?? '',
                'status' => $filters['status'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'sourceTypes' => $sourceTypes,
            'eventTypes' => $eventTypes,
            'statuses' => $statuses,
            'can' => [
                'view' => $request->user()?->can('module.accounting.events.view') ?? false,
                'review' => $request->user()?->can('module.accounting.events.review') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：不使用 implicit model binding；先 tenant scoped 查詢再授權，使跨租戶 ID 優先 404，降低 IDOR 存在性洩漏。
     */
    public function show(Request $request, int $accountingEvent): Response
    {
        $event = $this->scopedEventQuery($request->user())
            ->with([
                'company:id,name',
                'branch:id,name',
                'creator:id,name',
                'reviewer:id,name',
                'voider:id,name',
                'convertedJournalEntry:id,journal_number,status,entry_date',
            ])
            ->whereKey($accountingEvent)
            ->firstOrFail();

        $this->authorize('view', $event);

        $sourceTypes = config('accounting_events.source_types', []);
        $eventTypes = config('accounting_events.event_types', []);
        $statuses = config('accounting_events.statuses', []);

        return Inertia::render('Accounting/Events/Show', [
            'event' => $this->eventDetailPayload($event, $sourceTypes, $eventTypes, $statuses),
            'sourceTypes' => $sourceTypes,
            'eventTypes' => $eventTypes,
            'statuses' => $statuses,
            'can' => [
                'view' => $request->user()?->can('module.accounting.events.view') ?? false,
                'review' => $request->user()?->can('review', $event) ?? false,
                'void' => $request->user()?->can('void', $event) ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：覆核路由先做 tenant scoped 查詢再授權，跨租戶 ID 一律 404，避免 IDOR 存在性探測與跨分店狀態竄改。
     */
    public function review(ReviewAccountingEventRequest $request, int $accountingEvent): RedirectResponse
    {
        $event = $this->scopedEventQuery($request->user())
            ->with('reviewer:id,name')
            ->whereKey($accountingEvent)
            ->firstOrFail();

        $this->authorize('review', $event);
        abort_unless($event->status === 'pending', 403);

        $validated = $request->validated();
        $oldValues = $this->reviewAuditSnapshot($event, 'old_status');

        DB::transaction(function () use ($request, $event, $validated, $oldValues): void {
            // 技術註解：只更新覆核 allowlist 欄位，不接收前端狀態、金額、payload、tenant 或 journal 欄位，避免權限提升與會計認列被注入。
            $event->forceFill([
                'status' => 'reviewed',
                'review_note' => $validated['review_note'] ?? null,
                'reviewed_by' => (int) $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $event->load('reviewer:id,name');

            $this->auditLogService->log(
                $request->user(),
                'accounting_event.reviewed',
                'Accounting event reviewed',
                null,
                ['module' => 'accounting_events'],
                $event,
                $oldValues,
                $this->reviewAuditSnapshot($event, 'new_status'),
                $request,
                'accounting_event.reviewed',
            );
        });

        return redirect()->route('employee-system.accounting.events.show', $event->id)
            ->with('success', '會計事件已覆核');
    }

    /**
     * 技術註解：作廢只處理未轉傳票的候選事件；跨租戶先 404，再由 Policy 檢查 void 權限與狀態，避免 IDOR 與權限擴張。
     */
    public function void(VoidAccountingEventRequest $request, int $accountingEvent): RedirectResponse
    {
        $event = $this->scopedEventQuery($request->user())
            ->with(['reviewer:id,name', 'voider:id,name'])
            ->whereKey($accountingEvent)
            ->firstOrFail();

        $this->authorize('void', $event);
        abort_unless(in_array($event->status, ['pending', 'reviewed'], true), 403);
        abort_unless($event->converted_journal_entry_id === null, 403);
        abort_unless($event->voided_at === null, 403);

        $validated = $request->validated();
        $oldValues = $this->voidAuditSnapshot($event, 'old_status');

        DB::transaction(function () use ($request, $event, $validated, $oldValues): void {
            // 技術註解：只更新作廢 allowlist 欄位，不清除 review 欄位、不接收 journal 或認列欄位，避免尚未設計的沖銷/退款流程被注入。
            $event->forceFill([
                'status' => 'voided',
                'void_reason' => $validated['void_reason'],
                'voided_by' => (int) $request->user()->id,
                'voided_at' => now(),
            ])->save();

            $event->load(['reviewer:id,name', 'voider:id,name']);

            $this->auditLogService->log(
                $request->user(),
                'accounting_event.voided',
                'Accounting event voided',
                null,
                ['module' => 'accounting_events'],
                $event,
                $oldValues,
                $this->voidAuditSnapshot($event, 'new_status'),
                $request,
                'accounting_event.voided',
            );
        });

        return redirect()->route('employee-system.accounting.events.show', $event->id)
            ->with('success', '會計事件已作廢');
    }

    private function scopedEventQuery(?Authenticatable $user): Builder
    {
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        return AccountingEvent::query()
            ->where('company_id', $userCompanyId)
            ->when($userBranchId !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($userBranchId): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $userBranchId);
            }));
    }

    /**
     * @param array<string, string> $sourceTypes
     * @param array<string, string> $eventTypes
     * @param array<string, string> $statuses
     * @return array<string, mixed>
     */
    private function eventListPayload(AccountingEvent $event, array $sourceTypes, array $eventTypes, array $statuses): array
    {
        return [
            'id' => $event->id,
            'source_type' => $event->source_type,
            'source_type_label' => $sourceTypes[$event->source_type] ?? $event->source_type,
            'source_id' => $event->source_id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
            'event_type_label' => $eventTypes[$event->event_type] ?? $event->event_type,
            'event_date' => optional($event->event_date)->toDateString(),
            'status' => $event->status,
            'status_label' => $statuses[$event->status] ?? $event->status,
            'currency' => $event->currency,
            'amount' => (string) $event->amount,
            'created_by_name' => $event->creator?->name,
            'reviewed_by_name' => $event->reviewer?->name,
            'voided_at' => optional($event->voided_at)->toISOString(),
            'converted_journal_entry' => $this->convertedJournalPayload($event),
            'created_at' => optional($event->created_at)->toISOString(),
            'updated_at' => optional($event->updated_at)->toISOString(),
        ];
    }

    /**
     * @param array<string, string> $sourceTypes
     * @param array<string, string> $eventTypes
     * @param array<string, string> $statuses
     * @return array<string, mixed>
     */
    private function eventDetailPayload(AccountingEvent $event, array $sourceTypes, array $eventTypes, array $statuses): array
    {
        return [
            'id' => $event->id,
            'source_type' => $event->source_type,
            'source_type_label' => $sourceTypes[$event->source_type] ?? $event->source_type,
            'source_id' => $event->source_id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
            'event_type_label' => $eventTypes[$event->event_type] ?? $event->event_type,
            'event_date' => optional($event->event_date)->toDateString(),
            'status' => $event->status,
            'status_label' => $statuses[$event->status] ?? $event->status,
            'currency' => $event->currency,
            'amount' => (string) $event->amount,
            'payload' => $this->sanitizePayload($event->payload ?? []),
            'review_note' => $event->review_note,
            'reviewed_at' => optional($event->reviewed_at)->toISOString(),
            'company' => $event->company ? ['id' => $event->company->id, 'name' => $event->company->name] : null,
            'branch' => $event->branch ? ['id' => $event->branch->id, 'name' => $event->branch->name] : null,
            'creator' => $event->creator ? ['id' => $event->creator->id, 'name' => $event->creator->name] : null,
            'reviewer' => $event->reviewer ? ['id' => $event->reviewer->id, 'name' => $event->reviewer->name] : null,
            'voider' => $event->voider ? ['id' => $event->voider->id, 'name' => $event->voider->name] : null,
            'converted_journal_entry' => $this->convertedJournalPayload($event),
            'voided_at' => optional($event->voided_at)->toISOString(),
            'void_reason' => $event->void_reason,
            'created_at' => optional($event->created_at)->toISOString(),
            'updated_at' => optional($event->updated_at)->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function convertedJournalPayload(AccountingEvent $event): ?array
    {
        if (! $event->convertedJournalEntry) {
            return null;
        }

        return [
            'id' => $event->convertedJournalEntry->id,
            'journal_number' => $event->convertedJournalEntry->journal_number,
            'status' => $event->convertedJournalEntry->status,
            'entry_date' => optional($event->convertedJournalEntry->entry_date)->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewAuditSnapshot(AccountingEvent $event, string $statusKey): array
    {
        return [
            'id' => $event->id,
            'source_type' => $event->source_type,
            'source_id' => $event->source_id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
            $statusKey => $event->status,
            'review_note' => $event->review_note,
            'reviewed_by_name' => $event->reviewer?->name,
            'reviewed_at' => optional($event->reviewed_at)->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function voidAuditSnapshot(AccountingEvent $event, string $statusKey): array
    {
        return [
            'id' => $event->id,
            'source_type' => $event->source_type,
            'source_id' => $event->source_id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
            $statusKey => $event->status,
            'void_reason' => $event->void_reason,
            'voided_by_name' => $event->voider?->name,
            'voided_at' => optional($event->voided_at)->toISOString(),
            'reviewed_by_name' => $event->reviewer?->name,
            'reviewed_at' => optional($event->reviewed_at)->toISOString(),
        ];
    }

    /**
     * @param mixed $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $blockedKeys = array_flip([
            'company_id',
            'branch_id',
            'id_number',
            'birthday',
            'address',
            'customer_sensitive',
            'customer_sensitive_fields',
            'profit',
            'gross_profit',
            'gross_margin',
            'gross_margin_rate',
            'profit_rate',
            'purchase_cost',
            'cogs_amount',
            'revenue_amount',
            'accounting_event_id',
            'journal_entry_id',
        ]);

        // 技術註解：payload 可能來自未來後端候選摘要，遞迴移除敏感與毛利/認列鍵，避免 read-only UI 變成資料外洩面。
        $sanitize = function (array $items) use (&$sanitize, $blockedKeys): array {
            $sanitized = [];

            foreach ($items as $key => $value) {
                if (is_string($key) && isset($blockedKeys[$key])) {
                    continue;
                }

                $sanitized[$key] = is_array($value) ? $sanitize($value) : $value;
            }

            return $sanitized;
        };

        return $sanitize($payload);
    }
}
