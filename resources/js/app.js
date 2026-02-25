import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import PrimeVue from 'primevue/config';
import 'primeicons/primeicons.css';
import SplashScreen from './Components/SplashScreen.vue';

// Set up global toast function
window.$showToast = null;

// Debug flag for permission events
window.debugPermissionEvents = false;

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Create a splash screen element
const splashElement = document.createElement('div');
splashElement.id = 'splash-container';
document.body.appendChild(splashElement);

// Mount splash screen
const splashApp = createApp(SplashScreen, {
    duration: 2500
});
const splashInstance = splashApp.mount('#splash-container');

// Create and mount the main application after splash completes
setTimeout(() => {
    createInertiaApp({
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            ),
        setup({ el, App, props, plugin }) {
            // Remove splash screen
            splashApp.unmount();
            document.body.removeChild(splashElement);
            
            const app = createApp({ render: () => h(App, props) });
            
            // Use the Toast plugin
            app.use(Toast, {
                position: "top-right",
                timeout: 5000,
                closeOnClick: true,
                pauseOnFocusLoss: true,
                pauseOnHover: true,
                draggable: true,
                draggablePercent: 0.6,
                showCloseButtonOnHover: false,
                hideProgressBar: true,
                closeButton: "button",
                icon: true,
                rtl: false,
                zIndex: 9999
            });
            
            // // Assign the toast function to our global variable
            // window.$showToast = (options) => {
            //     const { useToast } = Toast;
            //     const toast = useToast();
            //     toast(options.message, {
            //         type: options.type || 'default',
            //         timeout: options.duration || 5000
            //     });
            // };
            
            return app
                .use(plugin)
                .use(PrimeVue)
                .use(ZiggyVue)
                .mount(el);
        },
        progress: {
            color: '#4B5563',
        },
    });
}, 2500); // Match the duration in the SplashScreen component