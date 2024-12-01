import * as PusherPushNotifications from "@pusher/push-notifications-web";

export function createPusherClient(instanceId: string): PusherPushNotifications.Client {
    return new PusherPushNotifications.Client({
        instanceId
    })
}

export function createPusherTokenProvider(): PusherPushNotifications.TokenProvider {
    return new PusherPushNotifications.TokenProvider({
        url: route('pusher.beams.auth'),
    });
}
