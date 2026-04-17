<script setup>
import { computed } from "vue";
import { useFavorites } from "@/composables/useFavorites";

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["unfavorite"]);
const { isFavorited, toggleFavorite } = useFavorites();

const handleToggleFav = async () => {
    const pId = props.product.id || props.product.product_id;
    if (!pId) return;

    // Check old state
    const wasFavorited = isFavorited(pId);

    const success = await toggleFavorite(pId);
    if (success && wasFavorited) {
        emit("unfavorite", pId); // Notify parent if it was removed
    }
};
</script>

<template>
    <div class="product-card ocean-card group mx-1">
        <router-link
            :to="{
                name: 'product-detail',
                params: { slug: props.product.slug },
            }"
            class="text-decoration-none"
        >
            <div class="product-img-wrapper">
                <!-- Badges -->
                <div class="badges-container">
                    <span
                        class="product-badge"
                        v-if="props.product.badge"
                        :class="{
                            'badge-hot': props.product.badge === 'Hot',
                            'badge-new': props.product.badge === 'New',
                        }"
                    >
                        {{ props.product.badge }}
                    </span>
                </div>

                <!-- Main Image -->
                <img
                    :src="props.product.image"
                    :alt="props.product.name"
                    class="product-img main-img"
                    loading="lazy"
                />

                <!-- Top Right Actions -->
                <div class="action-cluster">
                    <button
                        class="icon-action-btn fav-icon-btn"
                        :class="{
                            'is-active': isFavorited(
                                props.product.id || props.product.product_id,
                            ),
                        }"
                        @click.prevent="handleToggleFav"
                        title="Yêu thích"
                    >
                        <svg
                            v-if="
                                isFavorited(
                                    props.product.id ||
                                        props.product.product_id,
                                )
                            "
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="#ff4757"
                            stroke="#ff4757"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                        <svg
                            v-else
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                    </button>
                </div>

                <!-- Horizontal Add to Cart Button (Hover State) -->
                <div class="hover-add-to-cart">
                    <button
                        class="add-to-cart-horizontal"
                        @click.prevent="() => {}"
                    >
                        <span class="cart-btn-text">Thêm vào giỏ</span>
                        <svg class="cart-btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="8" cy="21" r="1"/>
                            <circle cx="19" cy="21" r="1"/>
                            <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="product-info">
                <h3
                    class="product-name text-truncate mb-0"
                    :title="props.product.name"
                >
                    {{ props.product.name }}
                </h3>
                <div
                    class="d-flex align-items-center justify-content-between mt-2"
                >
                    <span class="product-price">{{ props.product.price }}</span>
                </div>
            </div>
        </router-link>
    </div>
</template>

<style scoped>
.product-card {
    padding: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    background: transparent;
    border: none;
    border-radius: var(--radius-lg);
    height: 100%;
}

.product-card:hover {
    transform: translateY(-4px);
}

.product-img-wrapper {
    position: relative;
    margin: 0;
    border-radius: var(--radius-lg);
    aspect-ratio: 4/5;
    background: #f8fafc;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.3s ease;
}

.product-card:hover .product-img-wrapper {
    box-shadow: var(--shadow-md);
}

.badges-container {
    position: absolute;
    top: 16px;
    left: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 10;
}

.product-badge {
    background: var(--ocean-blue);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: none;
}

.badge-hot {
    background: #0f172a;
}
.badge-new {
    background: #64748b;
}

.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}

.product-card:hover .main-img {
    transform: scale(1.05); /* Slight gentle zoom */
}

/* Action cluster top right */
.action-cluster {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 10;
}

.icon-action-btn {
    background: #ffffff;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    opacity: 0;
    transform: translateX(10px);
}

/* Stagger animation */
.fav-icon-btn {
    transition-delay: 0.05s;
}

.product-card:hover .icon-action-btn,
.fav-icon-btn.is-active {
    opacity: 1;
    transform: translateX(0);
}

.icon-action-btn:hover {
    color: #111111;
    transform: translateX(0) scale(1.08) !important;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
}

.hover-add-to-cart {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    transform: translateY(150%);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    z-index: 10;
}

.product-card:hover .hover-add-to-cart {
    transform: translateY(0);
    opacity: 1;
}

.add-to-cart-horizontal {
    width: 100%;
    background: rgba(255, 255, 255, 0.95);
    color: var(--ocean-blue);
    border: 1px solid rgba(2, 132, 199, 0.1);
    border-radius: 8px;
    padding: 12px 0;
    min-height: 44px;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
    backdrop-filter: blur(4px);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cart-btn-icon {
    display: none;
}

@media (max-width: 1024px) {
    .cart-btn-text {
        display: none;
    }
    .cart-btn-icon {
        display: block;
    }
    .hover-add-to-cart {
        bottom: 8px;
        left: 8px;
        right: 8px;
    }
    .add-to-cart-horizontal {
        min-height: 40px;
        padding: 8px 0;
    }
}

.add-to-cart-horizontal:hover {
    background: var(--ocean-blue);
    color: #ffffff;
    border-color: var(--ocean-blue);
}

.fav-icon-btn.is-active {
    color: #ff4757;
}

.fav-icon-btn.is-active:hover {
    background: #ffffff;
    color: #ff6b81;
}

.product-info {
    padding: 14px 4px 16px;
    background: transparent;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.product-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #111111;
    text-transform: none;
    letter-spacing: 0px;
    transition: opacity 0.2s;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 38px;
    line-height: 1.4;
}

.product-card:hover .product-name {
    opacity: 0.7;
    color: var(--text-main);
}

.product-price {
    font-weight: 800;
    color: var(--ocean-blue);
    font-size: 1.05rem;
}

.swatch-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 1px #cbd5e1;
    cursor: pointer;
    transition: transform 0.2s;
}

.swatch-dot:hover {
    transform: scale(1.3);
}
</style>
