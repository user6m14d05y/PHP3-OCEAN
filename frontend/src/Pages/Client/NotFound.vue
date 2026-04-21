<template>
    <div class="not-found-page">
        <div class="not-found-content">
            <div class="error-code">
                <span class="digit">4</span>
                <div class="ocean-circle">
                    <div class="wave"></div>
                    <div class="wave wave-2"></div>
                </div>
                <span class="digit">4</span>
            </div>

            <h1 class="error-title">Trang Không Tồn Tại</h1>
            <p class="error-desc">
                Rất tiếc, trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển.
            </p>

            <div class="error-actions">
                <router-link to="/" class="btn-home">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Về Trang Chủ
                </router-link>
                <button class="btn-back" @click="goBack">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Quay Lại
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
    if (window.history.length > 1) {
        router.back()
    } else {
        router.push('/')
    }
}
</script>

<style scoped>
.not-found-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0a1628 0%, #0d2137 50%, #0a1628 100%);
    padding: 20px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.not-found-content {
    text-align: center;
    max-width: 520px;
}

.error-code {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 32px;
}

.digit {
    font-size: 120px;
    font-weight: 800;
    background: linear-gradient(135deg, #38bdf8, #818cf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1;
}

.ocean-circle {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3a5f, #0d2137);
    border: 3px solid rgba(56, 189, 248, 0.3);
    position: relative;
    overflow: hidden;
}

.wave {
    position: absolute;
    bottom: 30%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(ellipse at center, rgba(56, 189, 248, 0.15) 0%, transparent 70%);
    border-radius: 40%;
    animation: wave-spin 6s linear infinite;
}

.wave-2 {
    animation-duration: 8s;
    opacity: 0.6;
    bottom: 25%;
}

@keyframes wave-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.error-title {
    font-size: 28px;
    font-weight: 700;
    color: #e2e8f0;
    margin: 0 0 12px;
}

.error-desc {
    font-size: 16px;
    color: #94a3b8;
    line-height: 1.6;
    margin: 0 0 36px;
}

.error-actions {
    display: flex;
    gap: 14px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-home,
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    border: none;
}

.btn-home {
    background: linear-gradient(135deg, #38bdf8, #818cf8);
    color: #fff;
    box-shadow: 0 4px 16px rgba(56, 189, 248, 0.3);
}

.btn-home:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(56, 189, 248, 0.4);
}

.btn-back {
    background: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-back:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

@media (max-width: 480px) {
    .digit { font-size: 80px; }
    .ocean-circle { width: 76px; height: 76px; }
    .error-title { font-size: 22px; }
    .error-desc { font-size: 14px; }
}
</style>
