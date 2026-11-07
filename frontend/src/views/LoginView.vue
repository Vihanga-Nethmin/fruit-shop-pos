<template>
  <div class="login-page">
    <!-- Left promotional panel -->
    <section class="fruit-panel">
      <div class="brand">
        <span class="brand-icon">
          <Apple :size="32" />
        </span>
        <span>Fruit Shop POS</span>
      </div>

      <div class="fruit-content">
        <p class="eyebrow">FRESHNESS AT YOUR FINGERTIPS</p>

        <h1>
          Manage your store.
          <br />
          Grow your business.
        </h1>

        <p class="description">
          A smarter way to manage sales, products and inventory
          in one place.
        </p>

        <div class="fruit-illustration">
          <Apple :size="42" />
          <ShoppingBasket :size="42" />
          <Leaf :size="42" />
        </div>

        <p class="illustration-caption">
          Fresh products. Smarter business.
        </p>
      </div>

      <p class="panel-footer">
        © 2026 Fruit Shop POS. All rights reserved.
      </p>
    </section>

    <!-- Login form -->
    <section class="form-panel">
      <form class="login-card" @submit.prevent="handleLogin">
        <div class="mobile-brand">
          <Apple :size="28" />
          <span>Fruit Shop POS</span>
        </div>

        <div class="welcome-icon">
          <LockKeyhole :size="26" />
        </div>

        <h2>Welcome back!</h2>

        <p class="subtitle">
          Sign in to access your workspace.
        </p>

        <div
          v-if="errorMessage"
          class="error-message"
          role="alert"
        >
          <AlertCircle :size="18" />
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Username -->
        <label for="username">Username</label>

        <div class="input-field">
          <UserRound :size="19" />

          <input
            id="username"
            v-model.trim="username"
            type="text"
            placeholder="Enter your username"
            autocomplete="username"
            required
          />
        </div>

        <!-- Password -->
        <label for="password">Password</label>

        <div class="input-field password-field">
          <LockKeyhole :size="19" />

          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          />

          <button
            class="show-password"
            type="button"
            @click="showPassword = !showPassword"
            :aria-label="
              showPassword ? 'Hide password' : 'Show password'
            "
          >
            <EyeOff v-if="showPassword" :size="19" />
            <Eye v-else :size="19" />
          </button>
        </div>

        <!-- Sign in -->
        <button class="sign-in-button" type="submit">
          <span>Sign In</span>
          <ArrowRight :size="20" />
        </button>

        <div class="security-note">
          <ShieldCheck :size="17" />
          <span>Secure access for your team</span>
        </div>
      </form>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import {
  Apple,
  ShoppingBasket,
  Leaf,
  LockKeyhole,
  UserRound,
  Eye,
  EyeOff,
  ArrowRight,
  ShieldCheck,
  AlertCircle
} from 'lucide-vue-next'

const router = useRouter()

const username = ref('')
const password = ref('')
const showPassword = ref(false)
const errorMessage = ref('')

function handleLogin() {
  errorMessage.value = ''

  if (!username.value || !password.value) {
    errorMessage.value =
      'Please enter your username and password.'
    return
  }

  // Temporary frontend-only login.
  // Real authentication will be added through the backend.
  router.push('/dashboard')
}
</script>

<style scoped>
* {
  box-sizing: border-box;
}

.login-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: #ffffff;
  font-family: Inter, Arial, sans-serif;
}

/* Left promotional panel */

.fruit-panel {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px 9%;
  color: #ffffff;
  background: linear-gradient(145deg, #173f2c, #28633d);
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 22px;
  font-weight: 700;
}

.brand-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c5ed8c;
}

.fruit-content {
  width: 100%;
  max-width: 490px;
  margin: 50px auto;
}

.eyebrow {
  color: #b9e77a;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
}

.fruit-content h1 {
  margin: 20px 0;
  font-size: clamp(32px, 4vw, 48px);
  line-height: 1.2;
}

.description {
  color: #d5e5d8;
  font-size: 16px;
  line-height: 1.8;
}

.fruit-illustration {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 32px;
  margin-top: 40px;
  padding: 30px 18px;
  border: 1px solid #ffffff20;
  border-radius: 18px;
  background: #ffffff10;
  color: #d4eead;
}

.illustration-caption {
  margin-top: 16px;
  color: #d5e5d8;
  font-size: 13px;
  text-align: center;
}

.panel-footer {
  color: #d5e5d8;
  font-size: 12px;
}

/* Login panel */

.form-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 36px 24px;
}

.login-card {
  width: 100%;
  max-width: 390px;
}

.mobile-brand {
  display: none;
  align-items: center;
  gap: 10px;
  color: #24613a;
  font-size: 21px;
  font-weight: 700;
}

.welcome-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 15px;
  background: #edf5e9;
  color: #24613a;
}

.login-card h2 {
  margin: 25px 0 8px;
  color: #203a2a;
  font-size: 30px;
}

.subtitle {
  margin: 0 0 32px;
  color: #7c877e;
  font-size: 14px;
  line-height: 1.6;
}

/* Form fields */

.login-card label {
  display: block;
  margin: 20px 0 8px;
  color: #34483a;
  font-size: 13px;
  font-weight: 600;
}

.input-field {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0 13px;
  border: 1px solid #dce4dd;
  border-radius: 9px;
  background: #ffffff;
  color: #718074;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.input-field:focus-within {
  border-color: #38804b;
  box-shadow: 0 0 0 3px #38804b18;
}

.input-field > svg {
  flex-shrink: 0;
}

.input-field input {
  width: 100%;
  min-width: 0;
  padding: 14px 0;
  border: none;
  outline: none;
  background: transparent;
  color: #243629;
  font-family: inherit;
  font-size: 14px;
}

.input-field input::placeholder {
  color: #a1aaa3;
}

.show-password {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  padding: 5px;
  border: none;
  border-radius: 5px;
  background: transparent;
  color: #387448;
  cursor: pointer;
}

.show-password:hover {
  background: #edf5e9;
}

/* Sign-in button */

.sign-in-button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  margin-top: 28px;
  padding: 15px 18px;
  border: none;
  border-radius: 9px;
  background: #24613a;
  color: #ffffff;
  font-family: inherit;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s, transform 0.2s;
}

.sign-in-button:hover {
  background: #194b2b;
}

.sign-in-button:active {
  transform: scale(0.99);
}

/* Security and errors */

.security-note {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  margin-top: 28px;
  color: #7c877e;
  font-size: 12px;
}

.security-note svg {
  color: #38804b;
}

.error-message {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin-bottom: 18px;
  padding: 12px;
  border-radius: 8px;
  background: #fff1ed;
  color: #a43c27;
  font-size: 13px;
  line-height: 1.5;
}

.error-message svg {
  flex-shrink: 0;
}

.sign-in-button:focus-visible,
.show-password:focus-visible {
  outline: 3px solid #9acb83;
  outline-offset: 3px;
}

/* Mobile layout */

@media (max-width: 760px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .fruit-panel {
    display: none;
  }

  .form-panel {
    min-height: 100vh;
    padding: 32px 24px;
  }

  .login-card {
    max-width: 420px;
  }

  .mobile-brand {
    display: flex;
    margin-bottom: 45px;
  }

  .login-card h2 {
    font-size: 28px;
  }
}

@media (max-width: 380px) {
  .form-panel {
    padding: 24px 18px;
  }

  .mobile-brand {
    margin-bottom: 35px;
    font-size: 19px;
  }
}
</style>