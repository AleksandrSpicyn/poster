<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { ref } from 'vue';
import { useRoute } from 'ziggy-js';
const route = useRoute();
const passwordInput = ref<InstanceType<typeof Input> | null>(null);
const form = useForm({ password: '' });
function reset(open: boolean) {
    if (!open) {
        form.clearErrors();
        form.reset();
    }
}
function remove() {
    form.delete(route('profile.destroy'), { preserveScroll: true, onError: () => passwordInput.value?.focus(), onSuccess: () => reset(false) });
}
</script>
<template>
    <Dialog @update:open="reset">
        <DialogTrigger as-child><button type="button" class="rounded-md px-3 py-3 text-left text-sm">Удаление учётной записи</button></DialogTrigger>
        <DialogContent class="studio-confirm" :show-close="false">
            <DialogTitle>Удаление аккаунта</DialogTitle>
            <form @submit.prevent="remove">
                <div class="studio-confirm-notice">
                    <CircleAlert aria-hidden="true" /><strong>Удалить аккаунт?</strong>
                    <DialogDescription>Для подтверждения введите текущий пароль.</DialogDescription>
                </div>
                <Label class="my-2 block" for="delete-password">Текущий пароль</Label>
                <Input
                    id="delete-password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="h-12"
                    :aria-invalid="!!form.errors.password"
                    :aria-describedby="form.errors.password ? 'delete-error' : undefined"
                />
                <div v-if="form.errors.password" id="delete-error" class="studio-confirm-notice mt-3" role="alert">
                    <CircleAlert aria-hidden="true" /><strong>{{ form.errors.password }}</strong>
                    <p v-if="form.errors.password === 'Пароль не подошёл'">Проверьте пароль и попробуйте снова.</p>
                </div>
                <DialogFooter class="studio-confirm-actions">
                    <Button type="submit" variant="destructive" :disabled="form.processing">Удалить аккаунт</Button>
                    <DialogClose as-child><Button type="button" variant="outline" :disabled="form.processing">Отмена</Button></DialogClose>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
