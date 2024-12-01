import {type InjectionKey} from "@vue/runtime-core";
import {type PushNotificationService} from "@/app/services/push-notifications-service";

export const PUSH_NOTIFICATIONS: InjectionKey<PushNotificationService> = Symbol()

