<script setup lang="ts">
// 根路徑重導向至 /dashboard
definePageMeta({ middleware: [] })

const router = useRouter()
onMounted(() => router.replace('/dashboard'))
</script>

<template>
  <div />
</template>
