<script setup>
import Label from "@/Components/Label.vue";
import Textarea from "@/Components/Textarea.vue";
import Button from "@/Components/Button.vue";
import {useForm} from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    title: String,
    deposit: Object,
})

const emit = defineEmits(['update:depositActionModal']);
const closeModal = () => {
    emit('update:depositActionModal', false);
}

const form = useForm({
    id: props.deposit.id,
    status: props.title === 'Approve' ? 'approve' : 'reject',
    comment: '',
});

const submitForm = () => {
    form.post(route('transaction.deposit_approval'), {
        onSuccess: () => {
            closeModal();
        },
    });
}
</script>

<template>
    <h2 class="text-lg mb-2 font-medium text-gray-900 dark:text-gray-100">{{ $t('public.' + title) }} {{ $t('public.Deposit Request') }}</h2>
    <hr>

    <Label for="comment" class="mt-8" :value="$t('public.Remarks')"></Label>
    <Textarea
        class="mt-2"
        id="comment"
        v-model="form.comment"
        :placeholder="$t('public.Please enter your reason here...')"
    />
    <InputError class="mt-2" :message="form.errors.comment" />

    <div class="my-6 grid grid-cols-2 gap-4 float-right">
        <Button variant="secondary" class="px-6 justify-center" @click="closeModal">
            {{ $t('public.Cancel') }}
        </Button>
        <Button class="px-6 justify-center" @click.prevent="submitForm" :disabled="form.processing">{{ $t('public.Confirm') }}</Button>
    </div>
</template>
