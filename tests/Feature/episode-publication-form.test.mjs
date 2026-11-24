import assert from 'node:assert/strict';
import test from 'node:test';
import { effectScope, nextTick, reactive } from 'vue';
import { useEpisodePublicationForm } from '../../resources/js/composables/useEpisodePublicationForm.ts';

test('Согласие относится к выбранной версии, аккаунтам и подписи', async (context) => {
    const props = reactive({
        assembly: { id: 12, lock_version: 3, status: 'accepted' },
        workspace: { channels: [{ id: 1 }, { id: 2 }], screenplay: { public_caption: 'Подпись' } },
    });
    const scope = effectScope();
    context.after(() => scope.stop());
    const { publishForm, confirmed, canSend, sendLabel } = scope.run(() => useEpisodePublicationForm(props, () => false));

    assert.equal(confirmed.value, false);
    publishForm.channel_ids = [1];
    await nextTick();
    confirmed.value = true;
    assert.equal(canSend.value, true);
    publishForm.processing = true;
    assert.equal(canSend.value, false);
    publishForm.processing = false;
    publishForm.caption = 'Другая подпись';
    await nextTick();
    assert.equal(confirmed.value, false);

    confirmed.value = true;
    publishForm.channel_ids.push(2);
    await nextTick();
    assert.equal(confirmed.value, false);
    assert.equal(sendLabel.value, 'Отправить в 2 канала');
    confirmed.value = true;
    props.workspace.channels = [{ id: 1 }, { id: 2 }];
    await nextTick();
    assert.equal(canSend.value, true);
    props.assembly.id++;
    await nextTick();
    assert.equal(confirmed.value, false);
    confirmed.value = true;
    props.workspace.channels = [];
    await nextTick();
    assert.deepEqual(publishForm.channel_ids, []);
    assert.equal(canSend.value, false);
    assert.equal(publishForm.caption, 'Другая подпись');
});
