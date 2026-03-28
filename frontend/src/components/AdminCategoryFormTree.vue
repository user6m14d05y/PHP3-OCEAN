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
<<<<<<< HEAD
    <template v-for="category in categories" :key="category.category_id">
        <option
            :value="category.category_id"
            :disabled="category.category_id === currentParentId"
        >{{ '　'.repeat(level) + (level > 0 ? '└ ' : '') + category.name + (category.category_id === currentParentId ? ' (đang chỉnh sửa)' : '') }}</option>
=======
    <template v-for="category in categories" :key="category.category_id || category.post_category_id">
        <option
            :value="category.category_id || category.post_category_id"
            :disabled="(category.category_id || category.post_category_id) === currentParentId"
        >{{ '　'.repeat(level) + (level > 0 ? '└ ' : '') + category.name + ((category.category_id || category.post_category_id) === currentParentId ? ' (đang chỉnh sửa)' : '') }}</option>
>>>>>>> origin/binhbc
        <AdminCategoryFormTree
            v-if="category.children && category.children.length > 0"
            :categories="category.children"
            :level="level + 1"
            :currentParentId="currentParentId"
        />
    </template>
<<<<<<< HEAD
</template>
=======
</template>
>>>>>>> origin/binhbc
