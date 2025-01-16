<template>
  <div id="app" class="h-screen bg-gray-100">
    <nav class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <div class="flex-shrink-0 flex items-center">
              <img class="h-8 w-auto" src="/logo.svg" alt="Laravel Anthropic" />
            </div>
            <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
              <router-link
                v-for="item in navigationItems"
                :key="item.path"
                :to="item.path"
                class="inline-flex items-center px-1 pt-1 border-b-2"
                :class="[
                  $route.path === item.path
                    ? 'border-indigo-500 text-gray-900'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                ]"
              >
                {{ item.name }}
              </router-link>
            </div>
          </div>
          <div class="flex items-center">
            <div class="ml-3 relative">
              <notifications-dropdown />
            </div>
            <div class="ml-3 relative">
              <user-dropdown />
            </div>
          </div>
        </div>
      </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <router-view></router-view>
    </div>
  </div>
</template>

<script>
import NotificationsDropdown from './components/NotificationsDropdown.vue'
import UserDropdown from './components/UserDropdown.vue'

export default {
  name: 'App',
  components: {
    NotificationsDropdown,
    UserDropdown
  },
  data() {
    return {
      navigationItems: [
        { name: 'Dashboard', path: '/' },
        { name: 'Agents', path: '/agents' },
        { name: 'Sessions', path: '/sessions' },
        { name: 'Analytics', path: '/analytics' },
        { name: 'Settings', path: '/settings' }
      ]
    }
  }
}
</script>

<style>
@import 'tailwindcss/base';
@import 'tailwindcss/components';
@import 'tailwindcss/utilities';
</style>
