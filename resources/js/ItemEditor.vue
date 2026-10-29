<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { Box, Plus, Camera, Picture, CollectionTag, Check } from '@element-plus/icons-vue';
import { api } from './api';

const props = defineProps({
    item: Object,
    sections: Array,
    categories: Array,
    initialSection: [String, Number],
});
const emit = defineEmits(['saved', 'cancel', 'add-taxonomy']);
const originalItem = props.item;
const form = reactive({
    name: originalItem?.name ?? '',
    quantity: originalItem ? originalItem.quantity : 1,
    section_id: originalItem?.section_id ?? props.initialSection ?? '',
    category_id: originalItem?.category_id ?? '',
    position: originalItem?.position ?? '',
    serial: originalItem?.serial ?? '',
    purchased_on: originalItem?.purchased_on ?? '',
    expires_on: originalItem?.expires_on ?? '',
});
const errors = ref({});
const message = ref('');
const saving = ref(false);
const suggestions = ref([]);
const photo = ref(null);
const preview = ref('');
const removePhoto = ref(false);
const camera = ref();
const gallery = ref();
const shownPhoto = computed(
    () => preview.value || (!removePhoto.value ? originalItem?.photo_url : ''),
);
let positionRequest = 0;

async function loadPositions() {
    const request = ++positionRequest;
    suggestions.value = [];
    if (!form.section_id) {
        return;
    }
    try {
        const data = await api('positions?section_id=' + form.section_id);
        if (request === positionRequest) {
            suggestions.value = data.data;
        }
    } catch (error) {
        message.value = error.message;
    }
}
watch(
    () => form.section_id,
    () => {
        form.position = '';
        loadPositions();
    },
);
onMounted(loadPositions);
onBeforeUnmount(() => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
});

function suggestPositions(query, callback) {
    const search = query.toLocaleLowerCase('it');
    const matches = suggestions.value
        .filter((value) => value.toLocaleLowerCase('it').includes(search))
        .map((value) => ({ value }));

    callback(matches);
}

function choosePhoto(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
        return;
    }
    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size > 12 * 1024 * 1024
    ) {
        message.value = 'Choose a JPEG, PNG or WebP photo up to 12 MB.';
        return;
    }
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
    photo.value = file;
    preview.value = URL.createObjectURL(file);
    removePhoto.value = false;
    message.value = '';
}

function clearPhoto() {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
    photo.value = null;
    preview.value = '';
    removePhoto.value = true;
}

async function save() {
    message.value = '';
    errors.value = {};
    if (!form.name.trim() || !form.section_id || !form.category_id) {
        message.value = 'Enter a name, quantity, section and category.';
        return;
    }
    if (!Number.isInteger(form.quantity) || form.quantity < 1 || form.quantity > 999999) {
        message.value = 'Enter a whole quantity between 1 and 999999.';
        return;
    }
    saving.value = true;
    const body = new FormData();
    Object.entries(form).forEach(([key, value]) => body.append(key, value ?? ''));
    body.append('remove_photo', removePhoto.value ? '1' : '0');
    if (photo.value) {
        body.append('photo', photo.value);
    }
    if (originalItem) {
        body.append('_method', 'PUT');
    }
    try {
        const result = await api(originalItem ? 'items/' + originalItem.id : 'items', {
            method: 'POST',
            body,
        });
        ElMessage.success('Item saved');
        emit('saved', result.data);
    } catch (error) {
        errors.value = error.errors ?? {};
        message.value = error.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <el-form label-position="top" @submit.prevent="save">
        <p class="mb-6 text-xs leading-relaxed text-stone-500">Name, quantity, section and category are required.</p>
        <el-alert
            v-if="message"
            :title="message"
            type="error"
            :closable="false"
            show-icon
            class="mb-5"
            role="alert" />
        <section class="form-section" aria-label="Item">
            <h3 class="form-section-title">
                <el-icon><Box /></el-icon> Item</h3>
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-[1fr_160px]">
                <el-form-item label="Name" required :error="errors.name?.[0]">
                    <el-input
                        v-model="form.name"
                        maxlength="200"
                        placeholder="e.g. Rechargeable AA batteries"
                        autocomplete="off" />
                </el-form-item>
                <el-form-item label="Quantity" required :error="errors.quantity?.[0]">
                    <el-input-number
                        v-model="form.quantity"
                        :min="1"
                        :max="999999"
                        :step="1"
                        step-strictly
                        :precision="0"
                        aria-label="Quantity" />
                </el-form-item>
            </div>
            <div class="grid gap-x-4 sm:grid-cols-2">
                <el-form-item label="Section" required :error="errors.section_id?.[0]">
                    <el-select v-model="form.section_id" placeholder="Select a section" filterable>
                        <el-option
                            v-for="entry in sections"
                            :key="entry.id"
                            :label="entry.name"
                            :value="entry.id" />
                    </el-select>
                    <button
                        type="button"
                        class="mt-2 flex items-center gap-1 text-xs font-semibold text-forest"
                        @click="emit('add-taxonomy', 'sections')">
                        <el-icon><Plus /></el-icon> New section</button>
                </el-form-item>
                <el-form-item label="Category" required :error="errors.category_id?.[0]">
                    <el-select
                        v-model="form.category_id"
                        placeholder="Select a category"
                        filterable>
                        <el-option
                            v-for="entry in categories"
                            :key="entry.id"
                            :label="entry.name"
                            :value="entry.id" />
                    </el-select>
                    <button
                        type="button"
                        class="mt-2 flex items-center gap-1 text-xs font-semibold text-forest"
                        @click="emit('add-taxonomy', 'categories')">
                        <el-icon><Plus /></el-icon> New category</button>
                </el-form-item>
            </div>
            <el-form-item label="Location" :error="errors.position?.[0]">
                <el-autocomplete
                    v-model="form.position"
                    class="w-full"
                    :fetch-suggestions="suggestPositions"
                    clearable
                    :disabled="!form.section_id"
                    maxlength="150"
                    placeholder="e.g. Drawer A, shelf 2…" />
                <p class="mt-2 text-[11px] leading-relaxed text-stone-500">Enter a location or select one used in this section.</p>
            </el-form-item>
        </section>
        <section class="form-section" aria-label="Optional details">
            <h3 class="form-section-title">
                <el-icon><CollectionTag /></el-icon> Details
                <span class="ml-auto font-normal normal-case tracking-normal">Optional</span>
            </h3>
            <el-form-item label="Serial number" :error="errors.serial?.[0]">
                <el-input
                    v-model="form.serial"
                    maxlength="200"
                    placeholder="Letters and leading zeros allowed"
                    autocomplete="off" />
            </el-form-item>
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <el-form-item label="Purchase date" :error="errors.purchased_on?.[0]">
                    <el-date-picker
                        v-model="form.purchased_on"
                        type="date"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        placeholder="dd/mm/yyyy"
                        :editable="false" />
                </el-form-item>
                <el-form-item label="Expiry date" :error="errors.expires_on?.[0]">
                    <el-date-picker
                        v-model="form.expires_on"
                        type="date"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        placeholder="dd/mm/yyyy"
                        :editable="false" />
                </el-form-item>
            </div>
        </section>
        <section class="mb-6" aria-label="Photo">
            <h3 class="form-section-title">
                <el-icon><Camera /></el-icon> Photo
                <span class="ml-auto font-normal normal-case tracking-normal">Optional</span>
            </h3>
            <el-form-item :error="errors.photo?.[0]">
                <div class="photo-upload w-full">
                    <img
                        v-if="shownPhoto"
                        :src="shownPhoto"
                        alt="Item photo"
                        class="mb-4 max-h-56 w-full rounded-lg object-contain" />
                    <div class="flex flex-wrap gap-2">
                        <el-button type="primary" plain :icon="Camera" @click="camera?.click()">Take photo</el-button>
                        <el-button :icon="Picture" @click="gallery?.click()">Choose photo</el-button>
                        <el-button v-if="shownPhoto" type="danger" text @click="clearPhoto">Remove</el-button>
                    </div>
                    <p class="mt-3 text-[11px] leading-relaxed text-stone-500">JPEG, PNG or WebP · Up to 12 MB · One photo per item</p>
                    <input
                        ref="camera"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        class="hidden"
                        aria-label="Take a photo"
                        @change="choosePhoto" />
                    <input
                        ref="gallery"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        aria-label="Choose a photo"
                        @change="choosePhoto" />
                </div>
            </el-form-item>
        </section>
        <div class="editor-actions">
            <el-button class="flex-1" @click="emit('cancel')" :disabled="saving">Cancel</el-button>
            <el-button
                type="primary"
                native-type="submit"
                :icon="Check"
                class="flex-1"
                :loading="saving">Save item</el-button>
        </div>
    </el-form>
</template>
