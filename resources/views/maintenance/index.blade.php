@extends('anthropic::layouts.app')

@section('title', 'Maintenance Windows')

@section('content')
<div class="min-h-screen bg-gray-100" x-data="maintenanceWindows()">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-900">Maintenance Windows</h1>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Create Window
                </button>
            </div>
        </div>
    </header>

    <!-- Stats -->
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="px-4 py-5 bg-white shadow rounded-lg overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Active Windows</dt>
                <dd class="mt-1 text-3xl font-semibold text-indigo-600">@{{ stats.active }}</dd>
            </div>
            <div class="px-4 py-5 bg-white shadow rounded-lg overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Pending Windows</dt>
                <dd class="mt-1 text-3xl font-semibold text-yellow-600">@{{ stats.pending }}</dd>
            </div>
            <div class="px-4 py-5 bg-white shadow rounded-lg overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Total Windows</dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">@{{ stats.total }}</dd>
            </div>
        </dl>
    </div>

    <!-- Filters -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Environment</label>
                    <select
                        x-model="filters.environment"
                        @change="loadWindows()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    >
                        <option value="">All Environments</option>
                        @foreach($environments as $env)
                            <option value="{{ $env }}">{{ ucfirst($env) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select
                        x-model="filters.status"
                        @change="loadWindows()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sort By</label>
                    <select
                        x-model="filters.sort"
                        @change="loadWindows()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    >
                        <option value="start_time">Start Time</option>
                        <option value="duration">Duration</option>
                        <option value="environment">Environment</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Windows List -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul role="list" class="divide-y divide-gray-200">
                <template x-for="window in windows" :key="window.id">
                    <li>
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div :class="{
                                        'bg-green-100 text-green-800': window.status === 'active',
                                        'bg-yellow-100 text-yellow-800': window.status === 'pending',
                                        'bg-gray-100 text-gray-800': window.status === 'expired'
                                    }" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        <span x-text="window.status"></span>
                                    </div>
                                    <p class="ml-4 text-sm font-medium text-indigo-600" x-text="window.environment"></p>
                                </div>
                                <div class="flex space-x-2">
                                    <button
                                        @click="showEditModal(window)"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="extendWindow(window)"
                                        x-show="window.status === 'active'"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200"
                                    >
                                        Extend
                                    </button>
                                    <button
                                        @click="endWindow(window)"
                                        x-show="window.status === 'active'"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200"
                                    >
                                        End
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 sm:flex sm:justify-between">
                                <div class="sm:flex">
                                    <p class="flex items-center text-sm text-gray-500">
                                        <span class="truncate" x-text="window.comment"></span>
                                    </p>
                                </div>
                                <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                    <p>
                                        <span x-text="formatDate(window.start_time)"></span>
                                        <span class="mx-1">•</span>
                                        <span x-text="window.duration + ' hours'"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    <!-- Create Modal -->
    <div
        x-show="showCreateModal"
        class="fixed z-10 inset-0 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="showCreateModal"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                @click="showCreateModal = false"
                aria-hidden="true"
            ></div>

            <div
                x-show="showCreateModal"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
            >
                <form @submit.prevent="createWindow">
                    <div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Create Maintenance Window
                            </h3>
                            <div class="mt-2">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Environment</label>
                                        <select
                                            x-model="form.environment"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                            required
                                        >
                                            @foreach($environments as $env)
                                                <option value="{{ $env }}">{{ ucfirst($env) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Start Time</label>
                                        <input
                                            type="datetime-local"
                                            x-model="form.start_time"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Duration (hours)</label>
                                        <input
                                            type="number"
                                            x-model="form.duration"
                                            min="1"
                                            max="72"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Comment</label>
                                        <textarea
                                            x-model="form.comment"
                                            rows="3"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            required
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <button
                            type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm"
                        >
                            Create
                        </button>
                        <button
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm"
                            @click="showCreateModal = false"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function maintenanceWindows() {
    return {
        windows: [],
        stats: {
            active: 0,
            pending: 0,
            total: 0
        },
        filters: {
            environment: '',
            status: '',
            sort: 'start_time'
        },
        form: {
            environment: '',
            start_time: '',
            duration: '',
            comment: ''
        },
        showCreateModal: false,
        showEditModal: false,
        editingWindow: null,

        init() {
            this.loadWindows();
            this.setupRefresh();
        },

        setupRefresh() {
            setInterval(() => this.loadWindows(), 30000);
        },

        async loadWindows() {
            try {
                const response = await fetch(`/api/maintenance-windows/active?${new URLSearchParams(this.filters)}`);
                const data = await response.json();
                this.windows = data.windows;
                this.updateStats();
            } catch (error) {
                console.error('Failed to load windows:', error);
            }
        },

        updateStats() {
            this.stats = {
                active: this.windows.filter(w => w.status === 'active').length,
                pending: this.windows.filter(w => w.status === 'pending').length,
                total: this.windows.length
            };
        },

        async createWindow() {
            try {
                const response = await fetch('/api/maintenance-windows', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(this.form)
                });

                if (!response.ok) throw new Error('Failed to create window');

                this.showCreateModal = false;
                this.resetForm();
                this.loadWindows();
            } catch (error) {
                console.error('Failed to create window:', error);
            }
        },

        resetForm() {
            this.form = {
                environment: '',
                start_time: '',
                duration: '',
                comment: ''
            };
        },

        formatDate(date) {
            return new Date(date).toLocaleString();
        }
    };
}
</script>
@endpush
