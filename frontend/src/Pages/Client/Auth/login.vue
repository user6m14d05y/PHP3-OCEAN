<script setup>
import { ref, reactive, nextTick, onMounted, onBeforeUnmount } from 'vue';
import api from '../../../axios.js';
import { useRouter } from 'vue-router';
import { Toast } from 'bootstrap';
import ClientHeader from '../../../components/ClientHeader.vue';
import ClientFooter from '../../../components/ClientFooter.vue';

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const isSubmitting = ref(false);
const router = useRouter();
const toast = ref({ message: '', type: 'success' });
const turnstileToken = ref('');
let turnstileWidgetId = null;

// Field-level validation errors
const fieldErrors = reactive({ email: '', password: '' });
const touched = reactive({ email: false, password: false });

const validateField = (field) => {
  if (field === 'email') {
    if (!email.value) fieldErrors.email = 'Vui lòng nhập email';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) fieldErrors.email = 'Email không hợp lệ';
    else fieldErrors.email = '';
  }
  if (field === 'password') {
    if (!password.value) fieldErrors.password = 'Vui lòng nhập mật khẩu';
    else fieldErrors.password = '';
  }
};

const onBlur = (field) => {
  touched[field] = true;
  validateField(field);
};

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('loginToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

// Cloudflare Turnstile
const loadTurnstile = () => {
  if (window.turnstile) {
    renderTurnstile();
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad';
  script.async = true;
  window.onTurnstileLoad = () => renderTurnstile();
  document.head.appendChild(script);
};

const renderTurnstile = () => {
  const container = document.getElementById('turnstile-login');
  if (!container || !window.turnstile) return;
  container.innerHTML = '';
  turnstileWidgetId = window.turnstile.render('#turnstile-login', {
    sitekey: import.meta.env.VITE_TURNSTILE_SITE_KEY,
    callback: (token) => { turnstileToken.value = token; },
    'expired-callback': () => { turnstileToken.value = ''; },
    'error-callback': () => { turnstileToken.value = ''; },
    theme: 'light',
  });
};

onMounted(() => { loadTurnstile(); });
onBeforeUnmount(() => {
  if (turnstileWidgetId !== null && window.turnstile) window.turnstile.remove(turnstileWidgetId);
});

const login = async () => {
  // Validate all fields
  touched.email = true;
  touched.password = true;
  validateField('email');
  validateField('password');

  if (fieldErrors.email || fieldErrors.password) return;

  if (!turnstileToken.value) {
    showToast('Vui lòng xác thực CAPTCHA', 'danger');
    return;
  }

  isSubmitting.value = true;
  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value,
      turnstile_token: turnstileToken.value
    });

    if (response.data.status === 'success') {
      localStorage.setItem('auth_token', response.data.access_token);
      localStorage.setItem('user', JSON.stringify({
        isLoggedIn: true,
        id: response.data.user.id,
        name: response.data.user.name,
        email: response.data.user.email,
        role: response.data.user.role
      }));

      showToast('Đăng nhập thành công!', 'success');

      if (response.data.user.role === 'admin' || response.data.user.role === 'staff') {
        router.push('/admin');
      } else {
        router.push('/');
      }
    }
  } catch (error) {
    let msg = error.response?.data?.message || 'Đăng nhập thất bại!';
    if (error.response?.status === 429) {
      msg = 'Bạn đã thử quá nhiều lần! Vui lòng đợi 1 phút rồi thử lại.';
    }
    showToast(msg, 'danger');
    if (window.turnstile && turnstileWidgetId !== null) {
      window.turnstile.reset(turnstileWidgetId);
      turnstileToken.value = '';
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="page-wrapper">
    <ClientHeader />

    <main class="auth-main">
      <div class="auth-page">
        <div class="auth-card">
          <!-- Icon -->
          <div class="auth-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
          </div>

          <h1 class="auth-title">Đăng nhập</h1>

          <form @submit.prevent="login" class="auth-form" novalidate>
            <!-- Email -->
            <div class="form-group" :class="{ 'has-error': touched.email && fieldErrors.email }">
              <label for="login-email">Email</label>
              <input id="login-email" type="email" v-model="email" placeholder="your@email.com" :disabled="isSubmitting" @blur="onBlur('email')" @input="validateField('email')" />
              <p v-if="touched.email && fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
            </div>

            <!-- Password -->
            <div class="form-group" :class="{ 'has-error': touched.password && fieldErrors.password }">
              <label for="login-password">Mật khẩu</label>
              <div class="input-password">
                <input id="login-password" :type="showPassword ? 'text' : 'password'" v-model="password" placeholder="Mật khẩu" :disabled="isSubmitting" @blur="onBlur('password')" @input="validateField('password')" />
                <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                  <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <p v-if="touched.password && fieldErrors.password" class="field-error">{{ fieldErrors.password }}</p>
            </div>

            <!-- Remember + Forgot -->
            <div class="form-row">
              <label class="checkbox-label">
                <input type="checkbox" /> Ghi nhớ đăng nhập
              </label>
              <router-link to="/client/forgot" class="forgot-link">Quên mật khẩu?</router-link>
            </div>

            <!-- Cloudflare Turnstile CAPTCHA -->
            <div class="turnstile-wrapper">
              <div id="turnstile-login"></div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-primary" :disabled="isSubmitting || !turnstileToken">
              <span v-if="isSubmitting" class="spinner"></span>
              {{ isSubmitting ? 'Đang xử lý...' : 'Đăng nhập' }}
            </button>
          </form>

          <!-- Divider -->
          <div class="divider"><span>HOẶC</span></div>

          <!-- Social -->
          <div class="social-buttons">
            <button class="btn-social">
              <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" width="20" height="20" />
              Google
            </button>
            <button class="btn-social">
              <img src="https://www.svgrepo.com/show/475647/facebook-color.svg" alt="Facebook" width="20" height="20" />
              Facebook
            </button>
          </div>

          <!-- Register link -->
          <p class="auth-switch">
            Chưa có tài khoản?
            <router-link to="/client/register">Đăng ký ngay</router-link>
          </p>
        </div>
      </div>
    </main>

    <ClientFooter />

    <!-- Bootstrap Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
      <div class="toast align-items-center border-0" :class="toast.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="loginToast" role="alert">
        <div class="d-flex">
          <div class="toast-body">{{ toast.message }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
.auth-main { flex: 1; background: #f5f7fb; display: flex; flex-direction: column; }
.auth-page { flex: 1; padding: 48px 24px; display: flex; align-items: center; justify-content: center; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
.auth-card { width: 100%; max-width: 440px; background: #fff; border-radius: 16px; padding: 40px 36px 36px; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04); }
.auth-icon { width: 56px; height: 56px; border-radius: 14px; background: #eef2ff; color: #4f6ef7; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.auth-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: #1a1a2e; margin-bottom: 28px; }
.auth-form { display: flex; flex-direction: column; gap: 18px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
.form-group input { width: 100%; padding: 12px 14px; border: 1.5px solid #e0e4ec; border-radius: 10px; font-size: 0.9rem; font-family: inherit; color: #1a1a2e; background: #f8f9fc; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.form-group input:focus { border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79, 110, 247, 0.1); background: #fff; }
.form-group input::placeholder { color: #a0a8c0; }

/* Error state */
.form-group.has-error input { border-color: #ef4444; }
.form-group.has-error input:focus { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
.field-error { font-size: 0.78rem; color: #ef4444; margin-top: 5px; display: flex; align-items: center; gap: 4px; }

.input-password { position: relative; }
.input-password input { padding-right: 44px; }
.toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #a0a8c0; display: flex; padding: 4px; transition: color 0.2s; }
.toggle-pw:hover { color: #4f6ef7; }
.form-row { display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; }
.checkbox-label { display: flex; align-items: center; gap: 6px; color: #555; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: #4f6ef7; cursor: pointer; }
.forgot-link { color: #4f6ef7; text-decoration: none; font-weight: 500; transition: color 0.2s; }
.forgot-link:hover { color: #3b5de7; text-decoration: underline; }

.turnstile-wrapper { display: flex; justify-content: center; margin: 4px 0; }

.btn-primary { width: 100%; padding: 13px; border: none; border-radius: 10px; background: #4f6ef7; color: #fff; font-size: 0.95rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: background 0.2s, transform 0.1s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 4px; }
.btn-primary:hover:not(:disabled) { background: #3b5de7; }
.btn-primary:active:not(:disabled) { transform: scale(0.98); }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
.spinner { width: 18px; height: 18px; border: 2px solid rgba(255, 255, 255, 0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.divider { display: flex; align-items: center; gap: 16px; margin: 24px 0; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e0e4ec; }
.divider span { font-size: 0.75rem; font-weight: 600; color: #a0a8c0; letter-spacing: 1px; }
.social-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-social { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px; border: 1.5px solid #e0e4ec; border-radius: 10px; background: #fff; font-size: 0.85rem; font-weight: 500; font-family: inherit; color: #333; cursor: pointer; transition: background 0.2s, border-color 0.2s; }
.btn-social:hover { background: #f5f7fb; border-color: #c8cee0; }
.auth-switch { text-align: center; font-size: 0.85rem; color: #666; margin-top: 24px; }
.auth-switch a { color: #4f6ef7; font-weight: 600; text-decoration: underline; transition: color 0.2s; }
.auth-switch a:hover { color: #3b5de7; }
</style>