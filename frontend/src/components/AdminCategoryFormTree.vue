<script setup>
    const props = defineProps({
        categories: {
            type: Array,
            required: true
        },
        level: {
            type: Number,
            default: 0
        },
        currentParentId: {
            type: [Number, String, Object],
            default: null
        }
    })
</script>

<template>
    <template v-for="category in categories" :key="category.category_id">
        <option :value="category.category_id">
            <span v-if="props.level > 0" class="text-secondary me-1">{{ '— '.repeat(props.level) }}</span>
            {{ category.name }}
        </option>
        <AdminCategoryFormTree v-if="category.children && category.children.length > 0" :categories="category.children" :level="level + 1" :currentParentId="category.category_id" />
    </template>
</template>