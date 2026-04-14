<template>
  <div class="page-wrapper">
    <ClientHeader />

    <!-- Main Content -->
    <main class="site-main">
      <router-view></router-view>
    </main>

    <ClientFooter />

    <!-- AI Chatbot Floating Widget -->
    <ChatbotWidget />
  </div>
</template>

<script setup>
import ClientHeader from '../components/ClientHeader.vue';
import ClientFooter from '../components/ClientFooter.vue';
import ChatbotWidget from '../components/ChatbotWidget.vue';
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

.page-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  color: #1a1a2e;
  background: #fff;
}

.site-main {
  flex: 1;
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 24px; /* Default desktop padding */
  width: 100%;
}

@media (max-width: 768px) {
  .site-main {
    padding: 0 16px; /* Smaller padding on mobile */
  }
}
</style>
