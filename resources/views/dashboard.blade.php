<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LaraWebhook Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
<div class="min-h-screen" x-data="webhookDashboard()">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900">
                🪝 LaraWebhook Dashboard
            </h1>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Service Filter -->
                <div>
                    <label for="service" class="block text-sm font-medium text-gray-700 mb-1">Service</label>
                    <select
                        x-model="filters.service"
                        @change="fetchLogs()"
                        id="service"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Services</option>
                        @foreach($services as $service)
                            <option value="{{ $service }}">{{ ucfirst($service) }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select
                        x-model="filters.status"
                        @change="fetchLogs()"
                        id="status"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <!-- Date Filter -->
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input
                        x-model="filters.date"
                        @change="fetchLogs()"
                        type="date"
                        id="date"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Per Page -->
                <div>
                    <label for="per_page" class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                    <select
                        x-model="filters.per_page"
                        @change="fetchLogs()"
                        id="per_page"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Reset Button -->
            <div class="mt-4">
                <button
                    @click="resetFilters()"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition"
                >
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
            <p class="mt-4 text-gray-600">Loading webhook logs...</p>
        </div>

        <!-- Error State -->
        <div x-show="error" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800" x-text="error"></p>
        </div>

        <!-- Logs Table -->
        <div x-show="!loading && !error" x-cloak class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Empty State -->
            <div x-show="logs.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No webhook logs</h3>
                <p class="mt-1 text-sm text-gray-500">No logs match your current filters.</p>
            </div>

            <!-- Table -->
            <table x-show="logs.length > 0" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempt</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="log in logs" :key="log.id">
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.id"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800" x-text="log.service"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="log.event"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="log.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                        x-text="log.status"
                                    ></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="log.attempt"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="log.created_at"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button
                                @click="viewPayload(log)"
                                class="text-blue-600 hover:text-blue-900 mr-3"
                            >
                                View
                            </button>
                            <button
                                @click="replay(log.id)"
                                class="text-green-600 hover:text-green-900"
                                :disabled="replaying === log.id"
                            >
                                <span x-show="replaying !== log.id">Replay</span>
                                <span x-show="replaying === log.id">...</span>
                            </button>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>

            <!-- Pagination -->
            <div x-show="meta.total > 0" class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button
                        @click="goToPage(meta.current_page - 1)"
                        :disabled="!links.prev"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <button
                        @click="goToPage(meta.current_page + 1)"
                        :disabled="!links.next"
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium" x-text="(meta.current_page - 1) * meta.per_page + 1"></span>
                            to
                            <span class="font-medium" x-text="Math.min(meta.current_page * meta.per_page, meta.total)"></span>
                            of
                            <span class="font-medium" x-text="meta.total"></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <button
                                @click="goToPage(meta.current_page - 1)"
                                :disabled="!links.prev"
                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span class="sr-only">Previous</span>
                                ‹
                            </button>
                            <template x-for="page in paginationPages" :key="page">
                                <button
                                    @click="goToPage(page)"
                                    :class="page === meta.current_page
                                            ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                    x-text="page"
                                ></button>
                            </template>
                            <button
                                @click="goToPage(meta.current_page + 1)"
                                :disabled="!links.next"
                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span class="sr-only">Next</span>
                                ›
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Payload Modal -->
    <div
        x-show="selectedLog"
        @click.self="selectedLog = null"
        x-cloak
        class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50"
    >
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[80vh] overflow-hidden" @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Webhook Payload</h3>
                <button @click="selectedLog = null" class="text-gray-400 hover:text-gray-500">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
                <template x-if="selectedLog">
                    <div>
                        <div class="mb-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-semibold text-gray-700">ID:</span>
                                <span class="text-gray-600" x-text="selectedLog.id"></span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Service:</span>
                                <span class="text-gray-600" x-text="selectedLog.service"></span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Event:</span>
                                <span class="text-gray-600" x-text="selectedLog.event"></span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Status:</span>
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="selectedLog.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    x-text="selectedLog.status"
                                ></span>
                            </div>
                            <div class="col-span-2" x-show="selectedLog.error_message">
                                <span class="font-semibold text-gray-700">Error:</span>
                                <span class="text-red-600" x-text="selectedLog.error_message"></span>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Payload:</h4>
                            <pre class="bg-gray-50 border border-gray-200 rounded p-4 text-xs overflow-x-auto"><code x-text="JSON.stringify(selectedLog.payload, null, 2)"></code></pre>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
    function webhookDashboard() {
        return {
            logs: [],
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0
            },
            links: {
                first: null,
                last: null,
                prev: null,
                next: null
            },
            filters: {
                service: '',
                status: '',
                date: '',
                per_page: 10
            },
            loading: false,
            error: null,
            selectedLog: null,
            replaying: null,

            init() {
                this.fetchLogs();
            },

            async fetchLogs(page = 1) {
                this.loading = true;
                this.error = null;

                try {
                    const params = new URLSearchParams({
                        page: page,
                        per_page: this.filters.per_page,
                        ...(this.filters.service && { service: this.filters.service }),
                        ...(this.filters.status && { status: this.filters.status }),
                        ...(this.filters.date && { date: this.filters.date })
                    });

                    const response = await fetch(`/api/larawebhook/logs?${params}`);

                    if (!response.ok) {
                        throw new Error('Failed to fetch webhook logs');
                    }

                    const data = await response.json();
                    this.logs = data.data;
                    this.meta = data.meta;
                    this.links = data.links;
                } catch (err) {
                    this.error = err.message;
                    console.error('Error fetching logs:', err);
                } finally {
                    this.loading = false;
                }
            },

            async replay(logId) {
                if (!confirm('Are you sure you want to replay this webhook?')) {
                    return;
                }

                this.replaying = logId;

                try {
                    const response = await fetch(`/api/larawebhook/logs/${logId}/replay`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Webhook replayed successfully!');
                        this.fetchLogs(this.meta.current_page);
                    } else {
                        alert('Failed to replay webhook: ' + data.message);
                    }
                } catch (err) {
                    alert('Error replaying webhook: ' + err.message);
                    console.error('Error replaying webhook:', err);
                } finally {
                    this.replaying = null;
                }
            },

            viewPayload(log) {
                this.selectedLog = log;
            },

            resetFilters() {
                this.filters = {
                    service: '',
                    status: '',
                    date: '',
                    per_page: 10
                };
                this.fetchLogs();
            },

            goToPage(page) {
                if (page >= 1 && page <= this.meta.last_page) {
                    this.fetchLogs(page);
                }
            },

            get paginationPages() {
                const pages = [];
                const current = this.meta.current_page;
                const last = this.meta.last_page;

                // Show max 7 pages
                let start = Math.max(1, current - 3);
                let end = Math.min(last, start + 6);

                // Adjust start if we're near the end
                if (end - start < 6) {
                    start = Math.max(1, end - 6);
                }

                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }

                return pages;
            }
        }
    }
</script>

<style>
    [x-cloak] { display: none !important; }
</style>
</body>
</html>
