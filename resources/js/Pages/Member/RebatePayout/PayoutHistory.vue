<script setup>
import Action from "@/Pages/Member/RebatePayout/Action.vue";
import {ref, watch} from "vue";
import {TailwindPagination} from "laravel-vue-pagination";
import Loading from "@/Components/Loading.vue";

const props = defineProps({
    search: String,
    date: String,
    refresh: Boolean,
    isLoading: Boolean,
})

const payoutHistories = ref({data: []});
const currentPage = ref(1);
const refreshHistory = ref(props.refresh);
const historyLoading = ref(props.isLoading);
const emit = defineEmits(['update:loading', 'update:refresh']);

const getResults = async (page = 1, dateRange = '', search = '') => {
    historyLoading.value = true;
    try {
        let url = `/member/getPendingRebatePayout?page=${page}`;

        if (dateRange) {
            if (dateRange.length === 2) {
                const formattedDates = dateRange.map(date => `${date}`).join(' ~ ');
                url += `&date=${formattedDates}`;
            }
        }

        if (search) {
            url += `&search=${search}`;
        }

        const response = await axios.get(url);
        payoutHistories.value = response.data.payoutHistory;
    } catch (error) {
        console.error(error);
    } finally {
        historyLoading.value = false;
        emit('update:loading', false);
    }
}
getResults();
watch(() => props.refresh, (newVal) => {
    refreshHistory.value = newVal;
    if (newVal) {
        const dateRange = props.date.split(' ~ ');
        // Call the getResults function when refresh is true
        getResults(1, dateRange, props.search);
        emit('update:refresh', false);
    }
});

const handlePageChange = (newPage) => {
    if (newPage >= 1) {
        currentPage.value = newPage;
        const dateRange = props.date.split(' ~ ');
        getResults(currentPage.value, dateRange, props.search);
    }
};

const paginationClass = [
    'bg-transparent border-0 text-gray-500 text-xs'
];

const paginationActiveClass = [
    'dark:bg-transparent border-0 text-[#FF9E23] dark:text-[#FF9E23] !font-bold text-xs'
];
</script>

<template>
    <div v-if="historyLoading" class="w-full flex justify-center my-12">
        <Loading />
    </div>
    <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs font-bold text-gray-700 uppercase bg-gray-50 dark:bg-transparent dark:text-white text-center">
        <tr>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Date') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.IB Name') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.IB Number') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Account Type') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Total Volume (LOTS)') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Total Payout') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Action') }}
            </th>
        </tr>
        </thead>
        <tbody>
        <tr v-if="payoutHistories.data.length === 0">
            <th colspan="8" class="py-4 text-lg text-center">
                {{ $t('public.No History') }}
            </th>
        </tr>
        <tr v-for="rebateHistory in payoutHistories.data" class="bg-white odd:dark:bg-transparent even:dark:bg-dark-eval-0 text-xs font-thin text-gray-900 dark:text-white text-center">
            <th scope="row" class="px-6 py-4 font-thin rounded-l-full">
                {{ rebateHistory.date }}
            </th>
            <th class="px-6 py-4">
                {{ rebateHistory.of_user.first_name }}
            </th>
            <th>
                {{ rebateHistory.of_user.ib_id }}
            </th>
            <th>
                {{ rebateHistory.of_account_type.name }}
            </th>
            <th>
                {{ rebateHistory.total_volume.toFixed(2) }}
            </th>
            <th>
                {{ rebateHistory.total_revenue.toFixed(2) }}
            </th>
            <th class="px-6 py-2 font-thin rounded-r-full">
                <Action
                    :list="rebateHistory"
                    :date="date"
                    status="approve"
                />
            </th>
        </tr>
        </tbody>
    </table>
    <div class="flex justify-end mt-4" v-if="!historyLoading">
        <TailwindPagination
            :item-classes=paginationClass
            :active-classes=paginationActiveClass
            :data="payoutHistories"
            :limit=1
            :keepLength="true"
            @pagination-change-page="handlePageChange"
        />
    </div>
</template>
