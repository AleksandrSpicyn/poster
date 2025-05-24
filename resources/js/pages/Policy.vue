<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-12 text-gray-800 dark:text-gray-200">
            <h1 class="mb-6 text-3xl font-bold">Политика конфиденциальности</h1>

            <p class="mb-4 text-sm text-gray-500">Дата вступления в силу: 21 мая 2025 г.</p>

            <h2 class="mt-8 mb-2 text-xl font-semibold">1. Кто мы</h2>
            <p class="mb-4">
                Мы — команда проекта <strong>AI Fails</strong>, создающего короткие развлекательные видео с помощью генеративного искусственного
                интеллекта.
            </p>
            <p class="mb-4">
                Сайт: <a href="https://24i-poster.ru" class="text-blue-600 underline">24i-poster.ru</a><br />
                Контакт: <a href="mailto:fenixxxar85@gmail.com" class="text-blue-600 underline">fenixxxar85@gmail.com</a>
            </p>

            <h2 class="mt-8 mb-2 text-xl font-semibold">2. Какие данные мы собираем</h2>
            <ul class="mb-4 list-inside list-disc space-y-1">
                <li>Имя вашего аккаунта</li>
                <li>Идентификаторы каналов и страниц</li>
                <li>Разрешения OAuth (YouTube, Instagram)</li>
                <li>Access Token (только для публикации от вашего имени)</li>
            </ul>

            <h2 class="mt-8 mb-2 text-xl font-semibold">3. Как мы используем данные</h2>
            <p class="mb-4">Мы используем их исключительно для публикации видео от вашего имени. Данные не передаются третьим лицам.</p>

            <h2 class="mt-8 mb-2 text-xl font-semibold">4. Хранение данных</h2>
            <p class="mb-4">Все токены шифруются. Вы можете отозвать доступ через Meta или Google.</p>

            <h2 class="mt-8 mb-2 text-xl font-semibold">5. Контакты</h2>
            <p>По вопросам: <a href="mailto:fenixxxar85@gmail.com" class="text-blue-600 underline">fenixxxar85@gmail.com</a></p>
        </div>
    </AppLayout>
</template>
