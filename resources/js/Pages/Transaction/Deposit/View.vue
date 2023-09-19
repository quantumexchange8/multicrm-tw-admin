<script setup>
import Label from "@/Components/Label.vue";
import Input from "@/Components/Input.vue";
import {computed, ref} from "vue";
import Textarea from "@/Components/Textarea.vue";
import {faCopy} from "@fortawesome/free-solid-svg-icons";
import {library} from "@fortawesome/fontawesome-svg-core";
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Button from "@/Components/Button.vue";
import {transactionFormat} from "@/Composables/index.js";
import toast from "@/Composables/toast.js";
library.add(faCopy);

const props = defineProps({
    deposit: Object,
    type: String
})
const { formatAmount } = transactionFormat();

const getMediaUrlByCollection = (member, collectionName) => {
    const media = member.media;
    const foundMedia = media.find((m) => m.collection_name === collectionName);
    return foundMedia ? foundMedia.original_url : 'https://static.vecteezy.com/system/resources/thumbnails/009/007/126/small/document-file-not-found-search-no-result-concept-illustration-flat-design-eps10-modern-graphic-element-for-landing-page-empty-state-ui-infographic-icon-vector.jpg';
};
</script>

<template>
    <div v-if="type !== 'history'">
        <h2 class="text-lg mb-2 font-medium text-gray-900 dark:text-gray-100">View More Details</h2>
        <hr>
        <div class="flex justify-center flex-col text-center mt-8 space-y-2">
            <h4 class="text-lg font-medium text-gray-900 dark:text-dark-eval-4">Cash Wallet Balance</h4>
            <h3 class="text-4xl mb-2 font-medium text-gray-900 dark:text-gray-100">$ {{ formatAmount(deposit.of_user.cash_wallet) }}</h3>
        </div>

        <div class="flex justify-center my-6">
            <img class="rounded" :src="getMediaUrlByCollection(deposit, 'payment_receipt')" alt="Proof of Identity (Front)">
        </div>
    </div>

</template>
