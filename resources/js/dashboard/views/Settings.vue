<template>
  <div class="settings">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-xl font-semibold text-gray-900">Settings</h1>
      <p class="mt-2 text-sm text-gray-700">
        Configure system settings, manage API keys, and customize preferences.
      </p>
    </div>

    <!-- Settings Navigation -->
    <div class="bg-white shadow rounded-lg">
      <div class="divide-y divide-gray-200">
        <!-- General Settings -->
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">General Settings</h2>
          <div class="space-y-6">
            <!-- System Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700">System Name</label>
              <input type="text" v-model="settings.general.systemName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <!-- Environment -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Environment</label>
              <select v-model="settings.general.environment" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="production">Production</option>
                <option value="staging">Staging</option>
                <option value="development">Development</option>
              </select>
            </div>

            <!-- Debug Mode -->
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-sm font-medium text-gray-700">Debug Mode</label>
                <p class="text-sm text-gray-500">Enable detailed logging and debugging information</p>
              </div>
              <button type="button" @click="settings.general.debugMode = !settings.general.debugMode" :class="[settings.general.debugMode ? 'bg-indigo-600' : 'bg-gray-200']" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <span :class="[settings.general.debugMode ? 'translate-x-5' : 'translate-x-0']" class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                  <span :class="[settings.general.debugMode ? 'opacity-0 ease-out duration-100' : 'opacity-100 ease-in duration-200']" class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                    <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                      <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </span>
                  <span :class="[settings.general.debugMode ? 'opacity-100 ease-in duration-200' : 'opacity-0 ease-out duration-100']" class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                    <svg class="h-3 w-3 text-indigo-600" fill="currentColor" viewBox="0 0 12 12">
                      <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />
                    </svg>
                  </span>
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- API Configuration -->
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">API Configuration</h2>
          <div class="space-y-6">
            <!-- API Key -->
            <div>
              <label class="block text-sm font-medium text-gray-700">API Key</label>
              <div class="mt-1 flex rounded-md shadow-sm">
                <input type="password" v-model="settings.api.apiKey" class="flex-1 rounded-none rounded-l-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <button type="button" class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm" @click="regenerateApiKey">
                  Regenerate
                </button>
              </div>
            </div>

            <!-- Rate Limiting -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Rate Limiting (requests per minute)</label>
              <input type="number" v-model="settings.api.rateLimit" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <!-- Timeout -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Request Timeout (seconds)</label>
              <input type="number" v-model="settings.api.timeout" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
          </div>
        </div>

        <!-- Agent Configuration -->
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Agent Configuration</h2>
          <div class="space-y-6">
            <!-- Default Model -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Default AI Model</label>
              <select v-model="settings.agent.defaultModel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option v-for="model in aiModels" :key="model.id" :value="model.id">
                  {{ model.name }}
                </option>
              </select>
            </div>

            <!-- Memory Limit -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Memory Limit (MB)</label>
              <input type="number" v-model="settings.agent.memoryLimit" min="128" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <!-- Concurrent Sessions -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Max Concurrent Sessions</label>
              <input type="number" v-model="settings.agent.maxConcurrentSessions" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
          </div>
        </div>

        <!-- Notification Settings -->
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Notification Settings</h2>
          <div class="space-y-6">
            <!-- Email Notifications -->
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-sm font-medium text-gray-700">Email Notifications</label>
                <p class="text-sm text-gray-500">Receive important system notifications via email</p>
              </div>
              <button type="button" @click="settings.notifications.email = !settings.notifications.email" :class="[settings.notifications.email ? 'bg-indigo-600' : 'bg-gray-200']" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <span :class="[settings.notifications.email ? 'translate-x-5' : 'translate-x-0']" class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
              </button>
            </div>

            <!-- Slack Integration -->
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-sm font-medium text-gray-700">Slack Integration</label>
                <p class="text-sm text-gray-500">Send notifications to Slack channel</p>
              </div>
              <button type="button" @click="settings.notifications.slack = !settings.notifications.slack" :class="[settings.notifications.slack ? 'bg-indigo-600' : 'bg-gray-200']" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <span :class="[settings.notifications.slack ? 'translate-x-5' : 'translate-x-0']" class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
              </button>
            </div>

            <!-- Webhook URL -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Webhook URL</label>
              <input type="url" v-model="settings.notifications.webhookUrl" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="px-6 py-4 bg-gray-50 rounded-b-lg">
        <div class="flex justify-end">
          <button type="button" @click="saveSettings" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Save Changes
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Settings',
  data() {
    return {
      settings: {
        general: {
          systemName: 'Laravel Anthropic',
          environment: 'production',
          debugMode: false
        },
        api: {
          apiKey: '••••••••••••••••',
          rateLimit: 60,
          timeout: 30
        },
        agent: {
          defaultModel: 'claude-2',
          memoryLimit: 512,
          maxConcurrentSessions: 10
        },
        notifications: {
          email: true,
          slack: false,
          webhookUrl: ''
        }
      },
      aiModels: [
        { id: 'claude-2', name: 'Claude 2.0' },
        { id: 'claude-instant', name: 'Claude Instant' },
        { id: 'claude-1', name: 'Claude 1.0' }
      ]
    }
  },
  methods: {
    async loadSettings() {
      try {
        // TODO: Implement API call to load settings
        console.log('Loading settings...')
      } catch (error) {
        console.error('Error loading settings:', error)
        // TODO: Implement error handling
      }
    },
    async saveSettings() {
      try {
        // TODO: Implement API call to save settings
        console.log('Saving settings:', this.settings)
      } catch (error) {
        console.error('Error saving settings:', error)
        // TODO: Implement error handling
      }
    },
    async regenerateApiKey() {
      try {
        // TODO: Implement API call to regenerate API key
        this.settings.api.apiKey = 'new-api-key'
      } catch (error) {
        console.error('Error regenerating API key:', error)
        // TODO: Implement error handling
      }
    }
  },
  mounted() {
    this.loadSettings()
  }
}
</script>
