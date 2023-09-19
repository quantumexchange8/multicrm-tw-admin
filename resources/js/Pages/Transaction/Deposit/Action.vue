<script setup>
import Button from "@/Components/Button.vue";
import {ViewIcon} from "@/Components/Icons/outline.jsx";
import Modal from "@/Components/Modal.vue";
import {ref} from "vue";
import Approval from "@/Pages/Transaction/Deposit/Approval.vue";
import View from "@/Pages/Transaction/Deposit/View.vue";
import Tooltip from "@/Components/Tooltip.vue";

const props = defineProps({
    deposit: Object,
    type: String,
})

const depositActionModal = ref(false);
const modalComponent = ref(null);
const modalTitle = ref('');

const openDepositActionModal = (depositId, componentType, title) => {
    depositActionModal.value = true;
    if (componentType === 'approval') {
        modalComponent.value = 'DepositApproval';
        modalTitle.value = title;
    } else if (componentType === 'view') {
        modalComponent.value = 'DepositView';
    }
}

const closeModal = () => {
    depositActionModal.value = false
    modalComponent.value = null;
}
</script>

<template>
    <div class="flex gap-2 justify-center">
        <Button
            v-if="type !== 'history'"
            variant="success-opacity"
            class="text-xs justify-center"
            @click="openDepositActionModal(deposit.id, 'approval', 'Approve')"
        >
            Approve
        </Button>
        <Button
            v-if="type !== 'history'"
            variant="danger-opacity"
            class="text-xs justify-center"
            @click="openDepositActionModal(deposit.id, 'approval', 'Reject')"
        >
            Reject
        </Button>
        <div
            v-if="deposit.media.length > 0"
            class="flex justify-center items-center"
        >
            <Tooltip content="View" placement="top">
                <Button
                    class="justify-center px-4 pt-2 rounded-full w-7 h-7 focus:outline-none"
                    variant="primary-opacity"
                    @click="openDepositActionModal(deposit.id, 'view')"
                >
                    <ViewIcon aria-hidden="true" class="w-5 h-5 absolute" />
                    <span class="sr-only">View</span>
                </Button>
            </Tooltip>
        </div>

        <!-- Action Modal -->
        <Modal :show="depositActionModal" @close="closeModal" max-width="4xl">
            <div class="p-6">

                <!-- Approval -->
                <template v-if="modalComponent === 'DepositApproval'">
                    <Approval
                        :deposit="deposit"
                        :title="modalTitle"
                        @update:depositActionModal="depositActionModal = $event"
                    />
                </template>

                <!-- View -->
                <template v-if="modalComponent === 'DepositView'">
                    <View
                        :deposit="deposit"
                        :type="type"
                        @update:depositActionModal="depositActionModal = $event"
                    />
                </template>
            </div>
        </Modal>

    </div>
</template>
