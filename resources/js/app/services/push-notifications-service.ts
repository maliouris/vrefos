import type {User} from "@/app/types/models";

export interface PushNotificationService {
    connect(user: User): Promise<void>

    disconnect(): Promise<void>
}
