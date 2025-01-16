<template>
  <div class="agent-management">
    <!-- Header with Actions -->
    <div class="mb-8 sm:flex sm:items-center sm:justify-between">
      <div class="sm:flex-auto">
        <h1 class="text-xl font-semibold text-gray-900">AI Agents</h1>
        <p class="mt-2 text-sm text-gray-700">
          Manage and monitor your AI agents, their capabilities, and assignments.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="openCreateAgentModal" class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto">
          Create Agent
        </button>
      </div>
    </div>

    <!-- Agent Filters -->
    <div class="mb-6 bg-white shadow rounded-lg p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select v-model="filters.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All</option>
            <option value="online">Online</option>
            <option value="offline">Offline</option>
            <option value="busy">Busy</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Type</label>
          <select v-model="filters.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All</option>
            <option v-for="type in agentTypes" :key="type" :value="type">
              {{ type }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Capability</label>
          <select v-model="filters.capability" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All</option>
            <option v-for="cap in capabilities" :key="cap" :value="cap">
              {{ cap }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Search</label>
          <input type="text" v-model="filters.search" placeholder="Search agents..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
      </div>
    </div>

    <!-- Agents Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="agent in filteredAgents" :key="agent.id" class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <span class="h-3 w-3 rounded-full" :class="{
                'bg-green-400': agent.status === 'online',
                'bg-yellow-400': agent.status === 'busy',
                'bg-gray-400': agent.status === 'offline'
              }"></span>
              <h3 class="ml-2 text-lg font-medium text-gray-900">{{ agent.name }}</h3>
            </div>
            <div class="flex items-center space-x-2">
              <button @click="toggleAgent(agent)" class="inline-flex items-center p-1 border border-transparent rounded-full shadow-sm text-white"
                      :class="agent.status === 'online' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'">
                <span class="sr-only">{{ agent.status === 'online' ? 'Stop' : 'Start' }}</span>
                <svg class="h-5 w-5" :class="agent.status === 'online' ? 'transform rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
              </button>
              <button @click="editAgent(agent)" class="inline-flex items-center p-1 border border-transparent rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                <span class="sr-only">Edit</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
            </div>
          </div>
          <div class="mt-4">
            <p class="text-sm text-gray-500">{{ agent.description }}</p>
          </div>
          <div class="mt-4">
            <div class="text-sm font-medium text-gray-700">Capabilities</div>
            <div class="mt-2 flex flex-wrap gap-2">
              <span v-for="capability in agent.capabilities" :key="capability" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                {{ capability }}
              </span>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-sm font-medium text-gray-700">Current Task</div>
            <div class="mt-1 text-sm text-gray-500">
              {{ agent.currentTask || 'No active task' }}
            </div>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <div>
              <div class="text-sm font-medium text-gray-700">Success Rate</div>
              <div class="mt-1 text-sm font-semibold text-gray-900">
                {{ agent.metrics.successRate }}%
              </div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700">Response Time</div>
              <div class="mt-1 text-sm font-semibold text-gray-900">
                {{ agent.metrics.responseTime }}ms
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Agent Modal -->
    <div v-if="showModal" class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
          <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
              {{ editingAgent ? 'Edit Agent' : 'Create New Agent' }}
            </h3>
            <div class="mt-4">
              <form @submit.prevent="saveAgent">
                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" v-model="agentForm.name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Type</label>
                    <select v-model="agentForm.type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                      <option v-for="type in agentTypes" :key="type" :value="type">
                        {{ type }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="agentForm.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Capabilities</label>
                    <div class="mt-2 space-y-2">
                      <div v-for="cap in capabilities" :key="cap" class="flex items-center">
                        <input type="checkbox" :id="cap" v-model="agentForm.capabilities" :value="cap" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label :for="cap" class="ml-2 text-sm text-gray-700">{{ cap }}</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                  <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm">
                    {{ editingAgent ? 'Save Changes' : 'Create Agent' }}
                  </button>
                  <button type="button" @click="closeModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm">
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
  name: 'AgentManagement',
  data() {
    return {
      filters: {
        status: '',
        type: '',
        capability: '',
        search: ''
      },
      agents: [
        {
          id: 1,
          name: 'Development Agent',
          type: 'Development',
          status: 'online',
          description: 'Specialized in code review and optimization',
          capabilities: ['code_analysis', 'performance_optimization', 'refactoring'],
          currentTask: 'Analyzing code quality',
          metrics: {
            successRate: 98,
            responseTime: 245
          }
        },
        {
          id: 2,
          name: 'Documentation Agent',
          type: 'Documentation',
          status: 'online',
          description: 'Manages technical documentation and API docs',
          capabilities: ['documentation', 'api_documentation', 'markdown'],
          currentTask: 'Updating API documentation',
          metrics: {
            successRate: 99,
            responseTime: 180
          }
        }
      ],
      agentTypes: [
        'Development',
        'Documentation',
        'Security',
        'Testing',
        'DevOps',
        'Analytics'
      ],
      capabilities: [
        'code_analysis',
        'performance_optimization',
        'refactoring',
        'documentation',
        'api_documentation',
        'security_analysis',
        'testing',
        'deployment',
        'monitoring'
      ],
      showModal: false,
      editingAgent: null,
      agentForm: {
        name: '',
        type: '',
        description: '',
        capabilities: []
      }
    }
  },
  computed: {
    filteredAgents() {
      return this.agents.filter(agent => {
        if (this.filters.status && agent.status !== this.filters.status) return false
        if (this.filters.type && agent.type !== this.filters.type) return false
        if (this.filters.capability && !agent.capabilities.includes(this.filters.capability)) return false
        if (this.filters.search) {
          const search = this.filters.search.toLowerCase()
          return agent.name.toLowerCase().includes(search) ||
                 agent.description.toLowerCase().includes(search)
        }
        return true
      })
    }
  },
  methods: {
    openCreateAgentModal() {
      this.editingAgent = null
      this.agentForm = {
        name: '',
        type: '',
        description: '',
        capabilities: []
      }
      this.showModal = true
    },
    editAgent(agent) {
      this.editingAgent = agent
      this.agentForm = {
        name: agent.name,
        type: agent.type,
        description: agent.description,
        capabilities: [...agent.capabilities]
      }
      this.showModal = true
    },
    async saveAgent() {
      try {
        if (this.editingAgent) {
          // TODO: Implement API call to update agent
          const index = this.agents.findIndex(a => a.id === this.editingAgent.id)
          this.agents[index] = {
            ...this.editingAgent,
            ...this.agentForm
          }
        } else {
          // TODO: Implement API call to create agent
          const newAgent = {
            id: this.agents.length + 1,
            ...this.agentForm,
            status: 'offline',
            currentTask: null,
            metrics: {
              successRate: 0,
              responseTime: 0
            }
          }
          this.agents.push(newAgent)
        }
        this.closeModal()
      } catch (error) {
        console.error('Error saving agent:', error)
        // TODO: Implement error handling
      }
    },
    closeModal() {
      this.showModal = false
      this.editingAgent = null
      this.agentForm = {
        name: '',
        type: '',
        description: '',
        capabilities: []
      }
    },
    async toggleAgent(agent) {
      try {
        // TODO: Implement API call to start/stop agent
        agent.status = agent.status === 'online' ? 'offline' : 'online'
      } catch (error) {
        console.error('Error toggling agent:', error)
        // TODO: Implement error handling
      }
    }
  }
}
</script>
