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
    <template v-for="category in categories" :key="category.post_category_id">
        <option
            :value="category.post_category_id"
            :disabled="category.post_category_id === currentParentId"
        >{{ '　'.repeat(level) + (level > 0 ? '└ ' : '') + category.name + (category.post_category_id === currentParentId ? ' (đang chỉnh sửa)' : '') }}</option>
        <AdminPostCategoryFormTree
            v-if="category.children && category.children.length > 0"
            :categories="category.children"
            :level="level + 1"
            :currentParentId="currentParentId"
        />
    </template>
</template>
