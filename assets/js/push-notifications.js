// Request notification permission and setup Firebase messaging
async function setupPushNotifications() {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.log('Notification permission denied');
            return;
        }

        // Get Firebase messaging instance
        const messaging = firebase.messaging();
        
        // Get the token
        const token = await messaging.getToken({
            vapidKey: 'YOUR_VAPID_KEY'
        });

        // Save the token to your server
        await fetch('/newapp/includes/save-device-token.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ token })
        });

        // Handle incoming messages when the app is in focus
        messaging.onMessage((payload) => {
            console.log('Received foreground message:', payload);
            
            // Create and show notification
            const notification = new Notification(payload.notification.title, {
                body: payload.notification.body,
                icon: payload.notification.icon,
                badge: '/newapp/assets/images/badge.png',
                data: {
                    click_action: payload.notification.click_action
                }
            });

            // Handle notification click
            notification.onclick = function(event) {
                event.preventDefault();
                if (this.data.click_action) {
                    window.open(this.data.click_action, '_blank');
                }
                this.close();
            };
        });

    } catch (error) {
        console.error('Error setting up push notifications:', error);
    }
}

// Call setup function when the page loads
document.addEventListener('DOMContentLoaded', setupPushNotifications); 