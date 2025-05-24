@extends('app')

@section('title', 'Политика конфиденциальности')

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-bold mb-6">Политика конфиденциальности</h1>

        <p class="mb-4 text-sm text-gray-500">Дата вступления в силу: 21 мая 2025 г.</p>

        <h2 class="text-xl font-semibold mt-8 mb-2">1. Кто мы</h2>
        <p class="mb-4">
            Мы — команда проекта <strong>AI Fails</strong>, создающего короткие развлекательные видео с помощью генеративного искусственного интеллекта (DALL·E, RunwayML, Suno и др.) и публикующего их в социальных сетях от имени пользователей.
        </p>
        <p class="mb-4">
            Наш сайт: <a href="https://24i-poster.ru" class="text-blue-600 underline">https://24i-poster.ru</a><br>
            Контакт: <a href="mailto:fenixxxar85@gmail.com" class="text-blue-600 underline">fenixxxar85@gmail.com</a>
        </p>

        <h2 class="text-xl font-semibold mt-8 mb-2">2. Какие данные мы собираем</h2>
        <ul class="list-disc list-inside mb-4">
            <li>Имя вашего аккаунта</li>
            <li>Идентификаторы каналов и страниц</li>
            <li>Разрешения, предоставленные через OAuth (YouTube, Instagram)</li>
            <li>Access Token (только для публикации от вашего имени)</li>
        </ul>
        <p class="mb-4">Мы не собираем пароли и не получаем доступ к личным сообщениям.</p>

        <h2 class="text-xl font-semibold mt-8 mb-2">3. Как мы используем данные</h2>
        <p class="mb-4">
            Все данные используются исключительно для автоматической публикации видео, созданных с помощью ИИ, от вашего имени. Мы не передаём и не продаём ваши данные третьим лицам.
        </p>

        <h2 class="text-xl font-semibold mt-8 mb-2">4. Хранение данных</h2>
        <p class="mb-4">
            Access-токены хранятся в зашифрованном виде. Вы можете отозвать доступ через Google или Meta в любое время.
        </p>

        <h2 class="text-xl font-semibold mt-8 mb-2">5. Отказ от ответственности</h2>
        <p class="mb-4">
            Мы публикуем только тот контент, который был создан системой в рамках согласованной тематики. Вы можете удалить подключение канала/страницы в любое время.
        </p>

        <h2 class="text-xl font-semibold mt-8 mb-2">6. Контакты</h2>
        <p>
            По вопросам политики конфиденциальности обращайтесь: <br>
            📧 <a href="mailto:fenixxxar85@gmail.com" class="text-blue-600 underline">fenixxxar85@gmail.com</a>
        </p>
    </div>
@endsection
