<template>
    <div class="app-container">
        <!-- Permission changes are now handled globally in app.js -->
        <!-- Sidebar -->
        <div :class="['sidebar', { 'sidebar-open': sidebarOpen }]" class="p-0">
            <div class="sidebar-menu">
                <Link
                    v-if="can('dashboard_view')"
                    :href="route('dashboard')"
                    class="menu-item"
                    :class="{ active: route().current('dashboard') }"
                    style="margin-top: 30px"
                    @click="setCurrentPage('dashboard')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('dashboard')"
                                src="/assets/images/dashboard-b.png"
                                class="dashboard-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/dashboard-w.png"
                                class="dashboard-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text text-xs">Dashboard</span>
                    </div>
                </Link>
                <Link
                    v-if="can('order_view')"
                    :href="route('orders.index')"
                    class="menu-item"
                    :class="{ active: route().current('orders.*') }"
                    @click="setCurrentPage('orders')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('orders.*')"
                                src="/assets/images/tracking-b.png"
                                class="order-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/tracking-w.png"
                                class="order-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Orders</span>
                    </div>
                </Link>
                <Link
                    v-if="can('transfer_view')"
                    :href="route('transfers.index')"
                    class="menu-item"
                    :class="{ active: route().current('transfers.*') }"
                    @click="setCurrentPage('transfers')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('transfers.*')"
                                src="/assets/images/transfer-b.png"
                                class="transfer-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/transfer-w.png"
                                class="transfer-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Transfers</span>
                    </div>
                </Link>
                <Link
                    v-if="can('product_view')"
                    :href="route('products.index')"
                    class="menu-item"
                    :class="{ active: route().current('products.*') }"
                    @click="setCurrentPage('products')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('products.*')"
                                src="/assets/images/product-b.png"
                                class="product-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/product-w.png"
                                class="product-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Product List</span>
                    </div>
                </Link>
                <Link
                    v-if="can('inventory_view')"
                    :href="route('inventories.index')"
                    class="menu-item"
                    :class="{ active: route().current('inventories.*') }"
                    @click="setCurrentPage('inventories')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('inventories.*')"
                                src="/assets/images/inventory-b.png"
                                class="inventory-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/inventory-w.png"
                                class="inventory-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Inventory</span>
                    </div>
                </Link>
                <Link
                    v-if="can('expiry_view')"
                    :href="route('expired.index')"
                    class="menu-item"
                    :class="{ active: route().current('expired.*') }"
                    @click="setCurrentPage('expired')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('expired.*')"
                                src="/assets/images/expire-b.png"
                                class="expire-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/expire-w.png"
                                class="expire-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Expires</span>
                    </div>
                </Link>
                <!-- Liquidate and disposals -->
                <Link
                    v-if="can('wastage_view')"
                    :href="route('liquidate-disposal.index')"
                    class="menu-item"
                    :class="{ active: route().current('liquidate-disposal.*') }"
                    @click="setCurrentPage('liquidate-disposal')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('liquidate-disposal.*')"
                                src="/assets/images/wastage-b.png"
                                class="liquidate-disposal-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/wastage-w.png"
                                class="liquidate-disposal-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Wastages</span>
                    </div>
                </Link>
                <Link
                    v-if="can('purchase_order_view') || can('packing_list_view')"
                    :href="route('supplies.index')"
                    class="menu-item"
                    :class="{ active: route().current('supplies.*') }"
                    @click="setCurrentPage('supplies')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('supplies.*')"
                                src="/assets/images/supplier-b.png"
                                class="supplies-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/supplier-w.png"
                                class="supplies-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Supplies</span>
                    </div>
                </Link>
                <Link
                    v-if="can('reports_view')"
                    :href="route('reports.index')"
                    class="menu-item"
                    :class="{ active: route().current('reports.*') }"
                    @click="setCurrentPage('reports')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('reports.*')"
                                src="/assets/images/reports-b.png"
                                class="reports-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/reports-w.png"
                                class="reports-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Reports</span>
                    </div>
                </Link>
                <Link
                    v-if="can('facility_view')"
                    :href="route('facilities.index')"
                    class="menu-item"
                    :class="{ active: route().current('facilities.*') }"
                    @click="setCurrentPage('facilities')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('facilities.*')"
                                src="/assets/images/facility-b.png"
                                class="facility-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/facility-w.png"
                                class="facility-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Facilities</span>
                    </div>
                </Link>
                <!-- Assets Menu -->
                <Link
                    v-if="can('asset_view')"
                    :href="route('assets.index')"
                    class="menu-item"
                    :class="{ active: route().current('assets.*') }"
                    @click="setCurrentPage('assets')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('assets.*')"
                                src="/assets/images/asset-b.png"
                                class="asset-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/asset-w.png"
                                class="asset-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Assets</span>
                    </div>
                </Link>
                
                <!-- Settings Menu -->
                <Link
                    v-if="can('system_settings')"
                    :href="route('settings.index')"
                    class="menu-item"
                    :class="{ active: route().current('settings.*') }"
                    @click="setCurrentPage('settings')"
                >
                    <div class="menu-content">
                        <div class="menu-icon">
                            <img
                                v-if="route().current('settings.*')"
                                src="/assets/images/setting-b.png"
                                class="setting-icon"
                                style="height: 15px"
                            />
                            <img
                                v-else
                                src="/assets/images/setting-w.png"
                                class="setting-icon"
                                style="height: 15px"
                            />
                        </div>
                        <span class="menu-text">Settings</span>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Top Navigation - Full Width -->
        <div class="top-nav-full-width h-16 text-xs">
            <div class="inventory-banner-full">
                <div class="flex justify-between items-center w-full">
                    <div class="flex items-center" style="margin-left: 62px">
                        <button @click="goBack" class="back-button">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                width="24"
                                height="24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="inventory-text">
                            <h1>{{ title }}</h1>
                            <h3 class="text-black text-lg">
                                "{{ description }}"
                            </h3>
                        </div>
                        <div v-if="img">
                            <img
                                :src="img"
                                alt="Inventory illustration"
                                class="svg-image"
                                height="30"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="user-section-inline">
                            <div class="flex flex-row items-center">
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <span>A</span>
                                    </div>
                                    <div class="user-details">
                                        <span class="user-role">{{
                                            rolesLabel($page.props.auth.user)
                                        }} </span>
                                        <span class="user-name">{{
                                            $page.props.auth.user?.name
                                        }}</span>
                                        <span class="user-name">{{
                                            $page.props.warehouse?.name
                                        }}</span>
                                    </div>
                                </div>
                                <button class="logout-button ml-4" @click="logout">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        width="24"
                                        height="24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                                        ></path>
                                        <polyline
                                            points="16 17 21 12 16 7"
                                        ></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div
            :class="['main-content', { 'main-content-expanded': !sidebarOpen }]"
        >
            <!-- Page Content -->
            <main class="flex flex-col">
                <div class="flex-1">
                    <slot />
                </div>
                <div
                    class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 text-xs mt-3"
                >
                    <div class="container mx-auto text-xs">
                        <div class="flex justify-center text-xs items-center gap-3">
                            <img
                                src="/assets/images/moh.png"
                                alt="Vista"
                                class="w-[30px]"
                            />
                            <img
                                src="/assets/images/psi.jpg"
                                alt="Vista"
                                class="w-[30px]"
                            />
                            <img
                                src="/assets/images/vista.png"
                                alt="Vista"
                                class="w-[50px]"
                            />
                            <span class="flex items-center text-gray-400"
                                >|</span>
                            <span class="flex items-center text-gray-600"
                                >Copyright 2025 Vista. All rights
                                reserved.</span
                            >
                            <span class="flex items-center text-gray-400"
                                >|</span>
                            <span
                                class="flex items-center text-gray-600 hover:text-gray-800 cursor-pointer"
                                >Terms of Use</span
                            >
                            <span class="flex items-center text-gray-400"
                                >|</span>
                            <span
                                class="flex items-center text-gray-600 hover:text-gray-800 cursor-pointer"
                                >Privacy</span
                            >
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const toast = useToast();

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
    img: {
        type: String,
        default: "",
    },
});

const page = usePage();

function rolesLabel(user) {
    if (!user) return '—';
    if (user.roles?.length) return user.roles.map((r) => r.name).join(', ');
    return user.title || '—';
}

/** Permission-based access: true if user has the permission or is admin. Keys use underscore (e.g. order_view, system_settings). */
const can = (permissionKey) => {
    const auth = page.props.auth;
    if (!auth) return false;
    if (auth.isAdmin) return true;
    return Boolean(auth.can?.[permissionKey]);
};

const debug = ref(false); // Set to true to see permissions debug info
const sidebarOpen = ref(true);
const currentPage = ref("dashboard");
const assetsMenuOpen = ref(false);

// Setup permission change listener
onMounted(() => {
    setupPermissionChangeListener();
});

// Function to handle permission change events
const setupPermissionChangeListener = () => {
    if (!window.Echo) {
        console.warn(
            "⚠️ Echo not available, permission change listener not set up"
        );
        return;
    }

    // Get the current user ID
    const currentUserId = page.props.auth?.user?.id;
    if (!currentUserId) {
        console.warn(
            "⚠️ User ID not available, permission change listener not set up"
        );
        return;
    }

    console.log(
        "🔄 Setting up permission change listener for user:",
        currentUserId
    );

    // Listen on the private user channel
    const channel = window.Echo.private(`user.${currentUserId}`);

    // Listen for permission change events
    channel.listen(".permissions-changed", (event) => {
        console.log("🔔 Permission changed event received:", event);
        handlePermissionEvent(event);
    });
};

// Function to handle the permission event
const handlePermissionEvent = (event) => {
    console.log("🔄 Permission change detected, reloading page...");
    toast.info(
        "Your permissions have been updated. The page will reload to apply changes."
    );

    // Reload the page after a short delay
    setTimeout(() => {
        console.log("🔄 Reloading page now...");
        window.location.reload();
    }, 3000);
};

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const setCurrentPage = (page) => {
    currentPage.value = page;
};

const toggleAssetsMenu = () => {
    assetsMenuOpen.value = !assetsMenuOpen.value;
};

const closeAssetsMenu = () => {
    assetsMenuOpen.value = false;
};

const logout = () => {
    router.post(route("logout"));
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        // If no previous page, go to dashboard
        router.visit(route('dashboard'));
    }
};
</script>

<style scoped>
/* Sidebar Styles */
.sidebar {
    width: 0;
    min-width: 0;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    z-index: 50;
    background: linear-gradient(to bottom, #14d399, #ff8500);
    transform: translateX(-100%);
    opacity: 0;
    visibility: hidden;
}

.sidebar-open {
    width: 100px;
    min-width: 100px;
    transform: translateX(0);
    opacity: 1;
    visibility: visible;
    margin-top: 68px;
}

.sidebar:not(.sidebar-open) {
    width: 0;
    min-width: 0;
    transform: translateX(-100%);
    opacity: 0;
    visibility: hidden;
}

.white-box {
    background-color: white;
    padding: 1rem 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    position: relative;
    min-height: 40px;
}

.sidebar-collapsed .white-box {
    padding: 0.5rem 0;
    min-height: 50px;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: #333;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 0.25rem;
    transition: background-color 0.3s ease;
    position: absolute;
    right: 0.25rem;
    top: 0.25rem;
}

.sidebar-toggle:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.sidebar-toggle-bottom {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    border-radius: 50%;
    transition: background-color 0.3s ease;
    margin: 10px auto;
    width: 40px;
    height: 40px;
}

.sidebar-toggle-bottom:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

.sidebar-menu {
    display: flex;
    flex-direction: column;
    padding: 0;
    margin: -12px;
    flex-grow: 1;
    width: 100%;
    align-items: center;
    /* overflow-y: scroll; */
    /* scrollbar-width: none; Firefox */
    /* -ms-overflow-style: none; Internet Explorer 10+ */
}

.sidebar-menu::-webkit-scrollbar {
    display: none;
    /* WebKit */
}

.menu-item {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    margin: 0.1rem 0;
    padding: 10px;
    z-index: 1;
    width: 100%;
    height: 46px;
}

.menu-item:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

.menu-item.active {
    background: white;
    color: #111827;
    position: relative;
    border-top-left-radius: 25px;
    border-bottom-left-radius: 25px;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    margin-right: -25px;
    padding-right: 25px;
    z-index: 5;
    height: 32;
    display: flex;
    align-items: center;
    width: 100%;
    left: 0;
}

/* Create the curved effect for the top-right corner */
.menu-item.active::before {
    content: "";
    position: absolute;
    top: -24px;
    right: 0;
    width: 25px;
    height: 24px;
    background-color: transparent;
    border-bottom-right-radius: 20px;
    box-shadow: 10px 10px 0 0 white;
    z-index: 2;
}

/* Create the curved effect for the bottom-right corner */
.menu-item.active::after {
    content: "";
    position: absolute;
    bottom: -24px;
    right: 0;
    width: 25px;
    height: 25px;
    background-color: transparent;
    border-top-right-radius: 20px;
    box-shadow: 10px -10px 0 0 white;
    z-index: 2;
    display: block;
}

/* Ensure icon in active menu is colored correctly */
.menu-item.active .menu-icon svg {
    fill: #111827;
}

.menu-content {
    margin-left: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 0;
    position: relative;
    z-index: 10;
    height: 60%;
}

.menu-icon {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 3px;
}

.sidebar-collapsed .menu-icon {
    margin-bottom: 0;
}

.menu-text {
    white-space: nowrap;
    transition: opacity 0.3s ease;
    text-align: center;
    font-size: 12px;
    font-weight: bold;
    line-height: 1.2;
    margin-top: 5px;
    width: 100%;
}

/* Main Content Styles */
.main-content {
    flex-grow: 1;
    margin-left: 0;
    margin-top: 68px;
    transition: margin-left 0.3s ease;
    display: flex;
    flex-direction: column;
    width: 100%; 
    overflow-x: hidden;
}

.main-content-expanded {
    margin-left: 0;
    width: 100%;
}

.main-content-expanded {
    margin-left: 0;
}

/* Top Navigation Styles - Full Width */
.top-nav-full-width {
    background-color: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100vw;
    z-index: 40;
}

.inventory-banner-full {
    display: flex;
    align-items: center;
    background-color: #81c4f6;
    color: white;
    padding: 0.5rem 1.5rem;
    width: 100%;
    height: 68px;
    position: relative;
    overflow: hidden;
    border-top-left-radius: 40px;
    border-right-color: white;
}

.back-button {
    background-color: white;
    color: #333;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    cursor: pointer;
}

.inventory-text {
    z-index: 10;
}

.inventory-text h1 {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.inventory-text p {
    font-size: 0.9rem;
    opacity: 0.9;
}

.inventory-image {
    position: absolute;
    right: 200px;
    height: 100%;
    display: flex;
    align-items: center;
}

.svg-image {
    height: 70px;
    margin-left: 30px;
}

.user-section-inline {
    display: flex;
    align-items: center;
}

.notification-icon {
    margin-right: 1rem;
    cursor: pointer;
}

.user-info {
    display: flex;
    align-items: center;
    margin-right: 2rem;
}

.user-avatar {
    width: 36px;
    height: 36px;
    background-color: #ef4444;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 0.5rem;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-role {
    font-size: 0.7rem;
    color: white;
    opacity: 0.8;
}

.user-name {
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
}

.logout-button {
    background-color: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.logout-button:hover {
    background-color: #dc2626;
}

/* Page Content Styles */
main {
    padding: 1rem;
    flex-grow: 1;
}

/* Responsive Styles */
@media (min-width: 1025px) {
    .sidebar-open {
        transform: translateX(0);
        width: 100px;
        min-width: 100px;
        opacity: 1;
        visibility: visible;
    }
    .main-content {
        margin-left: 100px;
        width: calc(100vw - 120px);
    }
    .main-content-expanded {
        margin-left: 0;
        width: 100vw;
    }
}

@media (max-width: 640px) {
    .top-nav-full-width {
        padding: 0.75rem 1rem;
    }
    .page-content {
        padding: 1rem;
    }
    .banner-title {
        font-size: 1rem;
    }
    .banner-subtitle {
        font-size: 0.75rem;
    }
}

/* Add helper class for SVG icons */
.menu-icon svg {
    width: 15px;
    height: 15px;
    fill: currentColor;
}

/* When sidebar is open, adjust main content */
.sidebar-open + .main-content {
    margin-left: 100px;
    width: calc(100vw - 100px);
}
</style>
