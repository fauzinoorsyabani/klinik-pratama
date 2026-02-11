<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const mousePosition = ref({ x: 0, y: 0 });

const handleMouseMove = (event: MouseEvent) => {
    // Get mouse position relative to the viewport
    const { clientX, clientY } = event;
    // Calculate position from the center of the screen
    const x = clientX - window.innerWidth / 2;
    const y = clientY - window.innerHeight / 2;
    mousePosition.value = { x, y };
};

onMounted(() => {
    window.addEventListener('mousemove', handleMouseMove);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
});
</script>

<template>
    <div
        class="absolute inset-0 w-full h-full overflow-hidden transition-transform duration-300 ease-out z-0 pointer-events-none"
        :style="{
            backgroundImage: `
                linear-gradient(to right, rgba(100, 116, 139, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(100, 116, 139, 0.1) 1px, transparent 1px)
            `,
            backgroundSize: '40px 40px',
            animation: 'moveGrid 20s linear infinite',
            transform: `translate(${mousePosition.x / 30}px, ${mousePosition.y / 30}px)`,
        }"
    >
        <!-- Glow effect -->
        <div
            class="absolute top-1/2 left-1/2 w-[60vmin] h-[60vmin] bg-primary-500/10 dark:bg-cyan-500/20 rounded-full blur-[150px] -translate-x-1/2 -translate-y-1/2"
        ></div>
        
        <!-- Keyframes for the animation -->
        <component is="style">
            @keyframes moveGrid {
                0% { background-position: 0 0; }
                100% { background-position: 80px 80px; }
            }
        </component>
    </div>
</template>
