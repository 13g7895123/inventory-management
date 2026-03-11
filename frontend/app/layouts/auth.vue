<template>
  <!-- Auth 布局：畫面置中的簡潔登入卡片 -->
  <div class="min-h-screen flex items-center justify-center bg-background p-4 relative overflow-hidden">
    <!-- Subtle Background Pattern -->
    <div class="absolute inset-0 z-0">
      <div class="absolute inset-0 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] [mask-image:radial-gradient(ellipse_50%_50%_at_50%_50%,#000_70%,transparent_100%)] opacity-40 dark:bg-[radial-gradient(#1f2937_1px,transparent_1px)]"></div>
      <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
    </div>
    
    <!-- Content -->
    <div class="relative z-10 w-full max-w-sm">
      <slot />
    </div>
  </div>
</template>
