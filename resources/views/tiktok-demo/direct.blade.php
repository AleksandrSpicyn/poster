<section>
<h2>{{ $directMessage ? 'Статус публикации в TikTok' : 'Опубликовать в TikTok' }}</h2>
@if($directMessage)<p role="status">{{ $directMessage }} @if($canRefreshDirect)<a href="{{ route('tiktok-demo.show', $author, false) }}">Обновить статус</a>@endif</p>@endif
@if($errors->any())<ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
@if($creator && empty($creator['privacy']))<p>Для этого аккаунта сейчас нет доступных вариантов видимости.</p>
@elseif($creator)
@if($directMessage)<details @if($errors->any()) open @endif><summary>Опубликовать ещё один ролик</summary>@endif
<p>Аккаунт получателя: <strong>{{ $creator['nickname'] }}</strong></p>
<form id="direct-post" method="post" enctype="multipart/form-data" action="{{ route('tiktok-demo.direct', $author, false) }}">
@csrf
<input type="hidden" name="binding" value="{{ $form }}">
<p><label>Ваш MP4 (до 960 КиБ и {{ $creator['duration'] }} с) <input type="file" name="video" accept="video/mp4" required></label></p>
<video controls playsinline hidden style="max-width:100%;max-height:20rem"></video>
<p><label>Подпись <textarea name="title" maxlength="2200"></textarea></label></p>
<p><label>Кто увидит ролик?
<select name="privacy" required><option value="" disabled selected>Выберите видимость</option>
@foreach($creator['privacy'] as $level)<option value="{{ $level }}">{{ ['SELF_ONLY'=>'Только я','PUBLIC_TO_EVERYONE'=>'Все','FOLLOWER_OF_CREATOR'=>'Подписчики','MUTUAL_FOLLOW_FRIENDS'=>'Друзья'][$level] }}</option>@endforeach</select></label></p>
<fieldset><legend>Разрешить</legend>
@foreach(['comment'=>'Комментарии','duet'=>'Дуэты','stitch'=>'Сшивание'] as $key=>$label)
<label><input type="checkbox" name="{{ $key }}" value="1" @disabled(!$creator[$key])> {{ $label }}</label>
@endforeach</fieldset>
<p><label><input type="checkbox" name="ai" value="1"> Видео создано с помощью ИИ</label></p>
<p><label><input type="checkbox" name="disclose" value="1"> Ролик продвигает бренд, товар или услугу</label></p>
<fieldset id="brands" hidden><legend>Чей бренд продвигается?</legend>
<label><input type="checkbox" name="own_brand" value="1"> Мой бренд</label>
<label><input type="checkbox" name="partner" value="1"> Бренд партнёра</label>
<p>Для партнёрского контента выберите видимость «Все» или «Друзья».</p>
<p id="brand-label" aria-live="polite"></p></fieldset>
<p><label><input type="checkbox" name="consent" value="1" required> Подтверждаю отправку выбранного ролика и принимаю
<a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener">условия TikTok об использовании музыки</a><span id="partner-terms" hidden>
и <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" rel="noopener">правила брендированного контента</a></span>.</label></p>
<p>Обработка после отправки может занять несколько минут.</p>
<button disabled>Опубликовать в TikTok</button>
</form>
<script>
(() => {
    const form = document.querySelector('#direct-post');
    const fields = form.elements;
    const preview = form.querySelector('video');
    const submit = form.querySelector('button');
    let source;
    function update() {
        const commercial = fields.disclose.checked;
        document.querySelector('#brands').hidden = !commercial;
        if (!commercial) fields.own_brand.checked = fields.partner.checked = false;
        const partnerAudiences = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS'];
        const allowsPartner = Array.from(fields.privacy.options).some(option => partnerAudiences.includes(option.value));
        fields.partner.disabled = !allowsPartner || (fields.privacy.value !== '' && !partnerAudiences.includes(fields.privacy.value));
        for (const option of fields.privacy.options) {
            if (option.value) option.disabled = fields.partner.checked && !partnerAudiences.includes(option.value);
        }
        document.querySelector('#partner-terms').hidden = !fields.partner.checked;
        document.querySelector('#brand-label').textContent = fields.partner.checked ? 'Метка TikTok: Paid partnership' :
            fields.own_brand.checked ? 'Метка TikTok: Promotional content' : 'Укажите хотя бы один тип продвижения.';
        submit.disabled = !preview.duration || preview.duration > {{ $creator['duration'] }} || !form.checkValidity() ||
            (commercial && !fields.own_brand.checked && !fields.partner.checked);
    }
    fields.video.addEventListener('change', () => {
        if (source) URL.revokeObjectURL(source);
        const file = fields.video.files[0];
        fields.video.setCustomValidity(file && file.size > 960 * 1024 ? 'Файл больше 960 КиБ' : '');
        preview.hidden = !file;
        if (file) { source = URL.createObjectURL(file); preview.src = source; }
        else { preview.removeAttribute('src'); preview.load(); }
        submit.disabled = true;
    });
    form.addEventListener('input', update);
    form.addEventListener('change', event => {
        if (event.target !== fields.consent) fields.consent.checked = false;
        update();
    });
    preview.addEventListener('loadedmetadata', () => {
        if (preview.duration > {{ $creator['duration'] }}) fields.video.setCustomValidity('Ролик превышает лимит длительности аккаунта.');
        update();
    });
    preview.addEventListener('error', () => { submit.disabled = true; });
    form.addEventListener('submit', () => { submit.disabled = true; });
})();
</script>
@if($directMessage)</details>@endif
@endif
</section>
