<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';

const toast = {
  success: (msg) => Swal.fire({ icon: 'success', title: 'Thành công', text: msg, timer: 2000, showConfirmButton: false }),
  error: (msg) => Swal.fire({ icon: 'error', title: 'Lỗi', text: msg, timer: 3000, showConfirmButton: false })
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN', { hour: '2-digit', minute:'2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getImageUrl = (path) => {
  if (!path) return '/placeholder.jpg';
  if (path.startsWith('http')) return path;
  return `${import.meta.env.VITE_BASE_URL || 'http://localhost:8383'}/storage/${path}`;
};

// ================== SEARCH PRODUCTS ==================
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

const handleSearch = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }
  
  isSearching.value = true;
  try {
    const res = await api.get('/admin/pos/products/search', { params: { q: searchQuery.value } });
    if (res.data.status === 'success') {
      searchResults.value = res.data.data;
    }
  } catch (error) {
    console.error('Search error:', error);
  } finally {
    isSearching.value = false;
  }
};

let searchTimeout;
const onSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(handleSearch, 400);
};

// ================== SELECT VARIANT MODAL ==================
const selectedProduct = ref(null);
const selectedVariant = ref(null);
const showVariantModal = ref(false);

const selectProduct = (product) => {
  if (product.variants.length === 1) {
    // Only one variant, add directly
    addToCart(product.variants[0], product);
  } else {
    // Show variant selector
    selectedProduct.value = product;
    selectedVariant.value = null; // Reset selection
    showVariantModal.value = true;
  }
};

const closeVariantModal = () => {
    showVariantModal.value = false;
    selectedProduct.value = null;
    selectedVariant.value = null;
};

const confirmVariantSelect = () => {
    if(!selectedVariant.value) {
        return;
    }
    addToCart(selectedVariant.value, selectedProduct.value);
    closeVariantModal();
}

const selectVariant = (variant) => {
  if(variant.stock <= 0) return;
  selectedVariant.value = variant;
};

// ================== CART ==================
const cartItems = ref([]);

const addToCart = (variant, product) => {
  const existingItem = cartItems.value.find(item => item.variant_id === variant.variant_id);
  
  if (existingItem) {
    if (existingItem.quantity < variant.stock) {
      existingItem.quantity++;
    } else {
      toast.error('Số lượng vượt quá tồn kho!');
    }
  } else {
    cartItems.value.push({
      variant_id: variant.variant_id,
      product_name: product.name,
      variant_name: variant.variant_name,
      color: variant.color,
      size: variant.size,
      price: variant.price,
      stock: variant.stock,
      image_url: variant.image_url || product.thumbnail,
      quantity: 1
    });
  }
};

const increaseQuantity = (item) => {
  if (item.quantity < item.stock) {
    item.quantity++;
  } else {
    toast.error('Số lượng vượt quá tồn kho!');
  }
};

const decreaseQuantity = (item) => {
  if (item.quantity > 1) {
    item.quantity--;
  } else {
    removeFromCart(item);
  }
};

const removeFromCart = (item) => {
  cartItems.value = cartItems.value.filter(i => i.variant_id !== item.variant_id);
};

const clearCart = () => {
  cartItems.value = [];
};

// ================== CHECKOUT ==================
const customerName = ref('');
const customerPhone = ref('');
const paymentMethod = ref('pos_cash');
const note = ref('');
const discountAmount = ref(0);
const isCheckingOut = ref(false);

const subtotal = computed(() => {
  return cartItems.value.reduce((total, item) => total + (item.price * item.quantity), 0);
});

const grandTotal = computed(() => {
  return Math.max(0, subtotal.value - discountAmount.value);
});

const printReceipt = (order) => {
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    
    // Generate Items HTML
    let itemsHtml = '';
    order.items.forEach(item => {
        itemsHtml += `
            <div class="item">
                <div class="item-name">${item.product_name} (${item.color || ''} - ${item.size || ''})</div>
                <div class="item-details">
                    <span>${item.quantity} x ${formatPrice(item.unit_price)}</span>
                    <span>${formatPrice(item.line_total)}</span>
                </div>
            </div>
        `;
    });

    const receiptHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Hóa đơn ${order.order_code}</title>
            <style>
                body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0 auto; color: #000; font-size: 12px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
                .header h2 { margin: 0 0 5px 0; font-size: 18px; }
                .info { margin-bottom: 15px; }
                .info div { margin-bottom: 3px; }
                .item { margin-bottom: 10px; }
                .item-name { font-weight: bold; margin-bottom: 2px; }
                .item-details { display: flex; justify-content: space-between; }
                .summary { border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; }
                .summary-line { display: flex; justify-content: space-between; margin-bottom: 5px; }
                .summary-line.bold { font-weight: bold; font-size: 14px; }
                .footer { text-align: center; margin-top: 20px; border-top: 1px dashed #000; padding-top: 10px; font-size: 11px; }
                @media print {
                    body { width: 100%; }
                }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <div class="header">
                <h2>OCEAN SHOP</h2>
                <div>Địa chỉ: 123 Đường Bơi, Đại Dương</div>
                <div>SĐT: 0123 456 789</div>
            </div>
            
            <div class="info">
                <div><strong>Mã đơn:</strong> ${order.order_code}</div>
                <div><strong>Ngày:</strong> ${formatDate(order.created_at)}</div>
                <div><strong>Khách hàng:</strong> ${order.recipient_name || 'Khách lẻ'}</div>
            </div>

            <div class="items">
                ${itemsHtml}
            </div>

            <div class="summary">
                <div class="summary-line">
                    <span>Tổng phụ:</span>
                    <span>${formatPrice(order.subtotal)}</span>
                </div>
                <div class="summary-line">
                    <span>Chiết khấu:</span>
                    <span>- ${formatPrice(order.discount_amount)}</span>
                </div>
                <div class="summary-line bold">
                    <span>THANH TOÁN:</span>
                    <span>${formatPrice(order.grand_total)}</span>
                </div>
            </div>

            <div class="footer">
                <div>Cảm ơn quý khách đã mua hàng!</div>
                <div>Hẹn gặp lại!</div>
            </div>
        </body>
        </html>
    `;

    printWindow.document.write(receiptHtml);
    printWindow.document.close();
};

const handleCheckout = async () => {
  if (cartItems.value.length === 0) {
    toast.error('Giỏ hàng trống!');
    return;
  }
  
  isCheckingOut.value = true;
  
  try {
    const payload = {
      items: cartItems.value.map(item => ({
        variant_id: item.variant_id,
        quantity: item.quantity
      })),
      customer_name: customerName.value,
      customer_phone: customerPhone.value,
      payment_method: paymentMethod.value,
      note: note.value,
      discount_amount: discountAmount.value
    };
    
    const res = await api.post('/admin/pos/checkout', payload);
    
    if (res.data.status === 'success') {
      const createdOrder = res.data.data;
      
      // Mở hộp thoại thông báo + chức năng in
      Swal.fire({
          icon: 'success',
          title: 'Thanh toán thành công',
          html: `Mã đơn hàng: <strong class="text-primary">${createdOrder.order_code}</strong>`,
          confirmButtonText: 'Đóng',
          showCancelButton: true,
          cancelButtonText: '<i class="fas fa-print me-1"></i> In hóa đơn',
          cancelButtonColor: '#0ea5e9'
      }).then((result) => {
          if (result.dismiss === Swal.DismissReason.cancel) {
              printReceipt(createdOrder);
          }
      });

      // Reset form
      cartItems.value = [];
      customerName.value = '';
      customerPhone.value = '';
      note.value = '';
      discountAmount.value = 0;
      searchQuery.value = '';
      searchResults.value = [];
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Lỗi thanh toán';
    toast.error(errorMsg);
  } finally {
    isCheckingOut.value = false;
  }
};

// ================== BARCODE SCANNER ==================
const barcodeValue = ref('');
const isScannerActive = ref(true);
const isScanning = ref(false);
const scanResult = ref(null); // { type: 'success' | 'error', message: '' }
const barcodeInputRef = ref(null);
let scanResultTimeout = null;

const focusBarcodeInput = () => {
  nextTick(() => {
    if (barcodeInputRef.value && isScannerActive.value) {
      barcodeInputRef.value.focus();
    }
  });
};

const toggleScanner = () => {
  isScannerActive.value = !isScannerActive.value;
  if (isScannerActive.value) {
    focusBarcodeInput();
  }
};

const showScanResult = (type, message) => {
  scanResult.value = { type, message };
  clearTimeout(scanResultTimeout);
  scanResultTimeout = setTimeout(() => {
    scanResult.value = null;
  }, 3000);
};

const searchByBarcode = async (barcode) => {
  if (!barcode || isScanning.value) return;
  
  isScanning.value = true;
  scanResult.value = null;
  
  try {
    const res = await api.get('/admin/pos/products/scan', { params: { code: barcode } });
    
    if (res.data.status === 'success') {
      const variantData = res.data.data;
      
      // Tự động thêm vào giỏ hàng
      const existingItem = cartItems.value.find(item => item.variant_id === variantData.variant_id);
      
      if (existingItem) {
        if (existingItem.quantity < variantData.stock) {
          existingItem.quantity++;
          showScanResult('success', `+1 ${variantData.product.name} (${variantData.color || ''} ${variantData.size || ''})`);
        } else {
          showScanResult('error', 'Số lượng vượt quá tồn kho!');
        }
      } else {
        if (variantData.stock <= 0) {
          showScanResult('error', `${variantData.product.name} đã hết hàng!`);
        } else {
          cartItems.value.push({
            variant_id: variantData.variant_id,
            product_name: variantData.product.name,
            variant_name: variantData.variant_name,
            color: variantData.color,
            size: variantData.size,
            price: variantData.price,
            stock: variantData.stock,
            image_url: variantData.image_url || variantData.product.thumbnail,
            quantity: 1
          });
          showScanResult('success', `Đã thêm: ${variantData.product.name} (${variantData.color || ''} ${variantData.size || ''})`);
        }
      }
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Không tìm thấy sản phẩm với mã này';
    showScanResult('error', errorMsg);
  } finally {
    isScanning.value = false;
    barcodeValue.value = '';
    focusBarcodeInput();
  }
};

const onBarcodeEnter = () => {
  const barcode = barcodeValue.value.trim();
  if (barcode) {
    searchByBarcode(barcode);
  }
};

// Lắng nghe sự kiện keyboard global để hỗ trợ scanner hardware
let barcodeBuffer = '';
let barcodeTimeout = null;

const handleGlobalKeydown = (e) => {
  // Nếu đang focus vào input khác (search, form...) thì bỏ qua
  const activeEl = document.activeElement;
  const isOtherInput = activeEl && 
    (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA') && 
    activeEl !== barcodeInputRef.value;
  
  if (isOtherInput || !isScannerActive.value) return;
  
  // Scanner thường gửi ký tự rất nhanh, kết thúc bằng Enter
  if (e.key === 'Enter' && barcodeBuffer.length > 3) {
    e.preventDefault();
    searchByBarcode(barcodeBuffer);
    barcodeBuffer = '';
    return;
  }
  
  if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
    barcodeBuffer += e.key;
    clearTimeout(barcodeTimeout);
    barcodeTimeout = setTimeout(() => {
      barcodeBuffer = '';
    }, 200); // Scanner gửi toàn bộ trong vòng ~100ms
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);
  focusBarcodeInput();
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
  clearTimeout(scanResultTimeout);
  clearTimeout(barcodeTimeout);
});
</script>

<template>
  <div class="pos-container">
    <div class="row m-0 h-100">
      
      <!-- ================== CỘT TRÁI: TÌM KIẾM SẢN PHẨM ================== -->
      <div class="col-lg-7 col-xl-8 pos-left">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="mb-0 fw-bold">POS - Bán hàng</h4>
          <button 
            class="btn scanner-toggle-btn" 
            :class="{ 'active': isScannerActive }"
            @click="toggleScanner"
          >
            <i class="fas fa-barcode me-2"></i>
            <span>{{ isScannerActive ? 'Scanner: BẬT' : 'Scanner: TẮT' }}</span>
          </button>
        </div>

        <!-- Barcode Scanner Input -->
        <div v-if="isScannerActive" class="barcode-scanner-box mb-3">
          <div class="scanner-inner">
            <div class="scanner-icon-wrapper">
              <i class="fas fa-barcode scanner-icon" :class="{ 'scanning': isScanning }"></i>
              <div v-if="isScanning" class="scanner-pulse"></div>
            </div>
            <div class="scanner-input-group">
              <input
                ref="barcodeInputRef"
                type="text"
                v-model="barcodeValue"
                @keyup.enter="onBarcodeEnter"
                class="form-control barcode-input"
                placeholder="Quét barcode hoặc nhập mã sản phẩm..."
                autocomplete="off"
                :disabled="isScanning"
              >
              <button 
                class="btn btn-primary barcode-submit-btn" 
                @click="onBarcodeEnter" 
                :disabled="isScanning || !barcodeValue.trim()"
              >
                <i v-if="isScanning" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-search"></i>
              </button>
            </div>
            <small class="scanner-hint">
              <i class="fas fa-mobile-alt me-1"></i>
              Dùng điện thoại quét mã hoặc nhập barcode rồi nhấn Enter
            </small>
          </div>
          <!-- Scan Result Feedback -->
          <transition name="slide-fade">
            <div v-if="scanResult" class="scan-result" :class="scanResult.type">
              <i :class="scanResult.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
              <span>{{ scanResult.message }}</span>
            </div>
          </transition>
        </div>
        
        <!-- Thanh tìm kiếm -->
        <div class="search-box mb-4 position-relative">
          <i class="fas fa-search search-icon"></i>
          <input 
            type="text" 
            v-model="searchQuery" 
            @input="onSearchInput"
            class="form-control form-control-lg ps-5" 
            placeholder="Tìm kiếm sản phẩm theo tên, SKU..."
          >
          <div v-if="isSearching" class="spinner-border spinner-border-sm text-primary position-absolute top-50 end-0 translate-middle-y me-3" role="status"></div>
        </div>
        
        <!-- Kết quả tìm kiếm -->
        <div class="search-results">
            <div v-if="searchResults.length === 0 && searchQuery.length > 0 && !isSearching" class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3 text-light"></i>
                <p>Không tìm thấy sản phẩm nào</p>
            </div>
            
            <div v-else-if="searchResults.length > 0" class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
              <div v-for="product in searchResults" :key="product.product_id" class="col">
                <div class="product-card" @click="selectProduct(product)">
                  <div class="product-img-wrapper">
                      <img :src="getImageUrl(product.thumbnail)" :alt="product.name" class="product-img" onerror="this.src='/placeholder.jpg'">
                      <span v-if="product.variants.length > 1" class="variant-badge">Nhiều loại</span>
                  </div>
                  <div class="product-info">
                    <h6 class="product-title" :title="product.name">{{ product.name }}</h6>
                    <div class="product-price text-primary fw-bold">
                        <template v-if="product.variants.length === 1">
                            {{ formatPrice(product.variants[0].price) }}
                        </template>
                        <template v-else>
                            Xem tuỳ chọn
                        </template>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div v-else class="empty-state">
                <i class="fas fa-shopping-basket fa-4x text-light mb-3"></i>
                <h5 class="text-muted">Nhập từ khóa tìm kiếm hoặc quét SKU để thêm sản phẩm</h5>
            </div>
        </div>
      </div>
      
      <!-- ================== CỘT PHẢI: GIỎ HÀNG & THANH TOÁN ================== -->
      <div class="col-lg-5 col-xl-4 pos-right">
        
        <!-- Tabs Giỏ hàng / Đơn khác (Optional - for future) -->
        <ul class="nav nav-tabs cart-tabs mb-3">
          <li class="nav-item">
            <a class="nav-link active" href="#">Đơn hiện tại</a>
          </li>
          <!-- <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-plus"></i> Thêm khu vực</a></li> -->
          <li class="nav-item ms-auto">
            <button class="btn btn-sm btn-outline-danger mt-1 me-2" @click="clearCart" :disabled="cartItems.length === 0">Xóa hết</button>
          </li>
        </ul>
        
        <!-- Danh sách items -->
        <div class="cart-items-container">
          <table class="table table-borderless table-hover cart-table mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th width="45%">Sản phẩm</th>
                <th width="20%">Số lượng</th>
                <th width="25%" class="text-end">Thành tiền</th>
                <th width="10%"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="cartItems.length === 0">
                <td colspan="4" class="text-center py-5 text-muted">Giỏ hàng trống</td>
              </tr>
              <tr v-for="item in cartItems" :key="item.variant_id">
                <td>
                  <div class="d-flex align-items-center">
                    <img :src="getImageUrl(item.image_url)" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2" onerror="this.src='/placeholder.jpg'">
                    <div>
                      <div class="fw-bold truncate-1" :title="item.product_name">{{ item.product_name }}</div>
                      <small class="text-muted">{{ item.color }} - {{ item.size }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="qty-control">
                    <button class="qty-btn" @click="decreaseQuantity(item)">-</button>
                    <input type="text" readonly :value="item.quantity" class="qty-input">
                    <button class="qty-btn" @click="increaseQuantity(item)">+</button>
                  </div>
                </td>
                <td class="text-end fw-bold text-primary">
                  {{ formatPrice(item.price * item.quantity) }}
                </td>
                <td class="text-end">
                  <button class="btn btn-sm text-danger border-0 bg-transparent p-0" @click="removeFromCart(item)">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Box Thanh toán -->
        <div class="checkout-box">
          <div class="checkout-form">
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="form-floating">
                  <input type="text" class="form-control form-control-sm" id="custName" v-model="customerName" placeholder="Tên khách">
                  <label for="custName">Tên KH (Tùy chọn)</label>
                </div>
              </div>
              <div class="col-6">
                <div class="form-floating">
                  <input type="text" class="form-control form-control-sm" id="custPhone" v-model="customerPhone" placeholder="SĐT">
                  <label for="custPhone">SĐT (Tùy chọn)</label>
                </div>
              </div>
            </div>
            
            <div class="d-flex align-items-center mb-3">
                <span class="me-3 text-muted" style="min-width: 80px;">Thanh toán:</span>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="payment_method" id="pay_cash" value="pos_cash" v-model="paymentMethod">
                    <label class="btn btn-outline-primary btn-sm" for="pay_cash">Tiền mặt</label>

                    <input type="radio" class="btn-check" name="payment_method" id="pay_transfer" value="pos_transfer" v-model="paymentMethod">
                    <label class="btn btn-outline-primary btn-sm" for="pay_transfer">C.Khoản</label>
                    
                    <input type="radio" class="btn-check" name="payment_method" id="pay_card" value="pos_card" v-model="paymentMethod">
                    <label class="btn btn-outline-primary btn-sm" for="pay_card">Thẻ</label>
                </div>
            </div>

            <div class="form-floating mb-3">
                <textarea class="form-control" placeholder="Ghi chú" id="note" v-model="note" style="height: 60px"></textarea>
                <label for="note">Ghi chú đơn hàng / KH</label>
            </div>
            
            <div class="summary-line d-flex justify-content-between mb-2">
                <span class="text-muted">Tạm tính:</span>
                <span class="fw-bold">{{ formatPrice(subtotal) }}</span>
            </div>
            <div class="summary-line d-flex justify-content-between mb-3 align-items-center">
                <span class="text-muted">Giảm giá đ/đơn:</span>
                <input type="number" class="form-control form-control-sm text-end" style="width: 120px;" v-model.number="discountAmount" min="0" placeholder="0">
            </div>
            
            <div class="summary-line d-flex justify-content-between mb-4 border-top pt-3">
                <span class="fw-bold fs-5 text-dark">Khách phải trả:</span>
                <span class="fw-bold fs-4 text-danger">{{ formatPrice(grandTotal) }}</span>
            </div>
            
            <button class="btn btn-primary w-100 py-3 fw-bold fs-5 text-uppercase" @click="handleCheckout" :disabled="isCheckingOut || cartItems.length === 0">
                <span v-if="isCheckingOut" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                <i v-else class="fas fa-money-bill-wave me-2"></i>
                Thanh toán
            </button>
          </div>
        </div>
        
      </div>
      
    </div>
  </div>

  <!-- MODAL CHỌN BIẾN THỂ (VUE DRIVEN) -->
  <div v-if="showVariantModal" class="vue-modal-backdrop">
    <div class="vue-modal-container">
      <div class="vue-modal-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0 text-dark">{{ selectedProduct?.name }}</h5>
          <button type="button" class="btn-close" @click="closeVariantModal"></button>
        </div>
        
        <div class="vue-modal-body">
            <p class="text-muted mb-3">Vui lòng chọn kiểu phân loại:</p>
            <div class="variants-grid">
                <div 
                    v-for="variant in selectedProduct?.variants" 
                    :key="variant.variant_id"
                    class="variant-item"
                    :class="{ 
                        'is-selected': selectedVariant?.variant_id === variant.variant_id,
                        'is-out-of-stock': variant.stock <= 0
                    }"
                    @click="selectVariant(variant)"
                >
                    <div class="d-flex align-items-center">
                        <img :src="getImageUrl(variant.image_url || selectedProduct?.thumbnail)" alt="" class="variant-img me-3" onerror="this.src='/placeholder.jpg'">
                        <div>
                            <div class="fw-bold mb-1 text-dark">{{ variant.color }} <span v-if="variant.size">- {{ variant.size }}</span></div>
                            <div class="text-primary fw-bold">{{ formatPrice(variant.price) }}</div>
                            <div class="text-muted small">Kho: {{ variant.stock }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
          <button type="button" class="btn btn-light me-2" @click="closeVariantModal">Hủy</button>
          <button type="button" class="btn btn-primary px-4" :disabled="!selectedVariant" @click="confirmVariantSelect">Thêm vào giỏ</button>
        </div>
      </div>
    </div>
  </div>

</template>

<style scoped>
.pos-container {
  height: calc(100vh - 70px);
  background-color: #f4f6f9;
  overflow: hidden;
  margin: -24px; /* remove padding from AdminLayout */
}

/* LEFT COLUMN */
.pos-left {
  background-color: #f4f6f9;
  padding: 24px;
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Scanner Toggle Button */
.scanner-toggle-btn {
  background: white;
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 0.85rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  transition: all 0.3s ease;
}
.scanner-toggle-btn.active {
  background: linear-gradient(135deg, #0ea5e9, #3b82f6);
  color: white;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);
}
.scanner-toggle-btn:hover {
  transform: translateY(-1px);
}

/* Barcode Scanner Box */
.barcode-scanner-box {
  background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
  border: 2px solid #bae6fd;
  border-radius: 14px;
  padding: 16px 20px;
  position: relative;
  overflow: hidden;
}
.barcode-scanner-box::before {
  content: '';
  position: absolute;
  top: -2px;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #0ea5e9, #3b82f6, #0ea5e9);
  background-size: 200% 100%;
  animation: scannerLine 2s linear infinite;
}
@keyframes scannerLine {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.scanner-inner {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.scanner-icon-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  flex-shrink: 0;
}
.scanner-icon {
  font-size: 1.8rem;
  color: #0ea5e9;
  transition: all 0.3s;
}
.scanner-icon.scanning {
  color: #f59e0b;
  animation: pulse-scale 0.6s ease infinite alternate;
}
@keyframes pulse-scale {
  from { transform: scale(1); }
  to { transform: scale(1.15); }
}
.scanner-pulse {
  position: absolute;
  width: 100%;
  height: 100%;
  border: 2px solid #f59e0b;
  border-radius: 50%;
  animation: ripple 1s ease-out infinite;
}
@keyframes ripple {
  0% { transform: scale(0.8); opacity: 1; }
  100% { transform: scale(1.8); opacity: 0; }
}

.scanner-input-group {
  flex: 1;
  display: flex;
  min-width: 200px;
}
.barcode-input {
  border-radius: 10px 0 0 10px !important;
  border: 2px solid #94a3b8;
  border-right: none;
  font-size: 1.05rem;
  font-weight: 500;
  padding: 10px 14px;
  letter-spacing: 0.5px;
  transition: border-color 0.3s;
}
.barcode-input:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}
.barcode-submit-btn {
  border-radius: 0 10px 10px 0 !important;
  padding: 10px 16px;
  font-size: 1.1rem;
}

.scanner-hint {
  color: #64748b;
  font-size: 0.78rem;
  width: 100%;
  padding-left: 62px;
  margin-top: -4px;
}

/* Scan Result Feedback */
.scan-result {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  padding: 10px 16px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.9rem;
}
.scan-result.success {
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  color: #047857;
  border: 1px solid #6ee7b7;
}
.scan-result.error {
  background: linear-gradient(135deg, #fef2f2, #fee2e2);
  color: #b91c1c;
  border: 1px solid #fca5a5;
}
.scan-result i {
  font-size: 1.1rem;
}

/* Slide-fade transition */
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.2s ease-in;
}
.slide-fade-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.search-icon {
  position: absolute;
  top: 50%;
  left: 20px;
  transform: translateY(-50%);
  color: #a0aec0;
  font-size: 1.2rem;
  z-index: 10;
}

.form-control-lg {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.search-results {
  flex: 1;
  overflow-y: auto;
  padding-right: 8px;
}

/* Tùy chỉnh thanh scroll */
.search-results::-webkit-scrollbar { width: 6px; }
.search-results::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

.product-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.04);
  cursor: pointer;
  transition: all 0.2s ease;
  height: 100%;
  border: 2px solid transparent;
}

.product-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
  border-color: #e0f2fe;
}

.product-img-wrapper {
  position: relative;
  padding-top: 100%; /* 1:1 Aspect ratio */
  background: #f8fafc;
}

.product-img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 10px;
}

.variant-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(14, 165, 233, 0.9);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
}

.product-info {
  padding: 12px;
}

.product-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 6px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
  height: 2.8em;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    opacity: 0.6;
}

/* RIGHT COLUMN */
.pos-right {
  background-color: white;
  border-left: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 0;
}

.cart-tabs {
    padding: 16px 16px 0 16px;
    margin-bottom: 0 !important;
}

.cart-items-container {
  flex: 1;
  overflow-y: auto;
  background: #fff;
}

.cart-table th {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
}

.cart-table td {
    padding: 12px 16px;
}

.truncate-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.qty-control {
    display: inline-flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
}

.qty-btn {
    background: #f8fafc;
    border: none;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #475569;
}
.qty-btn:hover {
    background: #e2e8f0;
}

.qty-input {
    width: 36px;
    height: 28px;
    border: none;
    border-left: 1px solid #cbd5e1;
    border-right: 1px solid #cbd5e1;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Checkout Box */
.checkout-box {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  padding: 20px;
  box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05);
}

.form-floating > label {
    padding: 0.5rem 0.75rem;
}
.form-floating > .form-control {
    height: calc(3rem + 2px);
    line-height: 1.25;
}

/* MODAL */
.variants-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.variant-item {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.variant-item:hover:not(.is-out-of-stock) {
    border-color: #94a3b8;
}

.variant-item.is-selected {
    border-color: #0ea5e9;
    background-color: #f0f9ff;
}

.variant-item.is-out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8fafc;
}

.variant-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #eee;
}

/* VUE DRIVEN MODAL STYLES */
.vue-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vue-modal-container {
    width: 100%;
    max-width: 500px;
    margin: 1.75rem auto;
}

.vue-modal-content {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
}

.vue-modal-body {
    flex: 1 1 auto;
}
</style>
