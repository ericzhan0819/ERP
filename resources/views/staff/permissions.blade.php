<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('員工權限管理') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">員工列表</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">顯示目前系統可管理的員工與既有角色/權限資訊。</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">姓名</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">角色</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">權限</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($staff as $member)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $member['name'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $member['email'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ collect($member['roles'])->join(', ') ?: '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ collect($member['permissions'])->join(', ') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">目前沒有員工資料。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">權限矩陣</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">矩陣由後端傳入的模塊與權限動態渲染。</p>
                </div>

                @if ($permissionMatrix->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        目前尚無模塊/權限資料，待系統建立權限後將自動顯示於此矩陣。
                    </div>
                @else
                    <div class="p-6 space-y-6">
                        @foreach ($permissionMatrix as $moduleData)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ $moduleData['module'] }}</h4>
                                </div>
                                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach ($moduleData['permissions'] as $permissionName)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500"
                                                disabled
                                            >
                                            <span>{{ $permissionName }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
