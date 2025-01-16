<template>
  <div class="analytics">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-xl font-semibold text-gray-900">Analytics</h1>
      <p class="mt-2 text-sm text-gray-700">
        Comprehensive analytics and insights for AI agents and sessions.
      </p>
    </div>

    <!-- Time Range Filter -->
    <div class="mb-6 bg-white shadow rounded-lg p-4">
      <div class="sm:flex sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
        <div class="sm:w-64">
          <label class="block text-sm font-medium text-gray-700">Time Range</label>
          <select v-model="timeRange" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="24h">Last 24 Hours</option>
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="custom">Custom Range</option>
          </select>
        </div>
        <div v-if="timeRange === 'custom'" class="sm:flex sm:space-x-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Start Date</label>
            <input type="date" v-model="customRange.start" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">End Date</label>
            <input type="date" v-model="customRange.end" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          </div>
        </div>
      </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div v-for="stat in overviewStats" :key="stat.name" class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <dt class="text-sm font-medium text-gray-500 truncate">{{ stat.name }}</dt>
          <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ stat.value }}</dd>
          <div class="mt-2 flex items-center text-sm">
            <span :class="stat.trend > 0 ? 'text-green-600' : 'text-red-600'">
              {{ Math.abs(stat.trend) }}%
            </span>
            <span class="ml-2 text-gray-500">vs previous period</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Performance Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Response Time Distribution -->
      <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Response Time Distribution</h3>
        <div class="h-64">
          <!-- TODO: Implement chart component -->
          <div class="flex items-center justify-center h-full text-gray-500">
            Chart placeholder
          </div>
        </div>
      </div>

      <!-- Success Rate Trends -->
      <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Success Rate Trends</h3>
        <div class="h-64">
          <!-- TODO: Implement chart component -->
          <div class="flex items-center justify-center h-full text-gray-500">
            Chart placeholder
          </div>
        </div>
      </div>
    </div>

    <!-- Agent Performance -->
    <div class="bg-white shadow rounded-lg mb-8">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg font-medium text-gray-900">Agent Performance</h3>
      </div>
      <div class="px-4 py-5 sm:p-6">
        <div class="flow-root">
          <ul role="list" class="-mb-8">
            <li v-for="agent in agentPerformance" :key="agent.id" class="pb-8">
              <div class="relative">
                <div class="relative flex items-center space-x-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                      <p class="text-sm font-medium text-gray-900">
                        {{ agent.name }}
                      </p>
                      <p class="text-sm text-gray-500">
                        {{ agent.sessionCount }} sessions
                      </p>
                    </div>
                    <div class="mt-4 space-y-4">
                      <!-- Success Rate -->
                      <div>
                        <div class="flex items-center justify-between">
                          <div class="text-sm font-medium text-gray-500">Success Rate</div>
                          <div class="text-sm font-medium text-gray-900">{{ agent.metrics.successRate }}%</div>
                        </div>
                        <div class="mt-1">
                          <div class="bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-2 bg-green-500 rounded-full" :style="{ width: `${agent.metrics.successRate}%` }"></div>
                          </div>
                        </div>
                      </div>
                      <!-- Response Time -->
                      <div>
                        <div class="flex items-center justify-between">
                          <div class="text-sm font-medium text-gray-500">Avg Response Time</div>
                          <div class="text-sm font-medium text-gray-900">{{ agent.metrics.responseTime }}ms</div>
                        </div>
                        <div class="mt-1">
                          <div class="bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-2 bg-blue-500 rounded-full" :style="{ width: `${(agent.metrics.responseTime / 1000) * 100}%` }"></div>
                          </div>
                        </div>
                      </div>
                      <!-- Resource Usage -->
                      <div>
                        <div class="flex items-center justify-between">
                          <div class="text-sm font-medium text-gray-500">Resource Usage</div>
                          <div class="text-sm font-medium text-gray-900">{{ agent.metrics.resourceUsage }}%</div>
                        </div>
                        <div class="mt-1">
                          <div class="bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-2 bg-yellow-500 rounded-full" :style="{ width: `${agent.metrics.resourceUsage}%` }"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Session Analytics -->
    <div class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg font-medium text-gray-900">Session Analytics</h3>
      </div>
      <div class="px-4 py-5 sm:p-6">
        <div class="space-y-8">
          <!-- Session Types Distribution -->
          <div>
            <h4 class="text-base font-medium text-gray-900 mb-4">Session Types Distribution</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="type in sessionTypeStats" :key="type.name" class="bg-gray-50 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium text-gray-700">{{ type.name }}</span>
                  <span class="text-sm font-medium text-gray-900">{{ type.count }}</span>
                </div>
                <div class="bg-gray-200 rounded-full overflow-hidden">
                  <div class="h-2 bg-indigo-500 rounded-full" :style="{ width: `${type.percentage}%` }"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Session Duration Analysis -->
          <div>
            <h4 class="text-base font-medium text-gray-900 mb-4">Session Duration Analysis</h4>
            <div class="h-64">
              <!-- TODO: Implement chart component -->
              <div class="flex items-center justify-center h-full text-gray-500">
                Chart placeholder
              </div>
            </div>
          </div>

          <!-- Error Analysis -->
          <div>
            <h4 class="text-base font-medium text-gray-900 mb-4">Error Analysis</h4>
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
              <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                  <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Error Type</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Occurrences</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Impact</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Resolution Time</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                  <tr v-for="error in errorAnalysis" :key="error.type">
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ error.type }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ error.occurrences }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ error.impact }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ error.resolutionTime }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Analytics',
  data() {
    return {
      timeRange: '24h',
      customRange: {
        start: '',
        end: ''
      },
      overviewStats: [
        { name: 'Total Sessions', value: '1,234', trend: 12 },
        { name: 'Success Rate', value: '98.5%', trend: 2.3 },
        { name: 'Avg Response Time', value: '245ms', trend: -5.2 },
        { name: 'Resource Usage', value: '68%', trend: 3.1 }
      ],
      agentPerformance: [
        {
          id: 1,
          name: 'Development Agent',
          sessionCount: 456,
          metrics: {
            successRate: 98,
            responseTime: 245,
            resourceUsage: 65
          }
        },
        {
          id: 2,
          name: 'Documentation Agent',
          sessionCount: 324,
          metrics: {
            successRate: 99,
            responseTime: 180,
            resourceUsage: 45
          }
        }
      ],
      sessionTypeStats: [
        { name: 'Development', count: 450, percentage: 36 },
        { name: 'Documentation', count: 320, percentage: 26 },
        { name: 'Security', count: 280, percentage: 22 },
        { name: 'Testing', count: 200, percentage: 16 }
      ],
      errorAnalysis: [
        {
          type: 'API Timeout',
          occurrences: 45,
          impact: 'Medium',
          resolutionTime: '2m 30s'
        },
        {
          type: 'Memory Limit',
          occurrences: 23,
          impact: 'High',
          resolutionTime: '5m 15s'
        },
        {
          type: 'Invalid Input',
          occurrences: 78,
          impact: 'Low',
          resolutionTime: '1m 10s'
        }
      ]
    }
  },
  watch: {
    timeRange(newValue) {
      if (newValue !== 'custom') {
        this.fetchAnalytics(newValue)
      }
    },
    'customRange.start'() {
      if (this.timeRange === 'custom' && this.customRange.start && this.customRange.end) {
        this.fetchAnalytics('custom')
      }
    },
    'customRange.end'() {
      if (this.timeRange === 'custom' && this.customRange.start && this.customRange.end) {
        this.fetchAnalytics('custom')
      }
    }
  },
  methods: {
    async fetchAnalytics(range) {
      try {
        // TODO: Implement API call to fetch analytics data
        console.log('Fetching analytics for range:', range)
      } catch (error) {
        console.error('Error fetching analytics:', error)
        // TODO: Implement error handling
      }
    }
  },
  mounted() {
    this.fetchAnalytics(this.timeRange)
  }
}
</script>
