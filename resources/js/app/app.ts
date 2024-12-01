import '../bootstrap';
import '../../css/app.css';

import {createApp, DefineComponent, h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {ZiggyVue} from 'ziggy-js';
import ParentLayout from "@/app/Layouts/ParentPanelLayout.vue";
import {PushNotificationPlugin} from "@/app/plugins/push-notification-plugin";

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name: string) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue'))
        if (name.startsWith('Parent')) {
            page.default.layout ||= ParentLayout
        }

        return page
    },
    setup({ el, App, props, plugin }) {
        const pageProps = props.initialPage.props
        
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PushNotificationPlugin, {clientInstanceId: pageProps.pusher.id, user: pageProps.auth.user})
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
