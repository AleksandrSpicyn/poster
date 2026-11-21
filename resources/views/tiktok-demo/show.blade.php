<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Кабинет автора · Poster</title>
<style>
:root{font:16px/1.5 system-ui,sans-serif;color:#202730;background:#f4f6f7}main{max-width:58rem;margin:3rem auto;padding:0 1rem}section{background:white;padding:1.4rem;border-radius:.75rem;margin:1rem 0;overflow:auto}h1,h2,p{margin:0 0 .8rem}h2{font-size:1.25rem}button{font:inherit;border:0;border-radius:.4rem;background:#153d45;color:white;padding:.55rem 1rem}button:disabled{background:#58666a}th,td{padding:.5rem;text-align:left;border-bottom:1px solid #ddd}
</style></head><body><main>
<header><p>Poster · TikTok {{ config('tiktok_demo.mode') === 'sandbox' ? 'Sandbox' : 'Production' }}</p><h1>Кабинет автора</h1><p>Демонстрация будущего кабинета автора</p></header>
@if($notice)<p role="status">{{ $notice }}</p>@endif
<section><h2>Профиль TikTok</h2><p>{{ $name ?? 'Аккаунт ещё не подключён.' }}</p>
<form method="post" action="{{ route('tiktok-demo.connect', $author, false) }}">@csrf<button>{{ $name ? 'Подключить TikTok повторно' : 'Подключить TikTok' }}</button></form></section>
@if($name !== null)
<section><h2>Публичные ролики</h2>
@if($error)<p role="alert">{{ $error }}</p><a href="{{ route('tiktok-demo.show', $author, false) }}">Обновить</a>
@elseif(empty($videos))<p>Публичных роликов пока нет.</p>
@else<table><thead><tr><th>Ролик</th><th>Просмотры</th><th>Лайки</th><th>Комментарии</th><th>Репосты</th></tr></thead><tbody>
@foreach($videos as $video)<tr><td>{{ ($video['title'] ?? '') ?: 'Без названия' }}</td>
@foreach(['view_count','like_count','comment_count','share_count'] as $metric)<td>{{ $video[$metric] ?? '—' }}</td>@endforeach</tr>@endforeach
</tbody></table>@if($next)<a href="{{ route('tiktok-demo.show',['author'=>$author,'cursor'=>$next], false) }}">Следующие ролики</a>@endif
@endif</section>
@include('tiktok-demo.direct')
<section><h2>Тестовый черновик</h2><p>Файл sample.mp4 отправляется во входящие. Вы сами завершаете публикацию в приложении TikTok.</p>
@if($operation)<p role="status">{{ $operation }}</p>@else
<form method="post" action="{{ route('tiktok-demo.draft', $author, false) }}">@csrf<button @disabled(!$upload)>Отправить черновик в TikTok</button></form>
@if(!$upload)<p>Для отправки нужно согласование и подготовленный файл.</p>@endif
@endif</section>@endif
</main></body></html>
