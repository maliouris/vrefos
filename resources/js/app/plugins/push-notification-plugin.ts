import {App, Plugin} from 'vue'
import {PUSH_NOTIFICATIONS} from "@/app/injection-keys";
import {User} from "@/app/types/models";
import {BeamsNotificationService} from "@/app/services/beams-notification-service";

export const PushNotificationPlugin =  {
    install: (app: App, {clientInstanceId, user}: {clientInstanceId: string, user?: User}) => {
        const pushNotificationService = new BeamsNotificationService(clientInstanceId)
        if (user) {
            pushNotificationService.connect(user)
        }

        app.provide(PUSH_NOTIFICATIONS, pushNotificationService)
    }
} satisfies Plugin
