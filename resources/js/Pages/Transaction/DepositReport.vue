<script setup>
import AuthenticatedLayout from "@/Layouts/Authenticated.vue";
import Badge from "@/Components/Badge.vue";
import Label from "@/Components/Label.vue";
import InputSelect from "@/Components/InputSelect.vue";
import VueTailwindDatepicker from "vue-tailwind-datepicker";
import {ref, watchEffect} from "vue";
import InputIconWrapper from "@/Components/InputIconWrapper.vue";
import Input from "@/Components/Input.vue";
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Button from "@/Components/Button.vue";
import {faRotateRight, faSearch, faX} from "@fortawesome/free-solid-svg-icons";
import {library} from "@fortawesome/fontawesome-svg-core";
import {transactionFormat} from "@/Composables/index.js";
import {TailwindPagination} from "laravel-vue-pagination";
import Loading from "@/Components/Loading.vue";
import {usePage} from "@inertiajs/vue3";
import Action from "@/Pages/Transaction/Deposit/Action.vue";

library.add(faSearch,faX,faRotateRight);
const { getChannelName, formatDate, getStatusClass, formatAmount, formatType } = transactionFormat();

function refreshTable() {
    getResults();
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        refreshTable();
    }
});

const formatter = ref({
    date: 'YYYY-MM-DD',
    month: 'MM'
});
const activeComponent = ref("pending"); // 'pending' is initially active

const submitSearch = async () => {
    const dateRange = date.value.split(' ~ ');

    await getResults(1, type.value, dateRange, search.value);
};

const setActiveComponent = async (component) => {
    activeComponent.value = component;

    await getResults();
};

function clearField() {
    form.search = '';
}

function handleKeyDown(event) {
    if (event.keyCode === 27) {
        clearField();
    }
}

const depositPending = ref({data: []});
const depositHistory = ref({data: []});
const totalDeposit = ref('');
const type = ref('');
const date = ref('');
const search = ref('');
const isLoading = ref(false);
const currentPage = ref(1);

const getResults = async (page = 1, type = '',  dateRange, search = '') => {
    isLoading.value = true;
    try {
        let url = `/transaction/getDepositReport?page=${page}`;

        if (type) {
            url += `&type=${type}`;
        }

        if (dateRange) {
            if (dateRange.length === 2) {
                const formattedDates = dateRange.map(date => `date[]=${date}`).join('&');
                url += `&${formattedDates}`;
            }
        }

        if (search) {
            url += `&search=${search}`;
        }

        const response = await axios.get(url);
        depositHistory.value = response.data.deposits;
        depositPending.value = response.data.depositPending;
        totalDeposit.value = response.data.totalDeposit;
    } catch (error) {
        console.error(error);
    } finally {
        isLoading.value = false;
    }
}
getResults();
const reset = () => {
    getResults();
    date.value = '';
    type.value = '';
    search.value = '';
}

const handlePageChange = (newPage) => {
    if (newPage >= 1) {
        currentPage.value = newPage;
        const dateRange = date.value.split(' ~ ');
        getResults(currentPage.value, type.value, dateRange, search.value);
    }
};

const exportDeposit = () => {
    const dateRange = date.value.split(' ~ ');

    let url = `/transaction/getDepositReport?export=yes`;

    if (type) {
        url += `&type=${type.value}`;
    }

    if (dateRange.length === 2) {
        const formattedDates = dateRange.map(date => `date[]=${date}`).join('&');
        url += `&${formattedDates}`;
    }

    if (search) {
        url += `&search=${search.value}`;
    }

    window.location.href = url;
}

const paginationClass = [
    'bg-transparent border-0 text-gray-500 text-xs'
];

const paginationActiveClass = [
    'dark:bg-transparent border-0 text-[#FF9E23] dark:text-[#FF9E23] text-xs'
];

</script>

<template>
    <AuthenticatedLayout :title="$t('public.Deposit Report')">
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <h2 class="text-xl font-semibold leading-tight">
                    {{ $t('public.Deposit Report') }}
                </h2>
            </div>
        </template>

        <form @submit.prevent="submitSearch">
            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-2">
                    <Label>{{ $t('public.Filter By Deposit Method') }}</Label>
                    <InputSelect
                        class="block w-full text-sm"
                        v-model="type"
                        :placeholder="$t('public.Select Deposit Method')"
                    >
                        <option value="">{{ $t('public.All') }}</option>
                        <option value="bank">{{ $t('public.Bank Transfer') }}</option>
                        <option value="crypto">{{ $t('public.Cryptocurrency') }}</option>
                    </InputSelect>
                </div>
                <div class="space-y-2">
                    <Label>{{ $t('public.Filter By Date') }}</Label>
                    <vue-tailwind-datepicker
                        :formatter="formatter"
                        v-model="date"
                        input-classes="py-2 border-gray-400 w-full rounded-full text-sm placeholder:text-sm focus:border-gray-400 focus:ring focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:border-gray-600 dark:bg-[#202020] dark:text-gray-300 dark:focus:ring-offset-dark-eval-1 disabled:dark:bg-dark-eval-0 disabled:dark:text-dark-eval-4"
                    />
                </div>
                <div class="space-y-2">
                    <Label>{{ $t('public.Search By Name / Email / Transaction ID / Account No') }}</Label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                            </svg>
                        </div>
                        <InputIconWrapper>
                            <template #icon>
                                <font-awesome-icon
                                    icon="fa-solid fa-search"
                                    class="flex-shrink-0 w-5 h-5 cursor-pointer"
                                    aria-hidden="true"
                                />
                            </template>
                            <Input withIcon id="name" type="text" class="block w-full" v-model="search" @keydown="handleKeyDown" />
                        </InputIconWrapper>
                        <button type="submit" class="absolute right-1 bottom-2 py-2.5 text-gray-500 hover:text-dark-eval-4 font-medium rounded-full w-8 h-8 text-sm"><font-awesome-icon
                            icon="fa-solid fa-x"
                            class="flex-shrink-0 w-3 h-3 cursor-pointer"
                            aria-hidden="true"
                            @click="clearField"
                        /></button>
                    </div>
                </div>
                <div>
                    <div class="grid grid-cols-2 gap-4 mt-2 md:mt-0">
                        <Button
                            variant="primary-opacity"
                            class="justify-center"
                        >
                            {{ $t('public.Search') }}
                        </Button>
                        <Button
                            variant="danger-opacity"
                            class="justify-center"
                            @click.prevent="reset"
                        >
                            {{ $t('public.Reset') }}
                        </Button>
                    </div>
                </div>
                <div class="flex md:col-span-2 justify-end">
                    <Button
                        variant="primary"
                        class="justify-center w-full md:w-1/3"
                        @click.prevent="exportDeposit"
                    >
                        {{ $t('public.Export') }}
                    </Button>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-2 gap-4 w-full md:w-1/2">
            <Button
                variant="primary-opacity"
                class="px-6 border border-blue-800 justify-center mt-4 focus:ring-0"
                :class="{ 'bg-transparent': activeComponent !== 'pending', 'dark:bg-[#007BFF] dark:text-white': activeComponent === 'pending' }"
                @click="setActiveComponent('pending')"
            >
                {{ $t('public.Pending Transaction') }}
            </Button>
            <Button
                variant="primary-opacity"
                class="px-6 border border-blue-800 justify-center mt-4 focus:ring-0"
                :class="{ 'bg-transparent': activeComponent !== 'history', 'dark:bg-[#007BFF] dark:text-white': activeComponent === 'history' }"
                @click="setActiveComponent('history')"
            >
                {{ $t('public.Transaction History') }}
            </Button>
        </div>

        <div class="p-6 overflow-hidden bg-white rounded-md shadow-md dark:bg-dark-eval-1 mt-6">
            <div class="flex justify-end">
                <font-awesome-icon
                    icon="fa-solid fa-rotate-right"
                    class="flex-shrink-0 w-5 h-5 cursor-pointer dark:text-dark-eval-4"
                    aria-hidden="true"
                    @click="refreshTable"
                />
            </div>
            <div class="relative overflow-x-auto sm:rounded-lg">
                <!-- Deposit Pending -->
                <div v-if="isLoading && activeComponent === 'pending'" class="w-full flex justify-center my-12">
                    <Loading />
                </div>
                <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400" v-if="activeComponent === 'pending'">
                    <thead class="text-xs font-bold text-gray-700 uppercase bg-gray-50 dark:bg-transparent dark:text-white text-center">
                    <tr>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Name') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Email') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.To Account') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Date') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Deposit Method') }}
                        </th>
                        <th scope="col" class="px-2 py-3">
                            {{ $t('public.Transaction ID') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Deposit Amount') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Action') }}
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="depositPending.data.length === 0">
                        <th colspan="8" class="py-4 text-lg text-center">
                            {{ $t('public.No Pending') }}
                        </th>
                    </tr>
                    <tr v-for="deposit in depositPending.data" :key="deposit.id" class="bg-white odd:dark:bg-transparent even:dark:bg-dark-eval-0 text-xs font-thin text-gray-900 dark:text-white text-center">
                        <th scope="row" class="px-6 py-4 font-thin rounded-l-full">
                            {{ deposit.of_user.first_name }}
                        </th>
                        <th class="px-6 py-4">
                            {{ deposit.of_user.email }}
                        </th>
                        <th class="px-6 py-4">
                            {{ deposit.to }}
                        </th>
                        <th>
                            {{ formatDate(deposit.created_at) }}
                        </th>
                        <th>
                            {{ $t('public.' + getChannelName(deposit.channel)) }}
                        </th>
                        <th>
                            {{ deposit.payment_id }}
                        </th>
                        <th>
                            $ {{ formatAmount(deposit.amount) }}
                        </th>
                        <th class="px-6 py-4 font-thin rounded-r-full">
                            <Action
                                :deposit="deposit"
                            />
                        </th>
                    </tr>
                    </tbody>
                </table>
                <div class="flex justify-end mt-4" v-if="activeComponent === 'pending'">
                    <TailwindPagination
                        :item-classes=paginationClass
                        :active-classes=paginationActiveClass
                        :data="depositPending"
                        :limit=1
                        :keepLength="true"
                        @pagination-change-page="handlePageChange"
                    />
                </div>

                <!-- Deposit History -->
                <div v-if="isLoading && activeComponent === 'history'" class="w-full flex justify-center my-12">
                    <Loading />
                </div>
                <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400" v-if="activeComponent === 'history'">
                    <thead class="text-xs font-bold text-gray-700 uppercase bg-gray-50 dark:bg-transparent dark:text-white text-center">
                    <tr>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Name') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Email') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Date') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Deposit Method') }}
                        </th>
                        <th scope="col" class="px-2 py-3">
                            {{ $t('public.Transaction ID') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Deposit Amount') }}
                        </th>
                        <th scope="col" class="px-4 py-3">
                            {{ $t('public.Status') }}
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="deposit in depositHistory.data" :key="deposit.id" class="bg-white odd:dark:bg-transparent even:dark:bg-dark-eval-0 text-xs font-thin text-gray-900 dark:text-white text-center">
                        <th scope="row" class="px-6 py-4 font-thin rounded-l-full">
                            {{ deposit.of_user.first_name }}
                        </th>
                        <th class="px-6 py-4">
                            {{ deposit.of_user.email }}
                        </th>
                        <th>
                            {{ formatDate(deposit.created_at) }}
                        </th>
                        <th class="px-6 py-4">
                            {{ $t('public.' + getChannelName(deposit.channel)) }}
                        </th>
                        <th>
                            {{ deposit.payment_id }}
                        </th>
                        <th>
                            $ {{ formatAmount(deposit.amount) }}
                        </th>
                        <th class="px-6 py-2 font-thin rounded-r-full">
                            <Badge :status="getStatusClass(deposit.status)">{{ $t('public.' + deposit.status) }}</Badge>
                        </th>
                    </tr>
                    </tbody>
                </table>
                <div v-if="activeComponent === 'history' && !isLoading" class="flex md:flex-row flex-col md:justify-between mt-4">
                    <div class="ml-1 my-4">
                        <span class="text-sm dark:text-dark-eval-4">{{ $t('public.Total Success Deposit') }} :</span> $ {{ formatAmount(totalDeposit) }}
                    </div>
                    <TailwindPagination
                        :item-classes=paginationClass
                        :active-classes=paginationActiveClass
                        :data="depositHistory"
                        :limit=1
                        :keepLength="true"
                        @pagination-change-page="handlePageChange"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
