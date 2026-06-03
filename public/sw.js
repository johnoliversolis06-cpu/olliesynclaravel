/**
 * OllieSync Service Worker
 * FILE PATH: public/sw.js
 *
 * This runs in the BACKGROUND in the browser — separate from the main tab.
 * It's what allows notifications to appear even when OllieSync isn't open.
 *
 * How it gets activated:
 *  1. AuthenticatedLayout.vue calls: navigator.serviceWorker.register('/sw.js')
 *  2. The browser installs this worker silently
 *  3. When the Laravel cron sends a Web Push to the user's browser,
 *     the browser wakes this worker up and fires the 'push' event
 *  4. This worker shows the notification popup
 *  5. If the user clicks the notification, this worker opens OllieSync
 */

// ── Service Worker Install ────────────────────────────────────
// Skip waiting means it activates immediately without waiting
// for old tabs to close
self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim())
})

// ── Handle incoming push messages ────────────────────────────
// This fires when the Laravel server sends a push notification
self.addEventListener('push', (event) => {
  // Safety check: if there's no data in the push, show a generic notification
  if (!event.data) {
    event.waitUntil(
      self.registration.showNotification('OllieSync', {
        body: 'You have a new notification!',
        icon: '/favicon.ico',
      })
    )
    return
  }

  // Parse the JSON payload sent by CheckScheduledNotifications.php
  let data = {}
  try {
    data = event.data.json()
  } catch (e) {
    data = { title: 'OllieSync', body: event.data.text() }
  }

  const title   = data.title   || 'OllieSync'
  const body    = data.body    || ''
  const url     = data.url     || '/'
  const type    = data.type    || 'general'
  const icon    = data.icon    || '/favicon.ico'

  // Pick an emoji for the notification icon based on type
  const badgeEmoji = {
    'habit_reminder': '🔁',
    'streak_warning':  '🔥',
    'task_due':        '📋',
    'daily_summary':   '📊',
    'focus_complete':  '⏰',
  }[type] || '🔔'

  const options = {
    body:            body,
    icon:            icon,
    badge:           icon,
    tag:             type,             // groups notifications of the same type (replaces old one)
    renotify:        true,             // re-notify even if tag already exists
    vibrate:         [200, 100, 200],  // buzz pattern on mobile
    requireInteraction: false,         // auto-dismiss after a few seconds
    data: {
      url: url,      // we store the URL so we can open it on click
      type: type,
    },
    actions: [
      {
        action: 'open',
        title:  'Open OllieSync',
      },
      {
        action: 'dismiss',
        title:  'Dismiss',
      }
    ]
  }

  event.waitUntil(
    self.registration.showNotification(title, options)
  )
})

// ── Handle notification click ─────────────────────────────────
// When the user taps on the notification popup
self.addEventListener('notificationclick', (event) => {
  const action = event.action
  const notification = event.notification
  const url = notification.data?.url || '/'

  notification.close()

  if (action === 'dismiss') return

  // action === 'open' OR clicked the notification body itself
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // If OllieSync is already open in a tab, focus it and navigate
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.focus()
          client.navigate(url)
          return
        }
      }
      // Otherwise open a new tab
      if (clients.openWindow) {
        return clients.openWindow(url)
      }
    })
  )
})

// ── Handle push subscription change ──────────────────────────
// If the browser renews the push subscription automatically, update the server
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil(
    self.registration.pushManager.subscribe(event.oldSubscription.options)
      .then((subscription) => {
        // Notify the server about the new subscription
        return fetch('/notifications/subscribe', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            // Note: CSRF token not available in service worker
            // The server should handle this endpoint without CSRF for service workers
          },
          body: JSON.stringify(subscription.toJSON()),
        })
      })
  )
})