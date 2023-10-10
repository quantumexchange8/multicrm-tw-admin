<script setup>
import RightbarTitle from "@/Components/Rightbar/RightbarTitle.vue";
import RightbarContent from "@/Components/Rightbar/RightbarContent.vue";
import {Link, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import Button from "@/Components/Button.vue";
// import { usePermission } from '@/Composables/permissions.js'

const page = usePage();
const totalApprovedDeposit = page.props.totalApprovedDeposit;
const totalPendingDeposit = page.props.totalPendingDeposit;
const totalApprovedWithdrawal = page.props.totalApprovedWithdrawal;
const totalPendingWithdrawal = page.props.totalPendingWithdrawal;
const getTotalApprovedRebate = page.props.getTotalApprovedRebate;
const getTotalPendingRebate = page.props.getTotalPendingRebate;
const monthlyWithdrawal = page.props.monthlyWithdrawal;
const IBAccountTypes = page.props.IBAccountTypes;
const user = computed(() => page.props.auth.user)
// const { hasRole } = usePermission();

function formatAmount(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}


</script>

<template>
    <aside class="w-full md:w-auto space-y-4">
        <RightbarTitle :title="$t('public.rightbar.Today\'s Deposit')">
            <RightbarContent :title="$t('public.rightbar.Total Approved Deposit') + ' ($)'" :amount="formatAmount(totalApprovedDeposit)" />
            <RightbarContent :title="$t('public.rightbar.Total Pending Deposit') + ' ($)'" :amount="formatAmount(totalPendingDeposit)" />
            <Link
                :href="route('transaction.deposit_report')"
                class="inline-flex w-full p-2 px-6 justify-center text-center items-center transition-colors text-sm font-medium rounded-full select-none disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-dark-eval-2 bg-[#007bff33] text-[#007BFF] hover:bg-blue-800 hover:text-white focus:ring-blue-500"
            >
                {{ $t('public.Manage Pending Deposit') }}
            </Link>
        </RightbarTitle>

        <RightbarTitle :title="$t('public.rightbar.Today\'s Withdrawal')">
            <RightbarContent :title="$t('public.rightbar.Total Approved Withdrawal') + ' ($)'" :amount="formatAmount(totalApprovedWithdrawal)" />
            <RightbarContent :title="$t('public.rightbar.Total Pending Withdrawal') + ' ($)'" :amount="formatAmount(totalPendingWithdrawal)" />
            <Link
                :href="route('transaction.withdrawal_report')"
                class="inline-flex w-full p-2 px-6 justify-center text-center items-center transition-colors text-sm font-medium rounded-full select-none disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-dark-eval-2 bg-[#007bff33] text-[#007BFF] hover:bg-blue-800 hover:text-white focus:ring-blue-500"
            >
                {{ $t('public.Manage Pending Withdrawal') }}
            </Link>
        </RightbarTitle>

        <RightbarTitle :title="$t('public.rightbar.Today\'s Rebate')">
            <RightbarContent :title="$t('public.rightbar.Total Approved Rebate') + ' ($)'" :amount="formatAmount(getTotalApprovedRebate)" />
            <RightbarContent :title="$t('public.rightbar.Total Pending Rebate') + ' ($)'" :amount="formatAmount(getTotalPendingRebate)" />
            <Link
                :href="route('member.rebate_payout')"
                class="inline-flex w-full p-2 px-6 justify-center text-center items-center transition-colors text-sm font-medium rounded-full select-none disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-dark-eval-2 bg-[#007bff33] text-[#007BFF] hover:bg-blue-800 hover:text-white focus:ring-blue-500"
            >
                {{ $t('public.Manage Pending Rebate') }}
            </Link>
        </RightbarTitle>

<!--        <RightbarTitle title="Today's Credit">-->
<!--            <RightbarContent title="Total Approved Withdrawal ($)" :amount="formatAmount(totalApprovedWithdrawal)" />-->
<!--            <RightbarContent title="Total Pending Withdrawal ($)" :amount="formatAmount(totalPendingWithdrawal)" />-->
<!--        </RightbarTitle>-->
    </aside>
</template>
