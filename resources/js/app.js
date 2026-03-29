import * as PusherPushNotifications from '@pusher/push-notifications-web'

window.registerPushNotifications = async (userId) => {
    const beamsClient = new PusherPushNotifications.Client({ instanceId: '...' })
    await beamsClient.start()
    await beamsClient.addDeviceInterest(`user-${userId}`)
}
