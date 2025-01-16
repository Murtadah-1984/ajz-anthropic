<template>
  <div class="session-monitoring">
    <!-- Header with Actions -->
    <div class="mb-8 sm:flex sm:items-center sm:justify-between">
      <div class="sm:flex-auto">
        <h1 class="text-xl font-semibold text-gray-900">Session Monitoring</h1>
        <p class="mt-2 text-sm text-gray-700">
          Monitor and manage active AI sessions, view performance metrics, and analyze session outcomes.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="startNewSession" class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto">
          Start New Session
        </button>
      </div>
    </div>

    <!-- Session Filters -->
    <div class="mb-6 bg-white shadow rounded-lg p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select v-model="filters.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Type</label>
          <select v-model="filters.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All</option>
            <option v-for="type in sessionTypes" :key="type" :value="type">
              {{ type }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Agent</label>
          <select v-model="filters.agentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All</option>
            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
              {{ agent.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Search</label>
          <input type="text" v-model="filters.search" placeholder="Search sessions..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
      </div>
    </div>

    <!-- Active Sessions -->
    <div class="bg-white shadow rounded-lg mb-8">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          Active Sessions
        </h3>
      </div>
      <div class="px-4 py-5 sm:p-6">
        <div class="flow-root">
          <ul role="list" class="-mb-8">
            <li v-for="session in activeSessions" :key="session.id" class="pb-8">
              <div class="relative">
                <div class="relative flex space-x-3">
                  <div>
                    <span class="h-8 w-8 rounded-full flex items-center justify-center"
                          :class="{
                            'bg-green-100': session.status === 'active',
                            'bg-yellow-100': session.status === 'paused',
                            'bg-red-100': session.status === 'failed'
                          }">
                      <span class="h-2.5 w-2.5 rounded-full"
                            :class="{
                              'bg-green-500': session.status === 'active',
                              'bg-yellow-500': session.status === 'paused',
                              'bg-red-500': session.status === 'failed'
                            }"></span>
                    </span>
                  </div>
                  <div class="flex-grow min-w-0">
                    <div class="flex justify-between items-center mb-1">
                      <div class="text-sm font-medium text-gray-900">
                        {{ session.type }} - {{ session.id }}
                      </div>
                      <div class="flex space-x-2">
                        <button v-if="session.status === 'active'" @click="pauseSession(session)" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                          Pause
                        </button>
                        <button v-if="session.status === 'paused'" @click="resumeSession(session)" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                          Resume
                        </button>
                        <button @click="stopSession(session)" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                          Stop
                        </button>
                      </div>
                    </div>
                    <div class="mt-1 text-sm text-gray-500">
                      Started {{ session.startedAt }} by {{ session.agent.name }}
                    </div>
                    <div class="mt-2">
                      <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                          <span class="text-sm font-medium text-gray-500">Progress:</span>
                          <div class="ml-2 w-32 bg-gray-200 rounded-full h-2.5">
                            <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: `${session.progress}%` }"></div>
                          </div>
                          <span class="ml-2 text-sm text-gray-500">{{ session.progress }}%</span>
                        </div>
                        <div>
                          <span class="text-sm font-medium text-gray-500">Memory:</span>
                          <span class="ml-1 text-sm text-gray-900">{{ session.metrics.memory }}MB</span>
                        </div>
                        <div>
                          <span class="text-sm font-medium text-gray-500">CPU:</span>
                          <span class="ml-1 text-sm text-gray-900">{{ session.metrics.cpu }}%</span>
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

    <!-- Session History -->
    <div class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          Session History
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
                        Agent
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Duration
                      </th>
                      <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Actions</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="session in sessionHistory" :key="session.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ session.id }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ session.type }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ session.agent.name }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                              :class="{
                                'bg-green-100 text-green-800': session.status === 'completed',
                                'bg-red-100 text-red-800': session.status === 'failed'
                              }">
                          {{ session.status }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ session.duration }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button @click="viewSessionDetails(session)" class="text-indigo-600 hover:text-indigo-900">
                          View Details
                        </button>
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

    <!-- New Session Modal -->
    <div v-if="showNewSessionModal" class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
          <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
              Start New Session
            </h3>
            <div class="mt-4">
              <form @submit.prevent="createSession">
                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Session Type</label>
                    <select v-model="sessionForm.type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                      <option v-for="type in sessionTypes" :key="type" :value="type">
                        {{ type }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Agent</label>
                    <select v-model="sessionForm.agentId" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                      <option v-for="agent in availableAgents" :key="agent.id" :value="agent.id">
                        {{ agent.name }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Configuration</label>
                    <textarea v-model="sessionForm.configuration" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Enter JSON configuration..."></textarea>
                  </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                  <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm">
                    Start Session
                  </button>
                  <button type="button" @click="closeNewSessionModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SessionMonitoring',
  data() {
    return {
      filters: {
        status: '',
        type: '',
        agentId: '',
        search: ''
      },
      sessionTypes: [
        'CollaborativeSession',
        'DatabaseOptimizationSession',
        'DevOpsOptimizationSession',
        'DocumentationSprintSession',
        'FeatureDiscoverySession',
        'PerformanceOptimizationSession',
        'SecurityAuditSession',
        'TechnicalDebtPrioritizationSession'
      ],
      agents: [
        {
          id: 1,
          name: 'Development Agent',
          status: 'online'
        },
        {
          id: 2,
          name: 'Documentation Agent',
          status: 'online'
        }
      ],
      activeSessions: [
        {
          id: 'sess_123',
          type: 'CollaborativeSession',
          status: 'active',
          startedAt: '2024-01-15 14:30',
          agent: { name: 'Development Agent' },
          progress: 75,
          metrics: {
            memory: 256,
            cpu: 15
          }
        }
      ],
      sessionHistory: [
        {
          id: 'sess_122',
          type: 'SecurityAuditSession',
          status: 'completed',
          agent: { name: 'Security Agent' },
          duration: '45m 30s'
        }
      ],
      showNewSessionModal: false,
      sessionForm: {
        type: '',
        agentId: '',
        configuration: ''
      }
    }
  },
  computed: {
    availableAgents() {
      return this.agents.filter(agent => agent.status === 'online')
    }
  },
  methods: {
    startNewSession() {
      this.sessionForm = {
        type: '',
        agentId: '',
        configuration: ''
      }
      this.showNewSessionModal = true
    },
    async createSession() {
      try {
        // TODO: Implement API call to create session
        const newSession = {
          id: `sess_${Date.now()}`,
          type: this.sessionForm.type,
          status: 'active',
          startedAt: new Date().toISOString(),
          agent: this.agents.find(a => a.id === this.sessionForm.agentId),
          progress: 0,
          metrics: {
            memory: 0,
            cpu: 0
          }
        }
        this.activeSessions.push(newSession)
        this.closeNewSessionModal()
      } catch (error) {
        console.error('Error creating session:', error)
        // TODO: Implement error handling
      }
    },
    closeNewSessionModal() {
      this.showNewSessionModal = false
    },
    async pauseSession(session) {
      try {
        // TODO: Implement API call to pause session
        session.status = 'paused'
      } catch (error) {
        console.error('Error pausing session:', error)
        // TODO: Implement error handling
      }
    },
    async resumeSession(session) {
      try {
        // TODO: Implement API call to resume session
        session.status = 'active'
      } catch (error) {
        console.error('Error resuming session:', error)
        // TODO: Implement error handling
      }
    },
    async stopSession(session) {
      try {
        // TODO: Implement API call to stop session
        const index = this.activeSessions.findIndex(s => s.id === session.id)
        if (index !== -1) {
          this.activeSessions.splice(index, 1)
          session.status = 'completed'
          this.sessionHistory.unshift({
            ...session,
            duration: '30m 15s' // Calculate actual duration
          })
        }
      } catch (error) {
        console.error('Error stopping session:', error)
        // TODO: Implement error handling
      }
    },
    viewSessionDetails(session) {
      // TODO: Implement session details view
      console.log('View session details:', session)
    }
  }
}
</script>
