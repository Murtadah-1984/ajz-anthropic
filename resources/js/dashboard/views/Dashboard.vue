<template>
  <div class="dashboard">
    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div v-for="stat in overviewStats" :key="stat.name" class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <dt class="text-sm font-medium text-gray-500 truncate">
            {{ stat.name }}
          </dt>
          <dd class="mt-1 text-3xl font-semibold text-gray-900">
            {{ stat.value }}
          </dd>
          <div class="mt-2 flex items-center text-sm">
            <span :class="stat.trend > 0 ? 'text-green-600' : 'text-red-600'">
              {{ Math.abs(stat.trend) }}%
            </span>
            <span class="ml-2 text-gray-500">vs last period</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Agents -->
    <div class="bg-white shadow rounded-lg mb-8">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          Active Agents
        </h3>
      </div>
      <div class="px-4 py-5 sm:p-6">
        <div class="flow-root">
          <ul role="list" class="-mb-8">
            <li v-for="agent in activeAgents" :key="agent.id">
              <div class="relative pb-8">
                <div class="relative flex items-center space-x-3">
                  <div>
                    <span class="h-8 w-8 rounded-full flex items-center justify-center"
                          :class="agent.status === 'online' ? 'bg-green-100' : 'bg-yellow-100'">
                      <span class="h-2.5 w-2.5 rounded-full"
                            :class="agent.status === 'online' ? 'bg-green-500' : 'bg-yellow-500'"></span>
                    </span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900">
                      {{ agent.name }}
                    </div>
                    <div class="mt-1 text-sm text-gray-500">
                      {{ agent.currentTask }}
                    </div>
                  </div>
                  <div>
                    <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                      Details
                    </button>
                  </div>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Recent Sessions -->
    <div class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          Recent Sessions
        </h3>
      </div>
      <div class="px-4 py-5 sm:p-6">
        <div class="flex flex-col">
          <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
              <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Session ID
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Started
                      </th>
                      <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Actions</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="session in recentSessions" :key="session.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ session.id }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ session.type }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                              :class="{
                                'bg-green-100 text-green-800': session.status === 'completed',
                                'bg-yellow-100 text-yellow-800': session.status === 'in_progress',
                                'bg-red-100 text-red-800': session.status === 'failed'
                              }">
                          {{ session.status }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ session.startedAt }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="#" class="text-indigo-600 hover:text-indigo-900">View</a>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Dashboard',
  data() {
    return {
      overviewStats: [
        { name: 'Active Agents', value: '12', trend: 8 },
        { name: 'Active Sessions', value: '48', trend: 12 },
        { name: 'Success Rate', value: '98.5%', trend: 2.3 },
        { name: 'Avg Response Time', value: '245ms', trend: -5.2 }
      ],
      activeAgents: [
        {
          id: 1,
          name: 'Development Agent',
          status: 'online',
          currentTask: 'Code review and optimization'
        },
        {
          id: 2,
          name: 'Documentation Agent',
          status: 'online',
          currentTask: 'API documentation update'
        },
        {
          id: 3,
          name: 'Security Agent',
          status: 'busy',
          currentTask: 'Vulnerability assessment'
        }
      ],
      recentSessions: [
        {
          id: 'sess_123',
          type: 'CodeReview',
          status: 'completed',
          startedAt: '2024-01-15 14:30'
        },
        {
          id: 'sess_124',
          type: 'SecurityAudit',
          status: 'in_progress',
          startedAt: '2024-01-15 15:45'
        },
        {
          id: 'sess_125',
          type: 'Documentation',
          status: 'completed',
          startedAt: '2024-01-15 16:00'
        }
      ]
    }
  },
  mounted() {
    this.fetchDashboardData()
  },
  methods: {
    async fetchDashboardData() {
      try {
        // TODO: Implement API calls to fetch real data
        // const response = await this.$api.dashboard.getOverview()
        // this.overviewStats = response.stats
        // this.activeAgents = response.agents
        // this.recentSessions = response.sessions
      } catch (error) {
        console.error('Error fetching dashboard data:', error)
        // TODO: Implement error handling
      }
    }
  }
}
</script>

<style scoped>
.dashboard {
  @apply space-y-6;
}
</style>
