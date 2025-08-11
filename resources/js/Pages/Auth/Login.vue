<script setup>
import { Link, Head } from '@inertiajs/vue3'
import Layout from "@/Layouts/Layout.vue";
defineOptions({layout: Layout})
</script>

<template>
  <Head title="Login" />
  
  <!-- Minimal Login Page -->
  <div class="login-page">
    <!-- Content Area -->
    <div class="login-content">
      <!-- Title Section -->
      <div class="brand-section">
        <h1 class="brand-title">Seat Reservation</h1>
        <p class="brand-subtitle">Reserve your seats for Eurofurence Events</p>
      </div>
      
      <!-- Login Form Section -->
      <div class="login-form">
        <van-cell-group inset class="welcome-card">
          <van-cell
            title="Welcome"
            value="Tap below to sign in securely"
            icon="user-o"
          />
        </van-cell-group>
        
        <!-- Login Button -->
        <div class="login-button-container">
          <Link 
            as="button" 
            method="post" 
            :href="route('auth.login.redirect')"
            class="w-full"
          >
            <van-button 
              type="primary" 
              size="large" 
              block
              icon="sign"
              loading-text="Signing in..."
              class="w-full"
            >
              Sign In
            </van-button>
          </Link>
        </div>
        
        <!-- Info Notice -->
        <van-notice-bar
          class="login-notice"
          left-icon="info-o"
          text="You'll be redirected to a secure login page"
          color="#1989fa"
          background="#ecf9ff"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  display: flex;
  flex-direction: column;
}

.login-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 32px 16px;
  gap: 40px;
}

.brand-section {
  text-align: center;
  color: #333333;
}

.brand-title {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 8px;
  color: #333333;
}

.brand-subtitle {
  font-size: 16px;
  opacity: 0.7;
  line-height: 1.4;
  color: #666666;
}

.login-form {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 0 16px;
}

.login-button-container {
  width: 100%;
}

.login-notice {
  border-radius: 8px;
}

/* Vant component customization */
:deep(.van-cell-group--inset) {
  margin: 0;
  border-radius: 12px;
  overflow: hidden;
}

.welcome-card {
  margin-bottom: 0;
}

:deep(.van-button--primary) {
  background: #1989fa;
  border-color: #1989fa;
  border-radius: 12px;
  height: 50px;
  font-size: 16px;
  font-weight: 600;
  width: 100%;
}

.w-full {
  width: 100%;
}
</style>
