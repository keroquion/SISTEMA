/**
 * Push Notification Helper - Petulap SST
 * Include this script in any page that needs push subscription capability.
 * 
 * Usage:
 *   - Call petulap_subscribePush() after a successful login
 *   - Call petulap_unsubscribePush() before logout
 *   - Call petulap_checkPushStatus() to get current status
 */

var PETULAP_PUSH = {
    vapidKey: null,

    /** Convert base64url string to Uint8Array (for applicationServerKey) */
    urlBase64ToUint8Array: function(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },

    /** Convert ArrayBuffer to standard base64 */
    arrayBufferToBase64: function(buffer) {
        var binary = '';
        var bytes = new Uint8Array(buffer);
        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    },

    /** Check if push is supported */
    isSupported: function() {
        return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
    },

    /** Get current push status: 'granted', 'denied', 'default', 'unsupported' */
    getStatus: function() {
        if (!this.isSupported()) return 'unsupported';
        return Notification.permission;
    },

    /** Subscribe to push notifications (call after login) */
    subscribe: async function() {
        if (!this.isSupported()) {
            console.log('Push not supported in this browser');
            return false;
        }

        try {
            // 1. Get VAPID key from server
            if (!this.vapidKey) {
                var res = await fetch('api/push.php?action=vapid_key').then(function(r) { return r.json(); });
                if (!res.ok) return false;
                this.vapidKey = res.key;
            }

            // 2. Wait for service worker to be ready
            var reg = await navigator.serviceWorker.ready;

            // 3. Check existing subscription
            var sub = await reg.pushManager.getSubscription();
            
            if (!sub) {
                // 4. Request permission if needed
                var permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    console.log('Notification permission denied');
                    return false;
                }

                // 5. Subscribe with VAPID key
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidKey)
                });
            }

            // 6. Send subscription to server
            var key = sub.getKey('p256dh');
            var auth = sub.getKey('auth');

            await fetch('api/push.php?action=subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: sub.endpoint,
                    p256dh: this.arrayBufferToBase64(key),
                    auth: this.arrayBufferToBase64(auth)
                })
            });

            console.log('Push subscription saved');
            return true;
        } catch (err) {
            console.log('Push subscribe error:', err);
            return false;
        }
    },

    /** Unsubscribe from push (call before logout) */
    unsubscribe: async function() {
        try {
            // Remove from server
            await fetch('api/push.php?action=unsubscribe', { method: 'POST' });
            
            // Unsubscribe locally
            if (this.isSupported()) {
                var reg = await navigator.serviceWorker.ready;
                var sub = await reg.pushManager.getSubscription();
                if (sub) await sub.unsubscribe();
            }
            return true;
        } catch (err) {
            console.log('Push unsubscribe error:', err);
            return false;
        }
    },

    /** Send a test notification to yourself */
    test: async function() {
        var res = await fetch('api/push.php?action=test').then(function(r) { return r.json(); });
        return res;
    }
};

// Convenience global functions
function petulap_subscribePush()   { return PETULAP_PUSH.subscribe(); }
function petulap_unsubscribePush() { return PETULAP_PUSH.unsubscribe(); }
function petulap_checkPushStatus() { return PETULAP_PUSH.getStatus(); }
function petulap_testPush()        { return PETULAP_PUSH.test(); }
