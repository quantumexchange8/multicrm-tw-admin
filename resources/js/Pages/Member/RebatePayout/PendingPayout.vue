<script setup>
import Action from "@/Pages/Member/RebatePayout/Action.vue";
import Checkbox from "@/Components/Checkbox.vue";
import {computed, ref, watch , watchEffect} from "vue";
import Button from "@/Components/Button.vue";
import Swal from "sweetalert2";
import {trans} from "laravel-vue-i18n";
import {TailwindPagination} from "laravel-vue-pagination";
import Loading from "@/Components/Loading.vue";
import {usePage} from "@inertiajs/vue3";

const props = defineProps({
    search: String,
    date: String,
    refresh: Boolean,
    isLoading: Boolean,
})

const lists = ref({data: []});
const currentPage = ref(1);
const refreshPending = ref(props.refresh);
const pendingLoading = ref(props.isLoading);
const emit = defineEmits(['update:loading', 'update:refresh']);
const selectAllChecked = ref(false);
const selectedItems = ref([]);

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

const getResults = async (page = 1, dateRange = '', search = '') => {
    pendingLoading.value = true;
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
        lists.value = response.data.payoutPending;
    } catch (error) {
        console.error(error);
    } finally {
        pendingLoading.value = false;
        emit('update:loading', false);
    }
}
getResults();
watch(() => props.refresh, (newVal) => {
    refreshPending.value = newVal;
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

// Watch for changes in selectedItems array and update selectAllChecked accordingly
watch(selectedItems, () => {
    selectAllChecked.value = selectedItems.value.length ===  lists.data.length;
});

function toggleAllCheckboxes() {
    if (selectAllChecked.value) {
        selectedItems.value = lists.data.map((list) => ({
            ib_account_types_id: list.ib_account_types_id,
            closed_date: list.date, // Add the closed_date from the list
        }));
    } else {
        selectedItems.value = [];
    }
}

function toggleItemCheckbox(itemValue, closedDate) {
    const existingIndex = selectedItems.value.findIndex((item) => item.ib_account_types_id === itemValue);

    if (existingIndex !== -1) {
        selectedItems.value.splice(existingIndex, 1);
    } else {
        selectedItems.value.push({ ib_account_types_id: itemValue, closed_date: closedDate });
    }
}

function isItemSelected(itemValue) {
    return selectedItems.value.some((item) => item.ib_account_types_id === itemValue);
}

const showConfirmButton = computed(() => {
    return selectAllChecked.value || selectedItems.value.length > 0;
});


async function confirmAction() {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'bg-blue-500 py-2 px-6 rounded-full text-white hover:bg-blue-600 focus:ring-blue-500 mx-2',
            cancelButton: 'bg-transparent text-[#AF60FF] py-2 px-6 rounded-full text-white hover:bg-dark-eval-1 focus:ring-red-500 mx-2',
        },
        buttonsStyling: false,
        background: '#000000',
        iconColor: '#ffffff',
        color: '#ffffff',
    });

    const result = await swalWithBootstrapButtons.fire({
        title: trans('public.Are you sure?'),
        text: trans('public.Approve all selected IB!'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('public.Confirm'),
        cancelButtonText: trans('public.Cancel'),
        reverseButtons: true,
    });

    if (result.isConfirmed) {
        await approveSelectedRebatePayout();
    }
}

async function approveSelectedRebatePayout() {
    try {
        // Make the POST request using axios with selectedItems
        const response = await axios.post('/member/approve_rebate_payout', {
            selected_items: selectedItems.value,
            date: props.date,
            type: 'approve_selected',
        });

        if (response.data.success) {
            await Swal.fire({
                title: trans('public.Success'),
                text: response.data.message,
                icon: 'success',
                background: '#000000',
                iconColor: '#ffffff',
                color: '#ffffff',
                confirmButtonText: trans('public.OK'),
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-blue-500 py-2 px-6 rounded-full text-white hover:bg-blue-600 focus:ring-blue-500',
                },
            }).then(() => {
                // Reload the page after the SweetAlert is closed
                location.reload();
            });
        } else {
            console.log(response.data.message);
        }
    } catch (error) {
        if (error.response && error.response.status === 422) {
            await Swal.fire({
                title: trans('public.Error'),
                text: error.response.data.message,
                icon: 'error',
                background: '#000000',
                iconColor: '#ffffff',
                color: '#ffffff',
                confirmButtonText: trans('public.OK'),
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-blue-500 py-2 px-6 rounded-full text-white hover:bg-blue-600 focus:ring-blue-500',
                },
            });
        } else {
            await Swal.fire({
                title: trans('public.Error'),
                text: trans('public.An error occurred while applying the rebate.'),
                icon: 'error',
                background: '#000000',
                iconColor: '#ffffff',
                color: '#ffffff',
                confirmButtonText: trans('public.OK'),
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-blue-500 py-2 px-6 rounded-full text-white hover:bg-blue-600 focus:ring-blue-500',
                },
            });
        }
    }
}

</script>

<template>
    <div v-if="pendingLoading" class="w-full flex justify-center my-12">
        <Loading />
    </div>
    <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs font-bold text-gray-700 uppercase bg-gray-50 dark:bg-transparent dark:text-white text-center">
        <tr>
            <th scope="col" class="px-6 py-3">
                <Checkbox
                    v-model="selectAllChecked"
                    @change="toggleAllCheckboxes"
                />
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Date') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.IB Name') }}
            </th>
            <th scope="col" class="px-6 py-3">
                {{ $t('public.Account Number') }}
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
        <tr v-if="lists.data.length === 0">
            <th colspan="8" class="py-4 text-lg text-center">
                {{ $t('public.No Pending') }}
            </th>
        </tr>
        <tr v-for="list in lists.data" :key="list.ib_account_types_id" class="bg-white odd:dark:bg-transparent even:dark:bg-dark-eval-0 text-xs font-thin text-gray-900 dark:text-white text-center">
            <th class="py-2 font-thin rounded-l-full">
                <Checkbox
                    :checked="selectAllChecked || isItemSelected(list.ib_account_types_id)"
                    @change="toggleItemCheckbox(list.ib_account_types_id)"
                    :value="list.ib_account_types_id"
                />
            </th>
            <th>
                {{ list.date }}
            </th>
            <th class="px-6 py-4">
                {{ list.of_user.first_name }}
            </th>
            <th>
                {{ list.meta_login }}
            </th>
            <th>
                {{ (list.of_account_type.name ) }}
            </th>
            <th>
                {{ list.total_volume.toFixed(2) }}
            </th>
            <th>
                {{ list.total_revenue.toFixed(2) }}
            </th>
            <th class="px-6 py-2 font-thin rounded-r-full">
                <Action
                    :list="list"
                    :date="date"
                    status="pending"
                />
            </th>
        </tr>
        </tbody>
    </table>
    <div class="flex justify-end mt-4" v-if="!pendingLoading">
        <TailwindPagination
            :item-classes=paginationClass
            :active-classes=paginationActiveClass
            :data="lists"
            :limit=1
            :keepLength="true"
            @pagination-change-page="handlePageChange"
        />
    </div>
    <div class="flex justify-end">
        <Button
            v-if="showConfirmButton"
            variant="success"
            class="float-right text-xs"
            @click="confirmAction"
        >
            {{ $t('public.Confirm') }}
        </Button>
    </div>
</template>
