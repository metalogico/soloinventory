<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    House,
    Box,
    Search,
    Location,
    Plus,
    ArrowRight,
    ArrowDown,
    Setting,
    SwitchButton,
    FolderOpened,
    Coffee,
    Tools,
    Monitor,
    EditPen,
    Refresh,
    Operation,
} from '@element-plus/icons-vue';
import { api } from './api';
import ItemEditor from './ItemEditor.vue';

const booting = ref(true);
const authenticated = ref(false);
const configured = ref(true);
const login = reactive({ username: '', password: '' });
const loggingIn = ref(false);
const errorMessage = ref('');
const sections = ref([]);
const categories = ref([]);
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const page = ref(1);
const filters = reactive({ q: '', section_id: '', category_id: '', position: '' });
const filterOpen = ref(false);
const filterPositions = ref([]);
const editorOpen = ref(false);
const editing = ref(null);
const detail = ref(null);
const managementOpen = ref(false);
const managementTab = ref('sections');
const managing = ref(false);
const activeFilters = computed(
    () => [filters.section_id, filters.category_id, filters.position].filter(Boolean).length,
);
const managedEntries = computed(() =>
    managementTab.value === 'sections' ? sections.value : categories.value,
);
const sectionName = computed(
    () => sections.value.find((entry) => entry.id === filters.section_id)?.name,
);
let searchTimeout;
let itemRequestVersion = 0;
let positionRequestVersion = 0;

function showError(error) {
    errorMessage.value = error.message;
}

function expired() {
    authenticated.value = false;
    items.value = [];
    detail.value = null;
    editorOpen.value = false;
    managementOpen.value = false;
}

async function boot() {
    booting.value = true;
    errorMessage.value = '';
    try {
        const result = await api('session');
        authenticated.value = result.authenticated;
        configured.value = result.configured;
        if (authenticated.value) {
            await refresh();
        }
    } catch (error) {
        showError(error);
    } finally {
        booting.value = false;
    }
}

async function signIn() {
    loggingIn.value = true;
    errorMessage.value = '';
    try {
        const result = await api('session', { method: 'POST', body: login });
        authenticated.value = result.authenticated;
        login.password = '';
        await refresh();
    } catch (error) {
        showError(error);
    } finally {
        loggingIn.value = false;
    }
}

async function signOut() {
    try {
        await api('session', { method: 'DELETE' });
        expired();
        login.password = '';
        errorMessage.value = '';
    } catch (error) {
        showError(error);
    }
}

async function loadSectionsAndCategories() {
    const [sectionData, categoryData] = await Promise.all([api('sections'), api('categories')]);
    sections.value = sectionData.data;
    categories.value = categoryData.data;
}

async function loadItems() {
    // A slower response must not replace results for a newer search.
    const version = ++itemRequestVersion;
    loading.value = true;
    const params = new URLSearchParams({ page: page.value });
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '' && value != null) {
            params.set(key, value);
        }
    }
    try {
        const data = await api('items?' + params);
        if (version !== itemRequestVersion) {
            return;
        }
        items.value = data.data;
        total.value = data.meta.total;
        errorMessage.value = '';
    } catch (error) {
        if (version === itemRequestVersion) {
            showError(error);
        }
    } finally {
        if (version === itemRequestVersion) {
            loading.value = false;
        }
    }
}

async function refresh() {
    try {
        await loadSectionsAndCategories();
        await loadItems();
    } catch (error) {
        showError(error);
    }
}
watch(filters, () => {
    page.value = 1;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadItems, 250);
});
watch(
    () => filters.section_id,
    async (id) => {
        filters.position = '';
        filterPositions.value = [];
        const version = ++positionRequestVersion;
        if (!id) {
            return;
        }
        try {
            const result = await api('positions?section_id=' + id);
            if (version === positionRequestVersion) {
                filterPositions.value = result.data;
            }
        } catch (error) {
            showError(error);
        }
    },
);
function resetFilters() {
    Object.assign(filters, { q: '', section_id: '', category_id: '', position: '' });
}

function createItem() {
    editing.value = null;
    editorOpen.value = true;
}

function editItem() {
    editing.value = detail.value;
    detail.value = null;
    editorOpen.value = true;
}

async function openItem(id) {
    try {
        detail.value = (await api('items/' + id)).data;
    } catch (error) {
        showError(error);
    }
}

async function saved(item) {
    editorOpen.value = false;
    page.value = 1;
    await refresh();
    detail.value = item;
}

async function deleteItem() {
    const item = detail.value;
    try {
        await ElMessageBox.confirm(
            'Permanently delete “' + item.name + '” and its photo? This cannot be undone.',
            'Delete item',
            {
                confirmButtonText: 'Delete permanently',
                cancelButtonText: 'Cancel',
                type: 'warning',
            },
        );
        await api('items/' + item.id, { method: 'DELETE', body: { confirm: true } });
        detail.value = null;
        if (items.value.length === 1 && page.value > 1) {
            page.value--;
        }
        ElMessage.success('Item deleted');
        await refresh();
    } catch (error) {
        if (error instanceof Error) {
            showError(error);
        }
    }
}

async function changeTaxonomy(kind, entry = null) {
    const isSection = kind === 'sections';
    const title = entry ? 'Rename' : isSection ? 'New section' : 'New category';

    try {
        const { value } = await ElMessageBox.prompt(
            isSection ? 'Section name' : 'Category name',
            title,
            {
                inputValue: entry?.name ?? '',
                inputPlaceholder: 'e.g. ' + (kind === 'sections' ? 'Study' : 'Electronics'),
                inputValidator: (value) =>
                    Boolean(value?.trim() && value.trim().length <= 100) ||
                    'Enter a name of up to 100 characters.',
                confirmButtonText: 'Save',
                cancelButtonText: 'Cancel',
                closeOnClickModal: false,
            },
        );
        managing.value = true;
        await api(kind + (entry ? '/' + entry.id : ''), {
            method: entry ? 'PUT' : 'POST',
            body: { name: value.trim() },
        });
        await refresh();
        ElMessage.success(entry ? 'Name updated' : 'Added');
    } catch (error) {
        if (error instanceof Error) {
            showError(error);
            ElMessage.error(error.message);
        }
    } finally {
        managing.value = false;
    }
}

async function deleteTaxonomy(entry) {
    const kind = managementTab.value;
    try {
        await ElMessageBox.confirm(
            'Delete “' + entry.name + '”? Only empty sections and categories can be deleted.',
            kind === 'sections' ? 'Delete section' : 'Delete category',
            {
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                type: 'warning',
            },
        );
        await api(kind + '/' + entry.id, { method: 'DELETE' });
        if (kind === 'sections' && filters.section_id === entry.id) {
            filters.section_id = '';
        }
        if (kind === 'categories' && filters.category_id === entry.id) {
            filters.category_id = '';
        }
        await refresh();
    } catch (error) {
        if (error instanceof Error) {
            showError(error);
            ElMessage.error(error.message);
        }
    }
}

function categoryIcon(name) {
    const category = name.toLocaleLowerCase('it');
    if (category.includes('elettron')) {
        return Monitor;
    }
    if (category.includes('utensil')) {
        return Tools;
    }
    if (category.includes('dispensa')) {
        return Coffee;
    }
    return Box;
}

function categoryTone(name) {
    const category = name.toLocaleLowerCase('it');
    if (category.includes('elettron')) {
        return 'tone-blue';
    }
    if (category.includes('utensil')) {
        return 'tone-sand';
    }
    return 'tone-green';
}

function formatDate(value) {
    return value ? value.split('-').reverse().join('/') : 'Not set';
}

function connectionChanged() {
    if (!navigator.onLine) {
        errorMessage.value = 'No connection. Reconnect to your home network.';
    }
}
onMounted(() => {
    window.addEventListener('inventory-unauthorized', expired);
    window.addEventListener('offline', connectionChanged);
    boot();
});
onBeforeUnmount(() => {
    clearTimeout(searchTimeout);
    window.removeEventListener('inventory-unauthorized', expired);
    window.removeEventListener('offline', connectionChanged);
});
</script>

<template>
    <div v-if="booting" class="grid min-h-screen place-items-center text-forest" role="status">Loading…</div>
    <main v-else-if="!authenticated" class="login-page">
        <div class="login-card">
            <div class="brand-symbol mb-7">
                <el-icon :size="28"><House /></el-icon>
            </div>
            <h1 class="mb-7 text-3xl font-semibold tracking-tight">SoloInventory</h1>
            <el-alert
                v-if="!configured"
                title="Account not configured. Contact the administrator."
                type="info"
                :closable="false"
                class="mb-5" />
            <el-alert
                v-if="errorMessage"
                :title="errorMessage"
                type="error"
                :closable="false"
                class="mb-5"
                role="alert" />
            <el-form label-position="top" @submit.prevent="signIn">
                <el-form-item label="Username">
                    <el-input
                        v-model="login.username"
                        autocomplete="username"
                        name="username"
                        :disabled="!configured" />
                </el-form-item>
                <el-form-item label="Password">
                    <el-input
                        v-model="login.password"
                        type="password"
                        autocomplete="current-password"
                        name="password"
                        show-password
                        :disabled="!configured" />
                </el-form-item>
                <el-button
                    type="primary"
                    native-type="submit"
                    class="w-full"
                    :loading="loggingIn"
                    :disabled="!configured">Sign in <el-icon class="ml-2"><ArrowRight /></el-icon>
                </el-button>
            </el-form>
            <button
                v-if="errorMessage"
                class="mt-5 text-sm font-semibold text-forest"
                @click="boot">Retry connection</button>
        </div>
    </main>
    <div v-else class="min-h-screen safe-bottom">
        <header class="site-header">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <div class="flex items-center gap-3">
                    <div class="brand-symbol">
                        <el-icon :size="22"><House /></el-icon>
                    </div>
                    <p class="brand-name">SoloInventory</p>
                </div>
                <el-dropdown trigger="click">
                    <el-button class="account-menu" aria-label="SoloInventory menu">
                        <el-icon :size="17"><Setting /></el-icon>
                        <span class="hidden sm:inline">Manage</span>
                        <el-icon :size="12"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item :icon="FolderOpened" @click="managementOpen = true">Sections and categories</el-dropdown-item>
                            <el-dropdown-item :icon="SwitchButton" divided @click="signOut">Sign out</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-5 pt-8 pb-12 sm:px-8 sm:pt-11">
            <div
                v-if="errorMessage"
                role="alert"
                class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-sm text-red-800">{{ errorMessage }}</p>
                <button class="mt-2 text-sm font-semibold text-red-900 underline" @click="refresh">Retry</button>
            </div>
            <div class="page-heading">
                <h1>{{ sectionName || 'Items' }}</h1>
                <el-button type="primary" :icon="Plus" class="add-object" @click="createItem">
                    <span>Add item</span>
                </el-button>
            </div>
            <nav class="section-navigation" aria-label="Sections">
                <button
                    class="section-chip"
                    :class="{ active: !filters.section_id }"
                    :aria-pressed="!filters.section_id"
                    @click="filters.section_id = ''">
                    <el-icon><House /></el-icon>
                    <span>All items</span>
                </button>
                <span class="section-separator" aria-hidden="true"></span>
                <button
                    v-for="section in sections"
                    :key="section.id"
                    class="section-chip"
                    :class="{ active: filters.section_id === section.id }"
                    :aria-pressed="filters.section_id === section.id"
                    @click="filters.section_id = section.id">
                    <el-icon><FolderOpened /></el-icon>
                    <span>{{ section.name }}</span>
                    <span class="section-count">{{ section.items_count }}</span>
                </button>
                <button
                    class="new-section"
                    aria-label="New section"
                    title="New section"
                    @click="changeTaxonomy('sections')">
                    <el-icon><Plus /></el-icon>
                </button>
            </nav>
            <div class="search-toolbar">
                <el-input
                    v-model="filters.q"
                    :prefix-icon="Search"
                    clearable
                    placeholder="Search by name or serial…"
                    aria-label="Search by name or serial" />
                <el-button
                    :icon="Operation"
                    :type="filterOpen || activeFilters ? 'primary' : 'default'"
                    :plain="Boolean(filterOpen || activeFilters)"
                    :aria-expanded="filterOpen"
                    aria-controls="inventory-filters"
                    @click="filterOpen = !filterOpen">Filters<span v-if="activeFilters" class="filter-count">{{ activeFilters }}</span>
                </el-button>
            </div>
            <div v-if="filterOpen" id="inventory-filters" class="filter-panel">
                <div>
                    <label for="filter-section">Section</label>
                    <el-select
                        id="filter-section"
                        v-model="filters.section_id"
                        clearable
                        placeholder="All sections">
                        <el-option
                            v-for="entry in sections"
                            :key="entry.id"
                            :value="entry.id"
                            :label="entry.name" />
                    </el-select>
                </div>
                <div>
                    <label for="filter-category">Category</label>
                    <el-select
                        id="filter-category"
                        v-model="filters.category_id"
                        clearable
                        placeholder="All categories">
                        <el-option
                            v-for="entry in categories"
                            :key="entry.id"
                            :value="entry.id"
                            :label="entry.name" />
                    </el-select>
                </div>
                <div>
                    <label for="filter-position">Location</label>
                    <el-select
                        id="filter-position"
                        v-model="filters.position"
                        clearable
                        :disabled="!filters.section_id"
                        :placeholder="
                            filters.section_id ? 'All locations' : 'Select a section first'
                        ">
                        <el-option
                            v-for="position in filterPositions"
                            :key="position"
                            :value="position"
                            :label="position" />
                    </el-select>
                </div>
                <button class="text-left text-xs font-semibold text-forest" @click="resetFilters">Clear filters and search</button>
            </div>
            <div class="results-bar">
                <p aria-live="polite">
                    {{ loading ? 'Searching…' : total + (total === 1 ? ' item' : ' items') }}
                </p>
                <button
                    v-if="filters.q || activeFilters"
                    class="text-xs font-semibold text-forest"
                    @click="resetFilters">Show all</button>
                <button
                    v-else
                    class="refresh-button"
                    aria-label="Refresh inventory"
                    title="Refresh inventory"
                    :disabled="loading"
                    @click="refresh">
                    <el-icon :class="{ 'animate-spin': loading }"><Refresh /></el-icon>
                </button>
            </div>
            <div v-if="!items.length && !loading" class="empty-inventory">
                <span class="empty-icon">
                    <el-icon :size="32"><Box /></el-icon>
                </span>
                <h2>{{ filters.q || activeFilters ? 'No items found' : 'No items yet' }}</h2>
                <p v-if="filters.q || activeFilters">Try a different search or change the filters.</p>
                <el-button v-if="filters.q || activeFilters" @click="resetFilters">Show all items</el-button>
                <el-button v-else type="primary" :icon="Plus" @click="createItem">Add item</el-button>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" :aria-busy="loading">
                <button
                    v-for="item in items"
                    :key="item.id"
                    class="inventory-card"
                    @click="openItem(item.id)">
                    <div class="flex items-center justify-between gap-3">
                        <span class="category-label">{{ item.category.name }}</span>
                        <span class="quantity-badge" :aria-label="'Quantity: ' + item.quantity">
                            {{ item.quantity }} <span>pcs</span>
                        </span>
                    </div>
                    <div class="card-content">
                        <span class="item-thumbnail" :class="categoryTone(item.category.name)">
                            <img
                                v-if="item.photo_url"
                                :src="item.photo_url"
                                :alt="item.name"
                                loading="lazy" />
                            <el-icon v-else :size="30">
                                <component :is="categoryIcon(item.category.name)" />
                            </el-icon>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2>{{ item.name }}</h2>
                            <p v-if="item.serial" class="item-serial">S/N {{ item.serial }}</p>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span class="item-location">
                            <el-icon><Location /></el-icon>
                            <span>
                                {{ item.section.name }}<span v-if="item.position" class="location-position">
                                    · {{ item.position }}
                                </span>
                            </span>
                        </span>
                        <span class="open-item" aria-hidden="true">
                            <el-icon><ArrowRight /></el-icon>
                        </span>
                    </div>
                </button>
            </div>
            <el-pagination
                v-if="total > 24"
                v-model:current-page="page"
                :total="total"
                :page-size="24"
                layout="prev, pager, next"
                :pager-count="5"
                class="mt-8 justify-center"
                @current-change="loadItems" />
        </main>
        <el-drawer
            v-model="editorOpen"
            :title="editing ? 'Edit item' : 'New item'"
            size="100%"
            class="inventory-editor"
            :close-on-click-modal="false"
            destroy-on-close>
            <ItemEditor
                v-if="editorOpen"
                :key="editing?.id ?? 'new'"
                :item="editing"
                :sections="sections"
                :categories="categories"
                :initial-section="filters.section_id"
                @saved="saved"
                @cancel="editorOpen = false"
                @add-taxonomy="changeTaxonomy" />
        </el-drawer>
        <el-dialog
            :model-value="Boolean(detail)"
            :title="detail?.name"
            width="560px"
            class="item-dialog"
            @close="detail = null"
            destroy-on-close>
            <template v-if="detail">
                <div class="detail-summary">
                    <span class="detail-category">
                        <el-icon :size="22">
                            <component :is="categoryIcon(detail.category.name)" />
                        </el-icon>
                        <span>{{ detail.category.name }}</span>
                    </span>
                    <span class="detail-quantity">
                        <span>Quantity</span>
                        <strong>
                            {{ detail.quantity }}
                            <small>{{ detail.quantity === 1 ? 'piece' : 'pieces' }}</small>
                        </strong>
                    </span>
                </div>
                <img
                    v-if="detail.photo_url"
                    :src="detail.photo_url"
                    :alt="detail.name"
                    class="mb-5 max-h-72 w-full rounded-xl object-contain bg-stone-50" />
                <dl class="detail-fields">
                    <div>
                        <dt>Section</dt>
                        <dd>{{ detail.section.name }}</dd>
                    </div>
                    <div>
                        <dt>Location</dt>
                        <dd>{{ detail.position || 'Not set' }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt>Serial number</dt>
                        <dd class="font-mono">{{ detail.serial || 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt>Purchase date</dt>
                        <dd>{{ formatDate(detail.purchased_on) }}</dd>
                    </div>
                    <div>
                        <dt>Expiry date</dt>
                        <dd>{{ formatDate(detail.expires_on) }}</dd>
                    </div>
                </dl>
                <div class="mt-7 flex gap-3">
                    <el-button type="primary" :icon="EditPen" class="flex-1" @click="editItem">Edit item</el-button>
                    <el-button type="danger" plain @click="deleteItem">Delete</el-button>
                </div>
            </template>
        </el-dialog>
        <el-dialog v-model="managementOpen" title="Sections and categories" width="560px">
            <el-tabs v-model="managementTab">
                <el-tab-pane label="Sections" name="sections" />
                <el-tab-pane label="Categories" name="categories" />
            </el-tabs>
            <el-button
                type="primary"
                :icon="Plus"
                :loading="managing"
                @click="changeTaxonomy(managementTab)">
                {{ managementTab === 'sections' ? 'New section' : 'New category' }}
            </el-button>
            <div class="mt-5 max-h-96 overflow-auto">
                <div
                    v-for="entry in managedEntries"
                    :key="entry.id"
                    class="flex items-center justify-between gap-3 border-t border-stone-100 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ entry.name }}</p>
                        <p class="text-xs text-stone-500">
                            {{ entry.items_count }}
                            {{ entry.items_count === 1 ? 'item' : 'items' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <el-button size="small" @click="changeTaxonomy(managementTab, entry)">Rename</el-button>
                        <el-button
                            size="small"
                            type="danger"
                            plain
                            :disabled="entry.items_count > 0"
                            @click="deleteTaxonomy(entry)">Delete</el-button>
                    </div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>
