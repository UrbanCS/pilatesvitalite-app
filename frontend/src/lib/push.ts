import {
  getPublicConfig,
  getSubscriptionPreferences,
  removeSubscription,
  saveSubscription,
  saveSubscriptionPreferences
} from './api';
import type { NotificationPreferences, PushStatus } from '../types';

function urlBase64ToUint8Array(base64String: string) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let index = 0; index < rawData.length; index += 1) {
    outputArray[index] = rawData.charCodeAt(index);
  }

  return outputArray;
}

export function pushIsSupported() {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export async function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return null;
  }

  return navigator.serviceWorker.register('./sw.js');
}

export async function readPushStatus(): Promise<PushStatus> {
  if (!pushIsSupported()) {
    return 'unsupported';
  }

  if (Notification.permission === 'denied') {
    return 'denied';
  }

  const registration = await navigator.serviceWorker.ready;
  const current = await registration.pushManager.getSubscription();

  if (current) {
    return 'subscribed';
  }

  return Notification.permission;
}

async function currentSubscription() {
  const registration = await navigator.serviceWorker.ready;
  return registration.pushManager.getSubscription();
}

export async function readPushPreferences() {
  if (!pushIsSupported()) {
    throw new Error('Les notifications push ne sont pas supportées par ce navigateur.');
  }

  const current = await currentSubscription();
  if (!current) {
    throw new Error("Aucun abonnement actif n'a été trouvé.");
  }

  const response = await getSubscriptionPreferences(current.endpoint);
  return response.preferences;
}

export async function subscribeToPush(preferences: NotificationPreferences) {
  if (!pushIsSupported()) {
    throw new Error('Les notifications push ne sont pas supportées par ce navigateur.');
  }

  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    throw new Error("La permission de notification n'a pas été accordée.");
  }

  const publicConfig = await getPublicConfig();
  if (!publicConfig.vapidPublicKey) {
    throw new Error("La clé VAPID publique n'est pas configurée.");
  }

  const registration = await navigator.serviceWorker.ready;
  let subscription = await registration.pushManager.getSubscription();
  const createdSubscription = subscription === null;

  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicConfig.vapidPublicKey)
    });
  }

  try {
    const response = await saveSubscription(subscription, preferences);
    return { subscription, preferences: response.preferences };
  } catch (error) {
    if (createdSubscription) {
      await subscription.unsubscribe().catch(() => false);
    }
    throw error;
  }
}

export async function updatePushPreferences(preferences: NotificationPreferences) {
  if (!pushIsSupported()) {
    throw new Error('Les notifications push ne sont pas supportées par ce navigateur.');
  }

  const current = await currentSubscription();
  if (!current) {
    throw new Error("Aucun abonnement actif n'a été trouvé.");
  }

  const response = await saveSubscriptionPreferences(current.endpoint, preferences);
  return response.preferences;
}

export async function unsubscribeFromPush() {
  if (!pushIsSupported()) {
    return;
  }

  const registration = await navigator.serviceWorker.ready;
  const current = await registration.pushManager.getSubscription();

  if (!current) {
    return;
  }

  await removeSubscription(current.endpoint);
  await current.unsubscribe();
}
