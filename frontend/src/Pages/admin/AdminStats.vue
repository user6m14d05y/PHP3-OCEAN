<template>
  <div class="statistics-page">
    <!-- Page Header -->
    <div class="page-header animate-in">
      <div class="header-left">
        <h2 class="page-heading">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            <line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
          Thống kê chi tiết
        </h2>
        <span class="page-subtitle">Phân tích dữ liệu toàn diện hệ thống</span>
      </div>
      <div class="period-filter">
        <button v-for="p in periods" :key="p.value"
          class="period-btn" :class="{ active: selectedPeriod === p.value }"
          @click="changePeriod(p.value)">
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="loader"></div>
      <span>Đang tải dữ liệu thống kê...</span>
    </div>

    <template v-else>
      <!-- ═══ 1. SUMMARY KPI CARDS ═══ -->
      <div class="kpi-grid animate-in" style="animation-delay:.05s">
        <div class="kpi-card ocean-card" v-for="(kpi, i) in kpiCards" :key="kpi.title" :style="{ animationDelay: `${i * 0.06}s` }">
          <div class="kpi-icon" :style="{ background: kpi.iconBg }">
            <span v-html="kpi.icon"></span>
          </div>
          <div class="kpi-body">
            <span class="kpi-label">{{ kpi.title }}</span>
            <span class="kpi-value">{{ kpi.value }}</span>
          </div>
          <span class="kpi-change" :class="kpi.isUp ? 'up' : 'down'">
            {{ kpi.isUp ? '↑' : '↓' }} {{ kpi.changePercent }}%
          </span>
        </div>
      </div>

      <!-- ═══ 2. REVENUE CHART + ORDER STATUS ═══ -->
      <div class="row-two animate-in" style="animation-delay:.12s">
        <!-- Revenue Over Time -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">Doanh thu theo thời gian</h3>
            <div class="chart-legend">
              <span class="legend-dot" style="background:#0288d1"></span> Doanh thu
            </div>
          </div>
          <div class="revenue-chart" v-if="revenueData.length">
            <div class="chart-y-axis">
              <span>{{ formatShort(maxRevenue) }}</span>
              <span>{{ formatShort(maxRevenue / 2) }}</span>
              <span>0</span>
            </div>
            <div class="chart-bars-wrapper">
              <div class="chart-grid-lines">
                <div class="grid-line"></div>
                <div class="grid-line"></div>
                <div class="grid-line"></div>
              </div>
              <div class="chart-bars">
                <div class="rev-bar-col" v-for="(d, idx) in revenueData" :key="idx">
                  <div class="rev-bar-track">
                    <div class="rev-bar-fill" :style="{ height: getBarH(d.revenue) + '%' }">
                      <span class="rev-bar-tip">{{ formatNumber(d.revenue) }} đ<br/>{{ d.orders }} đơn</span>
                    </div>
                  </div>
                  <span class="rev-bar-label">{{ d.label }}</span>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">Không có dữ liệu</div>
        </div>

        <!-- Order Status Donut -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">Trạng thái đơn hàng</h3>
          </div>
          <div class="donut-wrapper" v-if="orderStatus.length">
            <div class="donut-chart">
              <svg viewBox="0 0 42 42" class="donut-svg">
                <circle class="donut-hole" cx="21" cy="21" r="15.91549430918954" fill="transparent"/>
                <circle v-for="(seg, i) in donutSegments" :key="i"
                  class="donut-segment"
                  cx="21" cy="21" r="15.91549430918954"
                  fill="transparent"
                  :stroke="seg.color"
                  stroke-width="5"
                  :stroke-dasharray="seg.dash"
                  :stroke-dashoffset="seg.offset"
                  stroke-linecap="round"/>
              </svg>
              <div class="donut-center">
                <span class="donut-total">{{ totalOrders }}</span>
                <span class="donut-label">Đơn hàng</span>
              </div>
            </div>
            <div class="donut-legend">
              <div class="legend-row" v-for="s in orderStatus" :key="s.key">
                <span class="legend-color" :style="{ background: s.color }"></span>
                <span class="legend-text">{{ s.label }}</span>
                <span class="legend-count">{{ s.count }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">Không có dữ liệu</div>
        </div>
      </div>

      <!-- ═══ 3. TOP PRODUCTS + REVENUE BY CATEGORY ═══ -->
      <div class="row-two animate-in" style="animation-delay:.18s">
        <!-- Top Products -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">🏆 Top sản phẩm bán chạy</h3>
          </div>
          <div class="h-bar-list" v-if="topProducts.length">
            <div class="h-bar-row" v-for="(p, i) in topProducts" :key="p.product_id">
              <span class="h-bar-rank" :class="'rank-' + (i + 1)">{{ i + 1 }}</span>
              <div class="h-bar-info">
                <span class="h-bar-name">{{ p.name }}</span>
                <div class="h-bar-track">
                  <div class="h-bar-fill" :style="{ width: getProductBar(p.quantity) + '%', background: getProductColor(i) }"></div>
                </div>
              </div>
              <div class="h-bar-stats">
                <span class="h-bar-qty">{{ p.quantity }} sp</span>
                <span class="h-bar-rev">{{ p.revenue_fmt }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">Không có dữ liệu</div>
        </div>

        <!-- Revenue by Category -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">📊 Doanh thu theo danh mục</h3>
          </div>
          <div class="h-bar-list" v-if="revenueByCategory.length">
            <div class="h-bar-row" v-for="(c, i) in revenueByCategory" :key="c.category_id">
              <span class="h-bar-rank cat-rank">{{ i + 1 }}</span>
              <div class="h-bar-info">
                <span class="h-bar-name">{{ c.name }}</span>
                <div class="h-bar-track">
                  <div class="h-bar-fill" :style="{ width: getCategoryBar(c.revenue) + '%', background: getCategoryColor(i) }"></div>
                </div>
              </div>
              <div class="h-bar-stats">
                <span class="h-bar-qty">{{ c.quantity }} sp</span>
                <span class="h-bar-rev">{{ c.revenue_fmt }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">Không có dữ liệu</div>
        </div>
      </div>

      <!-- ═══ 4. PAYMENT + NEW CUSTOMERS ═══ -->
      <div class="row-two animate-in" style="animation-delay:.24s">
        <!-- Payment Methods -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">💳 Phương thức thanh toán</h3>
          </div>
          <div class="payment-grid" v-if="paymentMethods.length">
            <div class="payment-card" v-for="pm in paymentMethods" :key="pm.method">
              <div class="payment-icon" :style="{ background: pm.color }">
                <span>{{ getPaymentIcon(pm.method) }}</span>
              </div>
              <span class="payment-label">{{ pm.label }}</span>
              <span class="payment-count">{{ pm.count }} đơn</span>
              <span class="payment-total">{{ pm.total_fmt }}</span>
            </div>
          </div>
          <div v-else class="empty-state">Không có dữ liệu</div>
        </div>

        <!-- New Customers -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">👥 Khách hàng mới</h3>
            <span class="card-sub">6 tháng gần nhất</span>
          </div>
          <div class="customer-chart" v-if="newCustomers.length">
            <div class="cust-bar-col" v-for="c in newCustomers" :key="c.label">
              <div class="cust-bar-track">
                <div class="cust-bar-fill" :style="{ height: getCustomerBar(c.count) + '%' }">
                  <span class="cust-bar-tip">{{ c.count }}</span>
                </div>
              </div>
              <span class="cust-bar-label">{{ c.label }}</span>
            </div>
          </div>
          <div v-else class="empty-state">Không có dữ liệu</div>
        </div>
      </div>

      <!-- ═══ 5. REVIEWS + LOW STOCK ═══ -->
      <div class="row-two animate-in" style="animation-delay:.3s">
        <!-- Review Stats -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">⭐ Đánh giá sản phẩm</h3>
          </div>
          <div class="review-summary" v-if="reviewStats.total > 0">
            <div class="review-avg">
              <span class="avg-number">{{ reviewStats.avg_rating }}</span>
              <div class="avg-stars">
                <span v-for="s in 5" :key="s" class="star" :class="{ filled: s <= Math.round(reviewStats.avg_rating) }">★</span>
              </div>
              <span class="avg-total">{{ reviewStats.total }} đánh giá</span>
            </div>
            <div class="rating-bars">
              <div class="rating-row" v-for="d in reviewStats.distribution" :key="d.star">
                <span class="rating-label">{{ d.star }}★</span>
                <div class="rating-track">
                  <div class="rating-fill" :style="{ width: d.percent + '%' }"></div>
                </div>
                <span class="rating-count">{{ d.count }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">Chưa có đánh giá nào</div>
        </div>

        <!-- Low Stock -->
        <div class="ocean-card chart-card">
          <div class="card-head">
            <h3 class="card-title">⚠️ Cảnh báo tồn kho</h3>
          </div>
          <div class="stock-table" v-if="lowStock.length">
            <div class="stock-header">
              <span>Sản phẩm</span>
              <span>SKU</span>
              <span>Tồn kho</span>
              <span>Mức an toàn</span>
            </div>
            <div class="stock-row" v-for="s in lowStock" :key="s.variant_id">
              <div class="stock-product">
                <span class="stock-name">{{ s.product_name }}</span>
                <span class="stock-variant">{{ s.variant_name }}</span>
              </div>
              <span class="stock-sku">{{ s.sku }}</span>
              <span class="stock-qty" :class="{ critical: s.stock <= 0 }">{{ s.stock }}</span>
              <span class="stock-safety">{{ s.safety_stock }}</span>
            </div>
          </div>
          <div v-else class="empty-state empty-good">
            <span>✅</span> Tất cả sản phẩm đều đủ hàng
          </div>
        </div>
      </div>

      <!-- ═══ 6. RECENT ORDERS TABLE ═══ -->
      <div class="ocean-card animate-in" style="animation-delay:.36s; padding: 22px;">
        <div class="card-head">
          <h3 class="card-title">📋 Đơn hàng gần đây</h3>
          <router-link to="/admin/order" class="link-all">Xem tất cả →</router-link>
        </div>
        <div class="orders-table" v-if="recentOrders.length">
          <div class="table-head">
            <span>Mã đơn</span>
            <span>Khách hàng</span>
            <span>Tổng tiền</span>
            <span>Thanh toán</span>
            <span>Trạng thái</span>
            <span>Ngày tạo</span>
          </div>
          <div class="table-row" v-for="o in recentOrders" :key="o.order_id" @click="$router.push('/admin/order/' + o.order_id)">
            <span class="order-code">#{{ o.order_code }}</span>
            <span class="order-customer">{{ o.customer }}</span>
            <span class="order-total">{{ o.grand_total }}</span>
            <span class="order-payment">{{ o.payment_status }}</span>
            <span class="order-status" :class="'st-' + o.status_key">{{ o.fulfillment_status }}</span>
            <span class="order-date">{{ o.created_at }}</span>
          </div>
        </div>
        <div v-else class="empty-state">Chưa có đơn hàng</div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../axios';

// ── State ──
const loading = ref(true);
const selectedPeriod = ref('30d');

const periods = [
  { value: '7d',  label: '7 ngày' },
  { value: '30d', label: '30 ngày' },
  { value: '3m',  label: '3 tháng' },
  { value: '6m',  label: '6 tháng' },
  { value: '1y',  label: '1 năm' },
];

// Data refs
const kpiCards = ref([]);
const revenueData = ref([]);
const orderStatus = ref([]);
const topProducts = ref([]);
const revenueByCategory = ref([]);
const paymentMethods = ref([]);
const newCustomers = ref([]);
const reviewStats = ref({ total: 0, avg_rating: 0, distribution: [] });
const lowStock = ref([]);
const recentOrders = ref([]);

// ── Computed ──
const maxRevenue = computed(() => {
  const max = Math.max(...revenueData.value.map(d => d.revenue), 0);
  return max || 1;
});

const totalOrders = computed(() => orderStatus.value.reduce((sum, s) => sum + s.count, 0));

const donutSegments = computed(() => {
  const total = totalOrders.value;
  if (total === 0) return [];
  let cumulative = 0;
  return orderStatus.value.map(s => {
    const pct = (s.count / total) * 100;
    const seg = {
      color: s.color,
      dash: `${pct} ${100 - pct}`,
      offset: 25 - cumulative, // SVG starts at 3 o'clock, offset moves it
    };
    cumulative += pct;
    return seg;
  });
});

// ── Methods ──
async function fetchData() {
  loading.value = true;
  try {
    const res = await api.get('/admin/statistics', { params: { period: selectedPeriod.value } });
    if (res.data?.status === 'success') {
      const d = res.data.data;

      // KPI Cards
      kpiCards.value = [
        {
          title: 'Tổng doanh thu', value: d.summary.revenue.display,
          changePercent: d.summary.revenue.change.percent, isUp: d.summary.revenue.change.is_up,
          icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
          iconBg: '#0288d1',
        },
        {
          title: 'Đơn hàng', value: d.summary.orders.display,
          changePercent: d.summary.orders.change.percent, isUp: d.summary.orders.change.is_up,
          icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>',
          iconBg: '#26a69a',
        },
        {
          title: 'Khách hàng mới', value: d.summary.new_customers.display,
          changePercent: d.summary.new_customers.change.percent, isUp: d.summary.new_customers.change.is_up,
          icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
          iconBg: '#7e57c2',
        },
        {
          title: 'Giá trị TB đơn', value: d.summary.aov.display,
          changePercent: d.summary.aov.change.percent, isUp: d.summary.aov.change.is_up,
          icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
          iconBg: '#ffa726',
        },
      ];

      revenueData.value = d.revenue_over_time || [];
      orderStatus.value = d.order_status || [];
      topProducts.value = d.top_products || [];
      revenueByCategory.value = d.revenue_by_category || [];
      paymentMethods.value = d.payment_methods || [];
      newCustomers.value = d.new_customers || [];
      reviewStats.value = d.review_stats || { total: 0, avg_rating: 0, distribution: [] };
      lowStock.value = d.low_stock || [];
      recentOrders.value = d.recent_orders || [];
    }
  } catch (err) {
    console.error('Lỗi khi lấy dữ liệu thống kê:', err);
  } finally {
    loading.value = false;
  }
}

function changePeriod(p) {
  selectedPeriod.value = p;
  fetchData();
}

function formatNumber(n) {
  return new Intl.NumberFormat('vi-VN').format(Math.round(n));
}

function formatShort(n) {
  if (n >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1) + 'B';
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(0) + 'K';
  return String(Math.round(n));
}

function getBarH(val) {
  if (maxRevenue.value === 0) return 0;
  const h = (val / maxRevenue.value) * 100;
  return val > 0 ? Math.max(h, 3) : 0;
}

function getProductBar(qty) {
  const max = topProducts.value.length ? topProducts.value[0].quantity : 1;
  return Math.max((qty / max) * 100, 4);
}

function getCategoryBar(rev) {
  const max = revenueByCategory.value.length ? revenueByCategory.value[0].revenue : 1;
  return Math.max((rev / max) * 100, 4);
}

function getCustomerBar(count) {
  const max = Math.max(...newCustomers.value.map(c => c.count), 1);
  return count > 0 ? Math.max((count / max) * 100, 5) : 0;
}

const productColors = ['#0288d1', '#26a69a', '#7e57c2', '#ffa726', '#ef5350', '#66bb6a', '#ec407a', '#29b6f6', '#ab47bc', '#78909c'];
function getProductColor(i) { return productColors[i % productColors.length]; }

const categoryColors = ['#0288d1', '#26a69a', '#ffa726', '#7e57c2', '#ef5350', '#66bb6a', '#ec407a', '#29b6f6'];
function getCategoryColor(i) { return categoryColors[i % categoryColors.length]; }

function getPaymentIcon(method) {
  const icons = { cod: '💵', vnpay: '🏦', momo: '📱', bank: '🏧', pos_cash: '💰', pos_card: '💳', pos_transfer: '📲' };
  return icons[method] || '💸';
}

onMounted(fetchData);
</script>

<style scoped>
.statistics-page { font-family: var(--font-inter); }

/* ── Page Header ── */
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px; flex-wrap: wrap; gap: 16px;
}
.header-left { display: flex; flex-direction: column; gap: 4px; }
.page-heading {
  font-size: 1.4rem; font-weight: 800; color: var(--text-main);
  display: flex; align-items: center; gap: 10px;
}
.page-heading svg { color: var(--ocean-blue); }
.page-subtitle { font-size: 0.82rem; font-weight: 500; color: var(--text-muted); padding-left: 34px; }

.period-filter {
  display: flex; gap: 4px; background: var(--card-bg); border: 1px solid var(--border-color);
  border-radius: 8px; padding: 3px;
}
.period-btn {
  padding: 6px 14px; border-radius: 6px; border: none; background: none;
  font-family: var(--font-inter); font-size: 0.78rem; font-weight: 600;
  color: var(--text-muted); cursor: pointer; transition: all 0.2s;
}
.period-btn.active { background: var(--ocean-blue); color: white; }
.period-btn:hover:not(.active) { color: var(--text-main); background: var(--hover-bg); }

/* ── Loading ── */
.loading-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 80px 0; gap: 16px; color: var(--text-muted);
}
.loader {
  width: 36px; height: 36px; border: 3px solid var(--border-color);
  border-top-color: var(--ocean-blue); border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── KPI Cards ── */
.kpi-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 16px; margin-bottom: 24px;
}
.kpi-card {
  padding: 20px; display: flex; align-items: center; gap: 14px;
  transition: all 0.2s;
}
.kpi-card:hover {
  border-color: rgba(2, 136, 209, 0.25);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(2, 136, 209, 0.08);
}
.kpi-icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: white; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.kpi-body { flex: 1; display: flex; flex-direction: column; }
.kpi-label {
  font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;
}
.kpi-value { font-size: 1.35rem; font-weight: 800; color: var(--text-main); }
.kpi-change { font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
.up { color: var(--seafoam, #26a69a); }
.down { color: var(--coral, #ef5350); }

/* ── Two Column Row ── */
.row-two {
  display: grid; grid-template-columns: 1.4fr 1fr;
  gap: 16px; margin-bottom: 24px;
}

/* ── Card Common ── */
.chart-card { padding: 22px; }
.card-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
}
.card-title { font-size: 1rem; font-weight: 800; color: var(--text-main); }
.card-sub { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; }
.chart-legend { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
.legend-dot { width: 8px; height: 8px; border-radius: 2px; }
.link-all { font-size: 0.8rem; font-weight: 600; color: var(--ocean-blue); text-decoration: none; }
.link-all:hover { text-decoration: underline; }

/* ── Revenue Bar Chart ── */
.revenue-chart {
  display: flex; gap: 8px; height: 220px;
}
.chart-y-axis {
  display: flex; flex-direction: column; justify-content: space-between;
  font-size: 0.65rem; color: var(--text-muted); font-weight: 600;
  width: 40px; text-align: right; padding: 0 4px 20px 0;
}
.chart-bars-wrapper {
  flex: 1; position: relative;
}
.chart-grid-lines {
  position: absolute; top: 0; left: 0; right: 0; bottom: 20px;
  display: flex; flex-direction: column; justify-content: space-between;
  pointer-events: none;
}
.grid-line {
  border-bottom: 1px dashed var(--border-color); opacity: 0.5;
}
.chart-bars {
  display: flex; align-items: flex-end; justify-content: space-around;
  height: 100%; position: relative; z-index: 1;
}
.rev-bar-col { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; max-width: 40px; }
.rev-bar-track { height: 180px; display: flex; align-items: flex-end; width: 100%; justify-content: center; }
.rev-bar-fill {
  width: 24px; border-radius: 4px 4px 1px 1px;
  background: linear-gradient(180deg, #0288d1 0%, #1565c0 100%);
  position: relative; transition: all 0.3s ease; cursor: pointer; min-height: 2px;
}
.rev-bar-fill:hover { opacity: 0.85; transform: scaleY(1.02); }
.rev-bar-tip {
  position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%) scale(0);
  background: var(--text-main); border-radius: 6px; padding: 6px 10px;
  font-size: 0.68rem; font-weight: 700; color: white; line-height: 1.4;
  white-space: nowrap; transition: transform 0.15s; pointer-events: none;
  box-shadow: 0 4px 8px rgba(0,0,0,0.15); text-align: center; z-index: 10;
}
.rev-bar-fill:hover .rev-bar-tip { transform: translateX(-50%) scale(1); }
.rev-bar-label { font-size: 0.62rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; }

/* ── Donut Chart ── */
.donut-wrapper { display: flex; align-items: center; gap: 24px; }
.donut-chart { position: relative; width: 160px; height: 160px; flex-shrink: 0; }
.donut-svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.donut-segment { transition: stroke-dasharray 0.5s ease, stroke-dashoffset 0.5s ease; }
.donut-center {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  display: flex; flex-direction: column; align-items: center;
}
.donut-total { font-size: 1.5rem; font-weight: 800; color: var(--text-main); }
.donut-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }
.donut-legend { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.legend-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.legend-color { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.legend-text { flex: 1; font-size: 0.8rem; font-weight: 600; color: var(--text-main); }
.legend-count { font-size: 0.8rem; font-weight: 800; color: var(--text-main); }

/* ── Horizontal Bar (Products & Category) ── */
.h-bar-list { display: flex; flex-direction: column; gap: 10px; }
.h-bar-row {
  display: flex; align-items: center; gap: 12px; padding: 6px 0;
  transition: all 0.2s;
}
.h-bar-row:hover { background: var(--hover-bg); border-radius: 6px; padding-left: 8px; }
.h-bar-rank {
  width: 24px; height: 24px; border-radius: 6px;
  background: var(--hover-bg); color: var(--text-muted);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem; font-weight: 800; flex-shrink: 0;
}
.rank-1 { background: linear-gradient(135deg, #ffd700, #ffb300); color: white; }
.rank-2 { background: linear-gradient(135deg, #b0bec5, #90a4ae); color: white; }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #a0522d); color: white; }
.h-bar-info { flex: 1; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.h-bar-name {
  font-size: 0.82rem; font-weight: 600; color: var(--text-main);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.h-bar-track {
  height: 6px; background: var(--hover-bg); border-radius: 3px; overflow: hidden;
}
.h-bar-fill {
  height: 100%; border-radius: 3px; transition: width 0.5s ease;
}
.h-bar-stats { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; }
.h-bar-qty { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
.h-bar-rev { font-size: 0.78rem; font-weight: 800; color: var(--text-main); }

/* ── Payment Methods ── */
.payment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.payment-card {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 16px 12px; border-radius: 10px; border: 1px solid var(--border-color);
  background: var(--ocean-deepest); transition: all 0.2s;
}
.payment-card:hover { border-color: rgba(2,136,209,0.25); transform: translateY(-1px); }
.payment-icon {
  width: 40px; height: 40px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.payment-label { font-size: 0.78rem; font-weight: 700; color: var(--text-main); }
.payment-count { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
.payment-total { font-size: 0.85rem; font-weight: 800; color: var(--ocean-blue); }

/* ── Customer Bar Chart ── */
.customer-chart {
  display: flex; align-items: flex-end; justify-content: space-around; height: 180px;
}
.cust-bar-col { display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1; }
.cust-bar-track { height: 150px; display: flex; align-items: flex-end; width: 100%; justify-content: center; }
.cust-bar-fill {
  width: 32px; border-radius: 6px 6px 2px 2px;
  background: linear-gradient(180deg, #7e57c2, #5e35b1);
  position: relative; transition: all 0.3s ease; cursor: pointer; min-height: 2px;
}
.cust-bar-fill:hover { opacity: 0.85; }
.cust-bar-tip {
  position: absolute; top: -26px; left: 50%; transform: translateX(-50%) scale(0);
  background: var(--text-main); border-radius: 6px; padding: 3px 8px;
  font-size: 0.7rem; font-weight: 700; color: white;
  white-space: nowrap; transition: transform 0.15s; pointer-events: none;
}
.cust-bar-fill:hover .cust-bar-tip { transform: translateX(-50%) scale(1); }
.cust-bar-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }

/* ── Review Stats ── */
.review-summary { display: flex; gap: 24px; align-items: center; }
.review-avg { display: flex; flex-direction: column; align-items: center; gap: 4px; flex-shrink: 0; min-width: 100px; }
.avg-number { font-size: 2.5rem; font-weight: 800; color: var(--text-main); line-height: 1; }
.avg-stars { display: flex; gap: 2px; }
.star { font-size: 1rem; color: var(--border-color); }
.star.filled { color: #ffa726; }
.avg-total { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
.rating-bars { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.rating-row { display: flex; align-items: center; gap: 8px; }
.rating-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); width: 24px; }
.rating-track { flex: 1; height: 8px; background: var(--hover-bg); border-radius: 4px; overflow: hidden; }
.rating-fill {
  height: 100%; border-radius: 4px; background: linear-gradient(90deg, #ffa726, #ff9800);
  transition: width 0.5s ease;
}
.rating-count { font-size: 0.72rem; font-weight: 700; color: var(--text-main); width: 28px; text-align: right; }

/* ── Low Stock Table ── */
.stock-table { display: flex; flex-direction: column; gap: 0; }
.stock-header, .stock-row {
  display: grid; grid-template-columns: 1.5fr 0.8fr 0.5fr 0.6fr;
  gap: 8px; padding: 8px 12px; align-items: center;
}
.stock-header {
  font-size: 0.7rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border-color);
}
.stock-row {
  font-size: 0.8rem; border-bottom: 1px solid var(--border-color);
  transition: background 0.2s;
}
.stock-row:hover { background: var(--hover-bg); }
.stock-row:last-child { border-bottom: none; }
.stock-product { display: flex; flex-direction: column; }
.stock-name { font-weight: 700; color: var(--text-main); font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stock-variant { font-size: 0.7rem; color: var(--text-muted); }
.stock-sku { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
.stock-qty { font-weight: 800; color: #ffa726; }
.stock-qty.critical { color: #ef5350; }
.stock-safety { font-weight: 600; color: var(--text-muted); }

/* ── Recent Orders Table ── */
.orders-table { display: flex; flex-direction: column; }
.table-head, .table-row {
  display: grid; grid-template-columns: 1fr 1.2fr 0.8fr 0.7fr 0.8fr 1fr;
  gap: 8px; padding: 10px 12px; align-items: center;
}
.table-head {
  font-size: 0.7rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border-color);
}
.table-row {
  font-size: 0.82rem; border-bottom: 1px solid var(--border-color);
  cursor: pointer; transition: all 0.2s;
}
.table-row:hover { background: var(--hover-bg); }
.table-row:last-child { border-bottom: none; }
.order-code { font-weight: 800; color: var(--ocean-blue); }
.order-customer { font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.order-total { font-weight: 800; color: var(--text-main); }
.order-payment { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }
.order-date { font-size: 0.75rem; color: var(--text-muted); }
.order-status {
  font-size: 0.65rem; font-weight: 700; padding: 3px 8px;
  border-radius: 6px; text-transform: uppercase; letter-spacing: 0.3px;
  text-align: center; white-space: nowrap;
}
.st-completed, .st-delivered { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.st-pending { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.st-confirmed { background: rgba(66, 165, 245, 0.15); color: #1565c0; }
.st-shipping, .st-shipped { background: rgba(3, 169, 244, 0.15); color: #0277bd; }
.st-cancelled { background: rgba(239, 83, 80, 0.15); color: #c62828; }

/* ── Empty State ── */
.empty-state {
  padding: 32px; text-align: center; color: var(--text-muted);
  font-size: 0.85rem; font-weight: 500;
}
.empty-good { color: var(--seafoam); font-weight: 600; }

/* ── Animation ── */
.animate-in {
  animation: fadeSlideIn 0.4s ease forwards;
  opacity: 0;
}
@keyframes fadeSlideIn {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 1200px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .row-two { grid-template-columns: 1fr; }
  .payment-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
  .kpi-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; align-items: flex-start; }
  .period-filter { flex-wrap: wrap; }
  .donut-wrapper { flex-direction: column; }
  .review-summary { flex-direction: column; }
  .payment-grid { grid-template-columns: repeat(2, 1fr); }
  .table-head, .table-row { grid-template-columns: 1fr 1fr 0.8fr 0.8fr; }
  .table-head span:nth-child(4), .table-row span:nth-child(4),
  .table-head span:nth-child(6), .table-row span:nth-child(6) { display: none; }
}
@media (max-width: 480px) {
  .payment-grid { grid-template-columns: 1fr; }
}
</style>
