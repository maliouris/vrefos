import type {Client, ITokenProvider} from "@pusher/push-notifications-web";
import {createPusherClient, createPusherTokenProvider} from "@/pusher/client";
import type {User} from "@/app/types/models";
import {PushNotificationService} from "@/app/services/push-notifications-service";

export class BeamsNotificationService implements PushNotificationService {
    private readonly client: Client
    private readonly tokenProvider: ITokenProvider

    constructor(clientInstanceId: string) {
        this.client = createPusherClient(clientInstanceId)
        this.tokenProvider = createPusherTokenProvider()
    }

     async connect(user: User) {
        await this.client.start()

        return this.client.setUserId(user.id.toString(), this.tokenProvider)
    }

    disconnect() {
        return this.client.stop()
    }
}
