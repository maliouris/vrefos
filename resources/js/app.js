import * as PusherPushNotifications from '@pusher/push-notifications-web'

window.registerPushNotifications = async () => {
    const beamsClient = new PusherPushNotifications.Client({ instanceId: '...' })
    await beamsClient.start()
    await beamsClient.addDeviceInterest('vrefos-default')
}
